<?php

class CRM_Automateddirectdebit_Job_DirectDebitEvents_PendingContributionsQueryBuilder {

  /**
   * Builds the main query to fetch the pending automated
   * direct debit contributions. Consumers of this class
   * can modify the query and add more filters according
   * to their needs.
   *
   * @return CRM_Utils_SQL_Select
   */
  public static function buildQuery() {
    $contributionPendingStatusID = CRM_Core_PseudoConstant::getKey('CRM_Contribute_BAO_Contribution', 'contribution_status_id', 'Pending');
    $mandateActiveStatusId = 1;

    $query = CRM_Utils_SQL_Select::from('civicrm_contribution c')
      ->join('cr', 'INNER JOIN civicrm_contribution_recur cr ON c.contribution_recur_id = cr.id')
      ->join('mandate', 'INNER JOIN civicrm_value_external_dd_mandate_information mandate ON cr.id = mandate.entity_id')
      ->join('ppea', 'INNER JOIN civicrm_value_payment_plan_extra_attributes ppea ON cr.id = ppea.entity_id')
      ->where("c.contribution_status_id = {$contributionPendingStatusID}")
      ->where("mandate.mandate_id IS NOT NULL")
      ->where('mandate.next_available_payment_date IS NOT NULL')
      ->where("mandate.mandate_status = {$mandateActiveStatusId}")
      ->where('ppea.is_active = 1');

    return $query;
  }

}
