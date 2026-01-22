<?php

/**
 * Responsible for emitting direct debit payment collection event (hook).
 * The dispatched event/hook is called: `automateddirectdebit_PaymentCollectionEvent`
 * where 3rd party payment processor extensions (such as GoCardless extension) can implement it
 * to create and collect payments.
 *
 */
class CRM_Automateddirectdebit_Job_DirectDebitEvents_PaymentCollectionEvent {

  const BACS_PAYMENT_SCHEME = "bacs";

  /**
   * Minutes after which a locked contribution becomes eligible for retry.
   */
  const LOCK_RETRY_WINDOW_START_MINUTES = 30;

  /**
   * Minutes after which a locked contribution is permanently excluded.
   */
  const LOCK_PERMANENT_FAILURE_MINUTES = 90;

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
   * Builds the query to fetch the contributions (invoices)
   * with BACS payment scheme and that
   * match the criteria of direct debit payment invoices
   *
   * @return CRM_Utils_SQL_Select
   */
  public function buildPendingBACSInvoicesQuery() {
    $recurContributionStatusIds = $this->getStatusesId('contribution_recur_status', ['In Progress', 'Overdue']);
    $contributionStatusIds = $this->getStatusesId('contribution_status', ['Pending', 'Partially paid']);

    $query = CRM_Utils_SQL_Select::from("civicrm_contribution c")
      ->join("cr", "INNER JOIN civicrm_contribution_recur cr ON c.contribution_recur_id = cr.id")
      ->join("mandate", "INNER JOIN civicrm_value_external_dd_mandate_information mandate ON cr.id = mandate.entity_id")
      ->join("ppea", "INNER JOIN civicrm_value_payment_plan_extra_attributes ppea ON cr.id = ppea.entity_id")
      ->join("epi", "LEFT JOIN civicrm_value_external_dd_payment_information epi ON c.id = epi.entity_id")
      ->where("mandate.mandate_id IS NOT NULL")
      ->where("mandate.mandate_scheme = @scheme", ["scheme" => self::BACS_PAYMENT_SCHEME])
      ->where("ppea.is_active = 1")
      ->where("cr.contribution_status_id IN (@recur_statuses)", ["recur_statuses" => $recurContributionStatusIds])
      ->where("c.contribution_status_id IN (@contrib_statuses)", ["contrib_statuses" => $contributionStatusIds])
      ->where("c.receive_date < DATE_ADD(CURDATE(), INTERVAL 1 DAY)")
      ->where('epi.payment_in_progress = 0
        OR epi.payment_in_progress IS NULL
        OR (epi.payment_in_progress = 1
            AND epi.payment_in_progress_at < DATE_SUB(NOW(), INTERVAL ' . self::LOCK_RETRY_WINDOW_START_MINUTES . ' MINUTE)
            AND epi.payment_in_progress_at >= DATE_SUB(NOW(), INTERVAL ' . self::LOCK_PERMANENT_FAILURE_MINUTES . ' MINUTE))')
      ->select('c.id as contribution_id, c.contact_id, c.receive_date, c.total_amount, c.currency, mandate.mandate_id');

    return $query;
  }

  /**
   * Builds the query to fetch the contributions (invoices)
   * with NON-BACS payment scheme (e.g. SEPA, PAD) and that
   * match the criteria of direct debit payment invoices
   *
   * @return CRM_Utils_SQL_Select
   */
  public function buildPendingOtherInvoicesQuery() {
    $recurContributionStatusIds = $this->getStatusesId('contribution_recur_status', ['In Progress', 'Overdue', 'Pending']);
    $contributionStatusIds = $this->getStatusesId('contribution_status', ['Pending', 'Partially paid']);

    $query = CRM_Utils_SQL_Select::from("civicrm_contribution c")
      ->join("cr", "INNER JOIN civicrm_contribution_recur cr ON c.contribution_recur_id = cr.id")
      ->join("mandate", "INNER JOIN civicrm_value_external_dd_mandate_information mandate ON cr.id = mandate.entity_id")
      ->join("ppea", "INNER JOIN civicrm_value_payment_plan_extra_attributes ppea ON cr.id = ppea.entity_id")
      ->join("epi", "LEFT JOIN civicrm_value_external_dd_payment_information epi ON c.id = epi.entity_id")
      ->where("mandate.mandate_id IS NOT NULL")
      ->where("mandate.mandate_scheme IS NULL OR mandate.mandate_scheme <> @scheme", ["scheme" => self::BACS_PAYMENT_SCHEME])
      ->where("ppea.is_active = 1")
      ->where("cr.contribution_status_id IN (@recur_statuses)", ["recur_statuses" => $recurContributionStatusIds])
      ->where("c.contribution_status_id IN (@contrib_statuses)", ["contrib_statuses" => $contributionStatusIds])
      ->where("c.receive_date < DATE_ADD(CURDATE(), INTERVAL 1 DAY)")
      ->where('epi.payment_in_progress = 0
        OR epi.payment_in_progress IS NULL
        OR (epi.payment_in_progress = 1
            AND epi.payment_in_progress_at < DATE_SUB(NOW(), INTERVAL ' . self::LOCK_RETRY_WINDOW_START_MINUTES . ' MINUTE)
            AND epi.payment_in_progress_at >= DATE_SUB(NOW(), INTERVAL ' . self::LOCK_PERMANENT_FAILURE_MINUTES . ' MINUTE))')
      ->select("c.id as contribution_id, c.contact_id, c.receive_date, c.total_amount, c.currency, mandate.mandate_id");

    return $query;
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
   * Sets the contribution "Payment In Progress" to True to prevent
   * this class from trying to submit more than one payment against the
   * same contribution.
   *
   * Timestamp logic:
   * - New record: set timestamp to NOW()
   * - Existing with flag=0 (new payment cycle): reset timestamp to NOW()
   * - Existing with flag=1 (retry): keep original timestamp
   *
   * @param int $contributionId
   * @return void
   */
  private function markContributionExternalPaymentToBeInProgress($contributionId) {
    $params = [1 => [$contributionId, 'Integer']];

    $existing = CRM_Core_DAO::executeQuery(
      "SELECT payment_in_progress FROM civicrm_value_external_dd_payment_information WHERE entity_id = %1",
      $params
    )->fetchAll();

    $recordExists = !empty($existing);
    $isAlreadyLocked = $recordExists && $existing[0]['payment_in_progress'] == 1;

    if (!$recordExists) {
      CRM_Core_DAO::executeQuery(
        "INSERT INTO civicrm_value_external_dd_payment_information
         (entity_id, payment_in_progress, payment_in_progress_at)
         VALUES (%1, 1, NOW())",
        $params
      );
    }
    elseif (!$isAlreadyLocked) {
      CRM_Core_DAO::executeQuery(
        "UPDATE civicrm_value_external_dd_payment_information
         SET payment_in_progress = 1, payment_in_progress_at = NOW()
         WHERE entity_id = %1",
        $params
      );
    }
    // If already locked (retry), no update needed - keep original timestamp
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
