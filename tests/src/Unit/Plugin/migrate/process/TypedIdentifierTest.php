<?php

declare(strict_types=1);

namespace Drupal\Tests\typed_identifier\Unit\Plugin\migrate\process;

use Drupal\migrate\MigrateException;
use Drupal\Tests\migrate\Unit\process\MigrateProcessTestCase;
use Drupal\typed_identifier\Plugin\migrate\process\TypedIdentifier;

/**
 * Tests the typed_identifier process plugin.
 *
 * @group typed_identifier
 * @coversDefaultClass \Drupal\typed_identifier\Plugin\migrate\process\TypedIdentifier
 */
class TypedIdentifierTest extends MigrateProcessTestCase {

  /**
   * Tests transformation with static values in configuration.
   *
   * @covers ::transform
   */
  public function testTransformWithStaticConfiguration(): void {
    $configuration = [
      'itemtype' => 'doi',
      'itemvalue' => '10.1234/example',
    ];

    $plugin = new TypedIdentifier($configuration, 'typed_identifier', []);
    $result = $plugin->transform(NULL, $this->migrateExecutable, $this->row, 'destination_property');

    $expected = [
      'itemtype' => 'doi',
      'itemvalue' => '10.1234/example',
    ];

    $this->assertSame($expected, $result);
  }

  /**
   * Tests transformation with static values for both itemtype and itemvalue.
   *
   * @covers ::transform
   */
  public function testTransformWithStaticValues(): void {
    $configuration = [
      'itemtype' => 'isbn',
      'itemvalue' => '978-3-16-148410-0',
    ];

    $plugin = new TypedIdentifier($configuration, 'typed_identifier', []);
    $result = $plugin->transform(NULL, $this->migrateExecutable, $this->row, 'destination_property');

    $expected = [
      'itemtype' => 'isbn',
      'itemvalue' => '978-3-16-148410-0',
    ];

    $this->assertSame($expected, $result);
  }

  /**
   * Tests transformation within sub_process with array value.
   *
   * This simulates how the plugin works within sub_process where the value
   * is an array item and configuration references keys in that array.
   *
   * @covers ::transform
   */
  public function testTransformWithSubProcess(): void {
    $configuration = [
      'itemtype' => 'type',
      'itemvalue' => 'value',
    ];

    $value = [
      'type' => 'orcid',
      'value' => '0000-0001-2345-6789',
    ];

    $plugin = new TypedIdentifier($configuration, 'typed_identifier', []);
    $result = $plugin->transform($value, $this->migrateExecutable, $this->row, 'destination_property');

    $expected = [
      'itemtype' => 'orcid',
      'itemvalue' => '0000-0001-2345-6789',
    ];

    $this->assertSame($expected, $result);
  }

  /**
   * Tests transformation using pipeline value as itemvalue.
   *
   * @covers ::transform
   */
  public function testTransformWithPipelineValue(): void {
    $configuration = [
      'itemtype' => 'doi',
    ];

    $plugin = new TypedIdentifier($configuration, 'typed_identifier', []);
    $result = $plugin->transform('https://doi.org/10.5678/test', $this->migrateExecutable, $this->row, 'destination_property');

    $expected = [
      'itemtype' => 'doi',
      'itemvalue' => 'https://doi.org/10.5678/test',
    ];

    $this->assertSame($expected, $result);
  }

  /**
   * Tests transformation with value array containing itemtype/itemvalue.
   *
   * @covers ::transform
   */
  public function testTransformWithValueArray(): void {
    $value = [
      'itemtype' => 'isbn',
      'itemvalue' => '978-3-16-148410-0',
    ];

    $plugin = new TypedIdentifier([], 'typed_identifier', []);
    $result = $plugin->transform($value, $this->migrateExecutable, $this->row, 'destination_property');

    $expected = [
      'itemtype' => 'isbn',
      'itemvalue' => '978-3-16-148410-0',
    ];

    $this->assertSame($expected, $result);
  }

  /**
   * Tests exception when itemtype is not available.
   *
   * @covers ::transform
   */
  public function testTransformWithoutItemtype(): void {
    $configuration = [
      'itemvalue' => 'some_value',
    ];

    $this->expectException(MigrateException::class);
    $this->expectExceptionMessage('itemtype" must be specified');

    $plugin = new TypedIdentifier($configuration, 'typed_identifier', []);
    $plugin->transform(NULL, $this->migrateExecutable, $this->row, 'destination_property');
  }

  /**
   * Tests exception when itemtype is empty.
   *
   * @covers ::transform
   */
  public function testTransformWithEmptyItemtype(): void {
    $configuration = [
      'itemtype' => '',
      'itemvalue' => 'some_value',
    ];

    $this->expectException(MigrateException::class);
    $this->expectExceptionMessage('The itemtype value is empty');

    $plugin = new TypedIdentifier($configuration, 'typed_identifier', []);
    $plugin->transform(NULL, $this->migrateExecutable, $this->row, 'destination_property');
  }

  /**
   * Tests exception when itemvalue is empty.
   *
   * @covers ::transform
   */
  public function testTransformWithEmptyItemvalue(): void {
    $configuration = [
      'itemtype' => 'doi',
      'itemvalue' => '',
    ];

    $this->expectException(MigrateException::class);
    $this->expectExceptionMessage('itemvalue is empty');

    $plugin = new TypedIdentifier($configuration, 'typed_identifier', []);
    $plugin->transform(NULL, $this->migrateExecutable, $this->row, 'destination_property');
  }

  /**
   * Tests exception when itemvalue cannot be determined from array.
   *
   * @covers ::transform
   */
  public function testTransformWithArrayMissingItemvalue(): void {
    $configuration = [
      'itemtype' => 'doi',
    ];

    $value = [
      'some_other_key' => 'value',
    ];

    $this->expectException(MigrateException::class);
    $this->expectExceptionMessage('itemvalue" must be specified');

    $plugin = new TypedIdentifier($configuration, 'typed_identifier', []);
    $plugin->transform($value, $this->migrateExecutable, $this->row, 'destination_property');
  }

  /**
   * Tests transformation with various identifier types.
   *
   * @covers ::transform
   * @dataProvider provideIdentifierTypes
   */
  public function testTransformWithVariousTypes(string $itemtype, string $itemvalue): void {
    $configuration = [
      'itemtype' => $itemtype,
      'itemvalue' => $itemvalue,
    ];

    $plugin = new TypedIdentifier($configuration, 'typed_identifier', []);
    $result = $plugin->transform(NULL, $this->migrateExecutable, $this->row, 'destination_property');

    $expected = [
      'itemtype' => $itemtype,
      'itemvalue' => $itemvalue,
    ];

    $this->assertSame($expected, $result);
  }

  /**
   * Data provider for testTransformWithVariousTypes.
   *
   * @return array
   *   Test cases with different identifier types.
   */
  public static function provideIdentifierTypes(): array {
    return [
      'DOI' => ['doi', '10.1234/example'],
      'ORCID' => ['orcid', '0000-0001-2345-6789'],
      'ISBN' => ['isbn', '978-3-16-148410-0'],
      'ISSN' => ['issn', '1234-5678'],
      'PubMed' => ['pubmed', '12345678'],
      'OpenAlex' => ['openalex', 'W2741809807'],
      'Scopus' => ['scopus', '12345678900'],
      'ResearcherID' => ['researcherid', 'ABC-1234-2020'],
      'URN' => ['urn', 'urn:nbn:de:101:1-201512094275'],
      'Generic' => ['generic', 'custom-id-123'],
    ];
  }

}
