<?php

namespace Drupal\indicia_api\api\Reports;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;

//Should be able to remove at end
use Symfony\Component\HttpFoundation\JsonResponse;

require 'get.php';

/**
 * The Report controller.
 */
class ReportController extends ControllerBase {

  /**
   * {@inheritdoc}
   */
  public function parse($report) {

    //Replace colons that were put in place of / as part of the UrlPathProcessor
    $report = str_replace(':','/', $report);

    switch ($_SERVER['REQUEST_METHOD']) {
      case 'GET':
        $request = $_GET;
        drupal_static('request', $request);

        indicia_api_log('[Reports get]');
        indicia_api_log(print_r($request, 1));

        return report_get($report);
        break;

      case 'OPTIONS':
        break;

      default:
        return error_print(405, 'Method Not Allowed');
    }
  }

}
