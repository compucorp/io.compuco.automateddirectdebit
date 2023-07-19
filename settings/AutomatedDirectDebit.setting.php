<?php

/*
 * Settings Metadata
 */
return [
  'automateddirectdebit_paymentplan_payment_collection_retry_count' => [
    'group_name' => 'MembershipExtras: Payment Plan',
    'group' => 'membershipextras_paymentplan',
    'name' => 'automateddirectdebit_paymentplan_payment_collection_retry_count',
    'title' => 'Payment collection number of retry attempts',
    'type' => 'Integer',
    'html_type' => 'text',
    'quick_form_type' => 'Element',
    'default' => 3,
    'is_required' => TRUE,
  ],
];
