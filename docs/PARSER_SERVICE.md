# IdentifierTypeParser Service

The `IdentifierTypeParser` service provides automatic detection and parsing of typed identifiers. It takes a raw string input and identifies which identifier type it matches, returning the normalized `itemtype` and `itemvalue`.

## Overview

Instead of manually specifying the identifier type, the parser can automatically detect what type an identifier is based on:
- URL prefixes (e.g., `https://openalex.org/`)
- URN format (e.g., `openalex:W00000000`)
- Validation regex patterns

This is especially useful in migration scenarios where you have raw identifier data but don't know the type in advance.

## Service Registration

The service is registered as `typed_identifier.parser` in `typed_identifier.services.yml`:

```yaml
typed_identifier.parser:
  class: Drupal\typed_identifier\Service\IdentifierTypeParser
  arguments: ['@plugin.manager.typed_identifier.identifier_type']
```

## Basic Usage

Get the service and call `parse()`:

```php
$parser = \Drupal::service('typed_identifier.parser');
$result = $parser->parse($input_string);

if ($result) {
  $itemtype = $result['itemtype'];
  $itemvalue = $result['itemvalue'];
} else {
  // No matching identifier type found
}
```

## Supported Input Formats

The parser accepts multiple input formats and automatically detects the type:

### 1. URN Format (Highest Confidence)
```php
$parser->parse('openalex:W00000000');
// Returns: ['itemtype' => 'openalex', 'itemvalue' => 'W00000000']

$parser->parse('doi:10.1234/example');
// Returns: ['itemtype' => 'doi', 'itemvalue' => '10.1234/example']

// Case insensitive for plugin ID
$parser->parse('ORCID:0000-0001-2345-6789');
// Returns: ['itemtype' => 'orcid', 'itemvalue' => '0000-0001-2345-6789']
```

### 2. URL Format with HTTPS Prefix (High Confidence)
```php
$parser->parse('https://openalex.org/W00000000');
// Returns: ['itemtype' => 'openalex', 'itemvalue' => 'W00000000']

$parser->parse('https://doi.org/10.1234/example');
// Returns: ['itemtype' => 'doi', 'itemvalue' => '10.1234/example']

$parser->parse('https://orcid.org/0000-0001-2345-6789');
// Returns: ['itemtype' => 'orcid', 'itemvalue' => '0000-0001-2345-6789']

$parser->parse('https://isbnsearch.org/isbn/978-3-16-148410-0');
// Returns: ['itemtype' => 'isbn', 'itemvalue' => '978-3-16-148410-0']
```

### 3. URL Format with HTTP Prefix (Also Works)
```php
$parser->parse('http://openalex.org/W00000000');
// Returns: ['itemtype' => 'openalex', 'itemvalue' => 'W00000000']

// Works even though prefix is defined as HTTPS
```

### 4. Prefixed Input (Strips Key Prefix)
```php
// Input with "id:" prefix gets stripped
$parser->parse('id:https://openalex.org/W00000000');
// Returns: ['itemtype' => 'openalex', 'itemvalue' => 'W00000000']

// Works with any prefix format
$parser->parse('key:openalex:W00000000');
// Returns: ['itemtype' => 'openalex', 'itemvalue' => 'W00000000']
```

### 5. Regex Validation (Lowest Confidence)
```php
// If no prefix/URN match, tries regex validation against all plugins
$parser->parse('0000-0001-2345-6789');
// Returns: ['itemtype' => 'orcid', 'itemvalue' => '0000-0001-2345-6789']
// (if this matches ORCID regex)
```

## Return Values

### Success
Returns an array with two keys:
```php
[
  'itemtype'  => 'openalex',      // The identifier type plugin ID
  'itemvalue' => 'W00000000',      // The normalized value
]
```

### No Match
Returns `NULL` if no identifier type matches:
```php
$result = $parser->parse('unknown_format_xyz');
// Returns: NULL
```

### Empty/Invalid Input
Returns `NULL` for empty or non-string input:
```php
$parser->parse('');        // NULL
$parser->parse(NULL);      // NULL
```

## Matching Priority

When an identifier matches multiple formats, the parser uses this priority:

1. **URN Format** (highest) - e.g., `openalex:W00000000`
2. **URL Prefix** (high) - e.g., `https://openalex.org/W00000000`
3. **Regex Validation** (lowest) - fallback to pattern matching

This ensures the most specific, confident match is returned first.

## Generic Type Handling

The parser **deliberately skips the `generic` identifier type** to avoid ambiguous matches. Generic types should be explicitly specified when needed, as they don't have patterns to match against.

## Migration Helper Integration Example

Use the parser in a custom migration process plugin to auto-detect identifier types:

```php
namespace Drupal\yse_scholarly_works\Plugin\migrate\process;

use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Row;
use Symfony\Component\DependencyInjection\ContainerInterface;

class AutoDetectTypedIdentifier extends ProcessPluginBase implements ContainerFactoryPluginInterface {

  protected $parser;

  public function __construct(array $configuration, $plugin_id, $plugin_definition, $parser) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->parser = $parser;
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('typed_identifier.parser')
    );
  }

  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_field_name) {
    if (empty($value)) {
      return [];
    }

    // Parse the value to detect identifier type
    $parsed = $this->parser->parse($value);

    if ($parsed) {
      return [
        'itemtype' => $parsed['itemtype'],
        'itemvalue' => $parsed['itemvalue'],
      ];
    }

    // Handle no match case
    return [];
  }
}
```

Usage in migration YAML:
```yaml
process:
  field_identifiers:
    plugin: auto_detect_typed_identifier
    source: identifier_url_or_urn
```

## Service Injection in Custom Code

### In a Service Class
```php
use Drupal\typed_identifier\Service\IdentifierTypeParser;

class MyService {
  protected $parser;

  public function __construct(IdentifierTypeParser $parser) {
    $this->parser = $parser;
  }

  public function processIdentifier($input) {
    $parsed = $this->parser->parse($input);
    // ... do something with $parsed
  }
}
```

Register in `.services.yml`:
```yaml
my_module.my_service:
  class: Drupal\my_module\MyService
  arguments: ['@typed_identifier.parser']
```

### In a Controller or Plugin
```php
use Drupal\Core\Controller\ControllerBase;

class MyController extends ControllerBase {
  public function processAction() {
    $parser = $this->container()->get('typed_identifier.parser');
    $result = $parser->parse($some_input);
    // ...
  }
}
```

## Supported Identifier Types

The parser can detect any identifier type plugin, including:

- `openalex` - OpenAlex identifiers
- `doi` - Digital Object Identifiers
- `orcid` - Open Researcher and Contributor IDs
- `isbn` - International Standard Book Numbers
- `issn` - International Standard Serial Numbers
- `pubmed` - PubMed identifiers
- `researcherid` - Web of Science ResearcherID
- `scopus` - Scopus Author Identifiers
- `urn` - Uniform Resource Names
- `netid` - NetID identifiers
- `upi` - UPI Numbers
- `url` - Web URLs

(Plus any custom identifier type plugins you've defined)

## Error Handling

The service returns `NULL` for unparseable input rather than throwing exceptions:

```php
$result = $parser->parse('completely.unknown.format');

if ($result === NULL) {
  // Log or handle the unparseable input
  \Drupal::logger('my_module')->warning('Could not parse identifier: @input', ['@input' => $input]);
} else {
  // Use the parsed result
}
```

## Performance Considerations

The parser iterates through plugins in this order:

1. **URN matching** - Fast string comparison
2. **Prefix matching** - Fast string prefix check (2x: HTTPS + HTTP)
3. **Regex validation** - Slower, only if no URN/prefix match

For best performance, ensure your input data is in URN or URL format when possible.

## See Also

- [Architecture Documentation](./ARCHITECTURE.md) - Overview of the typed_identifier module
- [IdentifierTypeInterface](../src/IdentifierTypeInterface.php) - Plugin interface definition
- [TypedIdentifierItem](../src/Plugin/Field/FieldType/TypedIdentifierItem.php) - Field type with normalization logic
