<?php

use CRM_MembershipExtras_Setup_Manage_AbstractManager as AbstractManager;

/**
 * Managing the 'Send Advance Notice Notifications' scheduled job.
 */
class CRM_Automateddirectdebit_Setup_Manage_ScheduledJob_AdvanceNoticeNotificationsJob extends AbstractManager {

  const JOB_NAME = 'Send Direct Debit Advance Notice Notifications';

  /**
   * @inheritDoc
   */
  public function create() {
    $result = civicrm_api3('Job', 'get', [
      'name' => self::JOB_NAME,
    ]);
    if (!empty($result['id'])) {
      return;
    }

    civicrm_api3('Job', 'create', [
      'run_frequency' => 'Daily',
      'name' => self::JOB_NAME,
      'description' => ts('Automatically send Direct Debit advance notice notifications to customers with pending payments'),
      'api_entity' => 'SendAdvanceNoticeNotificationJob',
      'api_action' => 'run',
      'is_active' => 0,
    ]);
  }

  /**
   * @inheritDoc
   */
  public function remove() {
    civicrm_api3('Job', 'get', [
      'name' => self::JOB_NAME,
      'api.Job.delete' => ['id' => '$value.id'],
    ]);
  }

  /**
   * @inheritDoc
   */
  protected function toggle($status) {
    civicrm_api3('Job', 'get', [
      'name' => self::JOB_NAME,
      'api.Job.create' => ['id' => '$value.id', 'is_active' => $status],
    ]);
  }

}
