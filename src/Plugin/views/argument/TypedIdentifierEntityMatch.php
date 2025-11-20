<?php

namespace Drupal\typed_identifier\Plugin\views\argument;

use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Plugin\views\argument\ArgumentPluginBase;

/**
 * Contextual filter for matching typed identifier values from an entity.
 *
 * This allows filtering a view based on typed identifier values from a
 * context entity (e.g., show research outputs that share ORCID values with
 * the profile node being viewed).
 *
 * IMPORTANT USAGE:
 * - Add this filter on the FIELD BEING FILTERED (target field on base entity).
 * - Set 'source_field' to the field on the context entity (where to GET
 *   values FROM).
 *
 * Example: Show research outputs for a profile page
 * - Base entity: research_output nodes
 * - Contextual filter: On field_author_typed_ids (the author field)
 * - source_field: field_profile_typed_ids (the profile's identifier field)
 * - The view reads identifiers FROM the profile and filters research outputs.
 *
 * Common mistake:
 * - DON'T add filter on source field (profile's field).
 * - DO add filter on target field (research output's field).
 *
 * @ViewsArgument("typed_identifier_entity_match")
 */
class TypedIdentifierEntityMatch extends ArgumentPluginBase {

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();
    $options['identifier_type'] = ['default' => ''];
    $options['source_entity_type'] = ['default' => 'node'];
    $options['source_field'] = ['default' => ''];
    $options['match_all'] = ['default' => FALSE];
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
      '#title' => $this->t('Identifier type to match'),
      '#description' => $this->t('Select a specific identifier type to match only that type, or select "Any type" to match across all types.'),
      '#options' => $identifier_options,
      '#empty_option' => $this->t('- Any type -'),
      '#default_value' => $this->options['identifier_type'],
      '#required' => FALSE,
    ];

    // Get available entity types.
    $entity_type_manager = \Drupal::entityTypeManager();
    $entity_types = $entity_type_manager->getDefinitions();
    $entity_type_options = [];
    foreach ($entity_types as $id => $entity_type) {
      if ($entity_type->entityClassImplements('\Drupal\Core\Entity\ContentEntityInterface')) {
        $entity_type_options[$id] = $entity_type->getLabel();
      }
    }

    $form['source_entity_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Source entity type'),
      '#description' => $this->t('The entity type of the context entity (usually "node" for content).'),
      '#options' => $entity_type_options,
      '#default_value' => $this->options['source_entity_type'],
      '#required' => TRUE,
    ];

    // Get typed_identifier fields on source entity with optgroups by bundle.
    $source_field_options = $this->getAllSourceFieldOptions($this->options['source_entity_type']);

    $form['source_field'] = [
      '#type' => 'select',
      '#title' => $this->t('Source field'),
      '#description' => $this->t('The typed_identifier field on the source entity to get values from. Fields are grouped by content type.'),
      '#options' => $source_field_options,
      '#default_value' => $this->options['source_field'],
      '#required' => TRUE,
      '#empty_option' => $this->t('- Select -'),
    ];

    $form['match_all'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Match all values'),
      '#description' => $this->t('If checked, results must match ALL identifier values from the source entity. If unchecked, results match ANY value (default).'),
      '#default_value' => $this->options['match_all'],
    ];

    $form['require_published'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Require published source content'),
      '#description' => $this->t('If checked, only extract identifiers from the source entity if it is published (status = 1).'),
      '#default_value' => $this->options['require_published'],
    ];

    // Add guidance for typical usage.
    $form['usage_info'] = [
      '#type' => 'markup',
      '#markup' => '<div class="messages messages--info">' .
      '<strong>' . $this->t('Typical Usage:') . '</strong><br>' .
      $this->t('When providing default value:') . '<ul>' .
      '<li>' . $this->t('Select "Content ID from URL" to use the node being viewed') . '</li>' .
      '<li>' . $this->t('Or select "Raw value from URL" and use path like /research-outputs/%node') . '</li>' .
      '</ul></div>',
    ];
  }

  /**
   * Get typed_identifier fields for a given entity type.
   *
   * @param string $entity_type_id
   *   The entity type ID.
   *
   * @return array
   *   Array of field options.
   */
  protected function getSourceFieldOptions($entity_type_id) {
    if (empty($entity_type_id)) {
      return [];
    }

    $entity_field_manager = \Drupal::service('entity_field.manager');
    $field_map = $entity_field_manager->getFieldMapByFieldType('typed_identifier');

    $options = [];
    if (isset($field_map[$entity_type_id])) {
      foreach ($field_map[$entity_type_id] as $field_name => $field_info) {
        $options[$field_name] = $field_name;
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
  protected function getAllSourceFieldOptions($entity_type_id) {
    if (empty($entity_type_id)) {
      return [];
    }

    $entity_type_manager = \Drupal::entityTypeManager();
    $entity_field_manager = \Drupal::service('entity_field.manager');

    try {
      $entity_type_def = $entity_type_manager->getDefinition($entity_type_id);
      $bundle_entity_type_id = $entity_type_def->getBundleEntityType();

      if (!$bundle_entity_type_id) {
        // Entity type doesn't have bundles (e.g., user).
        // Return flat list of fields.
        return $this->getSourceFieldOptions($entity_type_id);
      }

      // Get all bundles for this entity type.
      $bundle_storage = $entity_type_manager->getStorage($bundle_entity_type_id);
      $bundles = $bundle_storage->loadMultiple();

      $options = [];
      foreach ($bundles as $bundle_id => $bundle) {
        $field_definitions = $entity_field_manager->getFieldDefinitions(
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
    catch (\Exception $e) {
      // If entity type doesn't exist or error occurs, return empty.
      return [];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function query($group_by = FALSE) {
    $this->ensureMyTable();

    // Get configuration.
    $identifier_type = $this->options['identifier_type'];
    $source_entity_type = $this->options['source_entity_type'];
    $source_field = $this->options['source_field'];
    $match_all = $this->options['match_all'];
    // Get the WHERE clause group (usually 0).
    $group = $this->options['group'] ?? 0;

    // identifier_type can be empty (for "Any type" mode)
    if (empty($source_entity_type) || empty($source_field)) {
      // Exclude all results when configuration is missing.
      $this->query->addWhereExpression($group, '1 = 0');
      return;
    }

    // Get the argument value (entity ID of the source entity).
    $source_entity_id = $this->argument;

    if (empty($source_entity_id) || !is_numeric($source_entity_id)) {
      // No valid entity ID provided - exclude all results.
      $this->query->addWhereExpression($group, '1 = 0');
      return;
    }

    // Get require_published option.
    $require_published = $this->options['require_published'];

    // Load the source entity and get identifier values.
    try {
      $entity_storage = \Drupal::entityTypeManager()->getStorage($source_entity_type);
      $source_entity = $entity_storage->load($source_entity_id);

      if (!$source_entity || !$source_entity->hasField($source_field)) {
        // Entity doesn't exist or doesn't have the field - exclude all results.
        $this->query->addWhereExpression($group, '1 = 0');
        return;
      }

      // Check if source entity is published when required.
      if ($require_published && $source_entity_type === 'node') {
        if (!$source_entity->isPublished()) {
          // Source entity is not published - exclude all results.
          $this->query->addWhereExpression($group, '1 = 0');
          return;
        }
      }

      // Gather URN values from the source entity.
      // URNs are stored in lowercase for case-insensitive matching.
      $urns = [];
      foreach ($source_entity->get($source_field) as $item) {
        // When identifier_type is empty, match ALL types ("Any type" mode).
        // When identifier_type is set, match only that specific type.
        $type_matches = empty($identifier_type) || $item->itemtype === $identifier_type;

        if ($type_matches && !empty($item->urn)) {
          $urns[] = $item->urn;
        }
      }

      if (empty($urns)) {
        // No matching identifiers found, exclude all results.
        $this->query->addWhereExpression($group, '1 = 0');
        return;
      }

      // Get the field name being filtered.
      $field_name = $this->definition['field_name'];
      $table_alias = $this->tableAlias;

      if ($match_all) {
        // Match ALL: Entity must have all URN values.
        $match_count = count($urns);

        // Add a WHERE condition that counts matching URN values.
        $subquery = \Drupal::database()->select($this->table, 'sub');
        $subquery->addField('sub', 'entity_id');
        $subquery->condition('sub.' . $field_name . '_urn', $urns, 'IN');
        $subquery->groupBy('sub.entity_id');
        $subquery->having('COUNT(DISTINCT sub.' . $field_name . '_urn) = :match_count', [
          ':match_count' => $match_count,
        ]);

        // Use the subquery in a WHERE IN condition.
        $this->query->addWhere($group, "$table_alias.entity_id", $subquery, 'IN');
      }
      else {
        // Match ANY: Entity can have any of the URN values.
        // Simple IN clause - much faster than OR/AND conditions.
        $this->query->addWhere($group, "$table_alias.{$field_name}_urn", $urns, 'IN');
      }

    }
    catch (\Exception $e) {
      // Log error and exclude all results.
      \Drupal::logger('typed_identifier')->error('Error in TypedIdentifierEntityMatch: @message', [
        '@message' => $e->getMessage(),
      ]);
      $this->query->addWhereExpression($group, '1 = 0');
    }
  }

  /**
   * {@inheritdoc}
   */
  public function title() {
    if (!empty($this->argument)) {
      try {
        $source_entity_type = $this->options['source_entity_type'];
        $entity_storage = \Drupal::entityTypeManager()->getStorage($source_entity_type);
        $source_entity = $entity_storage->load($this->argument);

        if ($source_entity && $source_entity->hasField('title')) {
          return $this->t('Matches identifiers from: @title', [
            '@title' => $source_entity->label(),
          ]);
        }
        elseif ($source_entity) {
          return $this->t('Matches identifiers from: @type @id', [
            '@type' => $source_entity_type,
            '@id' => $this->argument,
          ]);
        }
      }
      catch (\Exception $e) {
        // Fall through to default.
      }
    }

    return $this->t('Matching identifiers');
  }

}
