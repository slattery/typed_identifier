# Typed Identifier

Provides a flexible field type for storing typed identifier pairs (type + value) such as ORCID, DOI, ISBN, and other standardized identifiers.

## Features

- **Pluggable identifier types** with built-in validation
- **10 default identifier plugins**: ORCID, DOI, Scopus, ResearcherID, OpenAlex, ISBN, ISSN, PubMed, URN, Generic
- **Flexible formatters**: Display as raw values, with prefixes, or as clickable links
- **Type filtering ("plucking")**: Control which identifier types are displayed in formatters, Views, and Twig templates
- **Field-level configuration**: Restrict allowed identifier types per field
- **Uniqueness enforcement**: Optional per-field constraint to prevent duplicates
- **Views integration**: Field handlers, filters, sorts, arguments, and relationships
- **Twig filter**: `pluck_types` filter for custom template filtering
- **Custom identifier types**: Easily create your own identifier plugins

## Installation

1. Download and enable the module:
   ```bash
   composer require drupal/typed_identifier
   drush en typed_identifier
   ```

2. Add the field type to any entity:
   - Navigate to Structure → Content types → [Your content type] → Manage fields
   - Add a new field of type "Typed Identifier"
   - Configure field settings (uniqueness, allowed types, custom labels)

## Usage

### Basic Configuration

**Field Settings:**
- **Enforce uniqueness**: Prevent duplicate itemtype:itemvalue pairs within this field
- **Allowed identifier types**: Restrict which identifier types are available (leave empty for all)
- **Custom labels for Generic type**: Define custom labels for the Generic identifier plugin

**Widget Settings:**
- **Item type selection widget**: Choose between dropdown or autocomplete
- **Placeholders**: Optional placeholder text for type and value inputs

**Formatter Settings:**
- **Display as link**: Show identifier as clickable link (when prefix is available)
- **Open in new window**: Add target="_blank" to links
- **Filter by identifier types**: Select which types to display (leave empty for all/global defaults)

### Default Identifier Types

| Type | Label | Example | URL Prefix |
|------|-------|---------|------------|
| netid | NetID | xyz14 | (none) |
| upi | UPI | 0123456789 | (none) |
| orcid | ORCID | 0000-0003-4777-5172 | https://orcid.org/ |
| doi | DOI | 10.1021/acs.joc.3c02691 | https://doi.org/ |
| scopus | Scopus Author ID | 12345678900 | https://www.scopus.com/authid/detail.uri?authorId= |
| researcherid | ResearcherID | A-1234-2023 | https://www.webofscience.com/wos/author/record/ |
| openalex | OpenAlex ID | W1234567890 | https://openalex.org/ |
| isbn | ISBN | 9780123456789 | https://isbnsearch.org/isbn/ |
| issn | ISSN | 1234-5678 | https://portal.issn.org/resource/ISSN/ |
| pubmed | PubMed ID | 12345678 | https://pubmed.ncbi.nlm.nih.gov/ |
| urn | URN | urn:isbn:0451450523 | (none) |
| generic | Custom | (any) | (none) |

### Generic Type with Per-Value Custom Labels

The Generic identifier type is special - it allows you to define custom identifier types without creating new plugins. As of the latest update, you can configure multiple custom labels and select which one to use for each identifier value.

**Configuration:**
1. Navigate to Structure → Content types → [Your content type] → Manage fields
2. Edit your Typed Identifier field
3. In "Custom labels for Generic type", enter custom key|label pairs, one per line:
   ```
   arxiv|arXiv Preprint
   hal|HAL Repository
   ssrn|SSRN Paper
   ```
4. Save the field settings

**How It Works:**

When you add custom labels, each label appears as a separate option in the widget dropdown:
- `Custom: arXiv Preprint`
- `Custom: HAL Repository`
- `Custom: SSRN Paper`

**Storage & Display:**
1. User selects "Custom: arXiv Preprint" from dropdown
2. User enters value: `2301.12345`
3. Form submits with compound value: `generic:arxiv`
4. System stores:
   - `itemtype`: `generic` (normalized for queries)
   - `itemvalue`: `2301.12345`
   - `urn`: `generic:arxiv:2301.12345` (preserves the label key)
5. Formatters display the custom label: "arXiv Preprint: 2301.12345"

**Benefits:**
- **Per-value selection**: Each identifier can have a different custom label
- **Efficient queries**: All generic identifiers stored with same `itemtype = 'generic'`
- **Label preservation**: URN field preserves which custom label was selected
- **Formatter support**: Labels displayed automatically without formatter configuration

**Example Use Case:**

A repository storing diverse preprints might configure:
```
arxiv|arXiv
biorxiv|bioRxiv
medrxiv|medRxiv
chemrxiv|ChemRxiv
```

Then each article can have the appropriate preprint identifier with the correct label:
- Article A: `generic:biorxiv:10.1101/2024.01.001` displays as "bioRxiv: 10.1101/2024.01.001"
- Article B: `generic:arxiv:2301.12345` displays as "arXiv: 2301.12345"

**Note:** If no custom labels are configured, the generic type option is hidden from the dropdown.

### Views Integration

**Field Handlers:**
- Display itemtype as raw plugin ID or formatted label
- Display itemvalue as raw value, with prefix, or as clickable link
- Multiple values displayed comma-delimited or as separate rows

**Filters:**
- Filter by identifier type (dropdown)
- Filter by identifier value (text input with contains/equals/starts with operators)

**Sorts:**
- Sort by identifier type (alphabetically by plugin ID)
- Sort by identifier value (alphabetically)

**Contextual Filters (Arguments):**
- Filter by itemtype: `/view-path/{itemtype}`
- Filter by itemvalue: `/view-path/{itemvalue}`
- Combined: `/view-path/{itemtype}/{itemvalue}`
- **Entity Match** (new): Show entities that share identifier values with another entity
  - Perfect for blocks on profile pages
  - Example: Show research outputs that share ORCID values with the profile being viewed
  - Configuration: Set "Provide default value" to "Content ID from URL"

**Relationships:**
- Join entities that share the same identifier
- Select which identifier type to match on (prevents duplicate rows)
- Choose target entity type and field
- Example: Relate Author profiles to Publications via shared ORCID

### Type Filtering ("Plucking")

Control which identifier types are displayed across different contexts while still storing all types.

**Global Configuration:**
Navigate to `/admin/config/content/typed-identifier` to set default display types site-wide.
- Empty = show all types
- Individual formatters can override global defaults

**Formatter-Level Filtering:**
Configure per view mode in field display settings:
- "DOI List" view mode → filter to DOI only
- "Contact Info" view mode → filter to ORCID and ResearcherID
- "Full Details" view mode → show all types

**Views Field Filtering:**
In Views field settings, select which identifier types to display in that field column.
- **Note:** This filters VALUES within the field, not which ROWS appear in the view
- Use Views Filters to control which rows appear

**Twig Template Filtering:**
Use the `pluck_types` Twig filter for custom theming:

```twig
{# Show only DOI identifiers #}
{{ content.field_identifiers|pluck_types('doi')|field_value }}

{# Show multiple types #}
{{ content.field_identifiers|pluck_types(['doi', 'openalex'])|field_value }}

{# Conditional display #}
{% if content.field_identifiers|pluck_types('doi')|length > 0 %}
  <h3>DOIs:</h3>
  {{ content.field_identifiers|pluck_types('doi')|field_value }}
{% endif %}

{# Loop through filtered values #}
{% set orcids = content.field_identifiers|pluck_types('orcid') %}
{% for delta, item in orcids if delta is numeric %}
  <div class="orcid">{{ item['#title'] }}</div>
{% endfor %}
```

**Filtering Hierarchy:**
1. Field settings (input) → controls what can be stored
2. Global defaults → site-wide display preference
3. Formatter settings → per-view-mode display
4. Twig filter → per-template display (highest priority)

### Creating Custom Identifier Plugins

Create a new plugin in `src/Plugin/IdentifierType/`:

```php
<?php

namespace Drupal\my_module\Plugin\IdentifierType;

use Drupal\typed_identifier\IdentifierTypePluginBase;

/**
 * Provides a custom identifier type.
 *
 * @IdentifierType(
 *   id = "my_identifier",
 *   label = @Translation("My Identifier"),
 *   prefix = "https://example.com/id/",
 *   validation_regex = "^[A-Z]{2}\d{6}$",
 *   description = @Translation("Custom identifier format: 2 letters + 6 digits")
 * )
 */
class MyIdentifierType extends IdentifierTypePluginBase {

  /**
   * Override validate() method if you need custom validation.
   */
  public function validate($value) {
    // Custom validation logic here.
    return parent::validate($value);
  }

}
```

## Use Cases

### Author Identifiers
Create a `field_author_ids` field on User entities:
- Allow only: ORCID, Scopus, ResearcherID, OpenAlex
- Enforce uniqueness to prevent duplicate researcher profiles

### Publication Identifiers
Create a `field_publication_ids` field on Article nodes:
- Allow only: DOI, ISBN, ISSN, PubMed, URN
- Multiple values to support various identifier systems

### Cross-Entity Relationships

**Method 1: Views Relationship (static JOIN)**
Use Views relationships to connect entities:
- Profile nodes with `field_researcher_ids`
- Publication nodes with `field_output_ids`
- Relationship configured to match on "ORCID"
- Results: Publications automatically linked to researcher profiles

**Method 2: Contextual Filter (dynamic blocks)**
Create blocks that show related content on profile pages:
1. Create a View of research outputs (base entity: research_output nodes)
2. Add Display: Block
3. Add Contextual Filter: "Typed Identifier: Entity Match"
   - **IMPORTANT**: Add the filter on the field being filtered (e.g., `field_author_typed_ids` if that's the field on research_output nodes)
   - **Table**: `node__field_author_typed_ids` (the field on the view's base entity)
   - **Field**: `field_author_typed_ids_entity_match`
   - **Identifier type**: ORCID (or openalex, scopus, etc.)
   - **Source entity type**: node
   - **Source field**: `field_researcher_ids` (the field on the context entity - the profile being viewed)
   - **Match all values**: No (match ANY)
   - **When the filter value is NOT available**: Display all results (the plugin automatically handles invalid cases)
   - **Provide default value** → "Content ID from URL"
4. Place block on profile node pages (use block visibility settings to restrict to appropriate content types)
5. Result: Automatically shows research outputs that share ORCID values with the profile

**Example: "My Publications" Block**
- View base: research_output nodes
- Contextual filter: On `field_author_typed_ids` (the author field in research outputs)
- Source field: `field_researcher_ids` (the identifier field on profile nodes)
- The view filters research outputs where `field_author_typed_ids` matches ORCID values from the profile node in the URL
- No hard-coding required
- Works automatically for any profile
- Supports multiple ORCID values (shows outputs matching ANY of the profile's ORCIDs)

**Common Mistake to Avoid:**
- ❌ Don't add the contextual filter on the source field (profile's field)
- ✅ Add the contextual filter on the target field (research output's field)
- The `source_field` configuration setting tells it where to GET the values FROM (the profile)

## Requirements

- Drupal 10.4+ or Drupal 11.x
- PHP 8.1+ (tested with PHP 8.2 and 8.3)

## Maintainers

- [Your Name/Organization]

## License

GPL-2.0-or-later
