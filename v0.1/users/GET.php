<?php


/**
 * This function handles the login request.
 *
 * The function either returns an error or the user's details.
 */
function indicia_api_users_get($uid) {
  indicia_api_log('Users GET');
  indicia_api_log(print_r($_GET, 1));

  if (!validate_user_get_request($uid)) {
    return;
  }

  // Return data.
  $data = [];

  $email = $_GET['email'];
  $name = $_GET['name'];
  if ($uid) {
    // UID.
    $existing_user = user_load($uid);
    if ($existing_user) {
      $existing_user_obj = entity_metadata_wrapper('user', $existing_user);
      $data = $existing_user_obj;

      check_indicia_id($existing_user_obj);
    }
  }
  elseif ($name) {
    // Username.
    $existing_user = user_load_by_name($name);
    if ($existing_user) {
      $existing_user_obj = entity_metadata_wrapper('user', $existing_user);
      array_push($data, $existing_user_obj);

      check_indicia_id($existing_user_obj);
    }
  }
  elseif ($email) {
    // Email.
    $existing_user = user_load_by_mail($email);
    if ($existing_user) {
      $existing_user_obj = entity_metadata_wrapper('user', $existing_user);
      array_push($data, $existing_user_obj);

      check_indicia_id($existing_user_obj);
    }
  }
  else {
    // Todo: support returning all users.
  }

  // Return the user's info to client.
  drupal_add_http_header('Status', '200 OK');
  return_user_details($data);
  indicia_api_log('User details returned');
}

function validate_user_get_request($uid) {
  // API key authorise.
  if (!indicia_api_authorise_key()) {
    error_print(401, 'Unauthorized', 'Missing or incorrect API key');

    return FALSE;
  }

  // User authorise
  $name =  $_SERVER['PHP_AUTH_USER'];
  $password = $_SERVER['PHP_AUTH_PW'];
  if (!$password && !$name) {
    error_print(401, 'Unauthorized', 'Incorrect password or email');

    return FALSE;
  }
  // User exists?
  if(filter_var($name, FILTER_VALIDATE_EMAIL)) {
    // Email.
    $existing_user = user_load_by_mail($name);
  }
  else {
    // Name.
    $existing_user = user_load_by_name($name);
  }
  if (!$existing_user) {
    error_print(401, 'Unauthorized', 'Incorrect password or email');

    return FALSE;
  }

  // User password and activations check
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

  // Check if the user filter is specified.
  if (!$uid && !$_GET['name'] && !$_GET['email'] && !$_GET['warehouse_id']) {
    // Todo: remove this once the GET supports returning all users.
    error_print(404, 'Not found', 'Full user listing is not supported yet. Please specify your user email.');
    return;
  }

  return TRUE;
}

/**
 *  Check for existing user that do not have indicia id in their profile field.
 *
 * @param $existing_user_obj
 */
function check_indicia_id($existing_user_obj) {
  // Allow to update own user record only.
  if (!ownAuthenticated($existing_user_obj)) {
    return;
  }

  $indicia_user_id = $existing_user_obj->{INDICIA_ID_FIELD}->value();
  if (!$indicia_user_id || $indicia_user_id == -1) {
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
  }
}
