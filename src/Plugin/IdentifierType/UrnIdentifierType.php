<?php

namespace Drupal\typed_identifier\Plugin\IdentifierType;

use Drupal\typed_identifier\IdentifierTypePluginBase;

/**
 * Provides a URN identifier type.
 *
 * @IdentifierType(
 *   id = "urn",
 *   label = @Translation("URN"),
 *   prefix = "",
 *   validation_regex = "^urn:[a-z0-9][a-z0-9-]{0,31}:[a-z0-9()+,\-.:=@;$_!*'%/?#]+$",
 *   description = @Translation("Uniform Resource Name")
 * )
 */
class UrnIdentifierType extends IdentifierTypePluginBase {

}
