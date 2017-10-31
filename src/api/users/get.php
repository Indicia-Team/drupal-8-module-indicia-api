<?php

use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * This function handles the login request.
 *
 * The function either returns an error or the user's details.
 */
function users_get() {
  $request = drupal_static('request');

  $valid = validate_users_get_request();
  if (!empty($valid)) {
    return error_print($valid['code'], $valid['header'], $valid['msg']);
  }

  // Return data.
  $users = [];

  if (isset($request['name'])) {
    // Username.
    $name = $request['name'];
    indicia_api_log('Searching users by name: ' . $name . '.');

    $user = user_load_by_name($name);
    if ($user) {
      array_push($users, $user);

      check_user_indicia_id($user);
    }
  }
  elseif (isset($request['email'])) {
    // Email.
    $email = $request['email'];
    indicia_api_log('Searching users by email: ' . $email . '.');

    $user = user_load_by_mail($email);
    if ($user) {
      array_push($users, $user);

      check_user_indicia_id($user);
    }
  }
  else {
    // Todo: support returning all users.
  }

  // Return the user's info to client.
  return return_users_details($users);
}

function return_users_details($user_full, $fullDetails = FALSE) {
  indicia_api_log('Returning response.');

  $data = [];

  foreach ($user_full as $user) {
    $userData = [
      'type' => 'users',
      'id' => (int) $user->id(),
      'firstname' => $user->get(FIRSTNAME_FIELD)->value,
      'secondname' => $user->get(SECONDNAME_FIELD)->value,
    ];

    if (ownAuthenticated($user, TRUE) || $fullDetails) {
      $userData['name'] = $user->get('name')->value;
      $userData['email'] = $user->get('mail')->value;
      $userData['warehouse_id'] = (int) $user->get(INDICIA_ID_FIELD)->value;
    }

    array_push($data, $userData);
  }

  $output = ['data' => $data];
  indicia_api_log(print_r($output, 1));

  $headers = [
    'Status' => '200 OK',
    'Access-Control-Allow-Origin' => '*',
    'Access-Control-Allow-Methods' => 'GET,PUT,OPTIONS',
    'Access-Control-Allow-Headers' => 'authorization, x-api-key, content-type',
  ];
  return new JsonResponse($data, '200', $headers);
}

function validate_users_get_request() {
  $request = drupal_static('request');

  // API key authorise.
  if (!indicia_api_authorise_key()) {
    return array(
      'code' => 401,
      'header' => 'Unauthorized',
      'msg' => 'Missing or incorrect API key.',
    );
  }

  // User authorise
  if (!indicia_api_authorise_user()) {
    return array(
      'code' => 401,
      'header' => 'Unauthorized',
      'msg' => 'Incorrect password or email.',
    );
  }

  // Check if the user filter is specified.
  if (!isset($request['name']) && !isset($request['email'])) {
    // Todo: remove this once the GET supports returning all users.
    return array(
      'code' => 404,
      'header' => 'Not found',
      'msg' => 'Full user listing is not supported yet.',
    );
  }

  return array();
}

