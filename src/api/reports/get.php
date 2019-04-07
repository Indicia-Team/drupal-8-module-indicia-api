<?php

use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Reports GET request handler.
 *
Requires the following
 * query parameters:
 * * report - the path to the report file to run on the warehouse,
 *   e.g. 'library/totals/filterable_species_occurrence_image_counts.xml'
 * * email - the logged in user's email, used for authentication
 * * password - the user password, used for authentication
 * * key - the secret key, used for authentication.
 * * caching - optional setting to define the caching mode which defaults to
 *   false (no caching).
 *   Set to global for a single global cache entry (which cannot be used for
 *   user-specific reports).
 *   Set to perUser to cache the report on a per user basis.
 * * cacheTimeout - number of seconds before which the cache cannot expire.
 *   After this, there is a random chance of expiry on each hit. Defaults to
 *   3600.
 * Additionally, provide a query parameter for each report parameter value,
 * orderby, sortdir, limit or offset you wish to pass to the report.
 * Prints out a JSON string for the report response.
 */
function report_get($report) {
  $request = drupal_static('request');
  $params = $request; // TODO: clone it

  $valid = validate_report_get_request($report);
  if (!empty($valid)) {
    return error_print($valid['code'], $valid['header'], $valid['msg']);
  }

  $connection = iform_get_connection_details(NULL);
  $auth = data_entry_helper::get_read_auth($connection['website_id'], $connection['password']);

  $url = data_entry_helper::$base_url . 'index.php/services/report/requestReport';

  $caching = !empty($request['caching']) ? $request['caching'] : 'false';
  $cache_timeout = !empty($request['cacheTimeout']) ? $request['cacheTimeout'] : 3600;

  unset($params['api_key']);
  unset($params['email']);
  unset($params['cacheTimeout']);
  unset($params['user_id']);

  $defaults = array(
    'reportSource' => 'local',
  );

  if ($caching === 'false' || $request['caching'] === 'perUser') {
    $user = indicia_api_authorise_user();
    $params['user_id'] = $user->get(INDICIA_ID_FIELD)->value;
  }

  $params = array_merge($defaults, $auth, $params, [ 'report' => $report ]);

  $cache_loaded = FALSE;
  if ($caching !== 'false') {
    $response = data_entry_helper::cache_get($params, $cache_timeout);
    if ($response !== FALSE) {
      $response = json_decode($response, TRUE);
      $cache_loaded = TRUE;
      print "cache read $cache_timeout<br/>";
    }
  }

  if (!isset($response) || $response === FALSE) {
    $response = data_entry_helper::http_post(
      $url . '?' . data_entry_helper::array_to_query_string($params),
      NULL,
      FALSE
    );
  }

  return report_response($response, $params, $cache_loaded, $caching, $cache_timeout);
}

/**
 * Validates the request params inc. user details.
 *
 * @return bool
 *   True if the request is valid
 */
function validate_report_get_request($report) {
  // Reject submissions with an incorrect secret (or instances where secret is
  // not set).
  if (!indicia_api_authorise_key()) {
    return array(
      'code' => 401,
      'header' => 'Unauthorized',
      'msg' => 'Missing or incorrect API key.',
    );
  }

  if (!indicia_api_authorise_user()) {
    return array(
      'code' => 401,
      'header' => 'Unauthorized',
      'msg' => 'Could not find/authenticate user.',
    );
  }

  if (empty($report)) {
    return array(
      'code' => 400,
      'header' => 'Bad Request',
      'msg' => 'Missing or incorrect report url.',
    );
  }

  return array();
}

function report_response($response, $params, $cache_loaded, $caching, $cache_timeout) {
  indicia_api_log('Returning response.');

  if (!isset($response['output'])) {
    return error_print(502, 'Bad gateway');
  }

  $data = json_decode($response['output'], TRUE);

  if (isset($data['error'])) {
    return error_print(404, 'Not Found', $data['error']);
  }

  if ($caching !== 'false' && !$cache_loaded) {
    data_entry_helper::cache_set($params, json_encode($response), $cache_timeout);
  }

  $output = ['data' => $data];
  indicia_api_log(print_r($output, 1));

  $headers = [
    'Status' => '200 OK',
    'Access-Control-Allow-Origin' => '*',
    'Access-Control-Allow-Methods' => 'GET,PUT,OPTIONS',
    'Access-Control-Allow-Headers' => 'authorization, x-api-key, content-type',
  ];
  return new JsonResponse($output, '200', $headers);
}
