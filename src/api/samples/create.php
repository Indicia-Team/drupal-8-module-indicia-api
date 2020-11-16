<?php

use Drupal\Core\Logger\RfcLogLevel;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Samples POST request handler.
 */
function samples_create() {
  $validate_request = validate_samples_create_request();
  if (!empty($validate_request)) {
    return error_print($validate_request['code'], $validate_request['header'], $validate_request['msg']);
  }

  $request = drupal_static('request');
  $data = $request['data'];

  // Get auth.
  try {
    $connection = iform_get_connection_details(NULL);
    $auth = data_entry_helper::get_read_write_auth($connection['website_id'], $connection['password']);
  }
  catch (Exception $e) {
    return error_print(502, 'Bad Gateway', 'Something went wrong in obtaining nonce.');
  }

  // Construct post parameter array.
  $processed = process_parameters($data, $connection);

  // Check for duplicates.
  $dupes = has_duplicates($processed['model']);
  if (!empty($dupes)) {
    return error_print(409, 'Conflict', NULL, $dupes);
  }

  // Send record to indicia.
  $response = forward_post_to('save', $processed['model'], $processed['files'], $auth['write_tokens']);

  // Return response to client.
  return return_response($response);
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
function process_parameters($data, $connection) {
  $model = [
    "id" => "sample",
    "fields" => [],
    "subModels" => [],
  ];

  $files = []; // Files for submission.

  foreach ($data['fields'] as $key => $field) {
    $field_key = $key;
    if (($int_key = intval($key)) > 0) {
      $field_key = 'smpAttr:' . $int_key;
    }
    $model['fields'][$field_key] = [
      'value' => $field,
    ];
  }

  // These must be set later than the rest of the fields for security.
  $model['fields']['website_id'] = ['value' => $connection['website_id']];
  $model['fields']['survey_id'] = ['value' => $data['survey_id']];

  if (isset($data['external_key'])) {
    $model['fields']['external_key'] = ['value' => $data['external_key']];
  }

  if (isset($data['input_form'])) {
    $model['fields']['input_form'] = ['value' => $data['input_form']];
  }

  if (isset($data['samples']) && is_array($data['samples'])) {
    foreach ($data['samples'] as $sample) {
      $processed = process_parameters($sample, $connection);
      array_push($model['subModels'], [
        'fkId' => 'parent_id',
        'model' => $processed['model'],
      ]);
      if (!empty($processed['files'])) {
        $files = array_merge($files, $processed['files']);
      }
    }
  }

  if (isset($data['occurrences']) && is_array($data['occurrences'])) {
    foreach ($data['occurrences'] as $occurrence) {
      $processed = process_occurrence_parameters($occurrence, $connection);
      array_push($model['subModels'], [
        'fkId' => 'sample_id',
        'model' => $processed['model'],
      ]);
      if (!empty($processed['files'])) {
        $files = array_merge($files, $processed['files']);
      }
    }
  }

  if (isset($data['media']) && is_array($data['media'])) {
    foreach ($data['media'] as $media) {
      $processed = process_media_parameters($media, FALSE);
      array_push($model['subModels'], [
        'fkId' => 'sample_id',
        'model' => $processed['model'],
      ]);
      if (!empty($processed['file'])) {
        array_push($files, $processed['file']);
      }
    }
  }
  indicia_api_log('Processed sample parameters.');
  indicia_api_log(print_r($data, 1));
  indicia_api_log(print_r($model, 1));
  indicia_api_log(print_r($files, 1));
  return [ 'model' => $model, 'files' => $files];
}

function process_occurrence_parameters($data, $connection) {
  $model = [
    "id" => "occurrence",
    "fields" => [],
    "subModels" => [],
  ];

  $files = []; // Files for submission.

  if (isset($data['fields']) && is_array($data['fields'])) {
    foreach ($data['fields'] as $key => $field) {
      $field_key = $key;
      if (($int_key = intval($key)) > 0) {
        $field_key = 'occAttr:' . $int_key;
      }
      $model['fields'][$field_key] = [
        'value' => $field,
      ];
    }
  }

  // These must be set later than the rest of the fields for security.
  $model['fields']['website_id'] = ['value' => $connection['website_id']];
  if (isset($data['training'])) {
    $model['fields']['training'] = [
      'value' => ($data['training'] ? 't' : 'f'),
    ];
  }

  $model['fields']['zero_abundance'] = ['value' => 'f'];
  if (isset($data['record_status'])) {
    $model['fields']['record_status'] = ['value' => $data['record_status']];
  }
  else {
    // Mark the record complete by default.
    $model['fields']['record_status'] = ['value' => 'C'];
  }

  if (isset($data['release_status'])) {
    $model['fields']['release_status'] = ['value' => $data['release_status']];
  }

  if (isset($data['confidential'])) {
    $model['fields']['confidential'] = ['value' => $data['confidential']];
  }

  if (isset($data['sensitive'])) {
    $model['fields']['sensitive'] = ['value' => $data['sensitive']];
  }

  if (isset($data['sensitivity_precision'])) {
    $model['fields']['sensitivity_precision'] = ['value' => $data['sensitivity_precision']];
  }

  if (isset($data['external_key'])) {
    $model['fields']['external_key'] = ['value' => $data['external_key']];
  }

  if (isset($data['media']) && is_array($data['media'])) {
    foreach ($data['media'] as $media) {
      $processed = process_media_parameters($media);
      array_push($model['subModels'], [
        'fkId' => 'occurrence_id',
        'model' => $processed['model'],
      ]);
      if (!empty($processed['file'])) {
        array_push($files, $processed['file']);
      }
    }
  }

  indicia_api_log('Processed occurrence parameters.');
  indicia_api_log(print_r($data, 1));
  indicia_api_log(print_r($model, 1));
  return [ 'model' => $model, 'files' => $files];
}


function process_media_parameters($data, $occurrence = TRUE) {
  $model = [
    "id" => $occurrence ? "occurrence_medium" : 'sample_medium',
    "fields" => [],
  ];

  // Generate new name.
  $file = $_FILES[$data['name']];
  $ext = $file['type'] === 'image/png' ? '.png' : '.jpg';
  $newName = bin2hex(openssl_random_pseudo_bytes(20)) . $ext;

  $file['name'] = $newName;

  $model['fields']['path'] = ['value' => $newName];

  indicia_api_log('Processed media parameters.');
  indicia_api_log(print_r($data, 1));
  indicia_api_log(print_r($model, 1));
  return [ 'model' => $model, 'file' => $file];
}

/**
 * Checks if any of the occurrences in the model have any duplicates.
 *
 * Does that based on their external keys in the warehouse.
 *
 * @param array $data
 *   The record model.
 *
 * @return bool
 *   Returns true if has duplicates.
 */
function has_duplicates($submission) {
  indicia_api_log('Searching for duplicates.');

  $duplicates = find_duplicates($submission);
  if (sizeof($duplicates) > 0) {
    $errors = [];
    foreach ($duplicates as $duplicate) {
      // TODO: get actual sample external_key,
      // because this could be a subsample occurrence
      array_push($errors, [
        'status' => '409',
        'id' => (int) $duplicate['id'],
        'external_key' => $duplicate['external_key'],
        'sample_id' => (int) $duplicate['sample_id'],
        'sample_external_key' => $submission['fields']['external_key']['value'],
        'title' => 'Occurrence already exists.',
      ]);
    }
    return $errors;
  }
  return array();
}

/**
 * Finds duplicates in the warehouse.
 *
 * @param array $data
 *   Record model.
 *
 * @return array
 *   Returns an array of duplicates.
 */
function find_duplicates($submission) {
  $connection = iform_get_connection_details(NULL);
  $auth = data_entry_helper::get_read_auth($connection['website_id'], $connection['password']);

  $duplicates = [];
  if (isset($submission['subModels']) && is_array($submission['subModels'])) {
    // don't run this intensive query if big model: todo - optimize and enable
    if (sizeof($submission['subModels']) > 5) {
      indicia_api_log('Submission is too big: skipping the search for duplicates.');
      return $duplicates;
    }

    foreach ($submission['subModels'] as $occurrence) {
      if (isset($occurrence['model']['fields']['external_key']['value'])) {
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
    }
  }
  return $duplicates;
}

/**
 * Validates the request params inc. user details.
 *
 * @return bool
 *   True if the request is valid
 */
function validate_samples_create_request() {
  $request = drupal_static('request');

  // Reject submissions with an incorrect secret (or instances where secret is
  // Not set).
  if (!indicia_api_authorise_key()) {
    return array(
      'code' => 401,
      'header' => 'Unauthorized',
      'msg' => 'Missing or incorrect API key.',
    );
  }

  if (empty($request) || !isset($request['data'])) {
    return array(
      'code' => 400,
      'header' => 'Bad Request',
      'msg' => 'Missing or invalid submission.',
    );
  }

  $user_authenticated = !empty($_SERVER['PHP_AUTH_USER']) && indicia_api_authorise_user();
  $anonymous_authenticated = empty($_SERVER['PHP_AUTH_USER']) && authorise_anonymous();

  if (!$user_authenticated && !$anonymous_authenticated) {
      return array(
          'code' => 400,
          'header' => 'Bad Request',
          'msg' => 'Could not find/authenticate user.',
        );
  }

  if (!isset($request['data']['type']) || $request['data']['type'] != 'samples') {
    return array(
      'code' => 400,
      'header' => 'Bad Request',
      'msg' => 'Resource of type samples not found.',
    );
  }


  if (!isset($request['data']['survey_id'])) {
    return array(
      'code' => 400,
      'header' => 'Bad Request',
      'msg' => 'Missing or incorrect survey id.',
    );
  }

  // Validate sample.
  $validate_sample = validate_sample($request['data']);
  if (!empty($validate_sample)) {
    return $validate_sample;
  }

  return array();
}

function authorise_anonymous() {
  $key = $_SERVER['HTTP_X_API_KEY'];

  // Check if matches API anonymous account.
  $result = db_query(
    "SELECT * FROM {indicia_api} WHERE api_key = :key",
    array(':key' => $key));

  $result_array = $result->fetchAll();

  if (count($result_array) !== 1) {
    return FALSE;
  }

  if (!is_numeric($result_array[0]->anonymous_user)) {
    indicia_api_log('Provided anonymous user ID is not numeric.',
      NULL, RfcLogLevel::ERROR);
    return FALSE;
  }

  $anonymous_user_id = $result_array[0]->anonymous_user;

  if ($anonymous_user_id == -1) {
    indicia_api_log('Anonymous recording is not allowed.',
      NULL, RfcLogLevel::ERROR);
    return FALSE;
  }

  // Find user.
  $existing_user = user_load($anonymous_user_id);

  if (empty($existing_user)) {
    indicia_api_log('Not found anonymous user with ID ' . $anonymous_user_id . '.',
      NULL, RfcLogLevel::ERROR);
    return FALSE;
  }

  indicia_api_log('Found anonymous user with ID ' . $anonymous_user_id . '.');

  // Assign this user to gobal user var so that it can be added to the indicia
  // submission.
  $GLOBALS['user'] = $existing_user;
  return TRUE;
}

function validate_sample($model) {
  if (!isset($model['fields']['date'])) {
    return array(
      'code' => 400,
      'header' => 'Bad Request',
      'msg' => 'Missing sample date.',
    );
  }
  if (!isset($model['fields']['entered_sref'])) {
    return array(
      'code' => 400,
      'header' => 'Bad Request',
      'msg' => 'Missing sample entered_sref.',
    );
  }
  if (!isset($model['fields']['entered_sref_system'])) {
    return array(
      'code' => 400,
      'header' => 'Bad Request',
      'msg' => 'Missing sample entered_sref_system.',
    );
  }

  if (isset($model['samples']) && is_array($model['samples'])) {
    foreach ($model['samples'] as $sample) {
      $valid_sample = validate_sample($sample);
      if (!empty($valid_sample)) {
        return $valid_sample;
      }
    }
  }

  if (isset($model['occurrences']) && is_array($model['occurrences'])) {
    foreach ($model['occurrences'] as $occurrence) {
      $valid_occurrence = validate_occurrence($occurrence);
      if (!empty($valid_occurrence)) {
        return $valid_occurrence;
      }
    }
  }

  if (isset($model['media']) && is_array($model['media'])) {
    foreach ($model['media'] as $media) {
      $valid_media = validate_media($media);
      if (!empty($valid_media)) {
        return $valid_media;
      }
    }
  }

  return array();
}

function validate_occurrence($model) {
  if (!isset($model['fields']['taxa_taxon_list_id'])) {
    return array(
      'code' => 400,
      'header' => 'Bad Request',
      'msg' => 'Missing occurrence taxa_taxon_list_id.',
    );
  }
  if (isset($model['media']) && is_array($model['media'])) {
    foreach ($model['media'] as $media) {
      $valid_media = validate_media($media);
      if (!empty($valid_media)) {
        return $valid_media;
      }
    }
  }

  return array();
}


function validate_media($model) {
  if (!isset($model['name'])) {
    return array(
      'code' => 400,
      'header' => 'Bad Request',
      'msg' => 'Missing media name.',
    );
  }

  if (!isset($_FILES[$model['name']])) {
    return array(
      'code' => 400,
      'header' => 'Bad Request',
      'msg' => 'Missing media.',
    );
  }

  return array();
}

function return_response($response) {
  indicia_api_log('Returning response.');

  if (isset($response['error'])) {
    if (sizeof($response['errors']) > 0) {
      $errors = [];
      foreach ($response['errors'] as $key => $error) {
        array_push($errors, [
          'title' => $key,
          'description' => $error,
        ]);
      }
      return error_print(400, 'Bad Request', NULL, $errors);
    }
    else {
      return error_print(400, 'Bad Request', $response['error']);
    }
  }
  else {
    // Created.
    $data = extract_sample_response($response['struct']);

    $output = ['data' => $data];
    indicia_api_log(print_r($output, 1));

    $headers = [
      'Status' => '201 Created',
      'Access-Control-Allow-Origin' => '*',
      'Access-Control-Allow-Methods' => 'GET,PUT,OPTIONS',
      'Access-Control-Allow-Headers' => 'authorization, x-api-key, content-type',
    ];
    return new JsonResponse($output, '201', $headers);
  }
}

function extract_sample_response($model) {
  $data = [
    'id' => (int) $model['id'],
    'external_key' => $model['external_key'],
    'created_on' => $model['created_on'],
    'updated_on' => $model['updated_on'],
  ];
  if (isset($model['children']) && is_array($model['children'])) {
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

  if (isset($model['children']) && is_array($model['children'])) {
    foreach ($model['children'] as $child) {
      switch ($child['model']) {
        case 'occurrence_medium':
          $data['media'] = empty($data['media']) ? [] : $data['media'];
          array_push($data['media'], extract_media_response($child));
          break;

        default:
      }
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

function forward_post_to($entity, $submission = NULL, $files = NULL, $writeTokens = NULL) {
  $media = prepare_media_for_upload($files);
  $request = data_entry_helper::$base_url . "index.php/services/data/$entity";
  $postargs = 'submission=' . urlencode(json_encode($submission));

  // Pass through the authentication tokens as POST data.
  foreach ($writeTokens as $token => $value) {
    $postargs .= '&' . $token . '=' . ($value === TRUE ? 'true' : ($value === FALSE ? 'false' : $value));
  }

  $user = $GLOBALS['user'];
  indicia_api_log('indicia_user_id ' . $user->get(INDICIA_ID_FIELD)->value);

  $postargs .= '&user_id=' . $user->get(INDICIA_ID_FIELD)->value;
  // If there are images, we will send them after the main post,
  // so we need to persist the write nonce.
  if (count($media) > 0) {
    $postargs .= '&persist_auth=true';
  }
  indicia_api_log('Sending new model to warehouse.');
  $response = data_entry_helper::http_post($request, $postargs, FALSE);

  // The response should be in JSON if it worked.
  $output = json_decode($response['output'], TRUE);

  // If this is not JSON, it is an error, so just return it as is.
  if (!$output) {
    indicia_api_log('Problem occurred with the submission.', NULL, RfcLogLevel::ERROR);
    $output = $response['output'];
  }

  if (is_array($output) && array_key_exists('success', $output))  {
    if (sizeof($media) > 0) {
      indicia_api_log('Uploading ' . sizeof($media) .' media files.');
    }

    // Submission succeeded.
    // So we also need to move the images to the final location.
    $image_overall_success = TRUE;
    $image_errors = array();
    foreach ((array)$media as $item) {
      // No need to resend an existing image, or a media link, just local files.
      if ((empty($item['media_type']) || preg_match('/:Local$/', $item['media_type'])) &&
        empty($item['id'])) {
        if (!isset(data_entry_helper::$final_image_folder) ||
            data_entry_helper::$final_image_folder === 'warehouse') {
          // Final location is the Warehouse
          // @todo Set PERSIST_AUTH false if last file
          indicia_api_log('Uploading ' . $item['path']);
          $success = data_entry_helper::send_file_to_warehouse($item['path'], TRUE, $writeTokens);
        }
        else {
          $success = rename(
            data_entry_helper::getInterimImageFolder('fullpath') . $item['path'],
            data_entry_helper::$final_image_folder.$item['path']
          );
        }

        if ($success !== TRUE) {
          indicia_api_log('Errors', NULL, RfcLogLevel::ERROR);
          indicia_api_log(print_r($success, 1), NULL, RfcLogLevel::ERROR);

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

function prepare_media_for_upload($files = []) {
  indicia_api_log('Preparing media for upload.');

  $r = [];
  foreach ($files as $key => $file) {
    if ($file['error'] == '1') {
      // File too big error dur to php.ini setting.
      if (data_entry_helper::$validation_errors === NULL) {
        data_entry_helper::$validation_errors = array();
      }
      data_entry_helper::$validation_errors[$key] = lang::get('file too big for webserver');
    }
    elseif (!data_entry_helper::checkUploadSize($file)) {
      // Warehouse may still block it.
      if (data_entry_helper::$validation_errors==NULL) data_entry_helper::$validation_errors = array();
      data_entry_helper::$validation_errors[$key] = lang::get('file too big for warehouse');
    }

    $destination = $file['name'];
    $uploadPath = data_entry_helper::getInterimImageFolder('fullpath');

    if (move_uploaded_file($file['tmp_name'], $uploadPath . $destination)) {
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
