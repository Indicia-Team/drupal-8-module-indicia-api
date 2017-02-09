<?php


function indicia_api_users_reset_post($uid) {
  indicia_api_log('Users reset POST');
  indicia_api_log(print_r($_POST, 1));

  if (!validate_user_reset_post_request($uid)) {
    return;
  }

  $existing_user = user_load($uid);
  $existing_user_obj = entity_metadata_wrapper('user', $existing_user);
  _user_mail_notify('password_reset', $existing_user);
  return_user_details($existing_user_obj);
}


function validate_user_reset_post_request($uid) {
  // Reject submissions with an incorrect secret (or instances where secret is
  // not set).
  if (!indicia_api_authorise_key()) {
    error_print(401, 'Unauthorized', 'Missing or incorrect API key');

    return FALSE;
  }

  if (!$uid || !user_load($uid)) {
    error_print(404, 'Not found', 'User not found');

    return FALSE;
  }

  return TRUE;
}