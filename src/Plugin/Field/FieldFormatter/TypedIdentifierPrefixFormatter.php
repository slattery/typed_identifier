<?php

namespace Drupal\typed_identifier\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Formatter that displays prefix + itemvalue.
 *
 * @FieldFormatter(
 *   id = "typed_identifier_prefix",
 *   label = @Translation("Prefix"),
 *   field_types = {
 *     "typed_identifier"
 *   }
 * )
 */
class TypedIdentifierPrefixFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'display_as_link' => TRUE,
      'open_in_new_window' => FALSE,
      'filter_by_types' => [],
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $element = parent::settingsForm($form, $form_state);

    $element['display_as_link'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Display as link'),
      '#default_value' => $this->getSetting('display_as_link'),
    ];

    $element['open_in_new_window'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Open link in new window'),
      '#default_value' => $this->getSetting('open_in_new_window'),
      '#states' => [
        'visible' => [
          ':input[name*="display_as_link"]' => ['checked' => TRUE],
        ],
      ],
    ];

    // Get identifier type options.
    $plugin_manager = \Drupal::service('plugin.manager.typed_identifier.identifier_type');
    $definitions = $plugin_manager->getDefinitions();
    $options = [];
    foreach ($definitions as $id => $definition) {
      $options[$id] = $definition['label'];
    }

    $element['filter_by_types'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Filter by identifier types'),
      '#description' => $this->t('Select which identifier types to display. Leave empty to show all types (or use global defaults if configured).'),
      '#options' => $options,
      '#default_value' => $this->getSetting('filter_by_types'),
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = [];
    if ($this->getSetting('display_as_link')) {
      $summary[] = $this->t('Displayed as link');
      if ($this->getSetting('open_in_new_window')) {
        $summary[] = $this->t('Opens in new window');
      }
    }
    else {
      $summary[] = $this->t('Displayed as plain text');
    }

    $filter_types = array_filter($this->getSetting('filter_by_types'));
    if (!empty($filter_types)) {
      $summary[] = $this->t('Filtered to: @types', [
        '@types' => implode(', ', $filter_types),
      ]);
    }
    else {
      $summary[] = $this->t('Showing all types');
    }

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];
    $plugin_manager = \Drupal::service('plugin.manager.typed_identifier.identifier_type');

    // Get filter types: use formatter setting, fallback to global default.
    $filter_types = array_filter($this->getSetting('filter_by_types'));
    if (empty($filter_types)) {
      $config = \Drupal::config('typed_identifier.settings');
      $filter_types = $config->get('default_display_types') ?: [];
    }

    foreach ($items as $delta => $item) {
      if (empty($item->itemtype) || empty($item->itemvalue)) {
        continue;
      }

      // Apply type filtering.
      if (!empty($filter_types) && !in_array($item->itemtype, $filter_types)) {
        continue;
      }

      try {
        $plugin = $plugin_manager->createInstance($item->itemtype);
        $url = $plugin->buildUrl($item->itemvalue);

        if ($this->getSetting('display_as_link') && !empty($plugin->getPrefix())) {
          $options = [
            'attributes' => [
              'rel' => 'nofollow',
            ],
          ];

          if ($this->getSetting('open_in_new_window')) {
            $options['attributes']['target'] = '_blank';
            $options['attributes']['rel'] = 'nofollow noopener';
          }

          $elements[$delta] = [
            '#type' => 'link',
            '#title' => $url,
            '#url' => Url::fromUri($url, $options),
            '#cache' => [
              'tags' => ['config:typed_identifier.settings'],
            ],
          ];
        }
        else {
          $elements[$delta] = [
            '#plain_text' => $url,
            '#cache' => [
              'tags' => ['config:typed_identifier.settings'],
            ],
          ];
        }
      }
      catch (\Exception $e) {
        // Plugin not found, just display the value.
        $elements[$delta] = [
          '#plain_text' => $item->itemvalue,
        ];
      }
    }

    return $elements;
  }

}
