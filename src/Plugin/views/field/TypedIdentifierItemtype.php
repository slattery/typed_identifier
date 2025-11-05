<?php

namespace Drupal\typed_identifier\Plugin\views\field;

use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;

/**
 * Field handler for typed identifier itemtype.
 *
 * @ViewsField("typed_identifier_itemtype")
 */
class TypedIdentifierItemtype extends FieldPluginBase {

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();
    $options['display_format'] = ['default' => 'label'];
    $options['filter_types'] = ['default' => []];
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, \Drupal\Core\Form\FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);

    $form['display_format'] = [
      '#type' => 'select',
      '#title' => $this->t('Display format'),
      '#options' => [
        'id' => $this->t('Raw plugin ID'),
        'label' => $this->t('Formatted label'),
      ],
      '#default_value' => $this->options['display_format'],
    ];

    // Get identifier type options for filtering.
    $plugin_manager = \Drupal::service('plugin.manager.typed_identifier.identifier_type');
    $definitions = $plugin_manager->getDefinitions();
    $type_options = [];
    foreach ($definitions as $id => $definition) {
      $type_options[$id] = $definition['label'];
    }

    $form['filter_types'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Filter by identifier types'),
      '#description' => $this->t('Select which identifier types to display. Leave empty to show all types. <strong>Note:</strong> This filters which VALUES are shown within the field, not which ROWS appear in the view.'),
      '#options' => $type_options,
      '#default_value' => $this->options['filter_types'],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    $value = $this->getValue($values);

    if (empty($value)) {
      return '';
    }

    // Apply type filtering.
    $filter_types = array_filter($this->options['filter_types']);
    if (!empty($filter_types) && !in_array($value, $filter_types)) {
      return '';
    }

    // If displaying as label, get the plugin label.
    if ($this->options['display_format'] === 'label') {
      $plugin_manager = \Drupal::service('plugin.manager.typed_identifier.identifier_type');
      try {
        $plugin = $plugin_manager->createInstance($value);
        return $plugin->getLabel();
      }
      catch (\Exception $e) {
        // Plugin not found, return raw value.
        return $value;
      }
    }

    // Return raw plugin ID.
    return $value;
  }

}
