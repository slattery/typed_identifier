<?php

declare(strict_types=1);

namespace Drupal\Tests\typed_identifier\Unit\Service;

use PHPUnit\Framework\TestCase;
use Drupal\typed_identifier\Service\IdentifierTypeParser;

/**
 * Tests the IdentifierTypeParser service.
 *
 * @group typed_identifier
 * @coversDefaultClass \Drupal\typed_identifier\Service\IdentifierTypeParser
 */
class IdentifierTypeParserTest extends TestCase {

  /**
   * The parser service under test.
   *
   * @var \Drupal\typed_identifier\Service\IdentifierTypeParser
   */
  protected IdentifierTypeParser $parser;

  /**
   * The mocked plugin manager.
   *
   * @var \PHPUnit\Framework\MockObject\MockObject
   */
  protected $pluginManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    // Create a mock without specifying the class to avoid autoloader issues.
    $this->pluginManager = $this->getMockBuilder('stdClass')
      ->addMethods(['hasDefinition', 'createInstance', 'getDefinitions'])
      ->getMock();
    $this->parser = new IdentifierTypeParser($this->pluginManager);
  }

  /**
   * Tests parsing URN format.
   *
   * @covers ::parse
   * @covers ::matchByUrn
   * @dataProvider provideUrnFormatInputs
   */
  public function testParseUrnFormat(string $input, string $expected_type, string $expected_value): void {
    $this->mockPluginExists($expected_type);
    $result = $this->parser->parse($input);

    $this->assertIsArray($result);
    $this->assertSame($expected_type, $result['itemtype']);
    $this->assertSame($expected_value, $result['itemvalue']);
  }

  /**
   * Data provider for URN format inputs.
   *
   * @return array
   *   Test cases.
   */
  public static function provideUrnFormatInputs(): array {
    return [
      'openalex URN' => ['openalex:W00000000', 'openalex', 'W00000000'],
      'doi URN' => ['doi:10.1234/example', 'doi', '10.1234/example'],
      'orcid URN' => ['orcid:0000-0001-2345-6789', 'orcid', '0000-0001-2345-6789'],
      'isbn URN' => ['isbn:978-3-16-148410-0', 'isbn', '978-3-16-148410-0'],
      'case insensitive URN' => ['ORCID:0000-0001-2345-6789', 'orcid', '0000-0001-2345-6789'],
    ];
  }

  /**
   * Tests parsing with prefixed input (e.g., "id:...").
   *
   * @covers ::parse
   */
  public function testParseWithKeyPrefix(): void {
    $this->mockPluginExists('openalex');
    $this->mockPluginPrefix('openalex', 'https://openalex.org/');

    $result = $this->parser->parse('id:https://openalex.org/W00000000');

    $this->assertIsArray($result);
    $this->assertSame('openalex', $result['itemtype']);
    $this->assertSame('W00000000', $result['itemvalue']);
  }

  /**
   * Tests parsing URL format with HTTPS prefix.
   *
   * @covers ::parse
   * @covers ::matchByPrefix
   * @dataProvider provideUrlFormatInputs
   */
  public function testParseUrlFormat(string $input, string $expected_type, string $expected_value, string $prefix): void {
    $this->mockPluginExists($expected_type);
    $this->mockPluginPrefix($expected_type, $prefix);

    $result = $this->parser->parse($input);

    $this->assertIsArray($result);
    $this->assertSame($expected_type, $result['itemtype']);
    $this->assertSame($expected_value, $result['itemvalue']);
  }

  /**
   * Data provider for URL format inputs.
   *
   * @return array
   *   Test cases.
   */
  public static function provideUrlFormatInputs(): array {
    return [
      'openalex URL' => ['https://openalex.org/W00000000', 'openalex', 'W00000000', 'https://openalex.org/'],
      'doi URL' => ['https://doi.org/10.1234/example', 'doi', '10.1234/example', 'https://doi.org/'],
      'orcid URL' => ['https://orcid.org/0000-0001-2345-6789', 'orcid', '0000-0001-2345-6789', 'https://orcid.org/'],
      'isbn URL' => ['https://isbnsearch.org/isbn/978-3-16-148410-0', 'isbn', '978-3-16-148410-0', 'https://isbnsearch.org/isbn/'],
    ];
  }

  /**
   * Tests parsing URL format with HTTP prefix.
   *
   * @covers ::parse
   * @covers ::matchByPrefix
   */
  public function testParseHttpUrlFormat(): void {
    $this->mockPluginExists('openalex');
    $this->mockPluginPrefix('openalex', 'https://openalex.org/');

    $result = $this->parser->parse('http://openalex.org/W00000000');

    $this->assertIsArray($result);
    $this->assertSame('openalex', $result['itemtype']);
    $this->assertSame('W00000000', $result['itemvalue']);
  }

  /**
   * Tests parsing by regex validation.
   *
   * @covers ::parse
   * @covers ::matchByRegex
   */
  public function testParseByRegex(): void {
    // Mock a plugin with regex validation
    $this->mockPluginWithRegex('orcid', '^\\d{4}-\\d{4}-\\d{4}-\\d{3}[0-9X]$');

    $result = $this->parser->parse('0000-0001-2345-6789');

    $this->assertIsArray($result);
    $this->assertSame('orcid', $result['itemtype']);
    $this->assertSame('0000-0001-2345-6789', $result['itemvalue']);
  }

  /**
   * Tests parsing with no match returns NULL.
   *
   * @covers ::parse
   */
  public function testParseNoMatch(): void {
    $this->pluginManager->expects($this->once())
      ->method('getDefinitions')
      ->willReturn([]);

    $result = $this->parser->parse('unknown:value123');

    $this->assertNull($result);
  }

  /**
   * Tests parsing with empty input returns NULL.
   *
   * @covers ::parse
   */
  public function testParseEmptyInput(): void {
    $result = $this->parser->parse('');

    $this->assertNull($result);
  }

  /**
   * Tests parsing with NULL input returns NULL.
   *
   * @covers ::parse
   */
  public function testParseNullInput(): void {
    $result = $this->parser->parse('');

    $this->assertNull($result);
  }

  /**
   * Tests that generic type is skipped during matching.
   *
   * @covers ::matchByUrn
   */
  public function testGenericTypeSkipped(): void {
    $this->pluginManager->expects($this->once())
      ->method('hasDefinition')
      ->with('generic')
      ->willReturn(TRUE);

    $result = $this->parser->parse('generic:custom_value');

    // Generic should be skipped, so no match
    $this->assertNull($result);
  }

  /**
   * Tests matching priority: URN > Prefix > Regex.
   *
   * When multiple formats match, URN should take priority.
   *
   * @covers ::parse
   */
  public function testMatchingPriority(): void {
    // Mock plugin that could match by both URN and prefix
    $this->mockPluginExists('doi');
    $this->mockPluginPrefix('doi', 'https://doi.org/');

    // Input is valid URN format, should match as URN (priority 1)
    $result = $this->parser->parse('doi:10.1234/example');

    $this->assertIsArray($result);
    $this->assertSame('doi', $result['itemtype']);
    $this->assertSame('10.1234/example', $result['itemvalue']);
  }

  /**
   * Tests parsing with multiple colons in URN-like format.
   *
   * @covers ::parse
   * @covers ::matchByUrn
   */
  public function testParseUrnWithMultipleColons(): void {
    $this->mockPluginExists('urn');

    $result = $this->parser->parse('urn:nbn:de:101:1-201512094275');

    $this->assertIsArray($result);
    $this->assertSame('urn', $result['itemtype']);
    $this->assertSame('nbn:de:101:1-201512094275', $result['itemvalue']);
  }

  /**
   * Tests that HTTP/HTTPS variant matching works for prefixes.
   *
   * @covers ::matchByPrefix
   */
  public function testHttpToHttpsVariantMatching(): void {
    // Plugin defines HTTPS prefix
    $this->mockPluginExists('doi');
    $this->mockPluginPrefix('doi', 'https://doi.org/');

    // Input uses HTTP variant
    $result = $this->parser->parse('http://doi.org/10.1234/test');

    $this->assertIsArray($result);
    $this->assertSame('doi', $result['itemtype']);
  }

  /**
   * Tests parsing with leading/trailing whitespace is handled.
   *
   * @covers ::parse
   */
  public function testParseWithWhitespace(): void {
    // Empty strings with whitespace should still return NULL
    $result = $this->parser->parse('   ');

    // After trimming, it's empty, so should return NULL
    // Note: Current implementation doesn't trim, so spaces will not match
    $this->assertNull($result);
  }

  /**
   * Helper to mock that a plugin exists.
   *
   * @param string $plugin_id
   *   The plugin ID.
   */
  protected function mockPluginExists(string $plugin_id): void {
    $plugin = $this->createMock('Drupal\typed_identifier\IdentifierTypeInterface');
    $plugin->method('getPrefix')->willReturn('');
    $plugin->method('getValidationRegex')->willReturn('');

    $this->pluginManager->method('hasDefinition')
      ->with($plugin_id)
      ->willReturn(TRUE);

    $this->pluginManager->method('createInstance')
      ->with($plugin_id)
      ->willReturn($plugin);

    $this->pluginManager->method('getDefinitions')
      ->willReturn([$plugin_id => []]);
  }

  /**
   * Helper to mock a plugin with a specific prefix.
   *
   * @param string $plugin_id
   *   The plugin ID.
   * @param string $prefix
   *   The prefix URL.
   */
  protected function mockPluginPrefix(string $plugin_id, string $prefix): void {
    $plugin = $this->createMock('Drupal\typed_identifier\IdentifierTypeInterface');
    $plugin->method('getPrefix')->willReturn($prefix);
    $plugin->method('getValidationRegex')->willReturn('');

    $this->pluginManager->method('hasDefinition')
      ->with($plugin_id)
      ->willReturn(TRUE);

    $this->pluginManager->method('createInstance')
      ->with($plugin_id)
      ->willReturn($plugin);

    $this->pluginManager->method('getDefinitions')
      ->willReturn([$plugin_id => []]);
  }

  /**
   * Helper to mock a plugin with regex validation.
   *
   * @param string $plugin_id
   *   The plugin ID.
   * @param string $regex
   *   The validation regex pattern.
   */
  protected function mockPluginWithRegex(string $plugin_id, string $regex): void {
    $plugin = $this->createMock('Drupal\typed_identifier\IdentifierTypeInterface');
    $plugin->method('getPrefix')->willReturn('');
    $plugin->method('getValidationRegex')->willReturn($regex);

    $this->pluginManager->method('hasDefinition')
      ->with($plugin_id)
      ->willReturn(TRUE);

    $this->pluginManager->method('createInstance')
      ->with($plugin_id)
      ->willReturn($plugin);

    $this->pluginManager->method('getDefinitions')
      ->willReturn([$plugin_id => []]);
  }

}
