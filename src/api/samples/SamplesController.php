<?php

namespace Drupal\indicia_api\api\Samples;

use Drupal\Core\Controller\ControllerBase;

//Should be able to remove at end
use Symfony\Component\HttpFoundation\JsonResponse;

require 'create.php';

/**
 * The Samples controller.
 */
class SamplesController extends ControllerBase {

  /**
   * {@inheritdoc}
   */
  public function parse() {

    // TODO: Need to move to be added to all responses
    $headers = [
      'Access-Control-Allow-Origin' => '*',
      'Access-Control-Allow-Methods' => 'GET,PUT,OPTIONS',
      'Access-Control-Allow-Headers' => 'authorization, x-api-key, content-type',
    ];

    switch ($_SERVER['REQUEST_METHOD']) {
      case 'POST':
        indicia_api_log('[Samples create]');

        $request = json_decode(file_get_contents('php://input'), TRUE);

        // Support form-data with files attached.
        if (empty($request) && !empty($_POST['submission'])) {
          indicia_api_log('Using POST');
          $submission = json_decode($_POST['submission'], TRUE);
          $request = $submission;
        }

        drupal_static('request', $request);
        indicia_api_log(print_r($request, 1));

        return samples_create();

      case 'OPTIONS':
        break;

      default:
        return error_print(405, 'Method Not Allowed', $_SERVER['REQUEST_METHOD']);
    }
  }
}
