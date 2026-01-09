<?php

return [
  [
    'name' => 'CustomField_payment_in_progress_at',
    'entity' => 'CustomField',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'custom_group_id.name' => 'external_dd_payment_information',
        'name' => 'payment_in_progress_at',
        'label' => 'Payment in progress since',
        'data_type' => 'Date',
        'html_type' => 'Select Date',
        'date_format' => 'yy-mm-dd',
        'time_format' => 2,
        'column_name' => 'payment_in_progress_at',
        'is_view' => TRUE,
        'is_searchable' => FALSE,
      ],
      'match' => ['custom_group_id', 'name'],
    ],
  ],
];
