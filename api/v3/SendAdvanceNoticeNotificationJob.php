<?php

/**
 * Auto Direct Debit Advance Notice Notifications scheduled job API
 *
 * @param $params
 * @return array
 */
function civicrm_api3_send_advance_notice_notification_job_run($params) {
  $lock = Civi::lockManager()->acquire('worker.automateddirectdebit.sendadvancenoticenotification');
  if (!$lock->isAcquired()) {
    return civicrm_api3_create_error("Could not acquire a lock, another 'Send Advance Notice Notifications' job is running");
  }

  try {
    $advanceNoticeEventJob = new CRM_Automateddirectdebit_Job_DirectDebitEvents_AdvanceNoticeEvent();
    $advanceNoticeEventJob->run();

    $lock->release();

    return civicrm_api3_create_success();
  }
  catch (Exception $error) {
    $lock->release();
    throw $error;
  }
}
