<?php

namespace Drupal\typed_identifier\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a widget for typed identifier fields.
 *
 * @FieldWidget(
 *   id = "typed_identifier_widget",
 *   label = @Translation("Typed Identifier"),
 *   field_types = {
 *     "typed_identifier"
 *   }
 * )
 */
class TypedIdentifierWidget extends WidgetBase {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'placeholder_itemtype' => '',
      'placeholder_itemvalue' => '',
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $element = parent::settingsForm($form, $form_state);

    // Note: Only select dropdown is currently supported.
    // Autocomplete may be added in future versions after implementing the
    // autocomplete callback route.
    $element['placeholder_itemtype'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Placeholder for item type field'),
      '#default_value' => $this->getSetting('placeholder_itemtype'),
    ];

    $element['placeholder_itemvalue'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Placeholder for item value field'),
      '#default_value' => $this->getSetting('placeholder_itemvalue'),
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = [];
    $summary[] = $this->t('Identifier Type widget: Select dropdown');
    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $plugin_manager = \Drupal::service('plugin.manager.typed_identifier.identifier_type');
    $definitions = $plugin_manager->getDefinitions();

    // Get allowed types from field settings.
    $allowed_types = $this->fieldDefinition->getSetting('allowed_identifier_types');
    // All keys are present, set with 0 for disabled so filter away empties
    $allowed_types = array_filter($allowed_types);

    // Build options list for select dropdown.
    $options = ['' => $this->t('- Select -')];
    $has_generic = FALSE;

    foreach ($definitions as $id => $definition) {
      // If allowed types is set, only include those types.
      if (!empty($allowed_types) && !in_array($id, $allowed_types)) {
        continue;
      }

      // Track if generic type is included.
      if ($id === 'generic') {
        $has_generic = TRUE;
      }

      $options[$id] = $definition['label'];
    }

    // If generic type is included, expand it with custom labels.
    if ($has_generic) {
      $custom_labels = $this->fieldDefinition->getSetting('custom_generic_labels');

      // Handle case where labels are incorrectly stored as string.
      // Parse it temporarily until the field settings are re-saved.
      if (is_string($custom_labels) && !empty($custom_labels)) {
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

      if (!empty($custom_labels) && is_array($custom_labels)) {
        // Remove the base 'generic' option since we're replacing it with
        // specific custom label options.
        unset($options['generic']);

        // Add each custom label as a separate option.
        foreach ($custom_labels as $custom) {
          if (isset($custom['key']) && isset($custom['label'])) {
            $option_value = 'generic:' . $custom['key'];
            $option_label = $this->t('Custom: @label', ['@label' => $custom['label']]);
            $options[$option_value] = $option_label;
          }
        }
      }
      else {
        // No custom labels configured - hide the generic option entirely
        // to avoid confusing users with an unconfigured "Custom" type.
        unset($options['generic']);
      }
    }

    // Determine default value for itemtype.
    $default_itemtype = $items[$delta]->itemtype ?? '';

    // If the stored itemtype is 'generic', we need to determine which custom
    // label was used by examining the URN field.
    if ($default_itemtype === 'generic' && !empty($items[$delta]->urn)) {
      $urn = $items[$delta]->urn;
      // URN format: 'generic:key:value' or 'generic:value'.
      if (preg_match('/^generic:([^:]+):/', $urn, $matches)) {
        // Reconstruct the compound value for the dropdown.
        $default_itemtype = 'generic:' . $matches[1];
      }
    }

    $element['itemtype'] = [
      '#type' => 'select',
      '#title' => $this->t('Identifier Type'),
      '#options' => $options,
      '#default_value' => $default_itemtype,
      '#required' => $element['#required'],
    ];

    $element['itemvalue'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Identifier Value'),
      '#default_value' => $items[$delta]->itemvalue ?? '',
      '#required' => $element['#required'],
      '#placeholder' => $this->getSetting('placeholder_itemvalue'),
      '#maxlength' => 255,
    ];

    // Add help text showing allowed identifier types.
    if (!empty($options) && count($options) > 1) {
      // Remove the '- Select -' option from the display list.
      $allowed_type_labels = array_slice($options, 1);
      $allowed_types_text = implode(', ', $allowed_type_labels);
      $element['#description'] = $this->t('Allowed Types: @types', [
        '@types' => $allowed_types_text,
      ]);
    }

    return $element;
  }

}
