<?php

namespace Drupal\typed_identifier\Plugin\views\relationship;

use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Plugin\views\relationship\RelationshipPluginBase;

/**
 * Relationship handler for typed identifiers.
 *
 * @ViewsRelationship("typed_identifier_relationship")
 */
class TypedIdentifierRelationship extends RelationshipPluginBase {

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();
    $options['identifier_type'] = ['default' => ''];
    $options['target_entity_type'] = ['default' => ''];
    $options['target_field'] = ['default' => ''];
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
      '#title' => $this->t('Match on identifier type'),
      '#description' => $this->t('Select which identifier type to use for matching. This prevents duplicate rows when entities have multiple matching identifiers.'),
      '#options' => $identifier_options,
      '#default_value' => $this->options['identifier_type'],
      '#required' => TRUE,
      '#weight' => -10,
    ];

    // Get available entity types.
    $entity_type_manager = \Drupal::entityTypeManager();
    $entity_types = $entity_type_manager->getDefinitions();
    $entity_type_options = [];
    foreach ($entity_types as $id => $entity_type) {
      if ($entity_type->entityClassImplements('\Drupal\Core\Entity\FieldableEntityInterface')) {
        $entity_type_options[$id] = $entity_type->getLabel();
      }
    }

    $form['target_entity_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Target entity type'),
      '#description' => $this->t('Select the entity type to relate to.'),
      '#options' => $entity_type_options,
      '#default_value' => $this->options['target_entity_type'],
      '#required' => TRUE,
      '#weight' => -9,
      '#ajax' => [
        'callback' => [static::class, 'updateTargetFieldOptions'],
        'wrapper' => 'target-field-wrapper',
      ],
    ];

    // Get typed_identifier fields on target entity.
    $target_field_options = $this->getTargetFieldOptions($this->options['target_entity_type']);

    $form['target_field'] = [
      '#type' => 'select',
      '#title' => $this->t('Target field'),
      '#description' => $this->t('Select which typed_identifier field on the target entity to join to.'),
      '#options' => $target_field_options,
      '#default_value' => $this->options['target_field'],
      '#required' => TRUE,
      '#weight' => -8,
      '#prefix' => '<div id="target-field-wrapper">',
      '#suffix' => '</div>',
      '#validated' => TRUE,
      '#empty_option' => $this->t('- Select a target entity type first -'),
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
  protected function getTargetFieldOptions($entity_type_id) {
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
   * AJAX callback to update target field options.
   */
  public static function updateTargetFieldOptions(array &$form, FormStateInterface $form_state) {
    // Get the triggering element to find the selected entity type.
    $triggering_element = $form_state->getTriggeringElement();
    $selected_entity_type = $form_state->getValue($triggering_element['#parents']);

    // Rebuild the field options based on the selected entity type.
    $entity_field_manager = \Drupal::service('entity_field.manager');
    $field_map = $entity_field_manager->getFieldMapByFieldType('typed_identifier');

    $options = [];
    if (!empty($selected_entity_type) && isset($field_map[$selected_entity_type])) {
      foreach ($field_map[$selected_entity_type] as $field_name => $field_info) {
        $options[$field_name] = $field_name;
      }
    }

    // Update the form element with new options.
    $form['options']['target_field']['#options'] = $options;

    return $form['options']['target_field'];
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    $this->ensureMyTable();

    // Get configuration.
    $identifier_type = $this->options['identifier_type'];
    $target_entity_type = $this->options['target_entity_type'];
    $target_field = $this->options['target_field'];

    if (empty($identifier_type) || empty($target_entity_type) || empty($target_field)) {
      return;
    }

    // Get the base table and field.
    $base_table = $this->table;
    $base_field = $this->definition['base field'];

    // Determine target table.
    $entity_type_manager = \Drupal::entityTypeManager();
    $target_entity_definition = $entity_type_manager->getDefinition($target_entity_type);
    $target_base_table = $target_entity_definition->getDataTable() ?: $target_entity_definition->getBaseTable();

    // Build target field table name.
    $target_field_table = $target_entity_type . '__' . $target_field;

    // Add the join.
    $def = $this->definition;
    $def['table'] = $target_field_table;
    $def['field'] = 'entity_id';
    $def['left_table'] = $base_table;
    $def['left_field'] = 'entity_id';

    // Add extra conditions to match itemtype and itemvalue.
    $def['extra'] = [
      [
        'field' => $target_field . '_itemtype',
        'value' => $identifier_type,
      ],
      [
        'left_field' => $base_field . '_itemtype',
        'value' => $identifier_type,
      ],
      [
        'left_field' => $base_field . '_itemvalue',
        'field' => $target_field . '_itemvalue',
      ],
    ];

    $join = \Drupal::service('plugin.manager.views.join')->createInstance('standard', $def);
    $alias = $this->alias = $this->query->addRelationship($target_field_table, $join, $target_base_table, $this->relationship);
  }

}
