<?php

namespace Drupal\typed_identifier\Plugin\IdentifierType;

use Drupal\typed_identifier\IdentifierTypePluginBase;

/**
 * Provides an ISBN identifier type.
 *
 * @IdentifierType(
 *   id = "isbn",
 *   label = @Translation("ISBN"),
 *   prefix = "https://isbnsearch.org/isbn/",
 *   validation_regex = "^(?:97[89])?\d{9}[\dX]$",
 *   description = @Translation("International Standard Book Number")
 * )
 */
class IsbnIdentifierType extends IdentifierTypePluginBase {

}
