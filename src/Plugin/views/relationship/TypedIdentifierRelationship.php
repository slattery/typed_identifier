<?php

namespace Drupal\typed_identifier\Plugin\views\relationship;

use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Plugin\views\display\DisplayPluginBase;
use Drupal\views\Plugin\views\relationship\RelationshipPluginBase;
use Drupal\views\ViewExecutable;

/**
 * Relationship handler for typed identifiers.
 *
 * @ViewsRelationship("typed_identifier_relationship")
 */
class TypedIdentifierRelationship extends RelationshipPluginBase {

  /**
   * {@inheritdoc}
   */
  public function init(ViewExecutable $view, DisplayPluginBase $display, ?array &$options = NULL) {
    parent::init($view, $display, $options);

    // Store the source field table name.
    // This is $this->table which is the field table (e.g., node__field_authors_typed_ids).
    // We need this later in query() to build the correct alias.
    $this->definition['source_field_table'] = $this->table;
    $this->definition['source_field'] = $this->definition['field_name'] ?? $this->definition['base field'];

    // Dynamically set the 'base' and 'entity_type' in the definition
    // based on the configured target entity type.
    // This tells Views which entity's fields to expose in the UI.
    if (!empty($this->options['target_entity_type'])) {
      $entity_type_manager = \Drupal::entityTypeManager();
      try {
        $target_entity_definition = $entity_type_manager->getDefinition($this->options['target_entity_type']);
        $this->definition['base'] = $target_entity_definition->getDataTable() ?: $target_entity_definition->getBaseTable();
        $this->definition['entity_type'] = $this->options['target_entity_type'];
        $this->definition['base field'] = $target_entity_definition->getKey('id');
      }
      catch (\Exception $e) {
        // Entity type doesn't exist, leave definition as-is.
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();
    $options['identifier_type'] = ['default' => ''];
    $options['target_entity_type'] = ['default' => 'node'];
    $options['target_bundle'] = ['default' => ''];
    $options['target_field'] = ['default' => ''];
    $options['require_published'] = ['default' => FALSE];
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);

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
      '#description' => $this->t('Optionally filter to match only a specific identifier type. Leave empty to match all types via URN.'),
      '#options' => $identifier_options,
      '#empty_option' => $this->t('- Any type -'),
      '#default_value' => $this->options['identifier_type'],
      '#required' => FALSE,
      '#weight' => -10,
    ];

    // Limit to nodes only for simplicity.
    $form['target_entity_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Target entity type'),
      '#description' => $this->t('Select the entity type to relate to.'),
      '#options' => ['node' => $this->t('Content')],
      '#default_value' => 'node',
      '#required' => TRUE,
      '#weight' => -9,
    ];

    // Get bundles for nodes.
    $bundle_options = $this->getBundleOptions('node');

    $form['target_bundle'] = [
      '#type' => 'select',
      '#title' => $this->t('Target bundle'),
      '#description' => $this->t('Select the content type to relate to.'),
      '#options' => $bundle_options,
      '#default_value' => $this->options['target_bundle'],
      '#required' => TRUE,
      '#weight' => -8,
    ];

    // Get ALL typed_identifier fields across all bundles, organized by bundle.
    $all_field_options = $this->getAllTargetFieldOptions('node');

    $form['target_field'] = [
      '#type' => 'select',
      '#title' => $this->t('Target field'),
      '#description' => $this->t('Select which typed_identifier field on the target entity to join to. Fields are grouped by content type.'),
      '#options' => $all_field_options,
      '#default_value' => $this->options['target_field'],
      '#required' => TRUE,
      '#weight' => -7,
      '#empty_option' => $this->t('- Select -'),
    ];

    $form['require_published'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Require published content'),
      '#description' => $this->t('If checked, only join to published nodes (status = 1).'),
      '#default_value' => $this->options['require_published'],
      '#weight' => -6,
    ];
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

    $entity_type_manager = \Drupal::entityTypeManager();
    $bundles = $entity_type_manager->getStorage('node_type')->loadMultiple();

    $options = [];
    foreach ($bundles as $bundle) {
      $options[$bundle->id()] = $bundle->label();
    }

    return $options;
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
   *   Array of field options.
   */
  protected function getTargetFieldOptions($entity_type_id, $bundle) {
    if (empty($entity_type_id) || empty($bundle)) {
      return [];
    }

    $entity_field_manager = \Drupal::service('entity_field.manager');
    $field_definitions = $entity_field_manager->getFieldDefinitions($entity_type_id, $bundle);

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
    if ($entity_type_id !== 'node') {
      return [];
    }

    $entity_type_manager = \Drupal::entityTypeManager();
    $entity_field_manager = \Drupal::service('entity_field.manager');
    $bundles = $entity_type_manager->getStorage('node_type')->loadMultiple();

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

  /**
   * {@inheritdoc}
   */
  public function query() {
    // Get configuration.
    $identifier_type = $this->options['identifier_type'];
    $target_entity_type = $this->options['target_entity_type'];
    $target_bundle = $this->options['target_bundle'];
    $target_field = $this->options['target_field'];
    $require_published = $this->options['require_published'];

    // Validate configuration. Only nodes supported.
    if ($target_entity_type !== 'node' || empty($target_field)) {
      return;
    }

    // Get the SOURCE field table (stored in init()).
    $source_field_table = $this->definition['source_field_table'] ?? $this->table;
    $source_field = $this->definition['source_field'] ?? $this->definition['field_name'];
    $source_urn_field = $source_field . '_urn';

    // Calculate target entity base table and key field.
    $entity_type_manager = \Drupal::entityTypeManager();
    $target_entity_definition = $entity_type_manager->getDefinition($target_entity_type);
    $target_base_table = $target_entity_definition->getDataTable() ?: $target_entity_definition->getBaseTable();
    $target_base_field = $target_entity_definition->getKey('id');

    // Build target field table name.
    $target_field_table = 'node__' . $target_field;
    $target_urn_field = $target_field . '_urn';
    $target_itemtype_field = $target_field . '_itemtype';

    // Ensure the relationship's source table (source field table) is in the query.
    // This call adds the source field table and returns its alias.
    // removed $this->ensureMyTable();
    $source_alias = $this->query->ensureTable($source_field_table, $this->relationship);

    // Determine join type based on required option.
    $join_type = !empty($this->options['required']) ? 'INNER' : 'LEFT';

    // FIRST JOIN: Source field table to target field table (via URN matching).
    // Build join definition.
    $first_join_def = [
      'table' => $target_field_table,
      'field' => $target_urn_field,
      'left_table' => $source_alias,
      'left_field' => $source_urn_field,
      'type' => $join_type,
      'extra' => [
        [
          'field' => 'deleted',
          'value' => 0,
          'numeric' => TRUE,
        ],
      ],
    ];

    // Add itemtype filtering if specified.
    if (!empty($identifier_type)) {
      $first_join_def['extra'][] = [
        'field' => $target_itemtype_field,
        'value' => $identifier_type,
      ];
    }

    // Create the join and add the table to the query.
    $first_join = \Drupal::service('plugin.manager.views.join')->createInstance('standard', $first_join_def);
    $target_field_alias = $this->query->addTable($target_field_table, $this->relationship, $first_join);

    // SECOND JOIN: Target field table to target entity base table.
    // This is the join that exposes the related entity's fields.

    // Build join definition.
    $second_join_def = [
      'table' => $target_base_table,
      'field' => $target_base_field,
      'left_table' => $target_field_alias,
      'left_field' => 'entity_id',
      'type' => $join_type,
      'adjusted' => TRUE,
      'extra' => [],
    ];

    // Add bundle condition.
    if (!empty($target_bundle)) {
      $second_join_def['extra'][] = [
        'field' => 'type',
        'value' => $target_bundle,
      ];
    }

    // Add published status condition.
    if ($require_published) {
      $second_join_def['extra'][] = [
        'field' => 'status',
        'value' => 1,
        'numeric' => TRUE,
      ];
    }

    // Create the join.
    $second_join = \Drupal::service('plugin.manager.views.join')->createInstance('standard', $second_join_def);

    // Build the alias following Drupal's standard pattern.
    // Format: {target_base_table}_{source_field_table}
    $relationship_alias = $target_base_table . '_' . $source_field_table;

    // Use addRelationship() to register this and make fields available.
    $this->alias = $this->query->addRelationship(
      $relationship_alias,
      $second_join,
      $this->definition['base'],
      NULL
    );
  }

}
