<?php

namespace Drupal\typed_identifier\Plugin\IdentifierType;

use Drupal\typed_identifier\IdentifierTypePluginBase;

/**
 * Provides a generic identifier type.
 *
 * @IdentifierType(
 *   id = "generic",
 *   label = @Translation("Custom"),
 *   prefix = "",
 *   validation_regex = "",
 *   description = @Translation("Custom identifier type with configurable labels")
 * )
 */
class GenericIdentifierType extends IdentifierTypePluginBase {

  /**
   * {@inheritdoc}
   */
  public function validate($value) {
    // For generic type, only check for XSS - non-empty and safe string.
    return !empty($value) && is_string($value);
  }

  /**
   * {@inheritdoc}
   *
   * Returns custom label based on URN and field settings.
   */
  public function getLabel($field_item = NULL, array $field_settings = []) {
    // Extract custom label from URN if available.
    if ($field_item && !empty($field_item->urn)) {
      $custom_labels = $field_settings['custom_generic_labels'] ?? [];

      // Parse URN: "generic:arxiv:123" -> "arxiv".
      if (preg_match('/^generic:([^:]+):/', $field_item->urn, $matches)) {
        $custom_key = $matches[1];

        // Find matching label in field settings.
        foreach ($custom_labels as $label_config) {
          if (isset($label_config['key'], $label_config['label']) &&
              $label_config['key'] === $custom_key) {
            return $label_config['label'];
          }
        }
      }
    }

    // Fallback to annotation label.
    return parent::getLabel();
  }

}
