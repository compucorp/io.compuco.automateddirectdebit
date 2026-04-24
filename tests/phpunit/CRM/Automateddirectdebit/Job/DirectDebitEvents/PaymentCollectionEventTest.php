<?php

/**
 * Unit test for PaymentCollectionEvent
 *
 * Tests that only contributions with 'Pending' or 'Partially paid' status
 * are included in the payment collection queries. Cancelled, Completed,
 * and Failed contributions should be excluded.
 *
 * This test is payment processor agnostic and uses a generic Dummy processor.
 *
 * @group headless
 */
class CRM_Automateddirectdebit_Job_DirectDebitEvents_PaymentCollectionEventTest extends BaseHeadlessTest {

  private $contact;
  private $recurringContribution;
  private $paymentProcessor;

  public function setUp(): void {
    parent::setUp();

    $this->contact = \Civi\Api4\Contact::create()
      ->addValue('contact_type', 'Individual')
      ->addValue('first_name', 'Test')
      ->addValue('last_name', 'Contact')
      ->execute()->first();

    $this->paymentProcessor = $this->createDummyPaymentProcessor();
    $this->recurringContribution = $this->createRecurringContribution('In Progress');
    $this->setupMandateInformation($this->recurringContribution['id'], 'TEST_MANDATE_001', 'bacs');
  }

  public function testPendingContributionIsIncludedInBACSQuery() {
    $contribution = $this->createContribution($this->recurringContribution['id'], 'Pending');
    $this->setupPaymentPlanExtraAttributes($this->recurringContribution['id']);

    $contributionIds = $this->getBACSQueryContributionIds();

    $this->assertTrue(
      in_array($contribution['id'], $contributionIds),
      'Pending contribution should be included in BACS query'
    );
  }

  public function testCancelledContributionIsNotIncludedInBACSQuery() {
    $contribution = $this->createContribution($this->recurringContribution['id'], 'Cancelled');
    $this->setupPaymentPlanExtraAttributes($this->recurringContribution['id']);

    $contributionIds = $this->getBACSQueryContributionIds();

    $this->assertFalse(
      in_array($contribution['id'], $contributionIds),
      'Cancelled contribution should NOT be included in BACS query'
    );
  }

  public function testCompletedContributionIsNotIncludedInBACSQuery() {
    $contribution = $this->createContribution($this->recurringContribution['id'], 'Completed');
    $this->setupPaymentPlanExtraAttributes($this->recurringContribution['id']);

    $contributionIds = $this->getBACSQueryContributionIds();

    $this->assertFalse(
      in_array($contribution['id'], $contributionIds),
      'Completed contribution should NOT be included in BACS query'
    );
  }

  public function testPartiallyPaidContributionIsIncludedInBACSQuery() {
    $contribution = $this->createContribution($this->recurringContribution['id'], 'Partially paid');
    $this->setupPaymentPlanExtraAttributes($this->recurringContribution['id']);

    $contributionIds = $this->getBACSQueryContributionIds();

    $this->assertTrue(
      in_array($contribution['id'], $contributionIds),
      'Partially paid contribution should be included in BACS query'
    );
  }

  public function testPendingContributionIsIncludedInNonBACSQuery() {
    $recurringContribution = $this->createRecurringContribution('In Progress');
    $this->setupMandateInformation($recurringContribution['id'], 'TEST_MANDATE_002', 'sepa_core');

    $contribution = $this->createContribution($recurringContribution['id'], 'Pending');
    $this->setupPaymentPlanExtraAttributes($recurringContribution['id']);

    $contributionIds = $this->getOtherInvoicesQueryContributionIds();

    $this->assertTrue(
      in_array($contribution['id'], $contributionIds),
      'Pending contribution should be included in non-BACS query'
    );
  }

  public function testCancelledContributionIsNotIncludedInNonBACSQuery() {
    $recurringContribution = $this->createRecurringContribution('In Progress');
    $this->setupMandateInformation($recurringContribution['id'], 'TEST_MANDATE_003', 'sepa_core');

    $contribution = $this->createContribution($recurringContribution['id'], 'Cancelled');
    $this->setupPaymentPlanExtraAttributes($recurringContribution['id']);

    $contributionIds = $this->getOtherInvoicesQueryContributionIds();

    $this->assertFalse(
      in_array($contribution['id'], $contributionIds),
      'Cancelled contribution should NOT be included in non-BACS query'
    );
  }

  public function testFailedContributionIsNotIncludedInBACSQuery() {
    $contribution = $this->createContribution($this->recurringContribution['id'], 'Failed');
    $this->setupPaymentPlanExtraAttributes($this->recurringContribution['id']);

    $contributionIds = $this->getBACSQueryContributionIds();

    $this->assertFalse(
      in_array($contribution['id'], $contributionIds),
      'Failed contribution should NOT be included in BACS query'
    );
  }

  public function testRefundedContributionIsNotIncludedInBACSQuery() {
    $contribution = $this->createContribution($this->recurringContribution['id'], 'Refunded');
    $this->setupPaymentPlanExtraAttributes($this->recurringContribution['id']);

    $contributionIds = $this->getBACSQueryContributionIds();

    $this->assertFalse(
      in_array($contribution['id'], $contributionIds),
      'Refunded contribution should NOT be included in BACS query'
    );
  }

  /**
   * Executes the BACS query and returns contribution IDs.
   */
  private function getBACSQueryContributionIds() {
    $paymentCollectionEvent = new CRM_Automateddirectdebit_Job_DirectDebitEvents_PaymentCollectionEvent();
    $query = $paymentCollectionEvent->buildPendingBACSInvoicesQuery();
    $result = CRM_Core_DAO::executeQuery($query->toSQL());

    $contributionIds = [];
    while ($result->fetch()) {
      $contributionIds[] = (int) $result->contribution_id;
    }

    return $contributionIds;
  }

  /**
   * Executes the non-BACS query and returns contribution IDs.
   */
  private function getOtherInvoicesQueryContributionIds() {
    $paymentCollectionEvent = new CRM_Automateddirectdebit_Job_DirectDebitEvents_PaymentCollectionEvent();
    $query = $paymentCollectionEvent->buildPendingOtherInvoicesQuery();
    $result = CRM_Core_DAO::executeQuery($query->toSQL());

    $contributionIds = [];
    while ($result->fetch()) {
      $contributionIds[] = (int) $result->contribution_id;
    }

    return $contributionIds;
  }

  /**
   * Creates a generic Dummy payment processor for testing.
   * This is payment processor agnostic - not tied to GoCardless or any specific provider.
   */
  private function createDummyPaymentProcessor() {
    return \Civi\Api4\PaymentProcessor::create()
      ->addValue('name', 'Test Direct Debit Processor')
      ->addValue('payment_processor_type_id:name', 'Dummy')
      ->addValue('is_active', TRUE)
      ->addValue('is_test', FALSE)
      ->execute()->first();
  }

  /**
   * Creates a recurring contribution with the given status.
   */
  private function createRecurringContribution($status) {
    return \Civi\Api4\ContributionRecur::create()
      ->addValue('contact_id', $this->contact['id'])
      ->addValue('amount', 100)
      ->addValue('currency', 'GBP')
      ->addValue('is_test', FALSE)
      ->addValue('contribution_status_id:name', $status)
      ->addValue('payment_processor_id', $this->paymentProcessor['id'])
      ->execute()->first();
  }

  /**
   * Creates a contribution linked to a recurring contribution.
   *
   * For "Partially paid" status, creates a Pending contribution and makes
   * a partial payment via Payment API (required by CiviCRM).
   */
  private function createContribution($recurringContributionId, $status) {
    $createStatus = ($status === 'Partially paid') ? 'Pending' : $status;

    $contribution = \Civi\Api4\Contribution::create()
      ->addValue('contact_id', $this->contact['id'])
      ->addValue('financial_type_id', 1)
      ->addValue('total_amount', 100)
      ->addValue('currency', 'GBP')
      ->addValue('contribution_recur_id', $recurringContributionId)
      ->addValue('contribution_status_id:name', $createStatus)
      ->addValue('receive_date', date('Y-m-d'))
      ->execute()->first();

    if ($status === 'Partially paid') {
      civicrm_api3('Payment', 'create', [
        'contribution_id' => $contribution['id'],
        'total_amount' => 50,
        'payment_instrument_id' => 'Check',
      ]);
      $contribution = \Civi\Api4\Contribution::get()
        ->addWhere('id', '=', $contribution['id'])
        ->execute()->first();
    }

    return $contribution;
  }

  /**
   * Sets up mandate information for a recurring contribution.
   * Uses generic mandate IDs - not tied to any specific payment processor.
   */
  private function setupMandateInformation($recurringContributionId, $mandateId, $mandateScheme) {
    $query = "INSERT INTO civicrm_value_external_dd_mandate_information (entity_id, mandate_id, mandate_scheme, mandate_status)
              VALUES (%1, %2, %3, 1)
              ON DUPLICATE KEY UPDATE mandate_id = %2, mandate_scheme = %3, mandate_status = 1";
    CRM_Core_DAO::executeQuery($query, [
      1 => [$recurringContributionId, 'Integer'],
      2 => [$mandateId, 'String'],
      3 => [$mandateScheme, 'String'],
    ]);
  }

  /**
   * Sets up payment plan extra attributes for a recurring contribution.
   *
   * @param int $recurringContributionId The recurring contribution ID
   * @param bool $isActive Whether the payment plan is active (default: TRUE)
   */
  private function setupPaymentPlanExtraAttributes($recurringContributionId, $isActive = TRUE) {
    $query = "INSERT INTO civicrm_value_payment_plan_extra_attributes (entity_id, is_active)
              VALUES (%1, %2)
              ON DUPLICATE KEY UPDATE is_active = %2";
    CRM_Core_DAO::executeQuery($query, [
      1 => [$recurringContributionId, 'Integer'],
      2 => [$isActive ? 1 : 0, 'Integer'],
    ]);
  }

  /**
   * Creates a contribution with a specific receive date.
   */
  private function createContributionWithDate($recurringContributionId, $status, $receiveDate) {
    return \Civi\Api4\Contribution::create()
      ->addValue('contact_id', $this->contact['id'])
      ->addValue('financial_type_id', 1)
      ->addValue('total_amount', 100)
      ->addValue('currency', 'GBP')
      ->addValue('contribution_recur_id', $recurringContributionId)
      ->addValue('contribution_status_id:name', $status)
      ->addValue('receive_date', $receiveDate)
      ->execute()->first();
  }

  /**
   * Sets up the payment_in_progress flag for a contribution.
   */
  private function setupPaymentInProgress($contributionId, $inProgress = TRUE) {
    $query = "INSERT INTO civicrm_value_external_dd_payment_information (entity_id, payment_in_progress)
              VALUES (%1, %2)
              ON DUPLICATE KEY UPDATE payment_in_progress = %2";
    CRM_Core_DAO::executeQuery($query, [
      1 => [$contributionId, 'Integer'],
      2 => [$inProgress ? 1 : 0, 'Integer'],
    ]);
  }

  public function testOverdueRecurringContributionIsIncludedInBACSQuery() {
    $recurringContribution = $this->createRecurringContribution('Overdue');
    $this->setupMandateInformation($recurringContribution['id'], 'TEST_MANDATE_OVERDUE', 'bacs');
    $this->setupPaymentPlanExtraAttributes($recurringContribution['id']);

    $contribution = $this->createContribution($recurringContribution['id'], 'Pending');

    $contributionIds = $this->getBACSQueryContributionIds();

    $this->assertTrue(
      in_array($contribution['id'], $contributionIds),
      'Contribution with Overdue recurring status should be included in BACS query'
    );
  }

  public function testPendingRecurringContributionIsIncludedInNonBACSQuery() {
    $recurringContribution = $this->createRecurringContribution('Pending');
    $this->setupMandateInformation($recurringContribution['id'], 'TEST_MANDATE_PENDING', 'sepa_core');
    $this->setupPaymentPlanExtraAttributes($recurringContribution['id']);

    $contribution = $this->createContribution($recurringContribution['id'], 'Pending');

    $contributionIds = $this->getOtherInvoicesQueryContributionIds();

    $this->assertTrue(
      in_array($contribution['id'], $contributionIds),
      'Contribution with Pending recurring status should be included in non-BACS query'
    );
  }

  public function testPendingRecurringContributionIsIncludedInBACSQuery() {
    $recurringContribution = $this->createRecurringContribution('Pending');
    $this->setupMandateInformation($recurringContribution['id'], 'TEST_MANDATE_PENDING_BACS', 'bacs');
    $this->setupPaymentPlanExtraAttributes($recurringContribution['id']);

    $contribution = $this->createContribution($recurringContribution['id'], 'Pending');

    $contributionIds = $this->getBACSQueryContributionIds();

    $this->assertTrue(
      in_array($contribution['id'], $contributionIds),
      'Contribution with Pending recurring status should be included in BACS query'
    );
  }

  public function testCompletedRecurringContributionIsIncludedInBACSQuery() {
    $recurringContribution = $this->createRecurringContribution('Completed');
    $this->setupMandateInformation($recurringContribution['id'], 'TEST_MANDATE_COMPLETED_BACS', 'bacs');
    $this->setupPaymentPlanExtraAttributes($recurringContribution['id']);

    $contribution = $this->createContribution($recurringContribution['id'], 'Pending');

    $contributionIds = $this->getBACSQueryContributionIds();

    $this->assertTrue(
      in_array($contribution['id'], $contributionIds),
      'Contribution with Completed recurring status should be included in BACS query if it has pending contributions'
    );
  }

  public function testCancelledRecurringContributionIsNotIncludedInBACSQuery() {
    $recurringContribution = $this->createRecurringContribution('Cancelled');
    $this->setupMandateInformation($recurringContribution['id'], 'TEST_MANDATE_CANCELLED_BACS', 'bacs');
    $this->setupPaymentPlanExtraAttributes($recurringContribution['id']);

    $contribution = $this->createContribution($recurringContribution['id'], 'Pending');

    $contributionIds = $this->getBACSQueryContributionIds();

    $this->assertFalse(
      in_array($contribution['id'], $contributionIds),
      'Contribution with Cancelled recurring status should NOT be included in BACS query'
    );
  }

  public function testFailedRecurringContributionIsNotIncludedInBACSQuery() {
    $recurringContribution = $this->createRecurringContribution('Failed');
    $this->setupMandateInformation($recurringContribution['id'], 'TEST_MANDATE_FAILED_BACS', 'bacs');
    $this->setupPaymentPlanExtraAttributes($recurringContribution['id']);

    $contribution = $this->createContribution($recurringContribution['id'], 'Pending');

    $contributionIds = $this->getBACSQueryContributionIds();

    $this->assertFalse(
      in_array($contribution['id'], $contributionIds),
      'Contribution with Failed recurring status should NOT be included in BACS query'
    );
  }

  public function testPartiallyPaidContributionIsIncludedInNonBACSQuery() {
    $recurringContribution = $this->createRecurringContribution('In Progress');
    $this->setupMandateInformation($recurringContribution['id'], 'TEST_MANDATE_PARTIAL', 'sepa_core');
    $this->setupPaymentPlanExtraAttributes($recurringContribution['id']);

    $contribution = $this->createContribution($recurringContribution['id'], 'Partially paid');

    $contributionIds = $this->getOtherInvoicesQueryContributionIds();

    $this->assertTrue(
      in_array($contribution['id'], $contributionIds),
      'Partially paid contribution should be included in non-BACS query'
    );
  }

  public function testFailedContributionIsNotIncludedInNonBACSQuery() {
    $recurringContribution = $this->createRecurringContribution('In Progress');
    $this->setupMandateInformation($recurringContribution['id'], 'TEST_MANDATE_FAILED', 'sepa_core');
    $this->setupPaymentPlanExtraAttributes($recurringContribution['id']);

    $contribution = $this->createContribution($recurringContribution['id'], 'Failed');

    $contributionIds = $this->getOtherInvoicesQueryContributionIds();

    $this->assertFalse(
      in_array($contribution['id'], $contributionIds),
      'Failed contribution should NOT be included in non-BACS query'
    );
  }

  public function testCompletedContributionIsNotIncludedInNonBACSQuery() {
    $recurringContribution = $this->createRecurringContribution('In Progress');
    $this->setupMandateInformation($recurringContribution['id'], 'TEST_MANDATE_COMPLETED', 'sepa_core');
    $this->setupPaymentPlanExtraAttributes($recurringContribution['id']);

    $contribution = $this->createContribution($recurringContribution['id'], 'Completed');

    $contributionIds = $this->getOtherInvoicesQueryContributionIds();

    $this->assertFalse(
      in_array($contribution['id'], $contributionIds),
      'Completed contribution should NOT be included in non-BACS query'
    );
  }

  public function testFutureReceiveDateContributionIsNotIncludedInBACSQuery() {
    $this->setupPaymentPlanExtraAttributes($this->recurringContribution['id']);

    $futureDate = date('Y-m-d', strtotime('+7 days'));
    $contribution = $this->createContributionWithDate($this->recurringContribution['id'], 'Pending', $futureDate);

    $contributionIds = $this->getBACSQueryContributionIds();

    $this->assertFalse(
      in_array($contribution['id'], $contributionIds),
      'Contribution with future receive_date should NOT be included in BACS query'
    );
  }

  public function testTodayReceiveDateContributionIsIncludedInBACSQuery() {
    $this->setupPaymentPlanExtraAttributes($this->recurringContribution['id']);

    $today = date('Y-m-d');
    $contribution = $this->createContributionWithDate($this->recurringContribution['id'], 'Pending', $today);

    $contributionIds = $this->getBACSQueryContributionIds();

    $this->assertTrue(
      in_array($contribution['id'], $contributionIds),
      'Contribution with today receive_date should be included in BACS query'
    );
  }

  public function testPastReceiveDateContributionIsIncludedInBACSQuery() {
    $this->setupPaymentPlanExtraAttributes($this->recurringContribution['id']);

    $pastDate = date('Y-m-d', strtotime('-7 days'));
    $contribution = $this->createContributionWithDate($this->recurringContribution['id'], 'Pending', $pastDate);

    $contributionIds = $this->getBACSQueryContributionIds();

    $this->assertTrue(
      in_array($contribution['id'], $contributionIds),
      'Contribution with past receive_date should be included in BACS query'
    );
  }

  public function testPaymentInProgressContributionIsNotIncludedInBACSQuery() {
    $this->setupPaymentPlanExtraAttributes($this->recurringContribution['id']);

    $contribution = $this->createContribution($this->recurringContribution['id'], 'Pending');
    $this->setupPaymentInProgress($contribution['id'], TRUE);

    $contributionIds = $this->getBACSQueryContributionIds();

    $this->assertFalse(
      in_array($contribution['id'], $contributionIds),
      'Contribution with payment_in_progress=1 should NOT be included in BACS query'
    );
  }

  public function testPaymentNotInProgressContributionIsIncludedInBACSQuery() {
    $this->setupPaymentPlanExtraAttributes($this->recurringContribution['id']);

    $contribution = $this->createContribution($this->recurringContribution['id'], 'Pending');
    $this->setupPaymentInProgress($contribution['id'], FALSE);

    $contributionIds = $this->getBACSQueryContributionIds();

    $this->assertTrue(
      in_array($contribution['id'], $contributionIds),
      'Contribution with payment_in_progress=0 should be included in BACS query'
    );
  }

  public function testInactivePaymentPlanContributionIsNotIncludedInBACSQuery() {
    $this->setupPaymentPlanExtraAttributes($this->recurringContribution['id'], FALSE);

    $contribution = $this->createContribution($this->recurringContribution['id'], 'Pending');

    $contributionIds = $this->getBACSQueryContributionIds();

    $this->assertFalse(
      in_array($contribution['id'], $contributionIds),
      'Contribution with inactive payment plan (is_active=0) should NOT be included in BACS query'
    );
  }

  public function testMixedContributionStatusesOnlyReturnsValidOnes() {
    $this->setupPaymentPlanExtraAttributes($this->recurringContribution['id']);

    $pendingContrib = $this->createContribution($this->recurringContribution['id'], 'Pending');
    $partiallyPaidContrib = $this->createContribution($this->recurringContribution['id'], 'Partially paid');
    $cancelledContrib = $this->createContribution($this->recurringContribution['id'], 'Cancelled');
    $completedContrib = $this->createContribution($this->recurringContribution['id'], 'Completed');
    $failedContrib = $this->createContribution($this->recurringContribution['id'], 'Failed');

    $contributionIds = $this->getBACSQueryContributionIds();

    $this->assertTrue(
      in_array($pendingContrib['id'], $contributionIds),
      'Pending contribution should be included'
    );
    $this->assertTrue(
      in_array($partiallyPaidContrib['id'], $contributionIds),
      'Partially paid contribution should be included'
    );
    $this->assertFalse(
      in_array($cancelledContrib['id'], $contributionIds),
      'Cancelled contribution should NOT be included'
    );
    $this->assertFalse(
      in_array($completedContrib['id'], $contributionIds),
      'Completed contribution should NOT be included'
    );
    $this->assertFalse(
      in_array($failedContrib['id'], $contributionIds),
      'Failed contribution should NOT be included'
    );
  }

}
