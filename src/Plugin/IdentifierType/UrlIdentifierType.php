<?php

namespace Drupal\typed_identifier\Plugin\IdentifierType;

use Drupal\typed_identifier\IdentifierTypePluginBase;

/**
 * Provides a Web URL identifier type.
 *
 * @IdentifierType(
 *   id = "url",
 *   label = @Translation("URL"),
 *   prefix = "",
 *   validation_regex = "^https?:\/\/[^\s]*$",
 *   description = @Translation("Full web URL (no ftp etc., no prefix)")
 * )
 */
class UrlIdentifierType extends IdentifierTypePluginBase {

}
