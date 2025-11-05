<?php

namespace Drupal\typed_identifier\Plugin\IdentifierType;

use Drupal\typed_identifier\IdentifierTypePluginBase;

/**
 * Provides a UPI identifier type.
 *
 * @IdentifierType(
 *   id = "upi",
 *   label = @Translation("UPI"),
 *   prefix = "",
 *   validation_regex = "^\d+$",
 *   description = @Translation("UPI Number")
 * )
 */
class UpiIdentifierType extends IdentifierTypePluginBase {

}
