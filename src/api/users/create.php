<?php

use Drupal\user\Entity\User;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * This function handles the registration request.
 *
 * The function either returns an error or the user's details.
 */
function users_create() {

  $valid = validate_users_create_request();
  if (!empty($valid)) {
    return error_print($valid['code'], $valid['header'], $valid['msg']);
  }

  // Create account for user.
  try {
    $new_user_obj = create_new_user();
  }
  catch (Exception $e) {
    return error_print(400, 'Bad Request', 'User could not be created. ' . $e);
  }

  // Send activation mail.
  send_activation_email($new_user_obj);

  // Return the user's details to client.
  return user_details_after_create($new_user_obj, TRUE);
}

function validate_users_create_request() {
  $request = drupal_static('request');

  // Reject submissions with an incorrect secret (or instances where secret is
  // not set).
  if (!indicia_api_authorise_key()) {
    return array(
      'code' => 401,
      'header' => 'Unauthorized',
      'msg' => 'Missing or incorrect API key.',
    );
  }

  if (!isset($request['data']['type']) || $request['data']['type'] != 'users') {
    return array(
      'code' => 400,
      'header' => 'Bad Request',
      'msg' => 'Resource of type users not found.',
    );
  }

  $data = $request['data'];

  // Check minimum valid parameters.
  $firstname = $data['firstname'];
  $secondname = $data['secondname'];
  if (empty($firstname) || empty($secondname)) {
    return array(
      'code' => 400,
      'header' => 'Bad Request',
      'msg' => 'Invalid or missing user firstname or secondname.',
    );
  }

  // Check email is valid.
  $email = $data['email'];
  if (empty($email) || !\Drupal::service('email.validator')->isValid($email)) {
    return array(
      'code' => 400,
      'header' => 'Bad Request',
      'msg' => 'Invalid or missing email.',
    );
  }

  // Apply a password strength requirement.
  $password = $data['password'];
  if (empty($password) || indicia_api_validate_password($password) != 1) {
    return array(
      'code' => 400,
      'header' => 'Bad Request',
      'msg' => 'Invalid or missing password.',
    );
  }

  // Check for an existing user. If found return "already exists" error.
  $user = user_load_by_mail($email);
  if ($user) {
    return array(
      'code' => 400,
      'header' => 'Bad Request',
      'msg' => 'Account already exists.',
    );
  }

  return array();
}

function create_new_user() {
  $request = drupal_static('request');
  $data = $request['data'];

  // Create user object.
  $user = User::create();

  //Mandatory settings
  $user->setPassword($data['password']);
  $user->enforceIsNew();
  $user->setEmail($data['email']);
  $user->setUsername($data['email']);

  $user->set(FIRSTNAME_FIELD, $data['firstname']);
  $user->set(SECONDNAME_FIELD, $data['secondname']);

  // Generate the user confirmation code returned via email.
  $activation_token = indicia_api_generate_random_string(20);
  $user->set(ACTIVATION_FIELD, $activation_token);

  // Look up indicia id. No need to send cms_id as this is a new user so they
  // cannot have any old records under this id to merge.
  $indicia_user_id = indicia_api_get_user_id(
    $data['email'],
    $data['firstname'],
    $data['secondname']
  );
  // Handle indicia_api_get_user_id returning an error.
  if (!is_int($indicia_user_id)) {/* todo. */ }
  $user->set(INDICIA_ID_FIELD, $indicia_user_id);

  $user->save();

  return $user;
}


function send_activation_email($new_user) {
  indicia_api_log('Sending activation email to ' . $new_user->getEmail());
  $request = drupal_static('request');

  $params = [
    'uid' => $new_user->id(),
    'activation_token' => $new_user->get(ACTIVATION_FIELD)->value,
  ];

  $mailManager = \Drupal::service('plugin.manager.mail');
  $result = $mailManager->mail(
    'indicia_api',
    'register',
    $new_user->getEmail(),
    $new_user->getPreferredLangcode(),
    $params,
    NULL,
    true
  );

  // TODO:
  // if ($result['result'] !== true) {
  //   error_print('There was a problem sending activation email.');
  // }
  // else {
  //   error_print('Activation email was sent.');
  // }
}

function user_details_after_create($user_full, $fullDetails = FALSE) {
  indicia_api_log('Returning response.');

  $data = [
    'type' => 'users',
    'id' => (int) $user_full->id(),
    'firstname' => $user_full->get(FIRSTNAME_FIELD)->value,
    'secondname' => $user_full->get(SECONDNAME_FIELD)->value,
  ];

  if (ownAuthenticated($user_full) || $fullDetails) {
    $data['name'] = $user_full->getDisplayName()();
    $data['email'] = $user_full->getEmail();
    $data['warehouse_id'] = (int) $user_full->get(INDICIA_ID_FIELD)->value;
  }

  $output = ['data' => $data];
  indicia_api_log(print_r($output, 1));

  $headers = [
    'Status' => '201 Created',
    'Access-Control-Allow-Origin' => '*',
    'Access-Control-Allow-Methods' => 'GET,PUT,OPTIONS',
    'Access-Control-Allow-Headers' => 'authorization, x-api-key, content-type',
  ];
  return new JsonResponse($output, '201', $headers);

}
