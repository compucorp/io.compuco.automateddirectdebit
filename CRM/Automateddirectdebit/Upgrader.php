<?php

/**
 * Collection of upgrade steps.
 */
class CRM_Automateddirectdebit_Upgrader extends CRM_Automateddirectdebit_Upgrader_Base {

  public function postInstall() {
    $creationSteps = [
      new CRM_Automateddirectdebit_Setup_Manage_ScheduledJob_AdvanceNoticeNotificationsJob(),
    ];

    foreach ($creationSteps as $step) {
      $step->create();
    }
  }

  public function enable() {
    $steps = [
      new CRM_Automateddirectdebit_Setup_Manage_CustomGroup_ExternalDDMandateInformation(),
    ];

    foreach ($steps as $step) {
      $step->activate();
    }
  }

  public function disable() {
    $steps = [
      new CRM_Automateddirectdebit_Setup_Manage_CustomGroup_ExternalDDMandateInformation(),
      new CRM_Automateddirectdebit_Setup_Manage_ScheduledJob_AdvanceNoticeNotificationsJob(),
    ];

    foreach ($steps as $step) {
      $step->deactivate();
    }
  }

  public function uninstall() {
    $removalSteps = [
      new CRM_Automateddirectdebit_Setup_Manage_CustomGroup_ExternalDDMandateInformation(),
      new CRM_Automateddirectdebit_Setup_Manage_ScheduledJob_AdvanceNoticeNotificationsJob(),
    ];

    foreach ($removalSteps as $step) {
      $step->remove();
    }
  }

}
