<?php

require 'get.php';

function indicia_api_report() {
  drupal_add_http_header('Access-Control-Allow-Origin', "*");
  drupal_add_http_header('Access-Control-Allow-Methods', 'GET,POST,OPTIONS');
  drupal_add_http_header('Access-Control-Allow-Headers', 'authorization, x-api-key');

  // Extract report ID
  $reportStartPos = strlen(base_path() . API_BASE . API_VERSION . '/reports/');
  $reportEndPos = strpos($_SERVER['REQUEST_URI'], '.xml') + 4 - $reportStartPos;
  $reportID = substr($_SERVER['REQUEST_URI'], $reportStartPos, $reportEndPos);

  switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
      $request = $_GET;
      drupal_static('request', $request);

      indicia_api_log('[Reports get]');
      indicia_api_log(print_r($request, 1));

      report_get($reportID);
      break;

    case 'OPTIONS':
      break;

    default:
      error_print(405, 'Method Not Allowed');
  }
}
