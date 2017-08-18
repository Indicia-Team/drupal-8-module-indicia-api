<?php

require 'create.php';

function indicia_api_samples() {
  drupal_add_http_header('Access-Control-Allow-Origin', "*");
  drupal_add_http_header('Access-Control-Allow-Methods', 'POST');
  drupal_add_http_header('Access-Control-Allow-Headers', 'authorization, x-api-key');

  switch ($_SERVER['REQUEST_METHOD']) {
    case 'POST':
      indicia_api_log('[Samples create]');

      $request = json_decode(file_get_contents('php://input'), TRUE);

      // Support form-data with files attached.
      if (empty($request) && !empty($_POST['submission'])) {
        indicia_api_log('Using POST');
        $submission = json_decode($_POST['submission'], TRUE);
        $request = $submission;
      }

      drupal_static('request', $request);
      indicia_api_log(print_r($request, 1));

      samples_create();
      break;

    case 'OPTIONS':
      break;

    default:
      error_print(405, 'Method Not Allowed');
  }
}