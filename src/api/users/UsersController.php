<?php

namespace Drupal\indicia_api\api\Users;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;

require 'get.php';
require 'create.php';

/**
 * The Users controller.
 */
class UsersController extends ControllerBase {

  /**
   * {@inheritdoc}
   */
  public function parse() {

    switch ($_SERVER['REQUEST_METHOD']) {
      case 'POST':
        $request = json_decode(file_get_contents('php://input'), TRUE);

        drupal_static('request', $request);

        indicia_api_log('[Users create]');
        indicia_api_log(print_r($request, 1));

        return users_create();
        break;

      case 'GET':
        $request = $_GET;
        drupal_static('request', $request);

        indicia_api_log('[Users get]');
        indicia_api_log(print_r($request, 1));

        return users_get();
        break;

      case 'OPTIONS':
        break;

      default:
        error_print(405, 'Method Not Allowed');
    }
  }
}
