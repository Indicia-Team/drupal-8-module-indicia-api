<?php


/**
 * This function handles the login request.
 *
 * The function either returns an error or the user's details.
 */
function users_get() {
  indicia_api_log('Users GET');
  indicia_api_log(print_r($_GET, 1));

  if (!validate_users_get_request()) {
    return;
  }

  // Return data.
  $users = [];

  $email = $_GET['email'];
  $name = $_GET['name'];
  if ($name) {
    // Username.
    $existing_user = user_load_by_name($name);
    if ($existing_user) {
      $existing_user_obj = entity_metadata_wrapper('user', $existing_user);
      array_push($users, $existing_user_obj);

      check_user_indicia_id($existing_user_obj);
    }
  }
  elseif ($email) {
    // Email.
    $existing_user = user_load_by_mail($email);
    if ($existing_user) {
      $existing_user_obj = entity_metadata_wrapper('user', $existing_user);
      array_push($users, $existing_user_obj);

      check_user_indicia_id($existing_user_obj);
    }
  }
  else {
    // Todo: support returning all users.
  }

  // Return the user's info to client.
  drupal_add_http_header('Status', '200 OK');
  return_users_details($users);
  indicia_api_log('User details returned');
}

function validate_users_get_request() {
  // API key authorise.
  if (!indicia_api_authorise_key()) {
    error_print(401, 'Unauthorized', 'Missing or incorrect API key');

    return FALSE;
  }

  // User authorise
  if (!indicia_api_authorise_user()) {
    error_print(401, 'Unauthorized', 'Incorrect password or email');

    return FALSE;
  }

  // Check if the user filter is specified.
  if (!$_GET['name'] && !$_GET['email']) {
    // Todo: remove this once the GET supports returning all users.
    error_print(404, 'Not found', 'Full user listing is not supported yet. Please specify your user email.');
    return;
  }

  return TRUE;
}
