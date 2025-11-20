<?php

namespace Drupal\typed_identifier\Plugin\views\filter;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Plugin\views\filter\FilterPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Filter by typed identifier existence in related entities.
 *
 * Uses an EXISTS subquery to efficiently filter entities that have matching
 * typed identifiers in a configurable target entity/field.
 *
 * @ViewsFilter("typed_identifier_exists")
 */
class TypedIdentifierExists extends FilterPluginBase {

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * Constructs a TypedIdentifierExists object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The entity field manager.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    EntityFieldManagerInterface $entity_field_manager,
    EntityTypeManagerInterface $entity_type_manager,
    Connection $database,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityFieldManager = $entity_field_manager;
    $this->entityTypeManager = $entity_type_manager;
    $this->database = $database;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition,
  ) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_field.manager'),
      $container->get('entity_type.manager'),
      $container->get('database')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();
    $options['target_entity_type'] = ['default' => ''];
    $options['target_bundle'] = ['default' => ''];
    $options['target_field'] = ['default' => ''];
    $options['identifier_type'] = ['default' => ''];
    $options['require_published'] = ['default' => FALSE];
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);

    // Entity type dropdown.
    $entity_type_options = $this->getEntityTypeOptions();

    // If there's only one entity type, auto-select it and populate
    // dependent fields.
    $selected_entity_type = $this->options['target_entity_type'];
    if (empty($selected_entity_type) && count($entity_type_options) === 1) {
      $selected_entity_type = key($entity_type_options);
    }

    $form['target_entity_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Target entity type'),
      '#description' => $this->t('Select the entity type to check for matching identifiers.'),
      '#options' => $entity_type_options,
      '#default_value' => $selected_entity_type,
      '#required' => TRUE,
      '#weight' => -10,
    ];

    // Bundle dropdown.
    // Use the selected entity type (which may have been auto-selected above).
    $bundle_options = $this->getBundleOptions($selected_entity_type);

    // If there's only one bundle, auto-select it.
    $selected_bundle = $this->options['target_bundle'];
    if (empty($selected_bundle) && count($bundle_options) === 1 && !empty($selected_entity_type)) {
      $selected_bundle = key($bundle_options);
    }

    $form['target_bundle'] = [
      '#type' => 'select',
      '#title' => $this->t('Target bundle'),
      '#description' => $this->t('Select the bundle to check for matching identifiers.'),
      '#options' => $bundle_options,
      '#default_value' => $selected_bundle,
      '#required' => TRUE,
      '#weight' => -9,
      '#empty_option' => $this->t('- Select an entity type first -'),
    ];

    // Field dropdown with ALL fields organized by bundle.
    $all_field_options = $this->getAllTargetFieldOptions($selected_entity_type);

    $form['target_field'] = [
      '#type' => 'select',
      '#title' => $this->t('Target field'),
      '#description' => $this->t('Select the typed_identifier field to check for matching identifiers. Fields are grouped by content type.'),
      '#options' => $all_field_options,
      '#default_value' => $this->options['target_field'],
      '#required' => TRUE,
      '#weight' => -8,
      '#empty_option' => $this->t('- Select -'),
    ];

    // Get available identifier types.
    $plugin_manager = \Drupal::service('plugin.manager.typed_identifier.identifier_type');
    $definitions = $plugin_manager->getDefinitions();

    $identifier_options = [];
    foreach ($definitions as $id => $definition) {
      $identifier_options[$id] = $definition['label'];
    }

    $form['identifier_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Identifier type'),
      '#description' => $this->t('Optionally filter to match only a specific identifier type. Leave empty to match all types.'),
      '#options' => $identifier_options,
      '#empty_option' => $this->t('- Any type -'),
      '#default_value' => $this->options['identifier_type'],
      '#required' => FALSE,
      '#weight' => -7,
    ];

    $form['require_published'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Limit to published content only'),
      '#description' => $this->t('If checked, only match identifiers from published nodes (status = 1).'),
      '#default_value' => $this->options['require_published'],
      '#weight' => -6,
    ];
  }

  /**
   * Get all entity types that have typed_identifier fields.
   *
   * Limited to nodes only for simplicity.
   *
   * @return array
   *   Array of entity_type_id => label.
   */
  protected function getEntityTypeOptions() {
    $field_map = $this->entityFieldManager->getFieldMapByFieldType('typed_identifier');

    $entity_type_options = [];
    // Limit to nodes only.
    if (isset($field_map['node'])) {
      $entity_type_def = $this->entityTypeManager->getDefinition('node');
      $entity_type_options['node'] = $entity_type_def->getLabel();
    }

    return $entity_type_options;
  }

  /**
   * Get bundles for a given entity type that have typed_identifier fields.
   *
   * @param string $entity_type_id
   *   The entity type ID.
   *
   * @return array
   *   Array of bundle_id => label.
   */
  protected function getBundleOptions($entity_type_id) {
    if (empty($entity_type_id)) {
      return [];
    }

    $entity_type_def = $this->entityTypeManager->getDefinition($entity_type_id);
    $bundle_entity_type_id = $entity_type_def->getBundleEntityType();

    if (!$bundle_entity_type_id) {
      // Entity type doesn't have bundles (e.g., user).
      return [$entity_type_id => $entity_type_def->getLabel()];
    }

    // Get all bundles for this entity type.
    $bundle_manager = $this->entityTypeManager->getStorage($bundle_entity_type_id);
    $bundles = $bundle_manager->loadMultiple();

    $bundle_options = [];
    foreach ($bundles as $bundle) {
      $bundle_options[$bundle->id()] = $bundle->label();
    }

    return $bundle_options;
  }

  /**
   * Get typed_identifier fields for a given entity type and bundle.
   *
   * @param string $entity_type_id
   *   The entity type ID.
   * @param string $bundle
   *   The bundle ID.
   *
   * @return array
   *   Array of field_name => label.
   */
  protected function getTargetFieldOptions($entity_type_id, $bundle) {
    if (empty($entity_type_id) || empty($bundle)) {
      return [];
    }

    $field_definitions = $this->entityFieldManager->getFieldDefinitions(
      $entity_type_id,
      $bundle
    );

    $options = [];
    foreach ($field_definitions as $field_name => $field_def) {
      if ($field_def->getType() === 'typed_identifier') {
        $options[$field_name] = $field_def->getLabel();
      }
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
    if (empty($entity_type_id)) {
      return [];
    }

    $entity_type_def = $this->entityTypeManager->getDefinition($entity_type_id);
    $bundle_entity_type_id = $entity_type_def->getBundleEntityType();

    if (!$bundle_entity_type_id) {
      // Entity type doesn't have bundles (e.g., user).
      // Return flat list of fields.
      return $this->getTargetFieldOptions($entity_type_id, $entity_type_id);
    }

    // Get all bundles for this entity type.
    $bundle_manager = $this->entityTypeManager->getStorage($bundle_entity_type_id);
    $bundles = $bundle_manager->loadMultiple();

    $options = [];
    foreach ($bundles as $bundle_id => $bundle) {
      $field_definitions = $this->entityFieldManager->getFieldDefinitions(
        $entity_type_id,
        $bundle_id
      );
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

  /**
   * {@inheritdoc}
   */
  public function query() {
    $this->ensureMyTable();

    // Get configuration.
    $target_entity_type = $this->options['target_entity_type'];
    $target_bundle = $this->options['target_bundle'];
    $target_field = $this->options['target_field'];
    $identifier_type = $this->options['identifier_type'];
    $require_published = $this->options['require_published'];

    // Validate configuration. Only entity type is 'node' for now.
    if ($target_entity_type !== 'node' || empty($target_field)) {
      return;
    }

    // Get the source field name from the View's field configuration.
    // Extract base field name (without _urn_exists suffix).
    $source_field_base = preg_replace('/_exists$/', '', $this->realField);

    // Get the source table alias.
    $source_table = $this->tableAlias;

    // Build target field table name.
    $target_field_table = 'node__' . $target_field;
    $target_urn_column = $target_field . '_urn';
    $target_itemtype_column = $target_field . '_itemtype';

    // Build EXISTS subquery using raw SQL for precise control.
    // Pattern: WHERE EXISTS (SELECT 1 FROM t2 INNER JOIN nfd WHERE conditions)
    $exists_sql = "EXISTS (
      SELECT 1
      FROM {" . $target_field_table . "} t2";

    // Add JOIN to node_field_data if published status checking is required.
    if ($require_published) {
      $exists_sql .= "
      INNER JOIN {node_field_data} nfd ON t2.entity_id = nfd.nid";
    }

    $exists_sql .= "
      WHERE t2." . $target_urn_column . " = " . $source_table . "." . $source_field_base;

    // Array to hold query arguments for parameter binding.
    $args = [];

    // Add bundle condition.
    if (!empty($target_bundle)) {
      if ($require_published) {
        $exists_sql .= " AND nfd.type = :target_bundle";
      }
      else {
        // Need to join to node_field_data just for bundle check.
        $exists_sql = "EXISTS (
          SELECT 1
          FROM {" . $target_field_table . "} t2
          INNER JOIN {node_field_data} nfd ON t2.entity_id = nfd.nid
          WHERE t2." . $target_urn_column . " = " . $source_table . "." . $source_field_base . "
            AND nfd.type = :target_bundle";
      }
      $args[':target_bundle'] = $target_bundle;
    }

    // Add itemtype condition if specified.
    if (!empty($identifier_type)) {
      $exists_sql .= " AND t2." . $target_itemtype_column . " = :identifier_type";
      $args[':identifier_type'] = $identifier_type;
    }

    // Add published status condition.
    if ($require_published) {
      $exists_sql .= " AND nfd.status = 1";
    }

    $exists_sql .= ")";

    // Add the EXISTS expression to the main query.
    $this->query->addWhereExpression($this->options['group'], $exists_sql, $args);
  }

}
