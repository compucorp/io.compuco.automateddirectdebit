<?php

namespace Civi\Api4\Action\AutoDirectDebitPaymentPlan;

use Civi\Api4\Generic\Result;

/**
 * Action for switching a recurring contribution
 * to new payment scheme that supports automated
 * direct debit.
 *
 * The action mainly:
 * - Links the new payment scheme to the recurring contribution.
 * - Updates the recurring contribution payment processor
 *   to match the one configured on the new payment scheme.
 * - Updates the external mandate information.
 * - Unsets fields like installments and frequency_unit on
 *   the recurring contribution given they are not needed
 *   for payment scheme enabled payment plans.
 *
 * @see \Civi\Api4\Generic\AbstractAction
 *
 * @package Civi\Api4\Action\AutoDirectDebitPaymentPlan
 */
class SwitchToDirectDebitPaymentScheme extends \Civi\Api4\Generic\AbstractAction {

  /**
   * @var int
   * @required
   */
  protected $contributionRecurId;

  /**
   * @var int
   * @required
   */
  protected $paymentSchemeID;

  /**
   * @var string
   * @required
   */
  protected $mandateId;

  /**
   * @var int
   * @required
   */
  protected $mandateStatus;

  /**
   * @var string
   * @required
   */
  protected $mandateScheme;

  /**
   * @var string
   * @required
   */
  protected $nextAvailablePaymentDate;

  /**
   * @inheritDoc
   */
  public function _run(Result $result) {
    $paymentScheme = \Civi\Api4\PaymentScheme::get(FALSE)
      ->addSelect('payment_processor')
      ->addWhere('id', '=', $this->paymentSchemeID)
      ->setLimit(1)
      ->execute();
    if (empty($paymentScheme[0])) {
      throw new \CRM_Core_Exception('Invalid payment scheme id.');
    }
    $newPaymentProcessorId = $paymentScheme[0]['payment_processor'];

    return \Civi\Api4\ContributionRecur::update(FALSE)
      ->addValue('payment_processor_id', $newPaymentProcessorId)
      ->addValue('frequency_unit', NULL)
      ->addValue('frequency_interval', NULL)
      ->addValue('payment_plan_extra_attributes.payment_scheme_id', $this->paymentSchemeID)
      ->addValue('external_direct_debit_mandate_information.mandate_id', $this->mandateId)
      ->addValue('external_direct_debit_mandate_information.mandate_status', $this->mandateStatus)
      ->addValue('external_direct_debit_mandate_information.mandate_scheme', strtolower($this->mandateScheme))
      ->addValue('external_direct_debit_mandate_information.next_available_payment_date', $this->nextAvailablePaymentDate)
      ->addWhere('id', '=', $this->contributionRecurId)
      ->execute();
  }

  /**
   * @inheritDoc
   *
   * @return array
   */
  public static function fields() {
    return [
      ['name' => 'contributionRecurId', 'data_type' => 'Integer'],
      ['name' => 'paymentSchemeID', 'data_type' => 'Integer'],
      ['name' => 'mandateId', 'data_type' => 'String'],
      ['name' => 'mandateStatus', 'data_type' => 'Integer'],
      ['name' => 'nextAvailablePaymentDate', 'data_type' => 'Date'],
    ];
  }

}
