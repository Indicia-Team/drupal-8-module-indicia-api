<?php

require 'get.php';
require 'create.php';

function indicia_api_users() {
  drupal_add_http_header('Access-Control-Allow-Origin', "*");
  drupal_add_http_header('Access-Control-Allow-Methods', 'GET,POST,OPTIONS');
  drupal_add_http_header('Access-Control-Allow-Headers', 'authorization, x-api-key, content-type');

  switch ($_SERVER['REQUEST_METHOD']) {
    case 'POST':
      $request = json_decode(file_get_contents('php://input'), TRUE);

      drupal_static('request', $request);

      indicia_api_log('[Users create]');
      indicia_api_log(print_r($request, 1));

      users_create();
      break;

    case 'GET':
      $request = $_GET;
      drupal_static('request', $request);

      indicia_api_log('[Users get]');
      indicia_api_log(print_r($request, 1));

      users_get();
      break;

    case 'OPTIONS':
      break;

    default:
      error_print(405, 'Method Not Allowed');
  }
}

function return_users_details($user_full, $fullDetails = FALSE) {
  indicia_api_log('Returning response.');

  $data = [];

  foreach ($user_full as $user) {
    $userData = [
      'type' => 'users',
      'id' => (int) $user->getIdentifier(),
      'firstname' => $user->{FIRSTNAME_FIELD}->value(),
      'secondname' => $user->{SECONDNAME_FIELD}->value(),
    ];

    if (ownAuthenticated($user, TRUE) || $fullDetails) {
      $userData['name'] = $user->name->value();
      $userData['email'] = $user->mail->value();
      $userData['warehouse_id'] = (int) $user->{INDICIA_ID_FIELD}->value();
    }

    array_push($data, $userData);
  }

  $output = ['data' => $data];
  drupal_json_output($output);
  indicia_api_log(print_r($output, 1));
}