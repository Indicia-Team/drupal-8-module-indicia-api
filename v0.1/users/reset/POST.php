<?php


function indicia_api_users_reset_post($uid) {
  indicia_api_log('Users reset POST');
  indicia_api_log(print_r($_POST, 1));

  if (!validate_user_reset_post_request($uid)) {
    return;
  }

  if ($uid !== 'anonymous') {
    $existing_user = user_load($uid);
  }
  else {
    if ($_POST['email']) {
      $existing_user = user_load_by_mail($_POST['email']);
    }
    else {
      $existing_user = user_load_by_name($_POST['name']);
    }
  }

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

  // Check if user with UID exists.
  if (!$uid || ($uid !== 'anonymous' && !user_load($uid))) {
    error_print(404, 'Not found', 'User not found');

    return FALSE;
  }

  // For anonymous users check if search params exist and user exists.
  if ($uid === 'anonymous' && !$_POST['email'] && !$_POST['name']) {
    error_print(404, 'Not found', 'User not found');

    return FALSE;
  }
  else if ($uid === 'anonymous') {
    if ($_POST['email']) {
      $existing_user = user_load_by_mail($_POST['email']);
    }
    else {
      $existing_user = user_load_by_name($_POST['name']);
    }
    if (!$existing_user) {
      error_print(404, 'Not found', 'User not found');

      return FALSE;
    }
  }

  return TRUE;
}