<?php


/**
 * This function handles the login request.
 *
 * The function either returns an error or the user's details.
 */
function users_get() {
  $request = drupal_static('request');

  if (!validate_users_get_request()) {
    return;
  }

  // Return data.
  $users = [];

  if (isset($request['name'])) {
    // Username.
    $name = $request['name'];
    indicia_api_log('Searching users by name: ' . $name . '.');

    $user = user_load_by_name($name);
    if ($user) {
      $user_full = entity_metadata_wrapper('user', $user);
      array_push($users, $user_full);

      check_user_indicia_id($user_full);
    }
  }
  elseif (isset($request['email'])) {
    // Email.
    $email = $request['email'];
    indicia_api_log('Searching users by email: ' . $email . '.');

    $user = user_load_by_mail($email);
    if ($user) {
      $user_full = entity_metadata_wrapper('user', $user);
      array_push($users, $user_full);

      check_user_indicia_id($user_full);
    }
  }
  else {
    // Todo: support returning all users.
  }

  // Return the user's info to client.
  drupal_add_http_header('Status', '200 OK');
  return_users_details($users);
}

function validate_users_get_request() {
  $request = drupal_static('request');

  // API key authorise.
  if (!indicia_api_authorise_key()) {
    error_print(401, 'Unauthorized', 'Missing or incorrect API key.');

    return FALSE;
  }

  // User authorise
  if (!indicia_api_authorise_user()) {
    error_print(401, 'Unauthorized', 'Incorrect password or email.');

    return FALSE;
  }

  // Check if the user filter is specified.
  if (!isset($request['name']) && !isset($request['email'])) {
    // Todo: remove this once the GET supports returning all users.
    error_print(404, 'Not found', 'Full user listing is not supported yet.');
    return;
  }

  return TRUE;
}
