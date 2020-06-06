<?php

namespace Drupal\indicia_api\Plugin\rest\resource;

use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Drupal\Core\Session\AccountProxyInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

const INDICIA_ID_FIELD = 'field_indicia_user_id';

iform_load_helpers(['data_entry_helper']);

/**
 * Provides a Report Resource
 *
 * @RestResource(
 *   id = "indicia_report_resource",
 *   label = @Translation("Indicia Warehouse Report"),
 *   uri_paths = {
 *     "canonical" = "/indicia/report"
 *   }
 * )
 */
class Report extends ResourceBase
{
  /**
   * A current user instance.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * The request object that contains the parameters.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected $request;

  /**
   * Constructs a new object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param array $serializer_formats
   *   The available serialization formats.
   * @param \Psr\Log\LoggerInterface $logger
   *   A logger instance.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   A current user instance.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    array $serializer_formats,
    LoggerInterface $logger,
    AccountProxyInterface $current_user,
    Request $request
  ) {
    parent::__construct(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $serializer_formats,
      $logger
    );
    $this->request = $request;
    $this->currentUser = $current_user;
    $this->logger = $logger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition
  ) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->getParameter('serializer.formats'),
      $container->get('logger.factory')->get('indicia_api'),
      $container->get('current_user'),
      $container->get('request_stack')->getCurrentRequest()
    );
  }

  /**
   * Responds to entity GET requests.
   * @return \Drupal\rest\ResourceResponse
   */
  public function get()
  {
    $this->logger->notice("[Reports get]");

    $params = $this->request->query->all();
    $this->logger->notice(print_r($params, 1));

    // Configure caching settings.
    $build = [
      '#cache' => [
        'max-age' => 0,
      ],
    ];

    return $this->fetchReport($params)->addCacheableDependency($build);
  }

  private function fetchReport($params)
  {
    $report = $params['report'];

    if (empty($report)) {
      $this->logger->notice("invalid report");
      return new ResourceResponse(
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
        ->load($this->currentUser->id());

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

    if (!isset($response['output'])) {
      return new ResourceResponse("Bad gateway", 502);
    }

    $data = json_decode($response['output'], true);

    if (isset($data['error'])) {
      return new ResourceResponse(['message' => $data['error']], 404);
    }

    if ($caching !== 'false' && !$cache_loaded) {
      \data_entry_helper::cache_set(
        $params,
        json_encode($response),
        $cache_timeout
      );
    }

    return new ResourceResponse(['data' => $data]);
  }
}
