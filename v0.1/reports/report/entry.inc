<?php

require 'get.php';

function indicia_api_report() {
  drupal_add_http_header('Access-Control-Allow-Origin', "*");
  drupal_add_http_header('Access-Control-Allow-Methods', 'GET,POST,OPTIONS');
  drupal_add_http_header('Access-Control-Allow-Headers', 'Authorization');

  switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
      report_get();
      break;

    case 'OPTIONS':
      break;

    default:
      error_print(405, 'Method Not Allowed');
  }
}
