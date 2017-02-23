<?php


function user_patch($user) {
  indicia_api_log('Users reset PATCH');
  indicia_api_log(print_r($_POST, 1));

  if (!validate_user_patch_request($user)) {
    return;
  }

  $existing_user_obj = entity_metadata_wrapper('user', $user);
  _user_mail_notify('password_reset', $user);
  return_user_details($existing_user_obj);
}


function validate_user_patch_request($user) {
  // Reject submissions with an incorrect secret (or instances where secret is
  // not set).
  if (!indicia_api_authorise_key()) {
    error_print(401, 'Unauthorized', 'Missing or incorrect API key');

    return FALSE;
  }

  // Check if user with UID exists.
  if (!$user) {
    error_print(404, 'Not found', 'User not found');

    return FALSE;
  }

  // Check if password field is set for a reset.
  if (!isset($_POST['password'])) {
    error_print(400, 'Bad Request', 'Nothing to process.');

    return FALSE;
  }

  return TRUE;
}