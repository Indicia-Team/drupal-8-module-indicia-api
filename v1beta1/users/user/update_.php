<?php


function user_update($user) {
  if (!validate_user_update_request($user)) {
    return;
  }

  _user_mail_notify('password_reset', $user);
  return_user_details($user);
  indicia_api_log('Password reset email sent to ' . $user->mail);
}


function validate_user_update_request($user) {
  $request = drupal_static('request');

  // Reject submissions with an incorrect secret (or instances where secret is
  // not set).
  if (!indicia_api_authorise_key()) {
    error_print(401, 'Unauthorized', 'Missing or incorrect API key.');

    return FALSE;
  }

  // Check if user with UID exists.
  if (!$user) {
    error_print(404, 'Not found', 'User not found.');

    return FALSE;
  }

  // Check if password field is set for a reset.
  if (!isset($request['password'])) {
    error_print(400, 'Bad Request', 'Nothing to process.');

    return FALSE;
  }

  return TRUE;
}