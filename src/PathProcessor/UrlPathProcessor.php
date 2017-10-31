<?php

namespace Drupal\indicia_api\PathProcessor;

use Drupal\Core\PathProcessor\InboundPathProcessorInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * UrlPathProcessor
 *
 * This inspects any inbound API request and looks for '/reports/' in the url
 * and replaces the path after with :'s instead of /'s.
 *
 * Drupal 8 doesn't support forward slashes in Url routing and so our requests
 * to get a report with a full file path fails.
 * i.e.: /api/v1/reports/path/to/file.xml
 *
 * The route is /api/v1/reports/, the file path is {path/to/file.xml}
 *
 * As explained here: https://drupal.stackexchange.com/questions/175758/slashes-in-single-route-parameter-or-other-ways-to-handle-a-menu-tail-with-dynam/187497#187497
 */

class UrlPathProcessor implements InboundPathProcessorInterface {

  public function processInbound($path, Request $request) {
    if (strpos($path, '/api/v1/reports/') === 0) {

      $filepath = preg_replace('/\/api\/v1\/reports\//', '', $path);
      $filepath = str_replace('/',':', $filepath);
      \Drupal::logger('indicia_api')->warning("/api/v1/reports/$filepath");
      return "/api/v1/reports/$filepath";
    }
    return $path;
  }

}
