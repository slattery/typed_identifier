<?php

namespace Drupal\typed_identifier\Plugin\IdentifierType;

use Drupal\typed_identifier\IdentifierTypePluginBase;

/**
 * Provides a NetID identifier type.
 *
 * @IdentifierType(
 *   id = "netid",
 *   label = @Translation("NetID"),
 *   prefix = "",
 *   validation_regex = "^[a-zA-Z0-9_]*$",
 *   description = @Translation("NetID")
 * )
 */
class NetidIdentifierType extends IdentifierTypePluginBase {

}
