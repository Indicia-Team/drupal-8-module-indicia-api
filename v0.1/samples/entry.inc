<?php

require 'create.php';

function indicia_api_samples() {
  drupal_add_http_header('Access-Control-Allow-Origin', "*");
  drupal_add_http_header('Access-Control-Allow-Methods', 'POST');
  drupal_add_http_header('Access-Control-Allow-Headers', 'Authorization');

  switch ($_SERVER['REQUEST_METHOD']) {
    case 'POST':
      samples_create();
      break;

    case 'OPTIONS':
      break;

    default:
      error_print(405, 'Method Not Allowed');
  }
}