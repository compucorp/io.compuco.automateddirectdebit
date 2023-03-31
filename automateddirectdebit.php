<?php

require_once 'automateddirectdebit.civix.php';
// phpcs:disable
use CRM_Automateddirectdebit_ExtensionUtil as E;
// phpcs:enable

/**
 * Implements hook_civicrm_config().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_config/
 */
function automateddirectdebit_civicrm_config(&$config) {
  _automateddirectdebit_civix_civicrm_config($config);
}

/**
 * Implements hook_civicrm_install().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_install
 */
function automateddirectdebit_civicrm_install() {
  _automateddirectdebit_civix_civicrm_install();
}

/**
 * Implements hook_civicrm_postInstall().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_postInstall
 */
function automateddirectdebit_civicrm_postInstall() {
  _automateddirectdebit_civix_civicrm_postInstall();
}

/**
 * Implements hook_civicrm_uninstall().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_uninstall
 */
function automateddirectdebit_civicrm_uninstall() {
  _automateddirectdebit_civix_civicrm_uninstall();
}

/**
 * Implements hook_civicrm_enable().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_enable
 */
function automateddirectdebit_civicrm_enable() {
  _automateddirectdebit_civix_civicrm_enable();
}

/**
 * Implements hook_civicrm_disable().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_disable
 */
function automateddirectdebit_civicrm_disable() {
  _automateddirectdebit_civix_civicrm_disable();
}

/**
 * Implements hook_civicrm_upgrade().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_upgrade
 */
function automateddirectdebit_civicrm_upgrade($op, CRM_Queue_Queue $queue = NULL) {
  return _automateddirectdebit_civix_civicrm_upgrade($op, $queue);
}

/**
 * Implements hook_civicrm_entityTypes().
 *
 * Declare entity types provided by this module.
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_entityTypes
 */
function automateddirectdebit_civicrm_entityTypes(&$entityTypes) {
  _automateddirectdebit_civix_civicrm_entityTypes($entityTypes);
}
