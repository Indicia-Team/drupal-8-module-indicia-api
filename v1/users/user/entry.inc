<?php

require 'get.php';
require 'update_.php';
require 'activate.php';

function indicia_api_user($uid = NULL, $activate = FALSE) {
  drupal_add_http_header('Access-Control-Allow-Origin', "*");
  drupal_add_http_header('Access-Control-Allow-Methods', 'GET,PUT,OPTIONS');
  drupal_add_http_header('Access-Control-Allow-Headers', 'Authorization');

  switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
      $request = $_GET;
      drupal_static('request', $request);

      if ($activate) {
        indicia_api_log('[User activate]');
        indicia_api_log(print_r($request, 1));

        // Only supports password reset at the moment.
        user_activate(load_user($uid));
        return;
      }

      indicia_api_log('[User get]');
      indicia_api_log(print_r($request, 1));

      user_get(load_user($uid));
      break;

    case 'PUT':
      $request = json_decode(file_get_contents('php://input'), TRUE);
      drupal_static('request', $request);

      indicia_api_log('[User update]');
      indicia_api_log(print_r($request, 1));

      // Only supports password reset at the moment.
      user_update(load_user($uid));
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
    indicia_api_log('Loading user by uid: ' . $uid . '.');
    $user = user_load($uid);
  }
  // Email.
  elseif (filter_var($uid, FILTER_VALIDATE_EMAIL)) {
    indicia_api_log('Loading user by email: ' . $uid . '.');
    $user = user_load_by_mail($uid);

    // In case the username is an email and username != email
    if (empty($user)) {
      indicia_api_log('Loading user by name: ' . $uid . '.');
      $user = user_load_by_name($uid);
    }
  }
  // Name.
  else {
    indicia_api_log('Loading user by name: ' . $uid . '.');
    $user = user_load_by_name($uid);
  }

  return $user;
}
