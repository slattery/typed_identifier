<?php

namespace Drupal\typed_identifier;

use Drupal\Component\Plugin\PluginBase;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Base class for IdentifierType plugins.
 */
abstract class IdentifierTypePluginBase extends PluginBase implements IdentifierTypeInterface {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function getLabel($field_item = NULL, array $field_settings = []) {
    return (string) $this->pluginDefinition['label'];
  }

  /**
   * {@inheritdoc}
   */
  public function getPrefix() {
    return $this->pluginDefinition['prefix'] ?? '';
  }

  /**
   * {@inheritdoc}
   */
  public function getValidationRegex() {
    return $this->pluginDefinition['validation_regex'] ?? '';
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return (string) ($this->pluginDefinition['description'] ?? '');
  }

  /**
   * {@inheritdoc}
   */
  public function validate($value) {
    $regex = $this->getValidationRegex();

    // If no regex, only check for XSS (non-empty and safe string).
    if (empty($regex)) {
      return !empty($value) && is_string($value);
    }

    // Validate against regex.
    return (bool) preg_match('/' . $regex . '/', $value);
  }

  /**
   * {@inheritdoc}
   */
  public function buildUrl($value) {
    $prefix = $this->getPrefix();
    if (empty($prefix)) {
      return $value;
    }
    return $prefix . $value;
  }

}
