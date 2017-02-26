<?php

/**
 * Samples POST request handler.
 */
function samples_create($request) {
  $submission = json_decode($request['submission'], TRUE);

  if (!validate_samples_create_request($request, $submission)) {
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

  // Construct post parameter array.
  $submission = process_parameters($submission, $connection);

  // Check for duplicates.
  if (has_duplicates($submission)) {
    return;
  }

  // Send record to indicia.
  $response = forward_post_to('save', $submission, $auth['write_tokens']);

  // Return response to client.
  return_response($response);
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
      // Resize.jpg?1333102276814 which will fail the warehouse submission
      // Process.
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
    // A field called sc:<gridrow>::photo[1-9] and will then get moved to the
    // Interim image folder and
    // Linked to the form using a field called
    // Sc:<gridrow>::occurremce_media:path:[1-9] .
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
      $field_key = 'smpAttr:' . $int_key;
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

  foreach ($submission['media'] as $media) {
    array_push($model['subModels'], [
      'fkId' => 'sample_id',
      'model' => process_media_parameters($media, FALSE),
    ]);
  }

  return $model;
}

function process_occurrence_parameters($submission, $connection) {
  $model = [
    "id" => "occurrence",
    "fields" => [],
    "subModels" => [],
  ];

  foreach ($submission['fields'] as $key => $field) {
    $field_key = $key;
    if (($int_key = intval($key)) > 0) {
      $field_key = 'occAttr:' . $int_key;
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

  foreach ($submission['media'] as $media) {
    array_push($model['subModels'], [
      'fkId' => 'occurrence_id',
      'model' => process_media_parameters($media),
    ]);
  }

  return $model;
}


function process_media_parameters($submission, $occurrence = TRUE) {
  $model = [
    "id" => $occurrence ? "occurrence_medium" : 'sample_medium',
    "fields" => [],
  ];

  // Generate new name.
  $ext = $_FILES[$submission['name']]['type'] === 'image/png' ? '.png' : '.jpg';
  $newName = bin2hex(openssl_random_pseudo_bytes(20)) . $ext;

  $_FILES[$submission['name']]['name'] = $newName;

  $model['fields']['path'] = ['value' => $newName];

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
      // TODO: get actual sample external_key,
      // because this could be a subsample occurrence
      array_push($errors, [
        'status' => '409',
        'id' => $duplicate['id'],
        'external_key' => $duplicate['external_key'],
        'sample_id' => $duplicate['sample_id'],
        'sample_external_key' => $submission['fields']['external_key']['value'],
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
function validate_samples_create_request($request, $submission) {
  // Reject submissions with an incorrect secret (or instances where secret is
  // Not set).
  if (!indicia_api_authorise_key($request)) {
    error_print(401, 'Unauthorized', 'Missing or incorrect API key');

    return FALSE;
  }

  if (!indicia_api_authorise_user()) {
    error_print(400, 'Bad Request', 'Could not find/authenticate user');

    return FALSE;
  }

  $survey_id = intval($request['survey_id']);
  if ($survey_id == 0) {
    error_print(400, 'Bad Request', 'Missing or incorrect survey_id');

    return FALSE;
  }

  // Validate sample.
  if (!validate_sample($submission)) {
    return FALSE;
  }

  return TRUE;
}

function validate_sample($model) {
  if (!$model['fields']['date']) {
    error_print(400, 'Bad Request', 'Missing sample date.');

    return FALSE;
  }
  if (!$model['fields']['entered_sref']) {
    error_print(400, 'Bad Request', 'Missing sample entered_sref.');

    return FALSE;
  }
  if (!$model['fields']['entered_sref_system']) {
    error_print(400, 'Bad Request', 'Missing sample entered_sref_system.');

    return FALSE;
  }

  if (!empty($model['samples'])) {
    foreach ($model['samples'] as $sample) {
      if (!validate_sample($sample)) {
        return FALSE;
      }
    }
  }

  if (!empty($model['occurrences'])) {
    foreach ($model['occurrences'] as $occurrence) {
      if (!validate_occurrence($occurrence)) {
        return FALSE;
      }
    }
  }

  if (!empty($model['media'])) {
    foreach ($model['media'] as $media) {
      if (!validate_media($media)) {
        return FALSE;
      }
    }
  }

  return TRUE;
}

function validate_occurrence($model) {
  if (!$model['fields']['taxa_taxon_list_id']) {
    error_print(400, 'Bad Request', 'Missing occurrence taxa_taxon_list_id.');

    return FALSE;
  }

  if (!empty($model['media'])) {
    foreach ($model['media'] as $media) {
      if (!validate_media($media)) {
        return FALSE;
      }
    }
  }

  return TRUE;
}


function validate_media($model) {
  if (!$model['name']) {
    error_print(400, 'Bad Request', 'Missing media name.');

    return FALSE;
  }

  if (!$_FILES[$model['name']]) {
    error_print(400, 'Bad Request', 'Missing media.');

    return FALSE;
  }

  return TRUE;
}

function return_response($response) {
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
    $data = extract_sample_response($response['struct']);

    $output = ['data' => $data];
    drupal_json_output($output);
    indicia_api_log(print_r($response, 1));
  }
}

function extract_sample_response($model) {
  $data = [
    'id' => (int) $model['id'],
    'external_key' => $model['external_key'],
    'created_on' => $model['created_on'],
    'updated_on' => $model['updated_on'],
  ];

  foreach ($model['children'] as $child) {
    switch ($child['model']) {
      case 'occurrence':
        $data['occurrences'] = empty($data['occurrences']) ? [] : $data['occurrences'];
        array_push($data['occurrences'], extract_occurrence_response($child));
        break;

      case 'sample':
        $data['samples'] = empty($data['samples']) ? [] : $data['samples'];
        array_push($data['samples'], extract_sample_response($child));
        break;

      case 'sample_medium':
        $data['media'] = empty($data['media']) ? [] : $data['media'];
        array_push($data['media'], extract_media_response($child));
        break;

      default:
    }
  }
  return $data;
}

function extract_occurrence_response($model) {
  $data = [
    'id' => (int) $model['id'],
    'external_key' => $model['external_key'],
    'created_on' => $model['created_on'],
    'updated_on' => $model['updated_on'],
  ];

  foreach ($model['children'] as $child) {
    switch ($child['model']) {
      case 'occurrence_medium':
        $data['media'] = empty($data['media']) ? [] : $data['media'];
        array_push($data['media'], extract_media_response($child));
        break;

      default:
    }
  }

  return $data;
}

function extract_media_response($model) {
  $data = [
    'id' => (int) $model['id'],
    // Images don't store external_keys yet.
    'created_on' => $model['created_on'],
    'updated_on' => $model['updated_on'],
  ];

  return $data;
}

function forward_post_to($entity, $submission = NULL, $writeTokens = NULL) {
  $media = extract_media_data($_FILES);
  $request = data_entry_helper::$base_url . "index.php/services/data/$entity";
  $postargs = 'submission=' . urlencode(json_encode($submission));

  // Pass through the authentication tokens as POST data.
  foreach ($writeTokens as $token => $value) {
    $postargs .= '&' . $token . '=' . ($value === TRUE ? 'true' : ($value === FALSE ? 'false' : $value));
  }

  $postargs .= '&user_id=' . hostsite_get_user_field('indicia_user_id');

  // If there are images, we will send them after the main post,
  // so we need to persist the write nonce.
  if (count($media) > 0) {
    $postargs .= '&persist_auth=true';
  }
  $response = data_entry_helper::http_post($request, $postargs, FALSE);

  // The response should be in JSON if it worked.
  $output = json_decode($response['output'], TRUE);

  // If this is not JSON, it is an error, so just return it as is.
  if (!$output) {
    $output = $response['output'];
  }

  if (is_array($output) && array_key_exists('success', $output))  {
    // Submission succeeded.
    // So we also need to move the images to the final location.
    $image_overall_success = TRUE;
    $image_errors = array();
    foreach ($media as $item) {
      // No need to resend an existing image, or a media link, just local files.
      if ((empty($item['media_type']) || preg_match('/:Local$/', $item['media_type'])) &&
        empty($item['id'])) {
        if (!isset(data_entry_helper::$final_image_folder) ||
          data_entry_helper::$final_image_folder=='warehouse') {
          // Final location is the Warehouse
          // @todo Set PERSIST_AUTH false if last file
          $success = data_entry_helper::send_file_to_warehouse($item['path'], TRUE, $writeTokens);
        }
        else {
          $success = rename(
            $interim_image_folder.$item['path'],
            $final_image_folder.$item['path']
          );
        }

        if ($success !== TRUE) {
          // Record all files that fail to move successfully.
          $image_overall_success = FALSE;
          $image_errors[] = $success;
        }
      }
    }
    if (!$image_overall_success) {
      // Report any file transfer failures.
      $error = lang::get('submit ok but file transfer failed') . '<br/>';
      $error .= implode('<br/>', $image_errors);
      $output = array('error' => $error);
    }
  }
  return $output;
}

function extract_media_data() {
  $r = [];
  foreach ($_FILES as $key => $file) {
    if ($file['error'] == '1') {
      // File too big error dur to php.ini setting.
      if (data_entry_helper::$validation_errors === NULL) {
        data_entry_helper::$validation_errors = array();
      }
      data_entry_helper::$validation_errors[$key] = lang::get('file too big for webserver');
    }
    elseif (!data_entry_helper::check_upload_size($file)) {
      // Warehouse may still block it.
      if (data_entry_helper::$validation_errors==NULL) data_entry_helper::$validation_errors = array();
      data_entry_helper::$validation_errors[$key] = lang::get('file too big for warehouse');
    }


    $destination = $file['name'];
    $interim_image_folder = isset(data_entry_helper::$interim_image_folder) ?
      data_entry_helper::$interim_image_folder :
      'upload/';
    $uploadpath = data_entry_helper::relative_client_helper_path() . $interim_image_folder;

    if (move_uploaded_file($file['tmp_name'], $uploadpath . $destination)) {
      $r[] = array(
        // Id is set only when saving over an existing record.
        // This will always be a new record.
        'id' => '',
        'path' => $destination,
        'caption' => '',
      );
      $pathField = str_replace(array(':medium',':image'), array('_medium:path','_image:path'), $key);
      $_POST[$pathField] = $destination;
    }

  }
  return $r;
}
