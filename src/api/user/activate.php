<?php
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;


function user_activate($user) {
  $valid = validate_user_activate_request($user);
  $url = \Drupal\Core\Url::fromRoute('<front>', [], ['absolute' => 'true']);
  if (!$valid) {
    return new RedirectResponse($url->toString());
  }

  $user->set(ACTIVATION_FIELD, NULL);
  $user->activate();
  $user->save();

  return new RedirectResponse($url->toString());
}

// No need to check API KEY because it is email authenticated.
function validate_user_activate_request($user) {
  if (!$user) {
    return FALSE;
  }

  $activationToken = $user->get(ACTIVATION_FIELD)->value;
  if (empty($user->get(ACTIVATION_FIELD)->value)) {
    return FALSE;
  }

  $request = drupal_static('request');
  $providedActivationToken = $request['activationToken'];
  if ($activationToken !== $providedActivationToken) {
    return FALSE;
  }

  if (empty($request['activationToken'])) {
    return FALSE;
  }

  return TRUE;
}
