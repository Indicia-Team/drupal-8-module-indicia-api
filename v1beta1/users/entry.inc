<?php

require 'get.php';
require 'create.php';

function indicia_api_users() {
  drupal_add_http_header('Access-Control-Allow-Origin', "*");
  drupal_add_http_header('Access-Control-Allow-Methods', 'GET,POST,OPTIONS');
  drupal_add_http_header('Access-Control-Allow-Headers', 'Authorization');

  switch ($_SERVER['REQUEST_METHOD']) {
    case 'POST':
      $request = $_GET + $_POST;

      indicia_api_log('Users create');
      indicia_api_log(print_r($request, 1));

      users_create($request);
      break;

    case 'GET':
      $request = $_GET + $_POST;

      indicia_api_log('Users get');
      indicia_api_log(print_r($request, 1));

      users_get($request);
      break;

    case 'OPTIONS':
      break;

    default:
      error_print(405, 'Method Not Allowed');
  }
}

function return_users_details($user_full, $fullDetails = FALSE) {
  $data = [];

  foreach ($user_full as $user) {
    $userData = [
      'type' => 'users',
      'id' => (int) $user->getIdentifier(),
      'firstname' => $user->{FIRSTNAME_FIELD}->value(),
      'secondname' => $user->{SECONDNAME_FIELD}->value(),
    ];

    if (ownAuthenticated($user) || $fullDetails) {
      $userData['name'] = $user->name->value();
      $userData['email'] = $user->mail->value();
      $userData['warehouse_id'] = (int) $user->{INDICIA_ID_FIELD}->value();
    }

    array_push($data, $userData);
  }

  $output = ['data' => $data];
  drupal_json_output($output);
}


function ownAuthenticated($user) {
  // Allow to update own user record only.
  if(filter_var($_SERVER['PHP_AUTH_USER'], FILTER_VALIDATE_EMAIL) &&
    $_SERVER['PHP_AUTH_USER'] === $user->mail->value()) {
    // Email.
    return TRUE;
  }
  elseif ($_SERVER['PHP_AUTH_USER'] === $user->name->value()) {
    // Name.
    return TRUE;
  }

  return FALSE;
}