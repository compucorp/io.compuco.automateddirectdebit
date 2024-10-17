<?php

/**
 * Responsible for emitting direct debit payment collection event (hook).
 * The dispatched event/hook is called: `automateddirectdebit_PaymentCollectionEvent`
 * where 3rd party payment processor extensions (such as GoCardless extension) can implement it
 * to create and collect payments.
 *
 */
class CRM_Automateddirectdebit_Job_DirectDebitEvents_PaymentCollectionEvent {

  const BASC_PAYMENT_SCHEME = "bacs";

  public function run() {
    $pendingBACSInvoicesQuery = $this->buildPendingBACSInvoicesQuery();
    $this->createPaymentForInvoices($pendingBACSInvoicesQuery);

    $pendingInvoicesQuery = $this->buildPendingOtherInvoicesQuery();
    $this->createPaymentForInvoices($pendingInvoicesQuery);
  }

  private function createPaymentForInvoices($invoiceQuery) {
    $pendingInvoices = CRM_Core_DAO::executeQuery($invoiceQuery->toSQL());
    while ($pendingInvoices->fetch()) {
      $pendingInvoiceData = $pendingInvoices->toArray();
      $invoiceTotalPaidAmount = CRM_Core_BAO_FinancialTrxn::getTotalPayments($pendingInvoiceData['contribution_id'], TRUE);
      if ($invoiceTotalPaidAmount >= $pendingInvoiceData['total_amount']) {
        continue;
      }

      $pendingInvoiceData['charge_amount'] = $pendingInvoiceData['total_amount'] - $invoiceTotalPaidAmount;

      $this->markContributionExternalPaymentToBeInProgress($pendingInvoiceData['contribution_id']);
      $this->dispatchPaymentCollectionEventHook($pendingInvoiceData);
    }
  }

  /**
   * Builds the query to fetch the contributions (invoices)
   * with BACS payment shceme and that
   * match the criteria of direct debit payment invoices
   *
   * @return CRM_Utils_SQL_Select
   */
  public function buildPendingBACSInvoicesQuery() {
    $recurContributionStatusesToProcess = implode(',', $this->getRecurContributionStatusesId(['In Progress', 'Overdue']));

    $query = CRM_Utils_SQL_Select::from('civicrm_contribution c')
      ->join('cr', 'INNER JOIN civicrm_contribution_recur cr ON c.contribution_recur_id = cr.id')
      ->join('mandate', 'INNER JOIN civicrm_value_external_dd_mandate_information mandate ON cr.id = mandate.entity_id')
      ->join('ppea', 'INNER JOIN civicrm_value_payment_plan_extra_attributes ppea ON cr.id = ppea.entity_id')
      ->join('epi', 'LEFT JOIN civicrm_value_external_dd_payment_information epi ON c.id = epi.entity_id')
      ->where("mandate.mandate_id IS NOT NULL")
      ->where('mandate.mandate_scheme = @scheme', ["scheme" => self::BASC_PAYMENT_SCHEME])
      ->where('ppea.is_active = 1')
      ->where("cr.contribution_status_id IN ({$recurContributionStatusesToProcess})")
      ->where('mandate.next_available_payment_date IS NOT NULL')
      ->where("c.receive_date < DATE_ADD(CURDATE(), INTERVAL 1 DAY)")
      ->where('epi.payment_in_progress = 0 OR epi.payment_in_progress IS NULL')
      ->select('c.id as contribution_id, c.contact_id, c.receive_date, c.total_amount, c.currency, mandate.mandate_id');

    return $query;
  }

  /**
   * Builds the query to fetch the contributions (invoices)
   * with NON-BACS payment shceme (e.g. SEPA, PAD) and that
   * match the criteria of direct debit payment invoices
   *
   * @return CRM_Utils_SQL_Select
   */
  public function buildPendingOtherInvoicesQuery() {
    $recurContributionStatusesToProcess = implode(',', $this->getRecurContributionStatusesId(['In Progress', 'Overdue', 'Pending']));

    $query = CRM_Utils_SQL_Select::from('civicrm_contribution c')
      ->join('cr', 'INNER JOIN civicrm_contribution_recur cr ON c.contribution_recur_id = cr.id')
      ->join('mandate', 'INNER JOIN civicrm_value_external_dd_mandate_information mandate ON cr.id = mandate.entity_id')
      ->join('ppea', 'INNER JOIN civicrm_value_payment_plan_extra_attributes ppea ON cr.id = ppea.entity_id')
      ->join('epi', 'LEFT JOIN civicrm_value_external_dd_payment_information epi ON c.id = epi.entity_id')
      ->where("mandate.mandate_id IS NOT NULL")
      ->where('mandate.mandate_scheme IS NULL OR mandate.mandate_scheme <> @scheme', ["scheme" => self::BASC_PAYMENT_SCHEME])
      ->where('ppea.is_active = 1')
      ->where("cr.contribution_status_id IN ({$recurContributionStatusesToProcess})")
      ->where('mandate.next_available_payment_date IS NOT NULL')
      ->where("c.receive_date < DATE_ADD(CURDATE(), INTERVAL 1 DAY)")
      ->where('epi.payment_in_progress = 0 OR epi.payment_in_progress IS NULL')
      ->select('c.id as contribution_id, c.contact_id, c.receive_date, c.total_amount, c.currency, mandate.mandate_id');

    return $query;
  }

  private function getRecurContributionStatusesId($statusesNamesToProcess) {
    $allStatuses = CRM_Core_OptionGroup::values('contribution_recur_status', FALSE, FALSE, FALSE, NULL, 'name');
    $statusesIdsToProcess = [];
    foreach ($allStatuses as $key => $val) {
      if (array_search($val, $statusesNamesToProcess) !== FALSE) {
        $statusesIdsToProcess[] = $key;
      }
    }

    return $statusesIdsToProcess;
  }

  /**
   * Sets  the contribution "Payment In Progress" to True to prevent
   * this class from trying to submit more than one payment against the
   * same contribution.
   *
   * @param $contributionId
   * @return void
   */
  private function markContributionExternalPaymentToBeInProgress($contributionId) {
    $query = "INSERT INTO civicrm_value_external_dd_payment_information (entity_id, payment_in_progress) VALUES({$contributionId}, 1) ON DUPLICATE KEY UPDATE payment_in_progress= 1";
    CRM_Core_DAO::executeQuery($query);
  }

  private function dispatchPaymentCollectionEventHook($pendingInvoiceData) {
    $nullObject = CRM_Utils_Hook::$_nullObject;
    $contributionData = [
      'id' => $pendingInvoiceData['contribution_id'],
      'contact_id' => $pendingInvoiceData['contact_id'],
      'receive_date' => $pendingInvoiceData['receive_date'],
      'total_amount' => $pendingInvoiceData['total_amount'],
      'currency' => $pendingInvoiceData['currency'],
    ];

    $chargeAmount = $pendingInvoiceData['charge_amount'];
    $mandateId = $pendingInvoiceData['mandate_id'];

    CRM_Utils_Hook::singleton()->invoke(
      ['contributionData', 'chargeAmount', 'mandateId'],
      $contributionData, $chargeAmount, $mandateId,
      $nullObject, $nullObject, $nullObject,
      'automateddirectdebit_PaymentCollectionEvent'
    );
  }

}
