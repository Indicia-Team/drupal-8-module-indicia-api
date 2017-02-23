<?php


/**
 * This function handles the login request.
 *
 * The function either returns an error or the user's details.
 */
function user_get($user) {
  indicia_api_log('User get');
  indicia_api_log(print_r($_GET, 1));

  if (!validate_user_get_request($user)) {
    return;
  }

  // Return the user's info to client.
  drupal_add_http_header('Status', '200 OK');
  return_user_details($user);
  indicia_api_log('User details returned');
}

function validate_user_get_request($user) {
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

  // Check if user with UID exists.
  if (!$user) {
    error_print(404, 'Not found', 'User not found');

    return FALSE;
  }

  return TRUE;
}
