<?php

/**
 * This function handles the registration request.
 *
 * The function either returns an error or the user's details.
 */
function users_create() {
  if (!validate_users_create_request()) {
    return;
  }

  // Create account for user.
  try {
    $new_user_obj = create_new_user();
  }
  catch (Exception $e) {
    error_print(400, 'Bad Request', 'User could not be created.');
    return;
  }

  // Send activation mail.
  send_activation_email($new_user_obj);

  // Return the user's details to client.
  drupal_add_http_header('Status', '201 Created');
  return_user_details($new_user_obj, TRUE);
}

function validate_users_create_request() {
  $request = drupal_static('request');

  // Reject submissions with an incorrect secret (or instances where secret is
  // not set).
  if (!indicia_api_authorise_key()) {
    error_print(401, 'Unauthorized', 'Missing or incorrect API key.');

    return FALSE;
  }

  // Check minimum valid parameters.
  $firstname = $request['firstname'];
  $secondname = $request['secondname'];
  if (empty($firstname) || empty($secondname)) {
    error_print(400, 'Bad Request', 'Invalid or missing user firstname or secondname.');

    return FALSE;
  }

  // Check email is valid.
  $email = $request['email'];
  if (empty($email) || valid_email_address($email) != 1) {
    error_print(400, 'Bad Request', 'Invalid or missing name.');

    return FALSE;
  }

  // Apply a password strength requirement.
  $password = $request['password'];
  if (empty($password) || indicia_api_validate_password($password) != 1) {
    error_print(400, 'Bad Request', 'Invalid or missing password.');

    return FALSE;
  }

  // Check for an existing user. If found return "already exists" error.
  $user = user_load_by_mail($email);
  if ($user) {
    error_print(400, 'Bad Request', 'Account already exists.');

    return FALSE;
  }

  return TRUE;
}

function create_new_user() {
  $request = drupal_static('request');

  // Pull out parameters from POST request.
  $firstname = empty($request['firstname']) ? '' : $request['firstname'];
  $secondname = empty($request['secondname']) ? '' : $request['secondname'];
  $email = $request['email'];
  $password = $request['password'];

  // Generate the user confirmation code returned via email.
  $confirmation_code = indicia_api_generate_random_string(20);

  // Look up indicia id. No need to send cms_id as this is a new user so they
  // cannot have any old records under this id to merge.
  $indicia_user_id = indicia_api_get_user_id($email, $firstname, $secondname);
  // Handle indicia_api_get_user_id returning an error.
  if (!is_int($indicia_user_id)) {
    // todo.
  }

  $user_details = array(
    'pass' => $password, /* handles the (unsalted) hash process */
    'name' => $email,
    'mail' => $email,
  );
  $user_details[FIRSTNAME_FIELD][LANGUAGE_NONE][0]['value'] = $firstname;
  $user_details[SECONDNAME_FIELD][LANGUAGE_NONE][0]['value'] = $secondname;
  $user_details[CONFIRMATION_FIELD][LANGUAGE_NONE][0]['value'] = $confirmation_code;
  $user_details[INDICIA_ID_FIELD][LANGUAGE_NONE][0]['value'] = $indicia_user_id;

  $new_user = user_save(NULL, $user_details);
  $new_user_obj = entity_metadata_wrapper('user', $new_user);
  return $new_user_obj;
}

function send_activation_email($new_user) {
  indicia_api_log('Sending activation email to ' . $new_user->mail->value());

  $params = [
    'uid' => $new_user->getIdentifier(),
    'confirmation_code' => $new_user->{CONFIRMATION_FIELD}->value(),
  ];
  drupal_mail('indicia_api',
    'register',
    $new_user->mail->value(),
    user_preferred_language($new_user),
    $params
  );
}

function return_user_details($user_full, $fullDetails = FALSE) {
  indicia_api_log('Returning response.');

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
  indicia_api_log(print_r($output, 1));
}