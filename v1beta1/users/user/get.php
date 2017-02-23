<?php


/**
 * This function handles the login request.
 *
 * The function either returns an error or the user's details.
 */
function user_get($request, $user) {
  if (!validate_user_get_request($request, $user)) {
    return;
  }

  // Return the user's info to client.
  drupal_add_http_header('Status', '200 OK');
  return_user_details($user);
  indicia_api_log('User details returned');
}

function validate_user_get_request($request, $user) {
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

  // Check if user with UID exists.
  if (!$user) {
    error_print(404, 'Not found', 'User not found');

    return FALSE;
  }

  return TRUE;
}

function return_user_details($user, $fullDetails = FALSE) {
  $user_full = entity_metadata_wrapper('user', $user);

  check_user_indicia_id($user_full);

  $data = [
    'type' => 'users',
    'id' => (int) $user_full->getIdentifier(),
    'firstname' => $user_full->{FIRSTNAME_FIELD}->value(),
    'secondname' => $user_full->{SECONDNAME_FIELD}->value(),
  ];

  if (ownAuthenticated($user_full) || $fullDetails) {
    $data['name'] = $user_full->name->value();
    $data['email'] = $user_full->mail->value();
    $data['warehouse_id'] = (int) $user_full->{INDICIA_ID_FIELD}->value();
  }

  $output = ['data' => $data];
  drupal_json_output($output);
}