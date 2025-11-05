# Plugin Development Guide

This guide explains how to create custom identifier type plugins for the Typed Identifier module.

## Plugin Architecture

The Typed Identifier module uses Drupal's Plugin API to allow developers to define new identifier types with custom validation rules and URL prefixes.

### Plugin Components

1. **IdentifierTypeInterface** - Defines the required methods
2. **IdentifierType Annotation** - Plugin discovery metadata
3. **IdentifierTypePluginBase** - Base class with common functionality
4. **IdentifierTypePluginManager** - Manages plugin discovery and instantiation

## Creating a Custom Plugin

### Step 1: Create the Plugin Class

Create a new file in your module at `src/Plugin/IdentifierType/YourIdentifierType.php`:

```php
<?php

namespace Drupal\your_module\Plugin\IdentifierType;

use Drupal\typed_identifier\IdentifierTypePluginBase;

/**
 * Provides a [Your Identifier] identifier type.
 *
 * @IdentifierType(
 *   id = "your_identifier",
 *   label = @Translation("Your Identifier"),
 *   prefix = "https://example.com/id/",
 *   validation_regex = "^[A-Z0-9]{8}$",
 *   description = @Translation("Description of your identifier format")
 * )
 */
class YourIdentifierType extends IdentifierTypePluginBase {

}
```

### Step 2: Configure the Annotation

The `@IdentifierType` annotation has these properties:

- **id** (required): Machine name (lowercase, underscores)
- **label** (required): Human-readable name (translatable)
- **prefix** (required): URL prefix for building links (empty string for none)
- **validation_regex** (required): Regular expression for validation (empty string for XSS-only)
- **description** (optional): Help text shown to users

### Step 3: Add Custom Validation (Optional)

If you need validation beyond regex matching, override the `validate()` method:

```php
class YourIdentifierType extends IdentifierTypePluginBase {

  /**
   * {@inheritdoc}
   */
  public function validate($value) {
    // First run the parent validation (regex check).
    if (!parent::validate($value)) {
      return FALSE;
    }

    // Add custom validation logic.
    // Example: Check a checksum digit
    $checksum = $this->calculateChecksum($value);
    $expected = substr($value, -1);

    return $checksum === $expected;
  }

  /**
   * Calculate checksum for identifier.
   */
  protected function calculateChecksum($value) {
    // Your checksum algorithm here.
    return '0';
  }

}
```

### Step 4: Override buildUrl() (Optional)

If your identifier URL requires custom formatting:

```php
class YourIdentifierType extends IdentifierTypePluginBase {

  /**
   * {@inheritdoc}
   */
  public function buildUrl($value) {
    // Custom URL building logic.
    $formatted_value = strtoupper($value);
    return $this->getPrefix() . 'lookup/' . $formatted_value;
  }

}
```

## Examples

### Example 1: Simple Identifier with Regex

```php
/**
 * @IdentifierType(
 *   id = "arXiv",
 *   label = @Translation("arXiv"),
 *   prefix = "https://arxiv.org/abs/",
 *   validation_regex = "^\d{4}\.\d{4,5}(v\d+)?$",
 *   description = @Translation("arXiv identifier (e.g., 2301.12345)")
 * )
 */
class ArxivIdentifierType extends IdentifierTypePluginBase {

}
```

### Example 2: Identifier with Custom Validation

```php
/**
 * @IdentifierType(
 *   id = "viaf",
 *   label = @Translation("VIAF"),
 *   prefix = "https://viaf.org/viaf/",
 *   validation_regex = "^\d{1,22}$",
 *   description = @Translation("Virtual International Authority File")
 * )
 */
class ViafIdentifierType extends IdentifierTypePluginBase {

  /**
   * {@inheritdoc}
   */
  public function validate($value) {
    // VIAF IDs must be numeric and within valid range.
    if (!parent::validate($value)) {
      return FALSE;
    }

    // Additional check: VIAF IDs are typically less than 20 digits.
    return strlen($value) <= 20;
  }

}
```

### Example 3: Identifier Without URL Prefix

```php
/**
 * @IdentifierType(
 *   id = "internal_id",
 *   label = @Translation("Internal ID"),
 *   prefix = "",
 *   validation_regex = "^INT-\d{6}$",
 *   description = @Translation("Internal identifier (INT-XXXXXX)")
 * )
 */
class InternalIdIdentifierType extends IdentifierTypePluginBase {

}
```

### Example 4: Identifier with Complex URL Building

```php
/**
 * @IdentifierType(
 *   id = "handle",
 *   label = @Translation("Handle"),
 *   prefix = "https://hdl.handle.net/",
 *   validation_regex = "^\d+(\.\d+)?/\S+$",
 *   description = @Translation("Handle System identifier")
 * )
 */
class HandleIdentifierType extends IdentifierTypePluginBase {

  /**
   * {@inheritdoc}
   */
  public function buildUrl($value) {
    // Handles may need special encoding.
    $encoded_value = rawurlencode($value);
    return $this->getPrefix() . $encoded_value;
  }

}
```

## Regular Expression Tips

### Common Patterns

- **Digits only**: `^\d+$`
- **Alphanumeric**: `^[A-Za-z0-9]+$`
- **With hyphens**: `^[A-Z0-9-]+$`
- **Fixed length**: `^[A-Z]{2}\d{6}$` (2 letters + 6 digits)
- **Range**: `^\d{4,8}$` (4 to 8 digits)
- **Optional prefix**: `^(PREFIX-)?[A-Z0-9]+$`

### Validation Regex Best Practices

1. **Use anchors**: Start with `^` and end with `$`
2. **Be specific**: Match exact format, not partial matches
3. **Test thoroughly**: Use online regex testers
4. **Document format**: Add examples in description
5. **Escape special chars**: Use `\` for `.`, `(`, `)`, etc.

## Testing Your Plugin

### Clear Cache

After creating a plugin, clear caches:

```bash
drush cr
```

### Verify Plugin Discovery

Check that your plugin is discovered:

```php
$plugin_manager = \Drupal::service('plugin.manager.typed_identifier.identifier_type');
$definitions = $plugin_manager->getDefinitions();
print_r($definitions['your_identifier']);
```

### Test Validation

```php
$plugin = $plugin_manager->createInstance('your_identifier');
$is_valid = $plugin->validate('TEST1234');
```

### Test URL Building

```php
$url = $plugin->buildUrl('TEST1234');
// Should output: https://example.com/id/TEST1234
```

## Plugin Discovery

Plugins are discovered automatically if placed in:
- `[your_module]/src/Plugin/IdentifierType/`
- Namespace: `Drupal\[your_module]\Plugin\IdentifierType`

No additional configuration needed - the PluginManager handles discovery.

## Altering Existing Plugins

Use `hook_typed_identifier_identifier_type_info_alter()` to modify plugin definitions:

```php
/**
 * Implements hook_typed_identifier_identifier_type_info_alter().
 */
function my_module_typed_identifier_identifier_type_info_alter(array &$info) {
  // Change ORCID prefix to sandbox environment.
  if (isset($info['orcid'])) {
    $info['orcid']['prefix'] = 'https://sandbox.orcid.org/';
  }
}
```

## Advanced: Dependency Injection

For complex plugins requiring services:

```php
class ComplexIdentifierType extends IdentifierTypePluginBase implements ContainerFactoryPluginInterface {

  /**
   * The HTTP client.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * Constructs a ComplexIdentifierType object.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ClientInterface $http_client) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->httpClient = $http_client;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('http_client')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function validate($value) {
    // Use HTTP client to validate against external API.
    try {
      $response = $this->httpClient->get('https://api.example.com/validate/' . $value);
      return $response->getStatusCode() === 200;
    }
    catch (\Exception $e) {
      return FALSE;
    }
  }

}
```

## Best Practices

1. **Keep it simple**: Extend `IdentifierTypePluginBase` for basic plugins
2. **Document validation**: Clearly describe the expected format
3. **Handle errors gracefully**: Return FALSE on validation failures
4. **Test edge cases**: Empty strings, special characters, maximum length
5. **Follow standards**: Use official identifier specifications
6. **Provide examples**: Add example values in the description
7. **Consider internationalization**: Use `@Translation()` for user-facing text

## Resources

- [Drupal Plugin API Documentation](https://www.drupal.org/docs/drupal-apis/plugin-api)
- [Identifier.org Registry](https://registry.identifiers.org/)
- [ORCID Documentation](https://info.orcid.org/)
- [DOI Handbook](https://www.doi.org/doi-handbook/)
