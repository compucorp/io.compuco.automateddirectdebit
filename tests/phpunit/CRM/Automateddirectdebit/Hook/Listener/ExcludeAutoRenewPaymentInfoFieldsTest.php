<?php

use Civi\Core\Event\GenericHookEvent;
use CRM_Automateddirectdebit_Setup_Manage_CustomGroup_ExternalDDPaymentInformation as PaymentInfoGroup;

/**
 * @group headless
 */
class CRM_Automateddirectdebit_Hook_Listener_ExcludeAutoRenewPaymentInfoFieldsTest extends BaseHeadlessTest {

  private function getPaymentInfoFieldIds() {
    return \Civi\Api4\CustomField::get(FALSE)
      ->addSelect('id')
      ->addWhere('custom_group_id:name', '=', PaymentInfoGroup::GROUP_NAME)
      ->execute()
      ->column('id');
  }

  public function testHandleAppendsPaymentInfoFields() {
    $customFieldIds = [];
    $event = GenericHookEvent::create(['customFieldIds' => &$customFieldIds]);

    CRM_Automateddirectdebit_Hook_Listener_ExcludeAutoRenewPaymentInfoFields::handle($event);

    $expectedFieldIds = $this->getPaymentInfoFieldIds();
    $this->assertNotEmpty($expectedFieldIds);
    foreach ($expectedFieldIds as $fieldId) {
      $this->assertContains($fieldId, $customFieldIds);
    }
  }

  public function testPaymentInProgressIsExcludedViaMembershipExtras() {
    if (!class_exists('CRM_MembershipExtras_Hook_CustomDispatch_AutoRenewExcludedCustomFields')) {
      $this->markTestSkipped('Requires the MembershipExtras auto-renew exclusion hook (compucorp/uk.co.compucorp.membershipextras#590).');
    }

    $excludedFieldIds = array_map('intval', CRM_MembershipExtras_SettingsManager::getCustomFieldsIdsToExcludeForAutoRenew());

    $paymentInProgressField = \Civi\Api4\CustomField::get(FALSE)
      ->addSelect('id')
      ->addWhere('custom_group_id:name', '=', PaymentInfoGroup::GROUP_NAME)
      ->addWhere('name', '=', 'payment_in_progress')
      ->execute()
      ->first();
    $paymentInProgressFieldId = is_array($paymentInProgressField) ? (int) $paymentInProgressField['id'] : 0;

    $this->assertNotEmpty($paymentInProgressFieldId);
    $this->assertContains($paymentInProgressFieldId, $excludedFieldIds);
  }

}
