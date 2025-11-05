<?php

namespace Drupal\typed_identifier;

use Drupal\Component\Plugin\PluginInspectionInterface;

/**
 * Defines an interface for IdentifierType plugins.
 */
interface IdentifierTypeInterface extends PluginInspectionInterface {

  /**
   * Returns the human-readable label.
   *
   * @param object|null $field_item
   *   Optional field item for context (contains urn, itemtype, itemvalue).
   * @param array $field_settings
   *   Optional field settings array.
   *
   * @return string
   *   The label.
   */
  public function getLabel($field_item = NULL, array $field_settings = []);

  /**
   * Returns the URL prefix.
   *
   * @return string
   *   The prefix.
   */
  public function getPrefix();

  /**
   * Returns the validation regex pattern.
   *
   * @return string
   *   The regex pattern.
   */
  public function getValidationRegex();

  /**
   * Returns the description.
   *
   * @return string
   *   The description.
   */
  public function getDescription();

  /**
   * Validates an identifier value.
   *
   * @param string $value
   *   The value to validate.
   *
   * @return bool
   *   TRUE if valid, FALSE otherwise.
   */
  public function validate($value);

  /**
   * Builds a full URL for the identifier.
   *
   * @param string $value
   *   The identifier value.
   *
   * @return string
   *   The full URL.
   */
  public function buildUrl($value);

}
