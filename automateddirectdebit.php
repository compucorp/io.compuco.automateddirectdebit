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
 * Implements hook_civicrm_container().
 *
 * Registers the listener that excludes the External DD Payment Information
 * fields (e.g. payment_in_progress) from MembershipExtras auto-renew custom
 * field copying. The event name is defined by
 * CRM_MembershipExtras_Hook_CustomDispatch_AutoRenewExcludedCustomFields::NAME.
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_container/
 */
function automateddirectdebit_civicrm_container($container) {
  $container->findDefinition('dispatcher')->addMethodCall('addListener', [
    'me.autorenew.excluded_custom_fields',
    ['CRM_Automateddirectdebit_Hook_Listener_ExcludeAutoRenewPaymentInfoFields', 'handle'],
  ]);
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
 * Implements hook_civicrm_enable().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_enable
 */
function automateddirectdebit_civicrm_enable() {
  _automateddirectdebit_civix_civicrm_enable();
}
