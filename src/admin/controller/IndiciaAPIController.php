<?php

namespace Drupal\indicia_api\Controller;

use Drupal\Core\Logger\RfcLogLevel;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\Core\Url;
/**
 * An example controller.
 */
class IndiciaAPIController extends ControllerBase {

  /**
   * {@inheritdoc}
   */
  public function content() {
    //$r = '<p>This dashboard allows you to manage API client keys. </p>';

    // Create table.
    $header = array('Enabled', 'Title', 'Description', 'Key', 'Logging', '');

    $rows = array();

    $keys = indicia_api_key_load();

    foreach ($keys as $key) {
      if (indicia_api_user_has_permission($key)) {
        $row = array();

        $row[0] = ($key['enabled'] ? 'Enabled' : 'Disabled');
        $row[1] = $key['title'];
        $row[2] = $key['description'];

        $row[3] = [
          'data' => $key['api_key'],
          'style' => 'color: rgba(0,0,0,0.87); font-style: italic; background-color: #f5f5f5; margin: 0px 0px 26px 0px;',
        ];

        $log_mode = $key['log'];
        switch ($log_mode) {
          case RfcLogLevel::ERROR:
            $row[4] = 'Error';
            break;

          case RfcLogLevel::DEBUG:
            $row[4] = 'Debug';
            break;

          default:
            $row[4] = 'None';
        }
        $row[5] = Link::fromTextAndUrl(t('Edit'), Url::fromRoute('indicia_api.configure_api', array('title' => $key['title'])));

        $rows[] = $row;
      }
    }

    $render['table'] = [
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $rows,
      '#attributes' => array('style' => 'width:100%; text-align:left')
    ];

    $url = Url::fromRoute('indicia_api.configure_api');
    $link_options = array(
      'attributes' => array(
        'class' => array(
          'button'
        ),
      ),
    );
    $url->setOptions($link_options);
    $link = Link::fromTextAndUrl(t('Add new key'), $url )->toRenderable();

    $render['add_button'] = array($link);

    return $render;
  }

}
