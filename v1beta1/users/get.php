<?php


/**
 * This function handles the login request.
 *
 * The function either returns an error or the user's details.
 */
function users_get($request) {
  if (!validate_users_get_request($request)) {
    return;
  }

  // Return data.
  $users = [];

  $email = $request['email'];
  $name = $request['name'];
  if ($name) {
    // Username.
    $user = user_load_by_name($name);
    if ($user) {
      $user_full = entity_metadata_wrapper('user', $user);
      array_push($users, $user_full);

      check_user_indicia_id($user_full);
    }
  }
  elseif ($email) {
    // Email.
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
  indicia_api_log('User details returned');
}

function validate_users_get_request($request) {
  // API key authorise.
  if (!indicia_api_authorise_key($request)) {
    error_print(401, 'Unauthorized', 'Missing or incorrect API key');

    return FALSE;
  }

  // User authorise
  if (!indicia_api_authorise_user()) {
    error_print(401, 'Unauthorized', 'Incorrect password or email');

    return FALSE;
  }

  // Check if the user filter is specified.
  if (!$request['name'] && !$request['email']) {
    // Todo: remove this once the GET supports returning all users.
    error_print(404, 'Not found', 'Full user listing is not supported yet.');
    return;
  }

  return TRUE;
}
