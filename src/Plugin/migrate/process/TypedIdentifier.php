<?php

namespace Drupal\typed_identifier\Plugin\migrate\process;

use Drupal\migrate\Attribute\MigrateProcess;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\MigrateException;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;

/**
 * Transforms data into typed_identifier field format.
 *
 * This plugin creates the proper structure for typed_identifier fields by
 * accepting itemtype and itemvalue parameters. The field's preSave() method
 * will automatically normalize URLs, validate values, and generate the URN.
 *
 * Available configuration keys:
 * - itemtype: The identifier type plugin ID (e.g., 'doi', 'orcid', 'isbn').
 *   Can be a static string or a source property name.
 * - itemvalue: The identifier value. Can be a static string, source property
 *   name, or the current source value if not specified.
 *
 * NOTE: generic typed values are not supported, other types with plugins are
 *   attempted.
 *
 * Examples:
 *
 * Simple example with static itemtype:
 * @code
 * process:
 *   field_doi:
 *     plugin: typed_identifier
 *     itemtype: doi
 *     itemvalue: source_doi_value
 * @endcode
 *
 * Dynamic itemtype from source data:
 * @code
 * process:
 *   field_identifiers:
 *     plugin: typed_identifier
 *     itemtype: source_identifier_type
 *     itemvalue: source_identifier_value
 * @endcode
 *
 * Multi-value field using sub_process:
 * @code
 * process:
 *   field_identifiers:
 *     plugin: sub_process
 *     source: identifier_array
 *     process:
 *       plugin: typed_identifier
 *       itemtype: type
 *       itemvalue: value
 * @endcode
 *
 * Using current source value as itemvalue:
 * @code
 * process:
 *   field_doi:
 *     -
 *       plugin: get
 *       source: doi_url
 *     -
 *       plugin: typed_identifier
 *       itemtype: doi
 * @endcode
 *
 * The field will automatically:
 * - Normalize URLs (https://doi.org/10.1234 -> 10.1234).
 * - Validate against the identifier type's regex pattern.
 * - Generate the URN (e.g., 'doi:10.1234').
 *
 * @see \Drupal\typed_identifier\Plugin\Field\FieldType\TypedIdentifierItem
 * @see \Drupal\migrate\Plugin\MigrateProcessInterface
 */
#[MigrateProcess(
  id: "typed_identifier",
  handle_multiples: FALSE,
)]
class TypedIdentifier extends ProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    // When used within sub_process, $value is the current array item.
    // When used standalone, $value is the pipeline value.
    // Determine itemtype.
    if (isset($this->configuration['itemtype'])) {
      $itemtype = $this->configuration['itemtype'];
    }
    elseif (is_array($value) && isset($value['itemtype'])) {
      // Extract from value array (e.g., within sub_process).
      $itemtype = $value['itemtype'];
    }
    else {
      throw new MigrateException('The "itemtype" must be specified in configuration or present in the value array.');
    }

    // Determine itemvalue.
    if (isset($this->configuration['itemvalue'])) {
      $itemvalue = $this->configuration['itemvalue'];
    }
    elseif (is_array($value) && isset($value['itemvalue'])) {
      // Extract from value array (e.g., within sub_process).
      $itemvalue = $value['itemvalue'];
    }
    elseif (!is_array($value)) {
      // Use the current pipeline value as itemvalue.
      $itemvalue = $value;
    }
    else {
      throw new MigrateException('The "itemvalue" must be specified in configuration, present in the value array, or provided via pipeline.');
    }

    // For values in configuration, check if they reference keys in the value.
    // This handles sub_process scenarios where config references array keys.
    if (is_array($value)) {
      if (is_string($itemtype) && isset($value[$itemtype])) {
        $itemtype = $value[$itemtype];
      }
      if (is_string($itemvalue) && isset($value[$itemvalue])) {
        $itemvalue = $value[$itemvalue];
      }
    }

    // Validate that both values are present and not empty.
    if (empty($itemtype)) {
      throw new MigrateException('The itemtype value is empty or could not be determined.');
    }

    if (empty($itemvalue)) {
      throw new MigrateException('The itemvalue is empty or could not be determined.');
    }

    // Return the structure expected by typed_identifier field.
    // The field's preSave() method will handle normalization and URN.
    return [
      'itemtype' => $itemtype,
      'itemvalue' => $itemvalue,
    ];
  }

}
