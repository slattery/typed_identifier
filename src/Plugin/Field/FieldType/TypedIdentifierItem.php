<?php

namespace Drupal\typed_identifier\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\TypedData\DataDefinition;

/**
 * Provides a field type for typed identifiers.
 *
 * @FieldType(
 *   id = "typed_identifier",
 *   label = @Translation("Typed Identifier"),
 *   description = @Translation("Store typed identifier pairs with validation."),
 *   default_widget = "typed_identifier_widget",
 *   default_formatter = "typed_identifier_prefix"
 * )
 */
class TypedIdentifierItem extends FieldItemBase {

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition) {
    return [
      'columns' => [
        'itemtype' => [
          'type' => 'varchar',
          'length' => 255,
          'not null' => TRUE,
          'default' => '',
          'description' => 'The identifier type (e.g., orcid, doi)',
        ],
        'itemvalue' => [
          'type' => 'varchar',
          'length' => 255,
          'not null' => TRUE,
          'default' => '',
          'description' => 'The identifier value',
        ],
        'urn' => [
          'type' => 'varchar',
          'length' => 512,
          'not null' => TRUE,
          'default' => '',
          'description' => 'Lowercase composite URN: type:value or type:label:value',
        ],
      ],
      'indexes' => [
        'itemtype' => ['itemtype'],
        'itemvalue' => ['itemvalue'],
        'itemtype_itemvalue' => ['itemtype', 'itemvalue'],
        'urn' => ['urn'],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    $properties['itemtype'] = DataDefinition::create('string')
      ->setLabel(t('Item Type'))
      ->setRequired(TRUE);

    $properties['itemvalue'] = DataDefinition::create('string')
      ->setLabel(t('Item Value'))
      ->setRequired(TRUE);

    $properties['urn'] = DataDefinition::create('string')
      ->setLabel(t('URN'))
      ->setDescription(t('Lowercase composite identifier'))
      ->setComputed(TRUE)
      ->setReadOnly(TRUE);

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty() {
    $itemtype = $this->get('itemtype')->getValue();
    $itemvalue = $this->get('itemvalue')->getValue();
    // Both must be empty for field to be considered empty.
    return empty($itemtype) && empty($itemvalue);
  }

  /**
   * {@inheritdoc}
   */
  public function getConstraints() {
    $constraints = parent::getConstraints();

    // Require itemvalue when itemtype is set.
    $constraint_manager = \Drupal::typedDataManager()
      ->getValidationConstraintManager();
    $constraints[] = $constraint_manager->create('TypedIdentifierValidation', []);

    return $constraints;
  }

  /**
   * {@inheritdoc}
   */
  public function preSave() {
    parent::preSave();

    $itemtype = $this->get('itemtype')->getValue();
    $itemvalue = $this->get('itemvalue')->getValue();

    // Both itemtype and itemvalue are required.
    if (empty($itemtype) || empty($itemvalue)) {
      return;
    }

    // Parse compound itemtype values for generic type (format: 'generic:key').
    $custom_label_key = NULL;
    if (str_contains($itemtype, ':')) {
      $parts = explode(':', $itemtype, 2);
      if ($parts[0] === 'generic' && !empty($parts[1])) {
        // Extract the custom label key and use base type for plugin lookup.
        $custom_label_key = $parts[1];
        $itemtype = 'generic';
        // Update the stored itemtype to base type only.
        $this->set('itemtype', $itemtype);
      }
    }

    // Get plugin for this identifier type.
    $plugin_manager = \Drupal::service('plugin.manager.typed_identifier.identifier_type');
    if (!$plugin_manager->hasDefinition($itemtype)) {
      return;
    }

    $plugin = $plugin_manager->createInstance($itemtype);
    $plugin_id = strtolower($itemtype);
    $prefix = $plugin->getPrefix();

    // Normalize itemvalue by detecting and stripping known formats.
    // This handles four input formats:
    // 1. URN format: "doi:10.1234/example" or "DOI:10.1234/example".
    // 2. URL format (HTTPS): "https://doi.org/10.1234/example".
    // 3. URL format (HTTP): "http://doi.org/10.1234/example".
    // 4. Bare ID: "10.1234/example".
    // Check 1: URN format (plugin_id:value) - case insensitive.
    if (str_starts_with(strtolower($itemvalue), $plugin_id . ':')) {
      $itemvalue = substr($itemvalue, strlen($plugin_id) + 1);
      $this->set('itemvalue', $itemvalue);
    }
    // Check 2: Exact prefix match (HTTPS).
    elseif (!empty($prefix) && str_starts_with($itemvalue, $prefix)) {
      $itemvalue = substr($itemvalue, strlen($prefix));
      $this->set('itemvalue', $itemvalue);
    }
    // Check 3: HTTP variant of prefix.
    elseif (!empty($prefix) && str_contains($prefix, 'https://')) {
      $http_prefix = str_replace('https://', 'http://', $prefix);
      if (str_starts_with($itemvalue, $http_prefix)) {
        $itemvalue = substr($itemvalue, strlen($http_prefix));
        $this->set('itemvalue', $itemvalue);
      }
    }
    // else: Bare ID format - keep as-is and validate below.
    // Validate normalized value if regex exists.
    if (!empty($plugin->getValidationRegex())) {
      if (!$plugin->validate($itemvalue)) {
        // Invalid value - validation constraint will catch this.
        // Don't generate URN for invalid values.
        return;
      }
    }

    // Generate clean URN from normalized itemtype + itemvalue.
    // IMPORTANT: Store URN in lowercase for case-insensitive matching.
    // Use plugin ID (not label) for stability and consistency.
    if ($itemtype === 'generic') {
      // Handle generic type with custom labels.
      // Use the parsed custom label key if available, otherwise fall back to
      // the first configured label.
      $label_to_use = $custom_label_key ?? $this->getCustomGenericLabel();

      if ($label_to_use) {
        // Sanitize custom label: replace non-alphanumeric with hyphens.
        $sanitized_label = preg_replace('/[^a-z0-9]+/i', '-', $label_to_use);
        $sanitized_label = trim($sanitized_label, '-');
        $urn = strtolower("generic:{$sanitized_label}:{$itemvalue}");
      }
      else {
        $urn = strtolower("generic:{$itemvalue}");
      }
    }
    else {
      // Use lowercase plugin ID for URN consistency.
      $urn = $plugin_id . ':' . strtolower($itemvalue);
    }

    $this->set('urn', $urn);
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultFieldSettings() {
    return [
      'uniqueness_scope' => 'entity',
      'allowed_identifier_types' => [],
      'custom_generic_labels' => [],
    ] + parent::defaultFieldSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function fieldSettingsForm(array $form, FormStateInterface $form_state) {
    $element = parent::fieldSettingsForm($form, $form_state);

    $element['uniqueness_scope'] = [
      '#type' => 'radios',
      '#title' => $this->t('Uniqueness scope'),
      '#description' => $this->t('Define where itemtype:itemvalue pairs must be unique. Use widget settings to control UX behavior (e.g., disable already-used types).'),
      '#options' => [
        'none' => $this->t('None - allow duplicates'),
        'entity' => $this->t('Per-entity - prevent duplicates within the same entity'),
        'bundle' => $this->t('Bundle - prevent duplicates across all entities in this bundle'),
      ],
      '#default_value' => $this->getSetting('uniqueness_scope'),
    ];

    $plugin_manager = \Drupal::service('plugin.manager.typed_identifier.identifier_type');
    $definitions = $plugin_manager->getDefinitions();

    $options = [];
    foreach ($definitions as $id => $definition) {
      $options[$id] = $definition['label'];
    }

    $element['allowed_identifier_types'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Allowed identifier types'),
      '#options' => $options,
      '#default_value' => $this->getSetting('allowed_identifier_types'),
      '#description' => $this->t('Leave empty to allow all types.'),
    ];

    $element['custom_generic_labels'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Custom labels for Generic identifier type'),
      '#description' => $this->t('One per line, in the format: key|Label. Example: custom_id_1|My Custom Type'),
      '#default_value' => $this->formatCustomLabelsForDisplay($this->getSetting('custom_generic_labels')),
      '#element_validate' => [[static::class, 'validateCustomGenericLabels']],
    ];

    return $element;
  }

  /**
   * Element validation callback for custom_generic_labels textarea.
   *
   * Parses the textarea string into proper array format for storage.
   *
   * @param array $element
   *   The form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param array $form
   *   The complete form.
   */
  public static function validateCustomGenericLabels(array $element, FormStateInterface $form_state, array $form) {
    $value = $element['#value'];

    if (empty($value)) {
      // Set empty array if nothing provided.
      $form_state->setValueForElement($element, []);
      return;
    }

    // Parse the textarea string into array format.
    $custom_labels = [];
    $lines = preg_split('/\r\n|\r|\n/', trim($value));

    foreach ($lines as $line) {
      $line = trim($line);
      if (empty($line)) {
        continue;
      }

      // Split by pipe character.
      $parts = explode('|', $line, 2);
      if (count($parts) === 2) {
        $key = trim($parts[0]);
        $label = trim($parts[1]);

        if (!empty($key) && !empty($label)) {
          $custom_labels[] = [
            'key' => $key,
            'label' => $label,
          ];
        }
      }
    }

    // Set the parsed array back to form state.
    $form_state->setValueForElement($element, $custom_labels);
  }

  /**
   * Formats custom labels array for textarea display.
   *
   * @param array|string $labels
   *   The custom labels array or string.
   *
   * @return string
   *   The formatted string.
   */
  protected function formatCustomLabelsForDisplay(array|string $labels) {
    // If already a string (e.g., incorrectly saved), return as-is.
    if (is_string($labels)) {
      return $labels;
    }

    // Convert array format to textarea format.
    $lines = [];
    foreach ($labels as $item) {
      if (isset($item['key']) && isset($item['label'])) {
        $lines[] = $item['key'] . '|' . $item['label'];
      }
    }
    return implode("\n", $lines);
  }

  /**
   * Gets the custom label key for generic identifier type.
   *
   * @param string|null $lookup_key
   *   Optional key to look up. If provided, returns the key if it exists in
   *   custom_generic_labels. If not provided, returns the first label's key.
   *
   * @return string|null
   *   The custom label key, or NULL if none is configured.
   */
  protected function getCustomGenericLabel($lookup_key = NULL) {
    $field_settings = $this->getFieldDefinition()->getSettings();
    $custom_labels = $field_settings['custom_generic_labels'] ?? [];

    if (empty($custom_labels)) {
      return NULL;
    }

    // Handle case where labels are incorrectly stored as string.
    // Parse it temporarily until the field settings are re-saved.
    if (is_string($custom_labels)) {
      $parsed_labels = [];
      $lines = preg_split('/\r\n|\r|\n/', trim($custom_labels));
      foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line)) {
          continue;
        }
        $parts = explode('|', $line, 2);
        if (count($parts) === 2) {
          $parsed_labels[] = [
            'key' => trim($parts[0]),
            'label' => trim($parts[1]),
          ];
        }
      }
      $custom_labels = $parsed_labels;
    }

    if (!is_array($custom_labels) || empty($custom_labels)) {
      return NULL;
    }

    // If a specific key is requested, look it up.
    if ($lookup_key !== NULL) {
      foreach ($custom_labels as $custom) {
        if (isset($custom['key']) && $custom['key'] === $lookup_key) {
          return $custom['key'];
        }
      }
      // Key not found.
      return NULL;
    }

    // Otherwise, return the first custom label's key for backward
    // compatibility.
    $first = reset($custom_labels);
    return $first['key'] ?? NULL;
  }

}
