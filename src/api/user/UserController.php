<?php

namespace Drupal\indicia_api\api\User;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;

require 'get.php';
require 'update_.php';
require 'activate.php';

/**
 * The User controller.
 */
class UserController extends ControllerBase {

  /**
   * {@inheritdoc}
   */
  public function parse($user = NULL, $activate = FALSE) {

    // TODO: Need to move to be added to all responses
    $headers = [
      'Access-Control-Allow-Origin' => '*',
      'Access-Control-Allow-Methods' => 'GET,PUT,OPTIONS',
      'Access-Control-Allow-Headers' => 'authorization, x-api-key, content-type',
    ];

    switch ($_SERVER['REQUEST_METHOD']) {
      case 'GET':
        $request = $_GET;
        drupal_static('request', $request);

        if ($activate) {
          indicia_api_log('[User activate]');
          indicia_api_log(print_r($request, 1));

          // Only supports password reset at the moment.
          user_activate($this->load_user($user));
          return;
        }

        indicia_api_log('[User get]');
        indicia_api_log(print_r($request, 1));

        return user_get($this->load_user($user));
        break;

      case 'PUT':
        $request = json_decode(file_get_contents('php://input'), TRUE);

        drupal_static('request', $request);

        indicia_api_log('[User update]');
        indicia_api_log(print_r($request, 1));

        // Only supports password reset at the moment.
        return user_update($this->load_user($user));
        break;

      case 'OPTIONS':
        break;

      default:
        error_print(405, 'Method Not Allowed');
    }
  }

  public function load_user($user) {
    // UID.
    if (is_numeric($user)) {
      indicia_api_log('Loading user by uid: ' . $user . '.');
      $user = user_load($user);
    }
    // Email.
    elseif (filter_var($user, FILTER_VALIDATE_EMAIL)) {
      indicia_api_log('Loading user by email: ' . $user . '.');
      $user = user_load_by_mail($user);

      // In case the username is an email and username != email
      if (empty($user)) {
        indicia_api_log('Loading user by name: ' . $user . '.');
        $user = user_load_by_name($user);
      }
    }
    // Name.
    else {
      indicia_api_log('Loading user by name: ' . $user . '.');
      $user = user_load_by_name($user);
    }

    return $user;
  }


}
