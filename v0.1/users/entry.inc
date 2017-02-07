<?php

require 'GET.php';
require 'POST.php';

function indicia_api_users($uid) {
  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    indicia_api_users_post();
  } else {
    indicia_api_users_get($uid);
  }
}


function return_user_details($user_obj, $ownAuthentication) {
  $data = [];
  if (is_array($user_obj)) {
    // multiple.
    foreach ($user_obj as $user) {
      $userData = [
        'type' => 'users',
        'id' => (int) $user->getIdentifier(),
        'warehouse_id' => (int) $user->{INDICIA_ID_FIELD}->value(),
        'firstname' => $user->{FIRSTNAME_FIELD}->value(),
        'secondname' => $user->{SECONDNAME_FIELD}->value(),
      ];

      $ownAuthentication = $_SERVER['PHP_AUTH_USER'] === $user->mail->value();
      if ($ownAuthentication) {
        $userData['email'] = $user->mail->value();
      }

      array_push($data, $userData);
    }
  } else {
    // single.
    if (!$ownAuthentication) {
      $ownAuthentication = $_SERVER['PHP_AUTH_USER'] === $user_obj->mail->value();
    }

    $data = [
      'type' => 'users',
      'id' => (int) $user_obj->getIdentifier(),
      'warehouse_id' => (int) $user_obj->{INDICIA_ID_FIELD}->value(),
      'firstname' => $user_obj->{FIRSTNAME_FIELD}->value(),
      'secondname' => $user_obj->{SECONDNAME_FIELD}->value(),
    ];

    if ($ownAuthentication) {
      $data['email'] = $user_obj->mail->value();
    }
  }

  $output = ['data' => $data];
  drupal_json_output($output);
}
