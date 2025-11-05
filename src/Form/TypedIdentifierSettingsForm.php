<?php

namespace Drupal\typed_identifier\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configuration form for typed identifier settings.
 */
class TypedIdentifierSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'typed_identifier_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['typed_identifier.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('typed_identifier.settings');
    $plugin_manager = \Drupal::service('plugin.manager.typed_identifier.identifier_type');
    $definitions = $plugin_manager->getDefinitions();

    // Build options list for checkboxes.
    $options = [];
    foreach ($definitions as $id => $definition) {
      $options[$id] = $definition['label'];
    }

    $form['default_display_types'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Default identifier types to display'),
      '#description' => $this->t('Select which identifier types should be displayed by default in formatters. Leave empty to show all types. Individual formatters can override this setting.'),
      '#options' => $options,
      '#default_value' => $config->get('default_display_types') ?: [],
    ];

    $form['generic_custom_labels'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Custom labels for Generic identifier type'),
      '#description' => $this->t('One per line, in the format: key|Label. Example: custom_id_1|My Custom Type'),
      '#default_value' => $this->formatCustomLabelsForDisplay($config->get('generic_custom_labels') ?: []),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Filter out unchecked values from checkboxes.
    $display_types = array_filter($form_state->getValue('default_display_types'));

    $this->config('typed_identifier.settings')
      ->set('default_display_types', array_values($display_types))
      ->set('generic_custom_labels', $this->parseCustomLabels($form_state->getValue('generic_custom_labels')))
      ->save();

    parent::submitForm($form, $form_state);
  }

  /**
   * Formats custom labels array for textarea display.
   *
   * @param array $labels
   *   The custom labels array.
   *
   * @return string
   *   The formatted string.
   */
  protected function formatCustomLabelsForDisplay(array $labels) {
    $lines = [];
    foreach ($labels as $item) {
      if (isset($item['key']) && isset($item['label'])) {
        $lines[] = $item['key'] . '|' . $item['label'];
      }
    }
    return implode("\n", $lines);
  }

  /**
   * Parses custom labels from textarea input.
   *
   * @param string $input
   *   The textarea input.
   *
   * @return array
   *   Array of custom labels.
   */
  protected function parseCustomLabels($input) {
    $labels = [];
    $lines = explode("\n", $input);
    foreach ($lines as $line) {
      $line = trim($line);
      if (empty($line)) {
        continue;
      }
      $parts = explode('|', $line, 2);
      if (count($parts) === 2) {
        $labels[] = [
          'key' => trim($parts[0]),
          'label' => trim($parts[1]),
        ];
      }
    }
    return $labels;
  }

}
