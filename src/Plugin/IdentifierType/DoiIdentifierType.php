<?php

namespace Drupal\typed_identifier\Plugin\IdentifierType;

use Drupal\typed_identifier\IdentifierTypePluginBase;

/**
 * Provides a DOI identifier type.
 *
 * @IdentifierType(
 *   id = "doi",
 *   label = @Translation("DOI"),
 *   prefix = "https://doi.org/",
 *   validation_regex = "^10\.\d{4,}\/[^\s]+$",
 *   description = @Translation("Digital Object Identifier")
 * )
 */
class DoiIdentifierType extends IdentifierTypePluginBase {

}
