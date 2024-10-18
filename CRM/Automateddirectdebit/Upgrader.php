<?php

/**
 * Collection of upgrade steps.
 */
class CRM_Automateddirectdebit_Upgrader extends CRM_Extension_Upgrader_Base {

  public function postInstall() {
    $creationSteps = [
      new CRM_Automateddirectdebit_Setup_Manage_ScheduledJob_PaymentCollectionJob(),
    ];
    foreach ($creationSteps as $step) {
      $step->create();
    }

    $configurationSteps = [
      new CRM_Automateddirectdebit_Setup_Configure_SetPaymentCollectionRetryCountDefaultValue(),
    ];
    foreach ($configurationSteps as $step) {
      $step->apply();
    }
  }

  public function enable() {
    $steps = [
      new CRM_Automateddirectdebit_Setup_Manage_CustomGroup_ExternalDDMandateInformation(),
      new CRM_Automateddirectdebit_Setup_Manage_CustomGroup_ExternalDDPaymentInformation(),
      new CRM_Automateddirectdebit_Setup_Manage_ScheduledJob_PaymentCollectionJob(),
    ];
    foreach ($steps as $step) {
      $step->activate();
    }
  }

  public function disable() {
    $steps = [
      new CRM_Automateddirectdebit_Setup_Manage_CustomGroup_ExternalDDMandateInformation(),
      new CRM_Automateddirectdebit_Setup_Manage_CustomGroup_ExternalDDPaymentInformation(),
      new CRM_Automateddirectdebit_Setup_Manage_ScheduledJob_PaymentCollectionJob(),
    ];
    foreach ($steps as $step) {
      $step->deactivate();
    }
  }

  public function uninstall() {
    $removalSteps = [
      new CRM_Automateddirectdebit_Setup_Manage_CustomGroup_ExternalDDMandateInformation(),
      new CRM_Automateddirectdebit_Setup_Manage_CustomGroup_ExternalDDPaymentInformation(),
      new CRM_Automateddirectdebit_Setup_Manage_ScheduledJob_PaymentCollectionJob(),
    ];
    foreach ($removalSteps as $step) {
      $step->remove();
    }
  }

  public function upgrade_1000() {
    $this->ctx->log->info('Applying update 1000');

    (new CRM_Automateddirectdebit_Setup_Configure_AddMandateSchemetoDDMandateInformation())->apply();

    return TRUE;
  }

}
