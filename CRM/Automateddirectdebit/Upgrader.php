<?php

/**
 * Collection of upgrade steps.
 */
class CRM_Automateddirectdebit_Upgrader extends CRM_Automateddirectdebit_Upgrader_Base {

  public function postInstall() {
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
    ];
    foreach ($steps as $step) {
      $step->activate();
    }
  }

  public function disable() {
    $steps = [
      new CRM_Automateddirectdebit_Setup_Manage_CustomGroup_ExternalDDMandateInformation(),
      new CRM_Automateddirectdebit_Setup_Manage_CustomGroup_ExternalDDPaymentInformation(),
    ];
    foreach ($steps as $step) {
      $step->deactivate();
    }
  }

  public function uninstall() {
    $removalSteps = [
      new CRM_Automateddirectdebit_Setup_Manage_CustomGroup_ExternalDDMandateInformation(),
      new CRM_Automateddirectdebit_Setup_Manage_CustomGroup_ExternalDDPaymentInformation(),
    ];
    foreach ($removalSteps as $step) {
      $step->remove();
    }
  }

}
