<?php

/**
 * @file
 * Provides Views data for the typed_identifier module.
 */

/**
 * Implements hook_field_views_data().
 */
function typed_identifier_field_views_data(\Drupal\field\FieldStorageConfigInterface $field_storage) {
  $data = views_field_default_views_data($field_storage);
  $field_name = $field_storage->getName();
  $entity_type_id = $field_storage->getTargetEntityTypeId();

  foreach ($data as $table_name => $table_data) {
    // Itemtype field handler.
    if (isset($data[$table_name][$field_name . '_itemtype'])) {
      $base_field = $data[$table_name][$field_name . '_itemtype']['field'] ?? [];
      $data[$table_name][$field_name . '_itemtype']['field'] = [
        'id' => 'typed_identifier_itemtype',
        'field_name' => $field_name,
        'entity_type' => $entity_type_id,
      ] + $base_field;

      // Itemtype filter.
      $data[$table_name][$field_name . '_itemtype']['filter'] = [
        'id' => 'typed_identifier_itemtype',
        'field_name' => $field_name,
        'entity_type' => $entity_type_id,
      ];

      // Itemtype sort.
      $data[$table_name][$field_name . '_itemtype']['sort'] = [
        'id' => 'standard',
      ];

      // Itemtype argument.
      $data[$table_name][$field_name . '_itemtype']['argument'] = [
        'id' => 'string',
      ];
    }

    // Itemvalue field handler.
    if (isset($data[$table_name][$field_name . '_itemvalue'])) {
      $base_field = $data[$table_name][$field_name . '_itemvalue']['field'] ?? [];
      $data[$table_name][$field_name . '_itemvalue']['field'] = [
        'id' => 'typed_identifier_itemvalue',
        'field_name' => $field_name,
        'entity_type' => $entity_type_id,
      ] + $base_field;

      // Itemvalue filter.
      $data[$table_name][$field_name . '_itemvalue']['filter'] = [
        'id' => 'string',
      ];

      // Itemvalue sort.
      $data[$table_name][$field_name . '_itemvalue']['sort'] = [
        'id' => 'standard',
      ];

      // Itemvalue argument.
      $data[$table_name][$field_name . '_itemvalue']['argument'] = [
        'id' => 'string',
      ];
    }

    // Relationship handler.
    $data[$table_name][$field_name . '_relationship'] = [
      'title' => t('@field_name: Typed Identifier relationship', ['@field_name' => $field_name]),
      'help' => t('Relate entities that share the same identifier type and value in the @field_name field.', ['@field_name' => $field_name]),
      'group' => t('Content'),
      'relationship' => [
        'id' => 'typed_identifier_relationship',
        'field_name' => $field_name,
        'base' => $table_name,
        'base field' => $field_name,
        'label' => t('@field_name: Typed Identifier', ['@field_name' => $field_name]),
      ],
    ];

    // Entity match contextual filter.
    $data[$table_name][$field_name . '_entity_match'] = [
      'title' => t('@field_name: Entity Match', ['@field_name' => $field_name]),
      'help' => t('Filter by matching identifier values from another entity in the @field_name field (e.g., show items that share ORCID values with a profile node).', ['@field_name' => $field_name]),
      'group' => t('Content'),
      'argument' => [
        'id' => 'typed_identifier_entity_match',
        'field_name' => $field_name,
        'entity_type' => $entity_type_id,
        'table' => $table_name,
      ],
    ];
  }

  return $data;
}
