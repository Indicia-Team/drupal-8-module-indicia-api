<?php

/**
 * @file
 * Contains \Drupal\iform\Form\SettingsForm.
 */

namespace Drupal\indicia_api\admin\forms;

use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\Core\Logger\RfcLogLevel;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

class SettingsForm extends FormBase {

  /**
   * {@inheritdoc}.
   */
  public function getFormId() {
    return 'indicia_api_settings_form';
  }

  /**
   * {@inheritdoc}.
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $title = \Drupal::request()->query->get('title');

    $existing = !is_null($title);

    if ($existing) {
      // Editing an existing key.
      $query = \Drupal::database()->select('indicia_api', 'ind');
      $qery = $query->fields('ind', ['title', 'description', 'api_key', 'enabled', 'log', 'created_by', 'anonymous_user']);
      $query->condition('ind.title', $title);
      $key = $query->execute()->fetchAssoc();

      if (empty($key)) {
        // Requested an key with an id that doesn't exist in DB.
        drupal_set_message(t('Unknown API Key.'));
        throw new NotFoundHttpException();
        return;
      }
      else {
        if (indicia_api_user_has_permission($key)) {
          $form['#title'] = $key['title'];
        }
        else {
          throw new AccessDeniedHttpException();
          return;
        }
      }
    }
    else {

      // New key, set variables to default values.
      $key = array();
      $key['enabled'] = 1;
      $key['log'] = 0;
      $key['title'] = '';
      $key['description'] = '';
      $key['api_key'] = indicia_api_generate_random_string(40);
      $key['anonymous_user'] = 0;
    }

    // Build form.
    $form['enabled'] = array(
      '#type' => 'checkbox',
      '#title' => t('Enabled'),
      '#default_value' => $key['enabled'],
      '#description' => t('Check to enable key.'),
    );
    $form['log'] = array(
      '#type' => 'select',
      '#title' => t('Logging mode'),
      '#options' => array(
        0 => t('None'),
        RfcLogLevel::ERROR => t('Error'),
        RfcLogLevel::DEBUG => t('Debug'),
      ),
      '#default_value' => $key['log'],
      '#description' => t("Select key's logging mode."),
    );
    $form['title'] = array(
      '#type' => 'textfield',
      '#title' => t('Title'),
      '#default_value' => $key['title'],
      '#description' => t('Set the human readable title for this key.'),
      '#required' => TRUE,
    );
    $form['description'] = array(
      '#type' => 'textarea',
      '#title' => t('Key description'),
      '#description' => t('Short key description.'),
      '#default_value' => $key['description'],
    );
    $form['api_key'] = array(
      '#type' => 'textfield',
      '#title' => t('API key'),
      '#default_value' => $key['api_key'],
      '#description' => t('Set the API key to be used for authentication.'),
      '#required' => TRUE,
    );

    $form['anonymous_user'] = array(
      '#type' => 'textfield',
      '#title' => t('Anonymous user ID'),
      '#default_value' => $key['anonymous_user'],
      '#description' => t('Set a user ID to allow anonymous record submissions.'),
      '#required' => FALSE,
    );


    if (!empty($title)) {
      // Editing existing key.
      $form['changed'] = array(
        '#type' => 'value',
        '#value' => time(),
      );
    }
    else {
      // New key.
      $time = time();
      global $user;
      $form['created_by'] = array(
        '#type' => 'value',
        '#value' => \Drupal::currentUser(),
      );
      $form['created'] = array(
        '#type' => 'value',
        '#value' => $time,
      );
      $form['changed'] = array(
        '#type' => 'value',
        '#value' => $time,
      );
    }

    $form['submit'] = array(
      '#type' => 'submit',
      '#value' => t('Save'),
    );

    $form['cancel'] = array(
      '#type' => 'link',
      '#title' => t('Cancel'),
      '#href' => CONFIG_PATH,
      '#attributes' => [
        'class' => ['button'],
        ],
    );

    if ($existing) {
/*      $form['delete'] = array(
        '#markup' => Link::fromTextAndUrl(t('Delete'), Url::fromRoute('indicia_api.delete', array('title' => $key['title']))),
      );*/
    }

    // Check if user has access to create new key.
    if (\Drupal::currentUser()->hasPermission('user mobile auth') || \Drupal::currentUser()->hasPermission('admin mobile auth')) {
      return $form;
    }
    else {
      throw new AccessDeniedHttpException();
      return;
    }
  }

  /**
   * Submit handler to save an key.
   *
   * Implements hook_submit() to submit a form produced by
   * indicia_api_key().
   */
  function submitForm(array &$form, FormStateInterface $form_state) {

    $form_values = $form_state->getValues();

    $enabled = $form_values['enabled'];
    $log = $form_values['log'];
    $title = $form_values['title'];
    $description = $form_values['description'];
    $api_key = $form_values['api_key'];
    $anonymous_user = $form_values['anonymous_user'];


/*    if (empty($form_values['secret'])) {
      // Don't overwrite old password if wasn't touched while editing.
      unset($form_values['secret']);
    }
*/

    $values = [
      'enabled' => $enabled,
      'log' => $log,
      'title' => $title,
      'description' => $description,
      'api_key' => $api_key,
      'anonymous_user' => $anonymous_user,
    ];

    // Save the key against the title key
    \Drupal::database()->merge('indicia_api')
      ->key(array('title' => $title))
      ->fields( $values)
      ->execute();

    $message = 'Key saved';

    // Inform user and return to dashboard.
    drupal_set_message(t($message, array('%key' => $title)));
    $form_values['redirect'] = CONFIG_PATH;
  }

}
