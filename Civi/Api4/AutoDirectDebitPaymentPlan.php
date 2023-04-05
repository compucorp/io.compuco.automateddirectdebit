<?php

namespace Civi\Api4;

/**
 *
 * @package Civi\Api4
 */
class AutoDirectDebitPaymentPlan extends Generic\AbstractEntity {

  /**
   * @inheritDoc
   */
  public static function getFields() {
    return (new Generic\BasicGetFieldsAction(__CLASS__, __FUNCTION__, function($getFieldsAction) {
      return [];
    }));
  }

}
