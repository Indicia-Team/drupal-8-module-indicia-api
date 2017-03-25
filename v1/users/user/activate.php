<?php


function user_activate($user) {
  if (!validate_user_activate_request($user)) {
    return;
  }

  $request = drupal_static('request');

  $uid = $user->uid;
  $code = $request['activationToken'];

  $user = user_load(intval($uid));
  $user_obj = entity_metadata_wrapper('user', $user);
  $key = 'field_activation_token';
  print($code);
  if ($user_obj->$key->value() === $code) {
    // Values match so activate account.
    indicia_api_log("Activating user $uid with code $code.");

    $user_obj->$key->set(NULL);
    $user_obj->status->set(1);
    $user_obj->save();

    // Redirect to page of admin's choosing.
    $path = variable_get('indicia_api_registration_redirect', "<front>");
    drupal_goto($path);
  }
  else {
    // Values did not match so redirect to page of admin's choosing.
    $path = variable_get('indicia_api_registration_redirect_unsuccessful', "<front>");
    drupal_goto($path);
  }
}

// No need to check API KEY because it is email authenticated.
function validate_user_activate_request($user) {
  $request = drupal_static('request');

  // Check if user with UID exists.
  if (!$user) {
    error_print(404, 'Not found', 'User not found.');

    return FALSE;
  }

  // Check if password field is set for a reset.
  if (!isset($request['activationToken']) || empty($request['activationToken'])) {
    error_print(400, 'Bad Request', 'Nothing to process.');

    return FALSE;
  }

  return TRUE;
}