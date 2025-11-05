<?php

namespace Drupal\typed_identifier\Plugin\IdentifierType;

use Drupal\typed_identifier\IdentifierTypePluginBase;

/**
 * Provides an ORCID identifier type.
 *
 * @IdentifierType(
 *   id = "orcid",
 *   label = @Translation("ORCID"),
 *   prefix = "https://orcid.org/",
 *   validation_regex = "^\d{4}-\d{4}-\d{4}-\d{3}[0-9X]$",
 *   description = @Translation("Open Researcher and Contributor ID")
 * )
 */
class OrcidIdentifierType extends IdentifierTypePluginBase {

}
