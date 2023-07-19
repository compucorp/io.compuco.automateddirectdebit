<?php

use CRM_MembershipExtras_Setup_Configure_ConfigurerInterface as ConfigurerInterface;

/**
 * Sets the default value for "Payment collection number of retry attempts"
 * setting.
 *
 */
class CRM_Automateddirectdebit_Setup_Configure_SetPaymentCollectionRetryCountDefaultValue implements ConfigurerInterface {

  public function apply() {
    \Civi::settings()->set('automateddirectdebit_paymentplan_payment_collection_retry_count', 3);
  }

}
