<?php

use CRM_MembershipExtras_Setup_Configure_ConfigurerInterface as ConfigureInterface;

/**
 * Managing 'External Direct Debit Mandate information -> Scheme' custom field.
 */
class CRM_Automateddirectdebit_Setup_Configure_AddMandateSchemetoDDMandateInformation implements ConfigureInterface {

  const GROUP_NAME = 'external_direct_debit_mandate_information';
  const FIELD_NAME = 'mandate_scheme';

  /**
   * @inheritDoc
   */
  public function apply() {

    $customField = \Civi\Api4\CustomField::get(FALSE)
      ->addWhere('custom_group_id:name', '=', self::GROUP_NAME)
      ->addWhere('name', '=', self::FIELD_NAME)
      ->execute()
      ->first();

    if (empty($customField)) {
      \Civi\Api4\CustomField::create(FALSE)
        ->addValue('custom_group_id.name', self::GROUP_NAME)
        ->addValue('name', self::FIELD_NAME)
        ->addValue('label', 'Mandate Scheme')
        ->addValue('html_type', 'Text')
        ->addValue('data_type', 'String')
        ->addValue('is_required', FALSE)
        ->addValue('is_searchable', TRUE)
        ->addValue('is_search_range', FALSE)
        ->addValue('is_view', TRUE)
        ->addValue('column_name', self::FIELD_NAME)
        ->execute();
    }

    return TRUE;
  }

}
