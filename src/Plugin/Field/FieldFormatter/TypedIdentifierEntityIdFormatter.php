<?php

namespace Drupal\typed_identifier\Plugin\Field\FieldFormatter;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Formatter that displays matched entity ID.
 *
 * This formatter finds entities that share matching typed identifiers with the
 * current entity and displays their entity ID.
 *
 * @FieldFormatter(
 *   id = "typed_identifier_entity_id",
 *   label = @Translation("Entity ID (via typed identifier)"),
 *   field_types = {
 *     "typed_identifier"
 *   }
 * )
 */
class TypedIdentifierEntityIdFormatter extends FormatterBase implements ContainerFactoryPluginInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a TypedIdentifierEntityIdFormatter object.
   *
   * @param string $plugin_id
   *   The plugin_id for the formatter.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The definition of the field to which the formatter is associated.
   * @param array $settings
   *   The formatter settings.
   * @param string $label
   *   The formatter label display setting.
   * @param string $view_mode
   *   The view mode.
   * @param array $third_party_settings
   *   Any third party settings.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   */
  public function __construct(
    $plugin_id,
    $plugin_definition,
    $field_definition,
    array $settings,
    $label,
    $view_mode,
    array $third_party_settings,
    EntityTypeManagerInterface $entity_type_manager
  ) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings);
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['label'],
      $configuration['view_mode'],
      $configuration['third_party_settings'],
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'target_bundle' => '',
      'target_field' => '',
      'identifier_type' => '',
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $element = parent::settingsForm($form, $form_state);

    // Get available identifier types.
    $plugin_manager = \Drupal::service('plugin.manager.typed_identifier.identifier_type');
    $definitions = $plugin_manager->getDefinitions();

    $identifier_options = [];
    foreach ($definitions as $id => $definition) {
      $identifier_options[$id] = $definition['label'];
    }

    $element['identifier_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Identifier type'),
      '#description' => $this->t('Optionally filter to match only a specific identifier type. Leave empty to match all types via URN.'),
      '#options' => $identifier_options,
      '#empty_option' => $this->t('- Any type -'),
      '#default_value' => $this->getSetting('identifier_type'),
      '#required' => FALSE,
      '#weight' => -10,
    ];

    // Get bundles for nodes.
    $bundle_options = $this->getBundleOptions('node');

    $element['target_bundle'] = [
      '#type' => 'select',
      '#title' => $this->t('Target bundle'),
      '#description' => $this->t('Select the content type to match entities from.'),
      '#options' => $bundle_options,
      '#default_value' => $this->getSetting('target_bundle'),
      '#required' => TRUE,
      '#weight' => -9,
    ];

    // Get ALL typed_identifier fields across all bundles, organized by bundle.
    $all_field_options = $this->getAllTargetFieldOptions('node');

    $element['target_field'] = [
      '#type' => 'select',
      '#title' => $this->t('Target field'),
      '#description' => $this->t('Select which typed_identifier field on the target entity to match against. Fields are grouped by content type.'),
      '#options' => $all_field_options,
      '#default_value' => $this->getSetting('target_field'),
      '#required' => TRUE,
      '#weight' => -8,
      '#empty_option' => $this->t('- Select -'),
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = [];

    $target_field = $this->getSetting('target_field');
    $target_bundle = $this->getSetting('target_bundle');

    if (!empty($target_bundle) && !empty($target_field)) {
      $summary[] = $this->t('Matching field: @bundle → @field', [
        '@bundle' => $target_bundle,
        '@field' => $target_field,
      ]);
    }
    else {
      $summary[] = $this->t('Not configured');
      return $summary;
    }

    $identifier_type = $this->getSetting('identifier_type');
    if (!empty($identifier_type)) {
      $summary[] = $this->t('Identifier type: @type', [
        '@type' => $identifier_type,
      ]);
    }
    else {
      $summary[] = $this->t('All identifier types');
    }

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];
    $uniqkeys = [];

    // Get configuration.
    $target_bundle = $this->getSetting('target_bundle');
    $target_field = $this->getSetting('target_field');
    $identifier_type = $this->getSetting('identifier_type');

    // Validate configuration.
    if (empty($target_bundle) || empty($target_field)) {
      return $elements;
    }

    // Build target field table name.
    $target_field_table = 'node__' . $target_field;
    $target_urn_field = $target_field . '_urn';
    $target_itemtype_field = $target_field . '_itemtype';

    foreach ($items as $delta => $item) {
      if (empty($item->itemtype) || empty($item->itemvalue)) {
        continue;
      }

      // Apply identifier type filter if specified.
      if (!empty($identifier_type) && $item->itemtype !== $identifier_type) {
        continue;
      }

      // Get the URN from the item.
      $source_urn = $item->urn;
      if (empty($source_urn)) {
        continue;
      }

      // Query for matching entity via URN.
      try {
        $matched_entity = $this->findMatchingEntity(
          $target_field_table,
          $target_urn_field,
          $source_urn,
          $target_bundle,
          $identifier_type ? $target_itemtype_field : NULL,
          $identifier_type
        );

        if ($matched_entity and !array_key_exists($matched_entity->id(), $uniqkeys)) {
          //typed identifier stores multiple pairs, track entity ids to show each only once
          $uniqkeys[$matched_entity->id()] = $matched_entity->id();
          // Display entity ID.
          $elements[$delta] = [
            '#plain_text' => $matched_entity->id(),
            '#cache' => [
              'tags' => [
                'node:' . $matched_entity->id(),
                'field_config:node.' . $target_bundle . '.' . $target_field,
              ],
            ],
          ];
        }
      }
      catch (\Exception $e) {
        // Log the error and skip this item.
        \Drupal::logger('typed_identifier')->error(
          'Error finding matched entity for typed_identifier: @error',
          ['@error' => $e->getMessage()]
        );
      }
    }

    return $elements;
  }

  /**
   * Find a matching entity via URN matching in a target field.
   *
   * @param string $target_field_table
   *   The target field table name (e.g., node__field_profile_typed_ids).
   * @param string $target_urn_field
   *   The target URN field name (e.g., field_profile_typed_ids_urn).
   * @param string $source_urn
   *   The source URN to match against.
   * @param string $target_bundle
   *   The target bundle to filter by.
   * @param string|null $target_itemtype_field
   *   Optional target itemtype field name for filtering.
   * @param string|null $identifier_type
   *   Optional identifier type to filter by.
   *
   * @return \Drupal\node\NodeInterface|null
   *   The matched entity, or NULL if no match found.
   *
   * @throws \Exception
   *   If the database query fails.
   */
  protected function findMatchingEntity(
    $target_field_table,
    $target_urn_field,
    $source_urn,
    $target_bundle,
    $target_itemtype_field = NULL,
    $identifier_type = NULL
  ) {
    $database = \Drupal::database();

    // Query the target field table for matching URN.
    $query = $database->select($target_field_table, 'tft')
      ->fields('tft', ['entity_id'])
      ->condition('tft.' . $target_urn_field, $source_urn)
      ->condition('tft.deleted', 0)
      ->range(0, 1);

    // Add itemtype filter if specified.
    if (!empty($target_itemtype_field) && !empty($identifier_type)) {
      $query->condition('tft.' . $target_itemtype_field, $identifier_type);
    }

    $result = $query->execute()->fetchField();

    if (!$result) {
      return NULL;
    }

    $entity_id = $result;

    // Load the entity and verify bundle.
    $entity = $this->entityTypeManager->getStorage('node')->load($entity_id);

    if (!$entity || $entity->bundle() !== $target_bundle) {
      return NULL;
    }

    return $entity;
  }

  /**
   * Get bundles for nodes.
   *
   * @param string $entity_type_id
   *   The entity type ID (should be 'node').
   *
   * @return array
   *   Array of bundle_id => label.
   */
  protected function getBundleOptions($entity_type_id) {
    if ($entity_type_id !== 'node') {
      return [];
    }

    $bundles = $this->entityTypeManager->getStorage('node_type')->loadMultiple();

    $options = [];
    foreach ($bundles as $bundle) {
      $options[$bundle->id()] = $bundle->label();
    }

    return $options;
  }

  /**
   * Get ALL typed_identifier fields across all bundles for an entity type.
   *
   * Returns fields organized by bundle using optgroups.
   *
   * @param string $entity_type_id
   *   The entity type ID.
   *
   * @return array
   *   Array of field options organized by bundle (optgroups).
   */
  protected function getAllTargetFieldOptions($entity_type_id) {
    if ($entity_type_id !== 'node') {
      return [];
    }

    $entity_field_manager = \Drupal::service('entity_field.manager');
    $bundles = $this->entityTypeManager->getStorage('node_type')->loadMultiple();

    $options = [];
    foreach ($bundles as $bundle_id => $bundle) {
      $field_definitions = $entity_field_manager->getFieldDefinitions($entity_type_id, $bundle_id);
      $bundle_fields = [];

      foreach ($field_definitions as $field_name => $field_def) {
        if ($field_def->getType() === 'typed_identifier') {
          $bundle_fields[$field_name] = $field_def->getLabel();
        }
      }

      // Only add optgroup if this bundle has typed_identifier fields.
      if (!empty($bundle_fields)) {
        $options[$bundle->label()] = $bundle_fields;
      }
    }

    return $options;
  }

}
