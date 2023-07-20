<?php

function civicrm_api3_external_dd_payment_collection_job_run($params, $paymentCollectionEventJob)
{
  $lock = Civi::lockManager()->acquire('worker.automateddirectdebit.externalddpaymentcollection');
  if (!$lock->isAcquired()) {
    return civicrm_api3_create_error("Could not acquire a lock, another 'External Payment Collection' job is running");
  }

  try {
    //todo

    $lock->release();

    return civicrm_api3_create_success();
  } catch (Exception $error) {
    $lock->release();
    throw $error;
  }
}
