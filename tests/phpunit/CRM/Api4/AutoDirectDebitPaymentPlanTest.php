<?php

/**
 * Unit test for the Example entity
 * @group headless
 */
class CRM_Api4_AutoDirectDebitPaymentPlanTest extends BaseHeadlessTest {

  private $recurringContribution;

  private $paymentScheme;

  public function setUp() {
    $this->recurringContribution = \Civi\Api4\ContributionRecur::create()
      ->addValue('contact_id', 1)
      ->addValue('amount', 100)
      ->addValue('is_test', FALSE)
      ->addValue('installments', 12)
      ->addValue('frequency_unit', 'month')
      ->addValue('frequency_interval', 1)
      ->addValue('payment_processor', 2)
      ->execute()[0];

    $this->paymentScheme = \Civi\Api4\PaymentScheme::create()
      ->addValue('name', 'testscheme')
      ->addValue('admin_title', 'Test Scheme')
      ->addValue('public_title', 'Test Scheme')
      ->addValue('permission', 'public')
      ->addValue('parameters', '{}')
      ->addValue('payment_processor', 1)
      ->addValue('public_description', 'test')
      ->execute()[0];

    \Civi\Api4\AutoDirectDebitPaymentPlan::switchToDirectDebitPaymentScheme()
      ->setContributionRecurId($this->recurringContribution['id'])
      ->setPaymentSchemeID($this->paymentScheme['id'])
      ->setMandateId('MAND_00001')
      ->setMandateStatus(1)
      ->setNextAvailablePaymentDate('2023-01-01')
      ->execute();
  }

  public function testSwitchToDirectDebitPaymentSchemeChangeTheRecurringContributionPaymentProcessorToThePaymentSchemeOne() {
    $recurringContributionAfterSwitch = \Civi\Api4\ContributionRecur::get()
      ->addSelect('payment_processor_id')
      ->addWhere('id', '=', $this->recurringContribution['id'])
      ->setLimit(1)
      ->execute()[0];

    $this->assertEquals(1, $recurringContributionAfterSwitch['payment_processor_id']);
  }

  public function testSwitchToDirectDebitPaymentSchemeWillUnsetIrrelevantRecurringContributionFields() {
    $recurringContributionAfterSwitch = \Civi\Api4\ContributionRecur::get()
      ->addSelect('installments', 'frequency_unit', 'frequency_interval')
      ->addWhere('id', '=', $this->recurringContribution['id'])
      ->setLimit(1)
      ->execute()[0];

    $this->assertEmpty($recurringContributionAfterSwitch['installments']);
    $this->assertEmpty($recurringContributionAfterSwitch['frequency_unit']);
    $this->assertEmpty($recurringContributionAfterSwitch['frequency_interval']);
  }

  public function testSwitchToDirectDebitPaymentSchemeLinksTheRecurringContributionToThePaymentScheme() {
    $recurringContributionAfterSwitch = \Civi\Api4\ContributionRecur::get()
      ->addSelect('payment_plan_extra_attributes.payment_scheme_id')
      ->addWhere('id', '=', $this->recurringContribution['id'])
      ->setLimit(1)
      ->execute()[0];

    $this->assertEquals($this->paymentScheme['id'], $recurringContributionAfterSwitch['payment_plan_extra_attributes.payment_scheme_id']);
  }

  public function testSwitchToDirectDebitPaymentSchemeSetsTheRecurringContributionExternalMandateInformation() {
    $recurringContributionAfterSwitch = \Civi\Api4\ContributionRecur::get()
      ->addSelect('external_direct_debit_mandate_information.mandate_id')
      ->addSelect('external_direct_debit_mandate_information.mandate_status')
      ->addSelect('external_direct_debit_mandate_information.next_available_payment_date')
      ->addWhere('id', '=', $this->recurringContribution['id'])
      ->setLimit(1)
      ->execute()[0];

    $this->assertEquals('MAND_00001', $recurringContributionAfterSwitch['external_direct_debit_mandate_information.mandate_id']);
    $this->assertEquals(1, $recurringContributionAfterSwitch['external_direct_debit_mandate_information.mandate_status']);
    $this->assertEquals('2023-01-01', $recurringContributionAfterSwitch['external_direct_debit_mandate_information.next_available_payment_date']);
  }

}
