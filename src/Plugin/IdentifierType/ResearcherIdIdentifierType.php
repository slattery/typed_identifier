<?php

namespace Drupal\typed_identifier\Plugin\IdentifierType;

use Drupal\typed_identifier\IdentifierTypePluginBase;

/**
 * Provides a ResearcherID identifier type.
 *
 * @IdentifierType(
 *   id = "researcherid",
 *   label = @Translation("ResearcherID"),
 *   prefix = "https://www.webofscience.com/wos/author/record/",
 *   validation_regex = "^[A-Z]-\d{4}-\d{4}$",
 *   description = @Translation("Web of Science ResearcherID")
 * )
 */
class ResearcherIdIdentifierType extends IdentifierTypePluginBase {

}
