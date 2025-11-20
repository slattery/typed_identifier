<?php

namespace Drupal\typed_identifier\Plugin\IdentifierType;

use Drupal\typed_identifier\IdentifierTypePluginBase;

/**
 * Provides a PubMed ID identifier type.
 *
 * @IdentifierType(
 *   id = "pmid",
 *   label = @Translation("PubMed ID"),
 *   prefix = "https://pubmed.ncbi.nlm.nih.gov/",
 *   validation_regex = "^\d+$",
 *   description = @Translation("PubMed Unique Identifier")
 * )
 */
class PubmedIdentifierType extends IdentifierTypePluginBase {

}
