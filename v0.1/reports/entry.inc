<?php

require 'GET_POST.php';

function indicia_api_reports() {
  drupal_add_http_header('Access-Control-Allow-Origin', "*");
  drupal_add_http_header('Access-Control-Allow-Methods', 'GET');
  drupal_add_http_header('Access-Control-Allow-Headers', 'Authorization');

  switch ($_SERVER['REQUEST_METHOD']) {
    case 'POST':
    case 'GET':
      indicia_api_reports_get_post();
      break;

    default:
      return;
  }
}