# TypedIdentifier Migration Plugin

**Plugin ID:** `typed_identifier`
**Module:** typed_identifier (core)
**Purpose:** General-purpose migration helper for creating typed_identifier field values

---

## Overview

The `TypedIdentifier` migrate process plugin provides a simple, reusable way to transform source data into typed_identifier field format. It's designed for general migrations where you need to map source data into `itemtype` and `itemvalue` pairs.

**Key characteristics:**
- Lightweight, general-purpose plugin
- Can be used in any Drupal project with typed_identifier module
- No field dependencies or validation
- Works with simple explicit configuration
- Delegates normalization and validation to the field's preSave() hook

---

## Usage

### Basic Example: Static Configuration

Map a specific source value to a known identifier type:

```yaml
process:
  field_doi:
    plugin: typed_identifier
    itemtype: doi
    itemvalue: source_doi_field
```

**Input:**
```
source_doi_field = "10.1234/example"
```

**Output:**
```php
['itemtype' => 'doi', 'itemvalue' => '10.1234/example']
```

The field's preSave() hook will automatically normalize this (strip URL prefixes if present, validate format, generate URN).

---

### Example: Dynamic itemtype from Source

Extract both type and value from source data:

```yaml
process:
  field_identifiers:
    plugin: typed_identifier
    itemtype: identifier_type    # Source field name
    itemvalue: identifier_value  # Source field name
```

**Input:**
```json
{
  "identifier_type": "orcid",
  "identifier_value": "0000-0001-2345-6789"
}
```

**Output:**
```php
['itemtype' => 'orcid', 'itemvalue' => '0000-0001-2345-6789']
```

---

### Example: Using Pipeline Value as itemvalue

Use the current pipeline value as itemvalue:

```yaml
process:
  field_doi:
    -
      plugin: get
      source: doi_url
    -
      plugin: typed_identifier
      itemtype: doi
```

**Input:**
```
doi_url = "https://doi.org/10.1234/example"
```

**Output:**
```php
['itemtype' => 'doi', 'itemvalue' => 'https://doi.org/10.1234/example']
```

The field's preSave() will normalize the URL to `10.1234/example`.

---

### Example: Multi-Value Fields with sub_process

Process multiple items, extracting type and value from each:

```yaml
process:
  field_identifiers:
    plugin: sub_process
    source: identifier_array
    process:
      value:
        plugin: typed_identifier
        itemtype: type       # Key in the array item
        itemvalue: value     # Key in the array item
```

**Input:**
```json
[
  { "type": "doi", "value": "10.1234/example1" },
  { "type": "orcid", "value": "0000-0001-2345-6789" },
  { "type": "isbn", "value": "978-0-596-00712-6" }
]
```

**Output:**
```php
[
  ['itemtype' => 'doi', 'itemvalue' => '10.1234/example1'],
  ['itemtype' => 'orcid', 'itemvalue' => '0000-0001-2345-6789'],
  ['itemtype' => 'isbn', 'itemvalue' => '978-0-596-00712-6']
]
```

---

## Configuration Options

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `itemtype` | string | Yes* | The identifier type (plugin ID). Can be static (e.g., `"doi"`) or a source field name |
| `itemvalue` | string | No* | The identifier value. Can be static, a source field name, or omitted to use pipeline value |

\* At least one must be provided. See error handling section for details.

---

## How It Works

### Configuration Resolution

When the plugin transforms data, it resolves configuration in this order:

1. **Configuration parameters** - First checks `itemtype` and `itemvalue` in plugin config
2. **Value array** - If value is an array, extracts `itemtype`/`itemvalue` keys
3. **Reference lookup** - If config looks like a field name, looks it up in the value array
4. **Pipeline value** - If no `itemvalue` specified, uses the current pipeline value

---

### Data Flow

```
Source Data
    ↓
Plugin extracts itemtype (from config, value array, or source)
    ↓
Plugin extracts itemvalue (from config, value array, pipeline, or source)
    ↓
Validation: Both values non-empty
    ↓
Return: ['itemtype' => ..., 'itemvalue' => ...]
    ↓
Field's preSave() hook:
  - Normalizes itemvalue (strips known prefixes)
  - Validates against identifier type's regex
  - Generates URN for efficient querying
    ↓
Stored in database
```

---

## Normalization Behavior

The field's preSave() hook automatically normalizes identifier values. The plugin doesn't do this—it just passes values through.

**Example normalization (by the field, not the plugin):**

| Input | Type | Stored Value | URN |
|-------|------|--------------|-----|
| `https://doi.org/10.1234/example` | doi | `10.1234/example` | `doi:10.1234/example` |
| `https://orcid.org/0000-0001-2345-6789` | orcid | `0000-0001-2345-6789` | `orcid:0000-0001-2345-6789` |
| `10.1234/example` | doi | `10.1234/example` | `doi:10.1234/example` |
| `0000-0001-2345-6789` | orcid | `0000-0001-2345-6789` | `orcid:0000-0001-2345-6789` |

All input formats are handled correctly by the field's preSave() hook.

---

## Comparison with ToTypedIdentifier

For more complex scenarios, consider the `to_typed_identifier` plugin from yse_scholarly_works module:

| Feature | typed_identifier | to_typed_identifier |
|---------|------------------|-------------------|
| **Purpose** | Simple, general | Complex OpenAlex data |
| **Input** | Single itemtype/itemvalue pair | Object with multiple ID keys |
| **Field-Aware** | No | Yes (validates against field settings) |
| **Generic Fallback** | No | Yes |
| **Auto-Detection** | No | Yes (for 'id' key) |
| **Handles Multiple** | No (use sub_process) | Yes (extracts all pairs) |
| **Module** | typed_identifier (reusable) | yse_scholarly_works (project-specific) |

**Use TypedIdentifier when:**
- You have explicit itemtype and itemvalue in source data
- You want a lightweight, general-purpose plugin
- You need something reusable across projects

**Use ToTypedIdentifier when:**
- Source data is a single object with multiple identifier keys
- You need field-aware validation
- You want automatic fallback for unknown types
- You need auto-detection for ambiguous identifiers

---

## Error Handling

The plugin throws `MigrateException` for:

### Missing itemtype
```yaml
# ERROR: itemtype not specified
process:
  field_identifiers:
    plugin: typed_identifier
    itemvalue: some_value
```

**Error message:** `The "itemtype" must be specified in configuration or present in the value array.`

### Missing itemvalue
```yaml
# ERROR: itemvalue not specified or available
process:
  field_identifiers:
    plugin: typed_identifier
    itemtype: doi
    # No itemvalue, value not array, no pipeline value
```

**Error message:** `The "itemvalue" must be specified in configuration, present in the value array, or provided via pipeline.`

### Empty values
```yaml
# ERROR: Either value resolved to empty
process:
  field_identifiers:
    plugin: typed_identifier
    itemtype: ''  # Empty
    itemvalue: some_value
```

**Error message:** `The itemtype value is empty or could not be determined.`

---

## Real-World Examples

### Example 1: Simple DOI Migration

Source data contains DOI URLs:

```yaml
process:
  field_primary_identifier:
    plugin: typed_identifier
    itemtype: doi
    itemvalue: doi_url
```

### Example 2: Multiple Identifier Types from Different Sources

```yaml
process:
  field_identifiers:
    -
      plugin: typed_identifier
      itemtype: orcid
      itemvalue: author_orcid
    -
      plugin: typed_identifier
      itemtype: pubmed
      itemvalue: article_pmid
```

### Example 3: DataCite with Dynamic Type

Source contains `{ "id_type": "datacite", "id_value": "10.5281/zenodo.1234567" }`:

```yaml
process:
  field_dataset_identifiers:
    plugin: typed_identifier
    itemtype: id_type
    itemvalue: id_value
```

---

## See Also

- [PARSER_SERVICE.md](PARSER_SERVICE.md) - Auto-detection service for identifying types from values
- [ARCHITECTURE.md](ARCHITECTURE.md) - Overall module architecture
- [PLUGIN_DEVELOPMENT.md](PLUGIN_DEVELOPMENT.md) - Creating custom identifier type plugins
- [Drupal Migrate Process Plugin API](https://www.drupal.org/docs/drupal-apis/migrate-api/migrate-process-plugins)
