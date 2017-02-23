<?php

require 'get.php';
require 'patch.php';

function indicia_api_user($uid = NULL) {
    drupal_add_http_header('Access-Control-Allow-Origin', "*");
    drupal_add_http_header('Access-Control-Allow-Methods', 'GET,POST,OPTIONS');
    drupal_add_http_header('Access-Control-Allow-Headers', 'Authorization');

    switch ($_SERVER['REQUEST_METHOD']) {
        case 'GET':
            user_get(load_user($uid));
            break;

        case 'PATCH':
            // Only supports password reset at the moment
            user_patch(load_user($uid));
            break;

        case 'OPTIONS':
            break;

        default:
            error_print(405, 'Method Not Allowed');
    }
}

function load_user($uid) {
    if (is_numeric($uid)) {
        // UID.
        $existing_user = user_load($uid);
        $existing_user_obj = entity_metadata_wrapper('user', $existing_user);
        check_user_indicia_id($existing_user_obj);
    }
    elseif(filter_var($uid, FILTER_VALIDATE_EMAIL)) {
        // Email.
        $existing_user = user_load_by_mail($uid);
        $existing_user_obj = entity_metadata_wrapper('user', $existing_user);
        check_user_indicia_id($existing_user_obj);
    }
    else {
        // Name.
        $existing_user = user_load_by_name($uid);
        $existing_user_obj = entity_metadata_wrapper('user', $existing_user);
        check_user_indicia_id($existing_user_obj);
    }

    return $existing_user_obj;
}

function return_user_details($user_obj, $fullDetails = FALSE) {
    $data = [
        'type' => 'users',
        'id' => (int) $user_obj->getIdentifier(),
        'firstname' => $user_obj->{FIRSTNAME_FIELD}->value(),
        'secondname' => $user_obj->{SECONDNAME_FIELD}->value(),
    ];

    if (ownAuthenticated($user_obj) || $fullDetails) {
        $data['name'] = $user_obj->name->value();
        $data['email'] = $user_obj->mail->value();
        $data['warehouse_id'] = (int) $user_obj->{INDICIA_ID_FIELD}->value();
    }

    $output = ['data' => $data];
    drupal_json_output($output);
}


function ownAuthenticated($user) {
    // Allow to update own user record only.
    if(filter_var($_SERVER['PHP_AUTH_USER'], FILTER_VALIDATE_EMAIL) &&
        $_SERVER['PHP_AUTH_USER'] === $user->mail->value()) {
        // Email.
        return TRUE;
    }
    elseif ($_SERVER['PHP_AUTH_USER'] === $user->name->value()) {
        // Name.
        return TRUE;
    }

    return FALSE;
}