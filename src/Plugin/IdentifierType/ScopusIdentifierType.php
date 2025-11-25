<?php

namespace Drupal\typed_identifier\Plugin\IdentifierType;

use Drupal\typed_identifier\IdentifierTypePluginBase;

/**
 * Provides a Scopus Author ID identifier type.
 *
 * @IdentifierType(
 *   id = "scopus",
 *   label = @Translation("Scopus"),
 *   prefix = "https://www.scopus.com/authid/detail.uri?authorId=",
 *   validation_regex = "^\d+$",
 *   description = @Translation("Scopus Author Identifier")
 * )
 */
class ScopusIdentifierType extends IdentifierTypePluginBase {

}
