<?php

/**
 * @file
 * Module's UI.
 */

/**
 * Creates a blank form for a new app or populates it if editing.
 */
function indicia_api_app($form, &$form_state, $apps = NULL) {
  if (!is_null($apps)) {
    // Editing an existing app.
    if (empty($apps)) {
      // Requested an app with an id that doesn't exist in DB.
      drupal_not_found();
      return;
    }
    else {
      // Since app account ids are unique and the URL argument is one id number
      // the $apps array returned from DB must contain information about only
      // one account.
      $app = $apps[0];

      if (user_has_permission($app)) {
        drupal_set_title(t('@title settings', array('@title' => $app['title'])));
      }
      else {
        return drupal_access_denied();
      }
    }
  }
  else {

    // New app, set variables to default values.
    $app = array();
    $app['enabled'] = 1;
    $app['debug'] = 0;
    $app['name'] = '';
    $app['title'] = '';
    $app['description'] = '';
    $app['secret'] = indicia_api_generate_random_string(8);
  }

  // Build form.
  $form['enabled'] = array(
    '#type' => 'checkbox',
    '#title' => t('Enabled'),
    '#default_value' => $app['enabled'],
    '#description' => t('Check to enable app.'),
  );
  $form['debug'] = array(
    '#type' => 'checkbox',
    '#title' => t('Debug mode'),
    '#default_value' => $app['debug'],
    '#description' => t('Check to enable debug mode. DO NOT ENABLE ON YOUR PRODUCTION SITE'),
  );
  $form['title'] = array(
    '#type' => 'textfield',
    '#title' => t('Title'),
    '#default_value' => $app['title'],
    '#description' => t('Set the human readable title for this app.'),
    '#required' => TRUE,
  );
  $form['description'] = array(
    '#type' => 'textarea',
    '#title' => t('App description'),
    '#description' => t('Short app description.'),
    '#default_value' => $app['description'],
  );
  $form['name'] = array(
    '#type' => 'textfield',
    '#title' => t('App name'),
    '#default_value' => $app['name'],
    '#description' => t('Set the app name to be used for authentication. Enter "anonymous" for backwards compatibility with old apps that do not send an app name.'),
    '#required' => TRUE,
  );
  $form['secret'] = array(
    '#type' => 'textfield',
    '#title' => t('Shared secret'),
    '#default_value' => $app['secret'],
    '#description' => t('Set the shared secret to be used for authentication.'),
    '#required' => TRUE,
  );

  if (!empty($app['id'])) {
    // Editing existing app.
    $form['id'] = array(
      '#type' => 'value',
      '#value' => $app['id'],
    );
    $form['changed'] = array(
      '#type' => 'value',
      '#value' => time(),
    );
  }
  else {
    // New app.
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
    '#markup' => l(t('Cancel'), CONFIG_PATH . '/mobile/dashboard'),
  );

  // Check if user has access to create new app.
  if (user_access('user mobile auth') || user_access('admin mobile auth')) {
    return $form;
  }
  else {
    return drupal_access_denied();
  }
}

/**
 * Main administrative page displays the information about stored applications.
 *
 * Allows to edit/delete each application's information and add new ones.
 *
 * @return string
 *   The html of the page.
 */
function indicia_api_dashboard() {
  $r = '<p>API client accounts. 
    This allows you to manage unique accounts 
    as well as the ones sharing the same <i>appname</i> or <i>appsecret</i>.</p>';

  $r .= '<fieldset><legend>Authentication accounts</legend>';

  // Create table.
  $header = array('Enabled', 'Title', 'Description', 'Debugging', '', '', '');
  $rows = array();
  $apps = indicia_api_app_load();
  foreach ($apps as $app) {
    if (user_has_permission($app)) {
      $row = array();
      $row[0] = '<input type="radio"' . ($app['enabled'] ? 'checked' : 'disabled') . ' >';
      $row[1] = $app['title'];
      $row[2] = $app['description'];
      $row[3] = '<input type="radio"' . ($app['debug'] ? 'checked' : 'disabled') . '>';
      $row[4] = l(t('Edit'), CONFIG_PATH . "/{$app['id']}");
      $row[5] = l(t('Delete'), CONFIG_PATH . "/delete/{$app['id']}");

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
      'title' => t('Add Mobile Application'),
      'href' => CONFIG_PATH . '/add',
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
 * Submit handler to save an app.
 *
 * Implements hook_submit() to submit a form produced by
 * indicia_api_app().
 */
function indicia_api_app_submit($form, &$form_state) {
  if (empty($form_state['values']['secret'])) {
    // Don't overwrite old password if wasn't touched while editing.
    unset($form_state['values']['secret']);
  }

  if (empty($form_state['values']['id'])) {
    // Save new app.
    drupal_write_record('indicia_api', $form_state['values']);
    $message = 'Created %app.';
  }
  else {
    // Save existing app.
    drupal_write_record('indicia_api', $form_state['values'], 'id');
    $message = 'Saved %app.';
  }

  // Inform user and return to dashboard.
  drupal_set_message(t($message, array('%app' => $form_state['values']['title'])));
  $form_state['redirect'] = CONFIG_PATH;
}

/**
 * A confirmation page to check if the user is sure to delete an app.
 */
function indicia_api_delete($form, &$form_state, $apps) {

  // Requested an app account with an id that does not exist in DB.
  if (empty($apps)) {
    return drupal_not_found();
  }

  // Since app account ids are unique and the URL argument is one id number
  // the $apps array returned from DB must contain information about only
  // one account.
  $app = $apps[0];

  // Set up the form information to be passed to submit handler.
  $form = array();
  $form['id'] = array(
    '#type' => 'value',
    '#value' => $app['id'],
  );
  $form['title'] = array(
    '#type' => 'value',
    '#value' => $app['title'] ,
  );

  $message = t('Are you sure you want to delete %app ?', array('%app' => $app['title']));

  // Check if the user has permission to delete an app.
  if (user_has_permission($app)) {
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
 * Submit handler to delete an app.
 *
 * Implements hook_submit() to submit a confirmation form produced by
 * indicia_api_delete().
 */
function indicia_api_delete_submit($form, &$form_state) {
  if ($form_state['values']['confirm']) {
    // Delete app from database.
    db_query('DELETE FROM {indicia_api} WHERE id = :id',
      array(':id' => $form_state['values']['id']));

    // Inform user and return to dashboard.
    drupal_set_message(t('Deleted %app.', array('%app' => $form_state['values']['title'])));
  }
  $form_state['redirect'] = CONFIG_PATH;
}
