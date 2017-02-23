<?php

require 'create.php';

function indicia_api_samples() {
  drupal_add_http_header('Access-Control-Allow-Origin', "*");
  drupal_add_http_header('Access-Control-Allow-Methods', 'POST');
  drupal_add_http_header('Access-Control-Allow-Headers', 'Authorization');

  switch ($_SERVER['REQUEST_METHOD']) {
    case 'POST':
      $request = $_POST + $_GET;

      indicia_api_log('Samples create');
      indicia_api_log(print_r($request, 1));

      samples_create($request);
      break;

    case 'OPTIONS':
      break;

    default:
      error_print(405, 'Method Not Allowed');
  }
}