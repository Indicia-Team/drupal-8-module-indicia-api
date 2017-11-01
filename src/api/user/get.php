<?php

use Drupal\user\Entity\User;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * This function handles the login request.
 *
 * The function either returns an error or the user's details.
 */
function user_get($user) {

  $valid = validate_user_get_request($user);
  if (!empty($valid)) {
    return error_print($valid['code'], $valid['header'], $valid['msg']);
  }

  // Return the user's info to client.
  return user_details($user);
  indicia_api_log('User details returned.');
}

function validate_user_get_request($user) {
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

  // Check if user with UID exists.
  if (!$user) {
    return array(
      'code' => 404,
      'header' => 'Not found',
      'msg' => 'User not found.',
    );
  }

  return array();
}

function user_details($user, $fullDetails = FALSE) {
  indicia_api_log('Returning response.');

  check_user_indicia_id($user);

  $data = [
    'type' => 'users',
    'id' => (int) $user->id(),
    'firstname' => $user->get(FIRSTNAME_FIELD)->value,
    'secondname' => $user->get(SECONDNAME_FIELD)->value,
  ];


  if (ownAuthenticated($user) || $fullDetails) {
    $data['name'] = $user->getUsername();
    $data['email'] = $user->getEmail();
    $data['warehouse_id'] = (int) $user->get(INDICIA_ID_FIELD)->value;
  }

  $output = ['data' => $data];
  indicia_api_log(print_r($output, 1));

  $headers = [
    'Status' => '200 OK',
    'Access-Control-Allow-Origin' => '*',
    'Access-Control-Allow-Methods' => 'GET,PUT,OPTIONS',
    'Access-Control-Allow-Headers' => 'authorization, x-api-key, content-type',
  ];

  return new JsonResponse($output, '200', $headers);
}
