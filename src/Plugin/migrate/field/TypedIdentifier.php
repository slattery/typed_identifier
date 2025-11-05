<?php

namespace Drupal\typed_identifier\Plugin\migrate\field;

use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate_drupal\Attribute\MigrateField;
use Drupal\migrate_drupal\Plugin\migrate\field\FieldPluginBase;

/**
 * MigrateField Plugin for typed_identifier fields.
 *
 * This plugin handles migration of typed_identifier fields from previous
 * Drupal versions. It can map from similar custom field types or be used
 * when migrating the typed_identifier module itself between sites.
 *
 * @see \Drupal\typed_identifier\Plugin\Field\FieldType\TypedIdentifierItem
 */
#[MigrateField(
  id: 'typed_identifier',
  core: [7, 8, 9, 10],
  type_map: [
    'typed_identifier' => 'typed_identifier',
  ],
  source_module: 'typed_identifier',
  destination_module: 'typed_identifier',
)]
class TypedIdentifier extends FieldPluginBase {

  /**
   * {@inheritdoc}
   */
  public function getFieldWidgetMap() {
    return [
      'typed_identifier_widget' => 'typed_identifier_widget',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldFormatterMap() {
    return [
      'typed_identifier_prefix' => 'typed_identifier_prefix',
      'typed_identifier_label' => 'typed_identifier_label',
      'typed_identifier_label_prefix' => 'typed_identifier_label_prefix',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function defineValueProcessPipeline(MigrationInterface $migration, $field_name, $data) {
    $process = [
      'plugin' => 'sub_process',
      'source' => $field_name,
      'process' => [
        'itemtype' => 'itemtype',
        'itemvalue' => 'itemvalue',
      ],
    ];
    $migration->setProcessOfProperty($field_name, $process);
  }

}
