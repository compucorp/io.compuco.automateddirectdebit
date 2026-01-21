<?php

/**
 * Unit tests for PaymentCollectionEvent
 * @group headless
 */
class CRM_Automateddirectdebit_Job_PaymentCollectionEventTest extends BaseHeadlessTest {

  private $paymentCollectionEvent;

  public function setUp(): void {
    parent::setUp();
    $this->paymentCollectionEvent = new CRM_Automateddirectdebit_Job_DirectDebitEvents_PaymentCollectionEvent();
  }

  /**
   * Test timestamp is set on first lock.
   */
  public function testMarkInProgressSetsTimestampOnFirstLock() {
    $contributionId = $this->createTestContribution();

    $this->invokeMethod('markContributionExternalPaymentToBeInProgress', [$contributionId]);

    $results = CRM_Core_DAO::executeQuery(
      "SELECT payment_in_progress, payment_in_progress_at
       FROM civicrm_value_external_dd_payment_information
       WHERE entity_id = %1",
      [1 => [$contributionId, 'Integer']]
    )->fetchAll();

    $this->assertCount(1, $results);
    $this->assertEquals(1, $results[0]['payment_in_progress']);
    $this->assertNotNull($results[0]['payment_in_progress_at']);
  }

  /**
   * Test timestamp is preserved on retry (when flag was already 1).
   */
  public function testMarkInProgressPreservesTimestampOnRetry() {
    $contributionId = $this->createTestContribution();
    $originalTimestamp = '2025-01-01 10:00:00';

    // Set initial lock with old timestamp
    CRM_Core_DAO::executeQuery(
      "INSERT INTO civicrm_value_external_dd_payment_information
       (entity_id, payment_in_progress, payment_in_progress_at)
       VALUES (%1, 1, %2)",
      [
        1 => [$contributionId, 'Integer'],
        2 => [$originalTimestamp, 'String'],
      ]
    );

    // Call mark in progress again (simulating retry)
    $this->invokeMethod('markContributionExternalPaymentToBeInProgress', [$contributionId]);

    $result = CRM_Core_DAO::singleValueQuery(
      "SELECT payment_in_progress_at
       FROM civicrm_value_external_dd_payment_information
       WHERE entity_id = %1",
      [1 => [$contributionId, 'Integer']]
    );

    $this->assertEquals($originalTimestamp, $result);
  }

  /**
   * Test timestamp is reset on new payment cycle (when flag was 0).
   */
  public function testMarkInProgressResetsTimestampOnNewPaymentCycle() {
    $contributionId = $this->createTestContribution();
    $oldTimestamp = '2025-01-01 10:00:00';

    // Set previous successful payment (flag = 0, but timestamp exists)
    CRM_Core_DAO::executeQuery(
      "INSERT INTO civicrm_value_external_dd_payment_information
       (entity_id, payment_in_progress, payment_in_progress_at)
       VALUES (%1, 0, %2)",
      [
        1 => [$contributionId, 'Integer'],
        2 => [$oldTimestamp, 'String'],
      ]
    );

    // Call mark in progress for new payment cycle
    $this->invokeMethod('markContributionExternalPaymentToBeInProgress', [$contributionId]);

    $result = CRM_Core_DAO::singleValueQuery(
      "SELECT payment_in_progress_at
       FROM civicrm_value_external_dd_payment_information
       WHERE entity_id = %1",
      [1 => [$contributionId, 'Integer']]
    );

    $this->assertNotEquals($oldTimestamp, $result);
  }

  /**
   * Test query excludes contributions locked less than 30 minutes ago.
   */
  public function testPendingQueryExcludesRecentlyLockedContributions() {
    $contributionId = $this->createTestContributionWithMandate();

    // Lock 10 minutes ago
    CRM_Core_DAO::executeQuery(
      "INSERT INTO civicrm_value_external_dd_payment_information
       (entity_id, payment_in_progress, payment_in_progress_at)
       VALUES (%1, 1, DATE_SUB(NOW(), INTERVAL 10 MINUTE))",
      [1 => [$contributionId, 'Integer']]
    );

    $query = $this->paymentCollectionEvent->buildPendingBACSInvoicesQuery();
    $results = CRM_Core_DAO::executeQuery($query->toSQL())->fetchAll();
    $ids = array_column($results, 'contribution_id');

    $this->assertNotContains($contributionId, $ids);
  }

  /**
   * Test query includes contributions in retry window (30-90 min).
   */
  public function testPendingQueryIncludesContributionsInRetryWindow() {
    $contributionId = $this->createTestContributionWithMandate();

    // Lock 45 minutes ago (in retry window)
    CRM_Core_DAO::executeQuery(
      "INSERT INTO civicrm_value_external_dd_payment_information
       (entity_id, payment_in_progress, payment_in_progress_at)
       VALUES (%1, 1, DATE_SUB(NOW(), INTERVAL 45 MINUTE))",
      [1 => [$contributionId, 'Integer']]
    );

    $query = $this->paymentCollectionEvent->buildPendingBACSInvoicesQuery();
    $results = CRM_Core_DAO::executeQuery($query->toSQL())->fetchAll();
    $ids = array_column($results, 'contribution_id');

    $this->assertContains($contributionId, $ids);
  }

  /**
   * Test query excludes permanently failed contributions (> 90 min).
   */
  public function testPendingQueryExcludesPermanentlyFailedContributions() {
    $contributionId = $this->createTestContributionWithMandate();

    // Lock 120 minutes ago (permanently failed)
    CRM_Core_DAO::executeQuery(
      "INSERT INTO civicrm_value_external_dd_payment_information
       (entity_id, payment_in_progress, payment_in_progress_at)
       VALUES (%1, 1, DATE_SUB(NOW(), INTERVAL 120 MINUTE))",
      [1 => [$contributionId, 'Integer']]
    );

    $query = $this->paymentCollectionEvent->buildPendingBACSInvoicesQuery();
    $results = CRM_Core_DAO::executeQuery($query->toSQL())->fetchAll();
    $ids = array_column($results, 'contribution_id');

    $this->assertNotContains($contributionId, $ids);
  }

  /**
   * Test query excludes legacy data with NULL timestamp.
   */
  public function testPendingQueryExcludesLegacyNullTimestamp() {
    $contributionId = $this->createTestContributionWithMandate();

    // Legacy lock with NULL timestamp
    CRM_Core_DAO::executeQuery(
      "INSERT INTO civicrm_value_external_dd_payment_information
       (entity_id, payment_in_progress, payment_in_progress_at)
       VALUES (%1, 1, NULL)",
      [1 => [$contributionId, 'Integer']]
    );

    $query = $this->paymentCollectionEvent->buildPendingBACSInvoicesQuery();
    $results = CRM_Core_DAO::executeQuery($query->toSQL())->fetchAll();
    $ids = array_column($results, 'contribution_id');

    $this->assertNotContains($contributionId, $ids);
  }

  // Helper methods

  /**
   * Create a basic test contribution.
   */
  private function createTestContribution() {
    $contact = \Civi\Api4\Contact::create(FALSE)
      ->addValue('contact_type', 'Individual')
      ->addValue('first_name', 'Test')
      ->addValue('last_name', 'Contact')
      ->execute()
      ->first();

    $contribution = \Civi\Api4\Contribution::create(FALSE)
      ->addValue('contact_id', $contact['id'])
      ->addValue('total_amount', 100)
      ->addValue('financial_type_id', 1)
      ->execute()
      ->first();

    return $contribution['id'];
  }

  /**
   * Create a test contribution with all required joins for the query.
   * (recurring contribution, mandate, payment plan attributes)
   */
  private function createTestContributionWithMandate() {
    $contact = \Civi\Api4\Contact::create(FALSE)
      ->addValue('contact_type', 'Individual')
      ->addValue('first_name', 'Test')
      ->addValue('last_name', 'Contact')
      ->execute()
      ->first();

    // Create recurring contribution
    $recurringContribution = \Civi\Api4\ContributionRecur::create(FALSE)
      ->addValue('contact_id', $contact['id'])
      ->addValue('amount', 100)
      ->addValue('frequency_unit', 'month')
      ->addValue('frequency_interval', 1)
      ->addValue('contribution_status_id:name', 'In Progress')
      ->execute()
      ->first();

    // Create contribution linked to recurring
    $contribution = \Civi\Api4\Contribution::create(FALSE)
      ->addValue('contact_id', $contact['id'])
      ->addValue('total_amount', 100)
      ->addValue('financial_type_id', 1)
      ->addValue('contribution_recur_id', $recurringContribution['id'])
      ->addValue('contribution_status_id:name', 'Pending')
      ->addValue('receive_date', 'now')
      ->execute()
      ->first();

    // Add mandate information
    CRM_Core_DAO::executeQuery(
      "INSERT INTO civicrm_value_external_dd_mandate_information
       (entity_id, mandate_id, mandate_scheme)
       VALUES (%1, %2, %3)",
      [
        1 => [$recurringContribution['id'], 'Integer'],
        2 => ['MANDATE_TEST_' . $contribution['id'], 'String'],
        3 => ['bacs', 'String'],
      ]
    );

    // Add payment plan extra attributes (is_active = 1)
    CRM_Core_DAO::executeQuery(
      "INSERT INTO civicrm_value_payment_plan_extra_attributes
       (entity_id, is_active)
       VALUES (%1, 1)",
      [1 => [$recurringContribution['id'], 'Integer']]
    );

    return $contribution['id'];
  }

  /**
   * Invoke a private method on the payment collection event object.
   */
  private function invokeMethod($methodName, $args = []) {
    $reflection = new ReflectionClass($this->paymentCollectionEvent);
    $method = $reflection->getMethod($methodName);
    $method->setAccessible(TRUE);
    return $method->invokeArgs($this->paymentCollectionEvent, $args);
  }

}
