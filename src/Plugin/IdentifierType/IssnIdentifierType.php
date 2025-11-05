<?php

namespace Drupal\typed_identifier\Plugin\IdentifierType;

use Drupal\typed_identifier\IdentifierTypePluginBase;

/**
 * Provides an ISSN identifier type.
 *
 * @IdentifierType(
 *   id = "issn",
 *   label = @Translation("ISSN"),
 *   prefix = "https://portal.issn.org/resource/ISSN/",
 *   validation_regex = "^\d{4}-\d{3}[0-9X]$",
 *   description = @Translation("International Standard Serial Number")
 * )
 */
class IssnIdentifierType extends IdentifierTypePluginBase {

}
