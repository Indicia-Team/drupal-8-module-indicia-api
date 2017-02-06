<?php


/**
 * This function handles the login request.
 *
 * The function either returns an error or the user's details.
 */
function indicia_api_users_auth_post() {
  indicia_api_log('Users Auth POST');
  indicia_api_log(print_r($_POST, 1));

  if (!validate_user_auth_request()) {
    return;
  }

  $email = $_POST['email'];
  $password = $_POST['password'];
  $existing_user = user_load_by_mail($email);
  $existing_user_obj = entity_metadata_wrapper('user', $existing_user);

  require_once DRUPAL_ROOT . '/' . variable_get('password_inc', 'includes/password.inc');

  if (!user_check_password($password, $existing_user)) {
    error_print(401, 'Unauthorized', 'Incorrect password or email');
    return;
  }
  elseif ($existing_user->status != 1) {
    // Check for activation.
    error_print(401, 'Unauthorized', 'User not activated.');
    return;
  }

  // Check for existing user that do not have indicia id in their profile field.
  check_indicia_id($existing_user_obj);

  // Return the user's info to client.
  drupal_add_http_header('Status', '200 OK');
  return_user_details($existing_user_obj);
  indicia_api_log('User created');
}

function validate_user_auth_request() {
  // Reject submissions with an incorrect secret (or instances where secret is
  // not set).
  if (!indicia_api_authorise_key()) {
    error_print(401, 'Unauthorized', 'Missing or incorrect API key');

    return FALSE;
  }

  // Check email is valid.
  $email = $_POST['email'];
  if (empty($email)) {
    error_print(400, 'Bad Request', 'Invalid or missing email');

    return FALSE;
  }

  // Apply a password strength requirement.
  $password = $_POST['password'];
  if (empty($password)) {
    error_print(400, 'Bad Request', 'Invalid or missing password');

    return FALSE;
  }

  // Check for an existing user. If found (and password matches) return the
  // secret to all user to 'log in'.
  $existing_user = user_load_by_mail($email);
  if (!$existing_user) {
    error_print(401, 'Unauthorized', 'Incorrect password or email');

    return FALSE;
  }

  return TRUE;
}

function check_indicia_id($existing_user_obj) {
  $indicia_user_id = $existing_user_obj->{INDICIA_ID_FIELD}->value();
  if (empty($indicia_user_id) || $indicia_user_id == -1) {
    indicia_api_log('Associating indicia user id');
    // Look up indicia id.
    $indicia_user_id = indicia_api_get_user_id($existing_user_obj->mail->value(),
      $existing_user_obj->{FIRSTNAME_FIELD}->value(),
      $existing_user_obj->{SECONDNAME_FIELD}->value(),
      $existing_user_obj->uid->value());

    if (is_int($indicia_user_id)) {
      $existing_user_obj->{INDICIA_ID_FIELD}->set($indicia_user_id);
      $existing_user_obj->save();
    }
    else {
      $error = $indicia_user_id;
    }
  }
}
