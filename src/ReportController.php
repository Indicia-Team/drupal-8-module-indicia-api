<?php

namespace Drupal\indicia_api;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Logger\RfcLogLevel;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;

const INDICIA_ID_FIELD = 'field_indicia_user_id';

iform_load_helpers(['data_entry_helper']);

function fetch_report($request, $currentUser)
{
  $params = $request->query->all();
  $report = $params['report'];

  if (empty($report)) {
    $this->logger->notice("invalid report");
    return new JsonResponse(
      ['message' => 'Missing or incorrect report url.'],
      400
    );
  }

  $connection = iform_get_connection_details(null);
  $auth = \data_entry_helper::get_read_auth(
    $connection['website_id'],
    $connection['password']
  );

  $url =
    \data_entry_helper::$base_url . 'index.php/services/report/requestReport';

  $caching = !empty($params['caching']) ? $params['caching'] : 'false';
  $cache_timeout = !empty($params['cacheTimeout'])
    ? $params['cacheTimeout']
    : 3600;

  unset($params['api_key']);
  unset($params['email']);
  unset($params['cacheTimeout']);
  unset($params['user_id']);

  $defaults = [
    'reportSource' => 'local',
  ];

  if ($caching === 'false' || $params['caching'] === 'perUser') {
    $user = \Drupal::entityTypeManager()
      ->getStorage('user')
      ->load($currentUser->id());

    $params['user_id'] = $user->get(INDICIA_ID_FIELD)->value;
  }

  $params = array_merge($defaults, $auth, $params, ['report' => $report]);

  $cache_loaded = false;
  if ($caching !== 'false') {
    $response = \data_entry_helper::cache_get($params, $cache_timeout);
    if ($response !== false) {
      $response = json_decode($response, true);
      $cache_loaded = true;
      print "cache read $cache_timeout<br/>";
    }
  }

  if (!isset($response) || $response === false) {
    $response = \data_entry_helper::http_post(
      $url . '?' . \data_entry_helper::array_to_query_string($params),
      null,
      false
    );
  }

  return report_response(
    $response,
    $params,
    $cache_loaded,
    $caching,
    $cache_timeout
  );
}

function report_response(
  $response,
  $params,
  $cache_loaded,
  $caching,
  $cache_timeout
) {
  indicia_api_log('Returning response.');

  if (!isset($response['output'])) {
    return error_print(502, 'Bad gateway');
  }

  $data = json_decode($response['output'], true);

  if (isset($data['error'])) {
    return error_print(404, 'Not Found', $data['error']);
  }

  if ($caching !== 'false' && !$cache_loaded) {
    \data_entry_helper::cache_set(
      $params,
      json_encode($response),
      $cache_timeout
    );
  }

  $output = ['data' => $data];

  $headers = [
    'Status' => '200 OK',
    'Access-Control-Allow-Origin' => '*',
    'Access-Control-Allow-Methods' => 'GET,PUT,OPTIONS',
    'Access-Control-Allow-Headers' => 'authorization, x-api-key, content-type',
  ];
  return new JsonResponse($output, '200', $headers);
}

/**
 * The Report controller.
 */
class ReportController extends ControllerBase
{
  /**
   * {@inheritdoc}
   */
  public function parse(Request $request)
  {
    switch ($_SERVER['REQUEST_METHOD']) {
      case 'GET':
        \Drupal::logger('indicia_api')->notice('[Reports get]');

        $user = $this->currentUser();

        $user_authenticated = $user->isAuthenticated();
        if (!$user_authenticated) {
          return error_print(
            400,
            'Bad Request',
            "Could not find/authenticate user."
          );
        }

        return fetch_report($request, $user);

      case 'OPTIONS':
        break;

      default:
        return error_print(
          405,
          'Method Not Allowed',
          $_SERVER['REQUEST_METHOD']
        );
    }
  }
}
