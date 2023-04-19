<?php

use CRM_Automateddirectdebit_Job_DirectDebitEvents_PendingContributionsQueryBuilder as PendingContributionsQueryBuilder;

/**
 * Responsible for sending direct debit advance notice notifications
 * event, the class does not send the notifications itself,
 * but rather dispatch a hook called: `automateddirectdebit_AdvanceNoticeEvent`
 * that 3rd party payment processor extensions can implement, so such extensions
 * can have more control over how such notifications can be sent.
 *
 */
class CRM_Automateddirectdebit_Job_DirectDebitEvents_AdvanceNoticeEvent {

  /**
   * How many days before the payment should
   * the user by notified about when the payment
   * will be taken.
   *
   * todo: it is hardcoded for now, but we should probably expose it as a setting in the UI.
   */
  const DAYS_TO_NOTIFY_IN_ADVANCE = 10;

  public function run() {
    $pendingContributions = $this->getPendingContributionsForAdvanceNotice();
    foreach ($pendingContributions as $pendingContribution) {
      $this->dispatchAdvanceNoticeEventHook($pendingContribution);
    }
  }

  private function getPendingContributionsForAdvanceNotice() {
    $daysToNotifyInAdvance = self::DAYS_TO_NOTIFY_IN_ADVANCE;
    $query = PendingContributionsQueryBuilder::buildQuery();
    $query->where("c.receive_date = DATE_SUB(mandate.next_available_payment_date, INTERVAL {$daysToNotifyInAdvance} DAY)")
      // todo: find out what data @Dome's wants and only select() that data here.
      // todo: There is an extra condition needed here to only send notifications for unprocessed payments (we don't have the schema for such data yet)
      ->select('c.*');

    $pendingContributions = CRM_Core_DAO::executeQuery($query->toSQL());

    $pendingContributionsList = [];
    while ($pendingContributions->fetch()) {
      // todo: find out what data @Dome's wants and add it here.
      $pendingContributionsList[] = [
        'contribution_id' => $pendingContributions->id,
      ];
    }

    return $pendingContributionsList;
  }

  private function dispatchAdvanceNoticeEventHook($pendingContributionData) {
    $nullObject = CRM_Utils_Hook::$_nullObject;
    // todo: replace PLACEHOLDER with the data @Dome's
    CRM_Utils_Hook::singleton()->invoke(
      ['PLACEHOLDER'],
      $pendingContributionData,
      $nullObject, $nullObject, $nullObject, $nullObject, $nullObject,
      'automateddirectdebit_AdvanceNoticeEvent'
    );
  }

}
