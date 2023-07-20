<?php

use CRM_Automateddirectdebit_Setup_Manage_AbstractManager as AbstractManager;

/**
 * Managing 'External Payment Information' custom group and its fields.
 */
class CRM_Automateddirectdebit_Setup_Manage_CustomGroup_ExternalDDPaymentInformation extends AbstractManager {

  const GROUP_NAME = 'external_dd_payment_information';

  /**
   * @inheritDoc
   */
  public function create() {
    // nothing to do here, the custom group will be created automatically
    // because it is defined in the extension XML files.
  }

  /**
   * @inheritDoc
   */
  public function remove() {
    $customFields = [
      'last_payment_status',
      'payment_in_progress',
    ];
    foreach ($customFields as $customFieldName) {
      civicrm_api3('CustomField', 'get', [
        'name' => $customFieldName,
        'custom_group_id' => self::GROUP_NAME,
        'api.CustomField.delete' => ['id' => '$value.id'],
      ]);
    }

    civicrm_api3('CustomGroup', 'get', [
      'name' => self::GROUP_NAME,
      'api.CustomGroup.delete' => ['id' => '$value.id'],
    ]);
  }

  /**
   * @inheritDoc
   */
  protected function toggle($status) {
    civicrm_api3('CustomGroup', 'get', [
      'name' => self::GROUP_NAME,
      'api.CustomGroup.create' => ['id' => '$value.id', 'is_active' => $status],
    ]);
  }

}
