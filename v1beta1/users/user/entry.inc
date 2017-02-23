<?php

require 'get.php';
require 'update_.php';

function indicia_api_user($uid = NULL) {
  drupal_add_http_header('Access-Control-Allow-Origin', "*");
  drupal_add_http_header('Access-Control-Allow-Methods', 'GET,POST,OPTIONS');
  drupal_add_http_header('Access-Control-Allow-Headers', 'Authorization');

  switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
      $request = $_GET;

      indicia_api_log('User get');
      indicia_api_log(print_r($request, 1));

      user_get($request, load_user($uid));
      break;

    case 'PUT':
      parse_str(file_get_contents('php://input'), $_PUT);
      $request = $_PUT + $_GET;

      indicia_api_log('User update');
      indicia_api_log(print_r($request, 1));

      // Only supports password reset at the moment.
      user_update($request, load_user($uid));
      break;

    case 'OPTIONS':
      break;

    default:
      error_print(405, 'Method Not Allowed');
  }
}

function load_user($uid) {
  // UID.
  if (is_numeric($uid)) {
    $user = user_load($uid);
  }
  // Email.
  elseif (filter_var($uid, FILTER_VALIDATE_EMAIL)) {
    $user = user_load_by_mail($uid);
  }
  // Name.
  else {
    $user = user_load_by_name($uid);
  }

  return $user;
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