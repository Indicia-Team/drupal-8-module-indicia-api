<?php

/**
 * @file
 * Module's UI.
 */

/**
 * Creates a blank form for a new key or populates it if editing.
 */
function indicia_api_key($form, &$form_state, $keys = NULL) {
  $existing = !is_null($keys);
  if ($existing) {
    // Editing an existing key.
    if (empty($keys)) {
      // Requested an key with an id that doesn't exist in DB.
      drupal_not_found();
      return;
    }
    else {
      // Since key ids are unique and the URL argument is one id number
      // the $keys array returned from DB must contain information about only
      // one key.
      $key = $keys[0];

      if (user_has_permission($key)) {
        drupal_set_title(t('@title settings', array('@title' => $key['title'])));
      }
      else {
        return drupal_access_denied();
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
      WATCHDOG_ERROR => t('Error'),
      WATCHDOG_DEBUG => t('Debug'),
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

  if (!empty($key['id'])) {
    // Editing existing key.
    $form['id'] = array(
      '#type' => 'value',
      '#value' => $key['id'],
    );
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
      '#value' => $user->uid,
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
    $form['delete'] = array(
      '#markup' => l(t('Delete'), CONFIG_PATH . "/delete/{$key['id']}"),
    );
  }

  // Check if user has access to create new key.
  if (user_access('user mobile auth') || user_access('admin mobile auth')) {
    return $form;
  }
  else {
    return drupal_access_denied();
  }
}

/**
 * Main administrative page displays the information about stored key.
 *
 * Allows to edit/delete each key's information and add new ones.
 *
 * @return string
 *   The html of the page.
 */
function indicia_api_dashboard() {
  $r = '<p>This dashboard allows you to manage API client keys. </p>';

  // Create table.
  $header = array('Enabled', 'Title', 'Description', 'Key', 'Logging', '');
  $rows = array();
  $keys = indicia_api_key_load();
  foreach ($keys as $key) {
    if (user_has_permission($key)) {
      $row = array();
      $row[0] = '<input type="radio"' . ($key['enabled'] ? 'checked' : 'disabled') . ' >';
      $row[1] = $key['title'];
      $row[2] = $key['description'];

      $row[3] = [
        'data' => $key['api_key'],
        'style' => 'color: rgba(0,0,0,0.87); font-style: italic; background-color: #f5f5f5; margin: 0px 0px 26px 0px;',
      ];

      $log_mode = $key['log'];
      switch ($log_mode) {
        case WATCHDOG_ERROR:
          $row[4] = 'Error';
          break;

        case WATCHDOG_DEBUG:
          $row[4] = 'Debug';
          break;

        default:
          $row[4] = 'None';
      }

      $row[5] = l(t('Edit'), CONFIG_PATH . "/{$key['id']}");

      $rows[] = $row;
    }
  }

  $table = theme('table', array(
    'header' => $header,
    'rows' => $rows,
    'attributes' => array('style' => 'width:100%; text-align:left'),
  ));
  $r .= $table;

  // Add links beneath the table.
  $links = array(
    'link1' => array(
      'title' => t('Add new key'),
      'href' => CONFIG_PATH . '/add',
      'attributes' => array('class' => array('button')),
    ),
  );

 
  $r .= theme('links', array(
    'links' => $links,
    'attributes' => array('class' => array('links', 'inline')),
  ));

  $r .= '</fieldset>';

  return $r;
}

/**
 * Submit handler to save an key.
 *
 * Implements hook_submit() to submit a form produced by
 * indicia_api_key().
 */
function indicia_api_key_submit($form, &$form_state) {
  if (empty($form_state['values']['secret'])) {
    // Don't overwrite old password if wasn't touched while editing.
    unset($form_state['values']['secret']);
  }

  if (empty($form_state['values']['id'])) {
    // Save new key.
    drupal_write_record('indicia_api', $form_state['values']);
    $message = 'Created %key.';
  }
  else {
    // Save existing key.
    drupal_write_record('indicia_api', $form_state['values'], 'id');
    $message = 'Saved %key.';
  }

  // Inform user and return to dashboard.
  drupal_set_message(t($message, array('%key' => $form_state['values']['title'])));
  $form_state['redirect'] = CONFIG_PATH;
}

/**
 * A confirmation page to check if the user is sure to delete an key.
 */
function indicia_api_delete($form, &$form_state, $keys) {

  // Requested an key key with an id that does not exist in DB.
  if (empty($keys)) {
    return drupal_not_found();
  }

  // Since key key ids are unique and the URL argument is one id number
  // the $keys array returned from DB must contain information about only
  // one key.
  $key = $keys[0];

  // Set up the form information to be passed to submit handler.
  $form = array();
  $form['id'] = array(
    '#type' => 'value',
    '#value' => $key['id'],
  );
  $form['title'] = array(
    '#type' => 'value',
    '#value' => $key['title'] ,
  );

  $message = t('Are you sure you want to delete %key ?', array('%key' => $key['title']));

  // Check if the user has permission to delete an key.
  if (user_has_permission($key)) {
    return confirm_form($form, $message,
      CONFIG_PATH,
      t('This action cannot be undone.'),
      t('Delete'),
      t('Cancel'));
    // Proceed to delete_submit handler,
  }
  return drupal_access_denied();
}

/**
 * Submit handler to delete an key.
 *
 * Implements hook_submit() to submit a confirmation form produced by
 * indicia_api_delete().
 */
function indicia_api_delete_submit($form, &$form_state) {
  if ($form_state['values']['confirm']) {
    // Delete key from database.
    db_query('DELETE FROM {indicia_api} WHERE id = :id',
      array(':id' => $form_state['values']['id']));

    // Inform user and return to dashboard.
    drupal_set_message(t('Deleted %key.', array('%key' => $form_state['values']['title'])));
  }
  $form_state['redirect'] = CONFIG_PATH;
}
