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

  /**
   * Cache for status IDs to avoid repeated DB queries.
   *
   * @var array
   */
  private static $statusIdCache = [];

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
   * Builds the query to fetch BACS contributions eligible for payment collection.
   *
   * @return CRM_Utils_SQL_Select
   */
  public function buildPendingBACSInvoicesQuery() {
    return $this->buildPendingInvoicesQuery("mandate.mandate_scheme = @scheme", ["scheme" => self::BASC_PAYMENT_SCHEME]);
  }

  /**
   * Builds the query to fetch non-BACS contributions (e.g. SEPA, PAD) eligible for payment collection.
   *
   * @return CRM_Utils_SQL_Select
   */
  public function buildPendingOtherInvoicesQuery() {
    return $this->buildPendingInvoicesQuery("mandate.mandate_scheme IS NULL OR mandate.mandate_scheme <> @scheme", ["scheme" => self::BASC_PAYMENT_SCHEME]);
  }

  /**
   * Builds the query to fetch contributions (invoices) eligible for direct debit payment collection.
   *
   * @param string $schemeCondition SQL WHERE clause for mandate scheme filtering
   * @param array $schemeParams Parameters for the scheme condition
   * @return CRM_Utils_SQL_Select
   */
  private function buildPendingInvoicesQuery(string $schemeCondition, array $schemeParams) {
    $excludedRecurStatusIds = $this->getStatusesId('contribution_recur_status', ['Cancelled', 'Failed']);
    $contributionStatusIds = $this->getStatusesId('contribution_status', ['Pending', 'Partially paid']);

    return CRM_Utils_SQL_Select::from("civicrm_contribution c")
      ->join("cr", "INNER JOIN civicrm_contribution_recur cr ON c.contribution_recur_id = cr.id")
      ->join("mandate", "INNER JOIN civicrm_value_external_dd_mandate_information mandate ON cr.id = mandate.entity_id")
      ->join("ppea", "INNER JOIN civicrm_value_payment_plan_extra_attributes ppea ON cr.id = ppea.entity_id")
      ->join("epi", "LEFT JOIN civicrm_value_external_dd_payment_information epi ON c.id = epi.entity_id")
      ->where("mandate.mandate_id IS NOT NULL")
      ->where($schemeCondition, $schemeParams)
      ->where("ppea.is_active = 1")
      ->where("cr.contribution_status_id NOT IN (@recur_statuses)", ["recur_statuses" => $excludedRecurStatusIds])
      ->where("c.contribution_status_id IN (@contrib_statuses)", ["contrib_statuses" => $contributionStatusIds])
      ->where("c.receive_date < DATE_ADD(CURDATE(), INTERVAL 1 DAY)")
      ->where("epi.payment_in_progress = 0 OR epi.payment_in_progress IS NULL")
      ->select("c.id as contribution_id, c.contact_id, c.receive_date, c.total_amount, c.currency, mandate.mandate_id");
  }

  /**
   * Gets status IDs for the given option group and status names.
   * Results are cached to avoid repeated DB queries.
   *
   * @param string $optionGroupName The option group name
   * @param array $statusesNamesToProcess The status names to get IDs for
   * @return array The status IDs
   */
  private function getStatusesId($optionGroupName, $statusesNamesToProcess) {
    $cacheKey = $optionGroupName . ":" . implode(",", $statusesNamesToProcess);

    if (isset(self::$statusIdCache[$cacheKey])) {
      return self::$statusIdCache[$cacheKey];
    }

    $allStatuses = CRM_Core_OptionGroup::values($optionGroupName, FALSE, FALSE, FALSE, NULL, "name");
    $statusesIdsToProcess = [];
    foreach ($allStatuses as $key => $val) {
      if (in_array($val, $statusesNamesToProcess)) {
        $statusesIdsToProcess[] = $key;
      }
    }

    self::$statusIdCache[$cacheKey] = $statusesIdsToProcess;

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
