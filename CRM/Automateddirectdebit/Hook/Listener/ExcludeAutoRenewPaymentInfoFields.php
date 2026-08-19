<?php

use Civi\Core\Event\GenericHookEvent;

/**
 * Excludes the External DD Payment Information custom fields (notably
 * payment_in_progress) from MembershipExtras auto-renew copying.
 *
 * Without this, the renewal copies the previous contribution's
 * payment_in_progress value onto the new contribution. A stale value of 1
 * makes the payment collection job skip the new contribution, so it is never
 * collected and the membership silently fails to renew.
 *
 * Listens to CRM_MembershipExtras_Hook_CustomDispatch_AutoRenewExcludedCustomFields::NAME.
 */
class CRM_Automateddirectdebit_Hook_Listener_ExcludeAutoRenewPaymentInfoFields {

  public static function handle(GenericHookEvent $event) {
    $customFieldIds =& $event->customFieldIds;

    foreach (self::getPaymentInfoFieldIds() as $fieldId) {
      $customFieldIds[] = $fieldId;
    }
  }

  /**
   * Field IDs of the External DD Payment Information group.
   *
   * Cached per request: this runs once per renewal contribution copy, and the
   * field IDs do not change within a request.
   *
   * @return array
   */
  private static function getPaymentInfoFieldIds() {
    static $fieldIds = NULL;
    if ($fieldIds === NULL) {
      $fieldIds = \Civi\Api4\CustomField::get(FALSE)
        ->addSelect('id')
        ->addWhere('custom_group_id:name', '=', CRM_Automateddirectdebit_Setup_Manage_CustomGroup_ExternalDDPaymentInformation::GROUP_NAME)
        ->execute()
        ->column('id');
    }

    return $fieldIds;
  }

}
