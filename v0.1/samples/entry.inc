<?php

require 'POST.php';

function indicia_api_samples() {
  drupal_add_http_header('Access-Control-Allow-Origin', "*");
  drupal_add_http_header('Access-Control-Allow-Methods', 'GET,POST');
  drupal_add_http_header('Access-Control-Allow-Headers', 'Authorization');

  switch ($_SERVER['REQUEST_METHOD']) {
    case 'POST':
      indicia_api_samples_post();
      break;

    default:
      return;
  }
}