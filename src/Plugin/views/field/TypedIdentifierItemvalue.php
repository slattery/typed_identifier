<?php

namespace Drupal\typed_identifier\Plugin\views\field;

use Drupal\Core\Url;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;

/**
 * Field handler for typed identifier itemvalue.
 *
 * @ViewsField("typed_identifier_itemvalue")
 */
class TypedIdentifierItemvalue extends FieldPluginBase {

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();
    $options['display_format'] = ['default' => 'raw'];
    $options['display_as_link'] = ['default' => FALSE];
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
        'raw' => $this->t('Raw value'),
        'prefix' => $this->t('Formatted with prefix'),
        'link' => $this->t('Formatted as link'),
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

    // Get the itemtype from the same row.
    $field_name = $this->definition['field_name'];
    $itemtype_key = $field_name . '_itemtype';
    $itemtype = NULL;

    // Try to get itemtype from the row.
    if (isset($values->$itemtype_key)) {
      $itemtype = $values->$itemtype_key;
    }

    // If we don't have an itemtype, just return raw value.
    if (empty($itemtype)) {
      return $value;
    }

    // Apply type filtering.
    $filter_types = array_filter($this->options['filter_types']);
    if (!empty($filter_types) && !in_array($itemtype, $filter_types)) {
      return '';
    }

    $plugin_manager = \Drupal::service('plugin.manager.typed_identifier.identifier_type');

    try {
      $plugin = $plugin_manager->createInstance($itemtype);

      // Format based on display option.
      switch ($this->options['display_format']) {
        case 'prefix':
          return $plugin->buildUrl($value);

        case 'link':
          $url = $plugin->buildUrl($value);
          if (!empty($plugin->getPrefix())) {
            return [
              '#type' => 'link',
              '#title' => $value,
              '#url' => Url::fromUri($url),
              '#attributes' => ['rel' => 'nofollow'],
            ];
          }
          return $value;

        default:
          return $value;
      }
    }
    catch (\Exception $e) {
      // Plugin not found, return raw value.
      return $value;
    }
  }

}
