# Generic Type Support for Unknown Identifiers

This guide explains how to handle identifier types during migration that don't have corresponding plugins in the typed_identifier module.

## Enhanced Generic Support (Per-Value Label Selection)

**NEW**: As of the latest update, the module now supports per-value custom label selection for generic identifiers.

### How It Works

When you configure `custom_generic_labels` in field settings, each label appears as a separate option in the widget dropdown:

**Field Settings:**
```yaml
field.field.node.article.field_identifiers:
  settings:
    custom_generic_labels:
      - key: 'preprint'
        label: 'Preprint Archive'
      - key: 'hal'
        label: 'HAL Repository'
```

**Widget Dropdown:**
- `<option value="generic:preprint">Custom: Preprint Archive</option>`
- `<option value="generic:hal">Custom: HAL Repository</option>`

**Storage & URN Generation:**
1. User selects "Custom: Preprint Archive" from dropdown
2. Form submits with `itemtype = "generic:preprint"`
3. preSave() parses this to extract the label key
4. Stores `itemtype = "generic"` in database
5. Generates URN: `generic:preprint:2301.12345`

**Benefits:**
- Users can choose which custom label to use for each value
- No schema changes required
- Backward compatible with existing data
- URNs preserve the custom label for searchability

### Migration Support

You can now use the `generic:key` pattern in migrations to specify which custom label to use:

```yaml
process:
  field_identifiers:
    plugin: typed_identifier
    itemtype: 'generic:preprint'  # Specify the exact custom label.
    itemvalue: source_identifier_value
```

Or map dynamically:
```yaml
process:
  field_identifiers:
    -
      plugin: static_map
      source: source_identifier_type
      map:
        arxiv: 'generic:preprint'
        hal: 'generic:hal'
      bypass: true
    -
      plugin: typed_identifier
      itemtype: '@source_identifier_type'
      itemvalue: source_identifier_value
```

---

## The Problem

When migrating data into a `typed_identifier` field, each identifier must have a corresponding plugin (e.g., `doi`, `orcid`, `isbn`). If you migrate an unknown type, the field's `preSave()` method encounters this:

```php
$plugin_manager = \Drupal::service('plugin.manager.typed_identifier.identifier_type');
if (!$plugin_manager->hasDefinition($itemtype)) {
  return;  // Exits early - no processing happens
}
```

### Consequences of Unknown Types

When `itemtype` doesn't match a plugin:
- ✅ `itemtype` and `itemvalue` are stored in the database
- ❌ No URL normalization happens
- ❌ No validation happens
- ❌ **No URN is generated** (stays empty)

Since the `urn` field is indexed and used for searching/matching, this creates problems for:
- Views filtering by identifier
- Deduplication logic
- The `TypedIdentifierRelationship` views plugin
- Any code that relies on the URN field

## Solutions

You have four options for handling unknown identifier types during migration:

---

## Option 1: Static Mapping in Migration

**Best for**: Small number of unknown types that should all map to 'generic'

Convert unknown identifier types to 'generic' using Drupal's `static_map` process plugin.

### How It Works

The migration pipeline transforms the itemtype before it reaches the TypedIdentifier process plugin:

```
Source data (itemtype='arxiv')
  → static_map (arxiv → generic)
  → TypedIdentifier plugin (itemtype='generic')
  → Field preSave (hasDefinition('generic') = TRUE ✅)
  → URN generated: 'generic:2301.12345'
```

### Migration Example

```yaml
process:
  field_identifiers:
    -
      # First, map unknown types to 'generic'.
      plugin: static_map
      source: source_identifier_type
      map:
        arxiv: generic
        hal: generic
        ssrn: generic
      bypass: true  # Pass through known types unchanged.
    -
      # Then process with typed_identifier.
      plugin: typed_identifier
      itemtype: '@source_identifier_type'
      itemvalue: source_identifier_value
```

### Using custom_generic_labels with Per-Value Selection

**ENHANCED**: You can now configure multiple custom labels and specify which one to use per identifier:

**Field Settings** (via UI or config):
```yaml
field.field.node.article.field_identifiers:
  settings:
    custom_generic_labels:
      - key: 'preprint'
        label: 'Preprint Archive'
      - key: 'hal'
        label: 'HAL Repository'
```

**Migration with Specific Label Selection:**
```yaml
process:
  field_identifiers:
    -
      plugin: static_map
      source: source_identifier_type
      map:
        arxiv: 'generic:preprint'  # Maps to "Preprint Archive".
        hal: 'generic:hal'          # Maps to "HAL Repository".
      bypass: true
    -
      plugin: typed_identifier
      itemtype: '@source_identifier_type'
      itemvalue: source_identifier_value
```

This generates URNs like:
- ArXiv → `generic:preprint:2301.12345`
- HAL → `generic:hal:hal-01234567`

**Each identifier can now use a different custom label!**

### Pros
- ✅ Simple to implement
- ✅ No code changes required
- ✅ Can use custom_generic_labels for better URN clarity
- ✅ Works with existing migration tools
- ✅ **NEW**: Can distinguish between different unknown types using different custom labels
- ✅ **NEW**: Per-value custom label selection in both UI and migrations

### Cons
- ❌ **Loses original type information** (arxiv becomes generic in storage, but preserved in URN)
- ❌ Migration config becomes complex with many types
- ❌ Requires pre-configuring custom labels in field settings

---

## Option 2: Create Identifier Type Plugins

**Best for**: Identifier types you'll use long-term and want proper validation/formatting

Create a proper plugin for each identifier type you need to support.

### How It Works

Add new plugins to `typed_identifier/src/Plugin/IdentifierType/` for your identifier types:

```
Plugin created (ArxivIdentifierType)
  → Migration uses itemtype='arxiv'
  → Field preSave (hasDefinition('arxiv') = TRUE ✅)
  → Plugin normalizes, validates, generates URL
  → URN: 'arxiv:2301.12345'
```

### Example: ArXiv Plugin

**File**: `src/Plugin/IdentifierType/ArxivIdentifierType.php`

```php
<?php

namespace Drupal\typed_identifier\Plugin\IdentifierType;

use Drupal\typed_identifier\IdentifierTypePluginBase;

/**
 * Provides an ArXiv identifier type.
 *
 * @IdentifierType(
 *   id = "arxiv",
 *   label = @Translation("ArXiv"),
 *   prefix = "https://arxiv.org/abs/",
 *   validation_regex = "^\d{4}\.\d{4,5}(v\d+)?$",
 *   description = @Translation("ArXiv preprint identifier")
 * )
 */
class ArxivIdentifierType extends IdentifierTypePluginBase {

}
```

### Migration Example

Once the plugin exists, migration is straightforward:

```yaml
process:
  field_identifiers:
    plugin: typed_identifier
    itemtype: source_identifier_type  # Can contain 'arxiv' now.
    itemvalue: source_identifier_value
```

The plugin will:
- Accept URLs: `https://arxiv.org/abs/2301.12345` → `2301.12345`
- Validate format: Must match `YYYY.NNNNN` pattern
- Generate URN: `arxiv:2301.12345`

### Pros
- ✅ **Preserves original type** (arxiv stays arxiv)
- ✅ Full URL normalization support
- ✅ Format validation
- ✅ Type-specific prefixes for link generation
- ✅ Clean, semantic URNs
- ✅ Reusable for manual entry (not just migration)

### Cons
- ❌ Requires PHP code for each type
- ❌ Need to know validation patterns for each identifier
- ❌ More upfront development time

---

## Option 3: Automatic Fallback in Process Plugin

**Best for**: Quick migration with many unknown types, where losing type info is acceptable

Enhance the TypedIdentifier process plugin to automatically convert unknown types to 'generic'.

### How It Works

The process plugin checks if the type exists before passing to the field:

```
Migration (itemtype='arxiv')
  → TypedIdentifier plugin checks hasDefinition('arxiv') = FALSE
  → Auto-converts to itemtype='generic'
  → Field preSave (hasDefinition('generic') = TRUE ✅)
  → URN: 'generic:2301.12345'
```

### Implementation

Add to `src/Plugin/migrate/process/TypedIdentifier.php`:

```php
public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
  // ... existing code to determine $itemtype and $itemvalue ...

  // Check if itemtype has a plugin definition.
  $plugin_manager = \Drupal::service('plugin.manager.typed_identifier.identifier_type');
  if (!$plugin_manager->hasDefinition($itemtype)) {
    // Convert unknown types to 'generic'.
    $itemtype = 'generic';
  }

  return [
    'itemtype' => $itemtype,
    'itemvalue' => $itemvalue,
  ];
}
```

### Migration Example

No special handling needed in migration config:

```yaml
process:
  field_identifiers:
    plugin: typed_identifier
    itemtype: source_identifier_type  # Any value accepted.
    itemvalue: source_identifier_value
```

### Enhanced: custom_generic_labels

**RESOLVED**: The limitation of using only the first custom label has been addressed.

The field's `custom_generic_labels` setting now supports per-value label selection. When using the process plugin with `generic:key` pattern, you can specify which custom label to use:

```yaml
process:
  field_identifiers:
    -
      plugin: static_map
      source: source_identifier_type
      map:
        arxiv: 'generic:preprint'
        hal: 'generic:hal'
        ssrn: 'generic:working_paper'
      bypass: true
    -
      plugin: typed_identifier
      itemtype: '@source_identifier_type'
      itemvalue: source_identifier_value
```

**This now means**:
- Field configured with multiple custom labels
- Migration has: arxiv, hal, ssrn (all unknown)
- Each gets a **different** URN:
  - ArXiv → `generic:preprint:VALUE`
  - HAL → `generic:hal:VALUE`
  - SSRN → `generic:working_paper:VALUE`
- You **can** distinguish between arxiv/hal/ssrn after migration via URN

**Fallback Behavior**: If no custom label key is specified (backward compatibility), the system uses the first configured label.

### Pros
- ✅ Automatic - no migration config changes needed
- ✅ Handles unlimited unknown types
- ✅ Guarantees URN generation for all identifiers
- ✅ No failed migrations due to unknown types

### Cons
- ❌ **Loses all original type information**
- ❌ All unknowns share the same generic label
- ❌ Can't distinguish between different unknown types after migration
- ❌ Requires modifying the process plugin code

---

## Option 3b: Enhanced Dynamic Labels (Proposed)

**Best for**: Preserving original type names while using generic fallback

This is a proposed enhancement that would preserve the original unknown type name in the URN.

### Concept

Instead of just converting to 'generic', preserve the original type as a dynamic label:

```
Migration (itemtype='arxiv', itemvalue='2301.12345')
  → TypedIdentifier plugin detects unknown type
  → Stores: itemtype='generic', itemvalue='2301.12345'
  → Passes original_type='arxiv' to field somehow
  → Field preSave generates: URN = 'generic:arxiv:2301.12345'
```

### Challenges

This requires field-level enhancements:

1. **Storage**: Need a place to store the original type per-value
   - Add `original_type` column to field schema? (breaks existing data)
   - Encode in itemvalue? (e.g., `arxiv:2301.12345`) - breaks normalization
   - Use a separate reference field? (complex)

2. **Custom Label Selection**: Need to support per-value label selection
   - Current `getCustomGenericLabel()` only returns first label
   - Would need to match original_type to custom_generic_labels array
   - Requires refactoring the preSave() logic

3. **Migration Plugin Interface**: Need to pass original type through
   - Process plugin needs to capture and pass original type
   - Field needs to accept and store it

### Proposed Implementation Outline

**Enhanced Field Schema**:
```php
'original_type' => [
  'type' => 'varchar',
  'length' => 255,
  'not null' => FALSE,
  'description' => 'Original identifier type before generic conversion',
],
```

**Enhanced preSave()**:
```php
if ($itemtype === 'generic') {
  $original_type = $this->get('original_type')->getValue();
  if ($original_type) {
    $sanitized = preg_replace('/[^a-z0-9]+/i', '-', $original_type);
    $urn = strtolower("generic:{$sanitized}:{$itemvalue}");
  }
  else {
    // Fallback to existing custom label logic.
  }
}
```

**Enhanced Process Plugin**:
```php
// Capture original type before conversion.
$original_type = $itemtype;

if (!$plugin_manager->hasDefinition($itemtype)) {
  $itemtype = 'generic';
}

return [
  'itemtype' => $itemtype,
  'itemvalue' => $itemvalue,
  'original_type' => ($itemtype === 'generic') ? $original_type : NULL,
];
```

### Pros
- ✅ Preserves original type information
- ✅ Generates semantic URNs: `generic:arxiv:VALUE`
- ✅ Can distinguish between different unknown types
- ✅ Automatic fallback like Option 3
- ✅ Searchable/filterable by original type

### Cons
- ❌ **Requires field schema changes** (breaking change)
- ❌ Significant development work
- ❌ More complex field logic
- ❌ Migration from Option 1/2/3 to 3b would require data transformation

---

## Comparison Table

| Aspect | Option 1: Static Map | Option 2: Create Plugins | Option 3: Auto Fallback | Option 3b: Enhanced (Proposed) |
|--------|---------------------|-------------------------|------------------------|-------------------------------|
| **Preserves Original Type** | ⚠️ In URN only | ✅ Yes (fully) | ⚠️ In URN only | ✅ Yes (in URN) |
| **URL Normalization** | Limited (generic only) | ✅ Full | Limited (generic only) | Limited (generic only) |
| **Format Validation** | Limited (generic only) | ✅ Full | Limited (generic only) | Limited (generic only) |
| **Development Effort** | Low (config only) | Medium (plugin per type) | Low (small code change) | High (field changes) |
| **Migration Complexity** | Medium (map config) | Low (straightforward) | Low (automatic) | Low (automatic) |
| **Distinguishable Types** | ✅ **Yes (via generic:key)** | ✅ Yes | ✅ **Yes (via generic:key)** | ✅ Yes (via URN) |
| **URN Format** | `generic:KEY:VALUE` | `TYPE:VALUE` | `generic:KEY:VALUE` | `generic:ORIGTYPE:VALUE` |
| **Per-Value Label Selection** | ✅ **Yes (NEW)** | N/A (unique plugins) | ✅ **Yes (NEW)** | ✅ Yes |
| **Reusable for Manual Entry** | ✅ Yes | ✅ Yes | ✅ Yes | ✅ Yes |
| **Breaking Changes** | ❌ No | ❌ No | ❌ No | ⚠️ Yes (schema) |

**Note**: Options 1 and 3 now support the `generic:key` pattern, providing per-value custom label selection without schema changes. This addresses the primary limitation described in the previous version of this document.

---

## Recommendations

### Choose Option 1 (Static Map) if:
- You have a known set of unknown types that should be categorized
- You want to distinguish between them using different custom labels (**NOW SUPPORTED**)
- You want a pure configuration-based solution
- You're okay with manual mapping in migration YAML
- **NEW**: This option now provides per-value label selection via `generic:key` pattern

### Choose Option 2 (Create Plugins) if:
- The identifier types will be used long-term
- You need proper URL normalization and validation
- You want clean, semantic URNs with type-specific prefixes
- You can invest time in creating plugins
- **This is still the recommended approach for production use with standardized identifier types**

### Choose Option 3 (Auto Fallback) if:
- You have many unknown types from diverse sources
- You need a quick migration without pre-configuration
- You can configure custom labels beforehand and map unknown types to them
- **NEW**: With `generic:key` support, you can now distinguish between types after migration
- You accept modifying the process plugin code

### Choose Option 3b (Enhanced - Future) if:
- You need truly automatic preservation of original type names without pre-configuration
- You want automatic handling of unknowns without defining custom labels
- You can handle breaking schema changes
- **Note**: The `generic:key` pattern now addresses most use cases that would have required Option 3b, making this enhancement lower priority

---

## Migration Examples

### Example 1: CSV with Mixed Known/Unknown Types

**CSV Data**:
```csv
id,title,identifier_type,identifier_value
1,Paper A,doi,10.1234/example
2,Paper B,arxiv,2301.12345
3,Paper C,orcid,0000-0001-2345-6789
4,Paper D,hal,hal-01234567
```

**Option 1 Migration** (Static Map with Per-Value Label Selection):
```yaml
source:
  plugin: csv
  path: 'identifiers.csv'
  ids: [id]

process:
  field_identifiers:
    -
      plugin: static_map
      source: identifier_type
      map:
        arxiv: 'generic:preprint'   # Uses "Preprint Archive" label.
        hal: 'generic:hal'           # Uses "HAL Repository" label.
      bypass: true
    -
      plugin: typed_identifier
      itemtype: '@identifier_type'
      itemvalue: identifier_value
```

**Result URNs:**
- ArXiv: `generic:preprint:2301.12345`
- HAL: `generic:hal:hal-01234567`
- DOI: `doi:10.1234/example`
- ORCID: `orcid:0000-0001-2345-6789`

**Option 2 Migration** (After Creating Plugins):
```yaml
# First create ArxivIdentifierType.php and HalIdentifierType.php.

source:
  plugin: csv
  path: 'identifiers.csv'
  ids: [id]

process:
  field_identifiers:
    plugin: typed_identifier
    itemtype: identifier_type  # All types supported.
    itemvalue: identifier_value
```

**Option 3 Migration** (Auto Fallback - after plugin modification):
```yaml
source:
  plugin: csv
  path: 'identifiers.csv'
  ids: [id]

process:
  field_identifiers:
    plugin: typed_identifier
    itemtype: identifier_type  # Unknowns auto-convert to generic.
    itemvalue: identifier_value
```

### Example 2: Multi-value Field with JSON Array

**Source Data**:
```json
{
  "id": 1,
  "title": "Research Paper",
  "identifiers": [
    {"type": "doi", "value": "10.1234/example"},
    {"type": "arxiv", "value": "2301.12345"},
    {"type": "custom_repo", "value": "REPO-123"}
  ]
}
```

**Option 1 Migration** (with Per-Value Label Selection):
```yaml
process:
  field_identifiers:
    -
      plugin: sub_process
      source: identifiers
      process:
        temp_type:
          plugin: static_map
          source: type
          map:
            arxiv: 'generic:preprint'        # ArXiv → Preprint Archive.
            custom_repo: 'generic:repository' # Custom → Repository.
          bypass: true
        result:
          plugin: typed_identifier
          itemtype: '@temp_type'
          itemvalue: value
```

**Result**: Each identifier gets its appropriate custom label in the URN.

**Option 2 or 3**:
```yaml
process:
  field_identifiers:
    plugin: sub_process
    source: identifiers
    process:
      plugin: typed_identifier
      itemtype: type
      itemvalue: value
```

---

## Conclusion

The best approach depends on your specific needs:

- **Production systems with well-defined identifier types**: Use **Option 2** (Create Plugins)
- **Quick migrations with many unknown types that need categorization**: Use **Option 1** (Static Map) with `generic:key` pattern - now supports per-value label selection
- **Automatic fallback with categorization**: Use **Option 3** (Auto Fallback) with pre-configured custom labels
- **Future enhancement for truly automatic type preservation**: Consider contributing **Option 3b** implementation (though less critical now)

### Updated Guidance

With the new `generic:key` pattern support:
- **Option 1** is now significantly more powerful, allowing you to distinguish between different unknown identifier types
- **Option 2** is still recommended for standardized, long-term identifier types requiring validation and URL normalization
- **Option 3** can now preserve type information via custom labels when configured properly
- **Option 3b** is less critical since most use cases are now covered by the `generic:key` pattern

For most cases involving unknown identifier types, **Option 1 with per-value custom labels** provides an excellent balance of flexibility, maintainability, and ease of implementation. For standardized identifier types, **Option 2** remains the gold standard.
