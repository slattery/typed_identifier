<?php

namespace Drupal\typed_identifier\Plugin\IdentifierType;

use Drupal\typed_identifier\IdentifierTypePluginBase;

/**
 * Provides an OpenAlex ID identifier type.
 *
 * @IdentifierType(
 *   id = "openalex",
 *   label = @Translation("OpenAlex ID"),
 *   prefix = "https://openalex.org/",
 *   validation_regex = "^[WAICVPFS]\d{2,10}$",
 *   description = @Translation("OpenAlex Identifier")
 * )
 */
class OpenAlexIdentifierType extends IdentifierTypePluginBase {

}
