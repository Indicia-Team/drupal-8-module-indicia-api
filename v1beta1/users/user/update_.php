<?php


function user_update($request, $user) {
  if (!validate_user_update_request($request, $user)) {
    return;
  }

  _user_mail_notify('password_reset', $user);
  return_user_details($user);
}


function validate_user_update_request($request, $user) {
  // Reject submissions with an incorrect secret (or instances where secret is
  // not set).
  if (!indicia_api_authorise_key($request)) {
    error_print(401, 'Unauthorized', 'Missing or incorrect API key');

    return FALSE;
  }

  // Check if user with UID exists.
  if (!$user) {
    error_print(404, 'Not found', 'User not found');

    return FALSE;
  }

  // Check if password field is set for a reset.
  if (!isset($request['password'])) {
    error_print(400, 'Bad Request', 'Nothing to process.');

    return FALSE;
  }

  return TRUE;
}