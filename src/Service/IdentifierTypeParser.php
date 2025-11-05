<?php

namespace Drupal\typed_identifier\Service;

use Drupal\typed_identifier\IdentifierTypePluginManager;

/**
 * Service to parse strings and identify their identifier type.
 *
 * Parses a string to detect which identifier type it matches, handling
 * multiple input formats (URN, URL, bare ID) and returning the identified
 * itemtype and normalized itemvalue.
 */
class IdentifierTypeParser {

  /**
   * The identifier type plugin manager.
   *
   * @var \Drupal\typed_identifier\IdentifierTypePluginManager
   */
  protected $pluginManager;

  /**
   * Constructs the IdentifierTypeParser.
   *
   * @param \Drupal\typed_identifier\IdentifierTypePluginManager $plugin_manager
   *   The identifier type plugin manager.
   */
  public function __construct(IdentifierTypePluginManager $plugin_manager) {
    $this->pluginManager = $plugin_manager;
  }

  /**
   * Parse a string to identify its type and value.
   *
   * Accepts multiple input formats and returns the identified itemtype
   * and normalized itemvalue. Returns NULL if no match found.
   *
   * Supported formats:
   * - URN: "openalex:W00000000" or "openalex:W00000000"
   * - URL (HTTPS): "https://openalex.org/W00000000"
   * - URL (HTTP): "http://openalex.org/W00000000"
   * - Prefixed key: "id:https://openalex.org/W00000000"
   * - Bare ID: "W00000000" (limited, requires regex match to identify type)
   *
   * @param string $input
   *   The input string to parse.
   *
   * @return array|null
   *   An array with 'itemtype' and 'itemvalue' keys if a match is found,
   *   or NULL if no match is found.
   */
  public function parse($input) {
    if (empty($input) || !is_string($input)) {
      return NULL;
    }

    // Strip "key:" prefix if present (e.g., "id:https://openalex.org/W...")
    $value = $input;
    if (preg_match('/^[a-zA-Z0-9_]+:(.+)$/', $input, $matches)) {
      $value = $matches[1];
    }

    // Attempt to match by URN format first (highest confidence).
    $result = $this->matchByUrn($value);
    if ($result) {
      return $result;
    }

    // Attempt to match by URL prefix (high confidence).
    $result = $this->matchByPrefix($value);
    if ($result) {
      return $result;
    }

    // Attempt to match by regex validation (lower confidence).
    $result = $this->matchByRegex($value);
    if ($result) {
      return $result;
    }

    return NULL;
  }

  /**
   * Match by URN format (plugin_id:value).
   *
   * @param string $value
   *   The value to match.
   *
   * @return array|null
   *   An array with 'itemtype' and 'itemvalue' keys if matched.
   */
  protected function matchByUrn($value) {
    // Check for "plugin_id:value" format (case insensitive for plugin_id).
    if (!str_contains($value, ':')) {
      return NULL;
    }

    $parts = explode(':', $value, 2);
    $potential_type = strtolower($parts[0]);
    $potential_value = $parts[1] ?? '';

    if (empty($potential_value)) {
      return NULL;
    }

    // Check if this plugin exists (and is not generic).
    if ($this->pluginManager->hasDefinition($potential_type) && $potential_type !== 'generic') {
      return [
        'itemtype' => $potential_type,
        'itemvalue' => $potential_value,
      ];
    }

    return NULL;
  }

  /**
   * Match by URL prefix.
   *
   * Checks if the value starts with any plugin's prefix URL.
   *
   * @param string $value
   *   The value to match.
   *
   * @return array|null
   *   An array with 'itemtype' and 'itemvalue' keys if matched.
   */
  protected function matchByPrefix($value) {
    $definitions = $this->pluginManager->getDefinitions();

    foreach ($definitions as $plugin_id => $definition) {
      // Skip generic type.
      if ($plugin_id === 'generic') {
        continue;
      }

      // Get the plugin to access its prefix.
      $plugin = $this->pluginManager->createInstance($plugin_id);
      $prefix = $plugin->getPrefix();

      if (empty($prefix)) {
        continue;
      }

      // Check exact prefix match (HTTPS).
      if (str_starts_with($value, $prefix)) {
        $itemvalue = substr($value, strlen($prefix));
        return [
          'itemtype' => $plugin_id,
          'itemvalue' => $itemvalue,
        ];
      }

      // Check HTTP variant of prefix.
      if (str_contains($prefix, 'https://')) {
        $http_prefix = str_replace('https://', 'http://', $prefix);
        if (str_starts_with($value, $http_prefix)) {
          $itemvalue = substr($value, strlen($http_prefix));
          return [
            'itemtype' => $plugin_id,
            'itemvalue' => $itemvalue,
          ];
        }
      }
    }

    return NULL;
  }

  /**
   * Match by validation regex.
   *
   * Tries regex validation against each plugin to identify the type.
   * This is lower confidence than URN or prefix matching as it may match
   * multiple types.
   *
   * @param string $value
   *   The value to match.
   *
   * @return array|null
   *   An array with 'itemtype' and 'itemvalue' keys if matched.
   */
  protected function matchByRegex($value) {
    $definitions = $this->pluginManager->getDefinitions();

    foreach ($definitions as $plugin_id => $definition) {
      // Skip generic type.
      if ($plugin_id === 'generic') {
        continue;
      }

      $plugin = $this->pluginManager->createInstance($plugin_id);
      $regex = $plugin->getValidationRegex();

      // Skip plugins without regex validation.
      if (empty($regex)) {
        continue;
      }

      // Test if value matches regex.
      if (preg_match('/' . $regex . '/', $value)) {
        return [
          'itemtype' => $plugin_id,
          'itemvalue' => $value,
        ];
      }
    }

    return NULL;
  }

}
