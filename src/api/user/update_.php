<?php


function user_update($user) {
  $valid = validate_user_update_request($user);
  if (!empty($valid)) {
    return error_print($valid['code'], $valid['header'], $valid['msg']);
  }

  _user_mail_notify('password_reset', $user);
  indicia_api_log('Password reset email sent to ' . $user->getEmail());

  return user_details($user);
}


function validate_user_update_request($user) {
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

  // Check if user with UID exists.
  if (!$user) {
    return array(
      'code' => 404,
      'header' => 'Not found',
      'msg' => 'User not found.',
    );
  }

  //echo var_dump($request);
  if (!isset($request['data']['type']) || $request['data']['type'] != 'users') {
    return array(
      'code' => 400,
      'header' => 'Bad Request',
      'msg' => 'Resource of type users not found.',
    );
  }

  // Check if password field is set for a reset.
  if (!isset($request['data']['password'])) {
    return array(
      'code' => 400,
      'header' => 'Bad Request',
      'msg' => 'Nothing to process.',
    );
  }

  return array();
}
