<?php

/**
 * Samples POST request handler.
 */
function indicia_api_samples_post() {
  indicia_api_log('Samples POST');
  indicia_api_log(print_r($_POST, 1));
  $submission = json_decode($_POST['submission'], true);

  if (!validate_samples_post_request($submission)) {
    return;
  }

  // Get auth.
  try {
    $connection = iform_get_connection_details(NULL);
    $auth = data_entry_helper::get_read_write_auth($connection['website_id'], $connection['password']);
  }
  catch (Exception $e) {
    error_print(502, 'Bad Gateway', 'Something went wrong in obtaining nonce');
    return;
  }

  // Construct photos.
  process_files();

  // Construct post parameter array.
  $submission = process_parameters($submission, $connection);

  // Check for duplicates.
  if (has_duplicates($submission)) {
    return;
  }

  // Send record to indicia.
  $response = data_entry_helper::forward_post_to('save', $submission, $auth['write_tokens']);

  // Return response to client.
  return_response($response, $submission);
}

/**
 * Processes the files attached to request.
 */
function process_files() {
  $processedFiles = array();
  foreach ($_FILES as $name => $info) {
    // If name is sample_photo1 or photo1 etc then process it.
    if (preg_match('/^(?P<sample>sample_)?photo(?P<id>[0-9])$/', $name, $matches)) {
      $baseModel = empty($matches['sample']) ? 'occurrence' : 'sample';
      $name = "$baseModel:image:$matches[id]";
      // Mobile generated files can have file name in format
      // resize.jpg?1333102276814 which will fail the warehouse submission
      // process.
      if (strstr($info['type'], 'jpg') !== FALSE || strstr($info['type'], 'jpeg') !== FALSE) {
        $info['name'] = uniqid() . '.jpg';
      }
      if (strstr($info['type'], 'png') !== FALSE) {
        $info['name'] = uniqid() . '.png';
      }
      $processedFiles[$name] = $info;
    }
    // Handle files sent along with a species checklist style submission.
    // Files should be POSTed in
    // a field called sc:<gridrow>::photo[1-9] and will then get moved to the
    // interim image folder and
    // linked to the form using a field called
    // sc:<gridrow>::occurremce_media:path:[1-9] .
    elseif (preg_match('/^sc:(?P<gridrow>.+)::photo(?P<id>[0-9])$/', $name, $matches)) {
      $interim_image_folder = isset(data_entry_helper::$interim_image_folder) ? data_entry_helper::$interim_image_folder : 'upload/';
      $uploadPath = data_entry_helper::relative_client_helper_path() . $interim_image_folder;
      $interimFileName = uniqid() . '.jpg';
      if (move_uploaded_file($info['tmp_name'], $uploadPath . $interimFileName)) {
        $_POST["sc:$matches[gridrow]::occurrence_medium:path:$matches[id]"] = $interimFileName;
      }
    }
  }
  if (!empty($processedFiles)) {
    $_FILES = $processedFiles;
    indicia_api_log(print_r($_FILES, 1));
  }
}

/**
 * Processes all the parameters sent as POST to form a valid record model.
 *
 * @param array $auth
 *   Authentication tokens.
 *
 * @return array
 *   Returns the new record model.
 */
function process_parameters($submission, $connection) {
  $model = [
    "id" => "sample",
    "fields" => [],
    "subModels" => [],
  ];

  foreach ($submission['fields'] as $key => $field) {
    $field_key = $key;
    if (($int_key = intval($key)) > 0) {
      $field_key = 'smp:' . $int_key;
    }
    $model['fields'][$field_key] = [
      'value' => $field,
    ];
  }

  $model['fields']['website_id'] = ['value' => $connection['website_id']];
  $model['fields']['survey_id'] = ['value' => $submission['survey_id']];

  if ($submission['external_key']) {
    $model['fields']['external_key'] = ['value' => $submission['external_key']];
  }

  if ($submission['input_form']) {
    $model['fields']['input_form'] = ['value' => $submission['input_form']];
  }

  foreach ($submission['samples'] as $sample) {
    array_push($model['subModels'], [
      'fkId' => 'sample_id',
      'model' => process_parameters($sample),
    ]);
  }

  foreach ($submission['occurrences'] as $occurrence) {
    array_push($model['subModels'], [
      'fkId' => 'sample_id',
      'model' => process_occurrence_parameters($occurrence, $connection),
    ]);
  }

  return $model;
}

function process_occurrence_parameters($submission, $connection) {
  $model = [
    "id" => "occurrence",
    "fields" => [],
  ];

  foreach ($submission['fields'] as $key => $field) {
    $field_key = $key;
    if (($int_key = intval($key)) > 0) {
      $field_key = 'smp:' . $int_key;
    }
    $model['fields'][$field_key] = [
      'value' => $field,
    ];
  }

  $model['fields']['website_id'] = ['value' => $connection['website_id']];
  $model['fields']['zero_abundance'] = ['training' => $submission['training']];
  $model['fields']['zero_abundance'] = ['value' => 'f'];
  $model['fields']['record_status'] = ['value' => 'C'];

  if ($submission['external_key']) {
    $model['fields']['external_key'] = ['value' => $submission['external_key']];
  }

  return $model;
}

/**
 * Checks if any of the occurrences in the model have any duplicates.
 *
 * Does that based on their external keys in the warehouse.
 *
 * @param array $submission
 *   The record model.
 *
 * @return bool
 *   Returns true if has duplicates.
 */
function has_duplicates($submission) {
  $duplicates = find_duplicates($submission);
  if (count($duplicates) > 0) {
    $errors = [];
    foreach ($duplicates as $duplicate) {
      array_push($errors, [
        'status' => '409',
        'id' => $duplicate['id'],
        'external_key' => $duplicate['external_key'],
        'sample_id' => $duplicate['sample_id'],
        'title' => 'Occurrence already exists.',
      ]);
    }
    error_print(409, 'Conflict', NULL, $errors);

    return TRUE;
  }

  return FALSE;
}

/**
 * Finds duplicates in the warehouse.
 *
 * @param array $submission
 *   Record model.
 *
 * @return array
 *   Returns an array of duplicates.
 */
function find_duplicates($submission) {
  $connection = iform_get_connection_details(NULL);
  $auth = data_entry_helper::get_read_auth($connection['website_id'], $connection['password']);

  $duplicates = [];
  foreach ($submission['subModels'] as $occurrence) {
    $existing = data_entry_helper::get_population_data(array(
      'table' => 'occurrence',
      'extraParams' => array_merge($auth, [
        'view' => 'detail',
        'external_key' => $occurrence['model']['fields']['external_key']['value'],
      ]),
      // Forces a load from the db rather than local cache.
      'nocache' => TRUE,
    ));
    $duplicates = array_merge($duplicates, $existing);
  }

  return $duplicates;
}

/**
 * Validates the request params inc. user details.
 *
 * @return bool
 *   True if the request is valid
 */
function validate_samples_post_request($submission) {
  // Reject submissions with an incorrect secret (or instances where secret is
  // not set).
  if (!indicia_api_authorise_key()) {
    error_print(401, 'Unauthorized', 'Missing or incorrect API key');

    return FALSE;
  }

  if (!indicia_api_authorise_user()) {
    error_print(400, 'Bad Request', 'Could not find/authenticate user');

    return FALSE;
  }

  $survey_id = intval($_POST['survey_id']);
  if ($survey_id == 0) {
    error_print(400, 'Bad Request', 'Missing or incorrect survey_id');

    return FALSE;
  }

  // Validate core fields.
  if (!$submission['fields']['date']) {
    error_print(400, 'Bad Request', 'Missing date.');

    return FALSE;
  }
  if (!$submission['fields']['entered_sref']) {
    error_print(400, 'Bad Request', 'Missing entered_sref.');

    return FALSE;
  }
  if (!$submission['fields']['entered_sref_system']) {
    error_print(400, 'Bad Request', 'Missing entered_sref_system.');

    return FALSE;
  }

  return TRUE;
}


// todo: remove submission param once the server returns external keys
function return_response($response, $submission) {
  if (isset($response['error'])) {
    $errors = [];
    foreach ($response['errors'] as $key => $error) {
      array_push($errors, [
        'title' => $key,
        'description' => $error,
      ]);
    }
    error_print(400, 'Bad Request', NULL, $errors);
  }
  else {
    // Created.
    drupal_add_http_header('Status', '201 Created');
    $data = [
      'type' => 'samples',
      'id' => (int) $response['struct']['id'],
      'external_key' => $submission['fields']['external_key']['value'],
      'subModels' => [],
    ];

    // Occurrences only
    // todo: subModel samples
    foreach ($response['struct']['children'] as $subModel) {
      if ($subModel['model'] === 'occurrence') {
        array_push($data['subModels'], [
          'type' => 'occurrence',
          'id' => (int) $subModel['id'],
          // todo: extract from response once the warehouse supports it
          'external_key' => $submission['subModels'][0]['model']['fields']['external_key']['value'],
        ]);
      }
    }

    $output = ['data' => $data];
    drupal_json_output($output);
    indicia_api_log(print_r($response, 1));
  }
}
