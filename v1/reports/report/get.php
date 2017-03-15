<?php

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
function report_get($reportID) {
  $request = drupal_static('request');
  $params = $request; // TODO: clone it

  if (!validate_report_get_request($reportID)) {
    return;
  }

  // Wrap user for ease of accessing fields.
  $user_wrapped = entity_metadata_wrapper('user', $GLOBALS['user']);

  $connection = iform_get_connection_details(NULL);
  $auth = data_entry_helper::get_read_auth($connection['website_id'], $connection['password']);

  $url = helper_config::$base_url . 'index.php/services/report/requestReport';

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
    $params['user_id'] = $user_wrapped->field_indicia_user_id->value();
  }

  $params = array_merge($defaults, $auth, $params, [ 'report' => $reportID ]);

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

  return_report_response($response, $params, $cache_loaded, $caching, $cache_timeout);
}

/**
 * Validates the request params inc. user details.
 *
 * @return bool
 *   True if the request is valid
 */
function validate_report_get_request($reportID) {
  // Reject submissions with an incorrect secret (or instances where secret is
  // not set).
  if (!indicia_api_authorise_key()) {
    error_print(401, 'Unauthorized', 'Missing or incorrect API key.');

    return FALSE;
  }

  if (!indicia_api_authorise_user()) {
    error_print(401, 'Unauthorized', 'Could not find/authenticate user.');

    return FALSE;
  }

  if (empty($reportID)) {
    error_print(400, 'Bad Request', 'Missing or incorrect report url.');

    return FALSE;
  }

  return TRUE;
}

function return_report_response($response, $params, $cache_loaded, $caching, $cache_timeout) {
  indicia_api_log('Returning response.');

  if (!isset($response['output'])) {
    error_print(502, 'Bad gateway');
    return;
  }

  $data = json_decode($response['output'], TRUE);

  if (isset($data['error'])) {
    error_print(404, 'Not Found', $data['error']);
    return;
  }

  if ($caching !== 'false' && !$cache_loaded) {
    data_entry_helper::cache_set($params, json_encode($response), $cache_timeout);
  }

  drupal_add_http_header('Status', '200 OK');
  $output = ['data' => $data];
  drupal_json_output($output);
  indicia_api_log(print_r($output, 1));
}
