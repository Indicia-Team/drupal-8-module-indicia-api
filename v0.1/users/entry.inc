<?php

require 'GET.php';
require 'POST.php';
require 'reset/POST.php';

function indicia_api_users($uid = NULL, $attribute = '') {
  drupal_add_http_header('Access-Control-Allow-Origin', "*");
  drupal_add_http_header('Access-Control-Allow-Methods', 'GET,POST,OPTIONS');
  drupal_add_http_header('Access-Control-Allow-Headers', 'Authorization');

  switch ($_SERVER['REQUEST_METHOD']) {
    case 'POST':
      if ($attribute === 'reset') {
        indicia_api_users_reset_post($uid);
      }
      else {
        indicia_api_users_post();
      }
      break;

    case 'GET':
      indicia_api_users_get($uid);
      break;

    case 'OPTIONS':
      break;

    default:
      error_print(405, 'Method Not Allowed');
  }
}

function return_user_details($user_obj, $fullDetails = FALSE) {
  $data = [];

  if (is_array($user_obj)) {
    // Multiple.
    foreach ($user_obj as $user) {
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
  }
  else {
    // Single.
    $data = [
      'type' => 'users',
      'id' => (int) $user_obj->getIdentifier(),
      'firstname' => $user_obj->{FIRSTNAME_FIELD}->value(),
      'secondname' => $user_obj->{SECONDNAME_FIELD}->value(),
    ];

    if (ownAuthenticated($user_obj) || $fullDetails) {
      $data['name'] = $user_obj->name->value();
      $data['email'] = $user_obj->mail->value();
      $data['warehouse_id'] = (int) $user_obj->{INDICIA_ID_FIELD}->value();
    }
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