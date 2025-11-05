# Usage Examples

This document provides practical examples for using the Typed Identifier module.

## Table of Contents

1. [Basic Field Setup](#basic-field-setup)
2. [Author/Researcher Identifiers](#authorresearcher-identifiers)
3. [Publication Identifiers](#publication-identifiers)
4. [Views Configuration](#views-configuration)
5. [Programmatic Usage](#programmatic-usage)
6. [Twig Templates](#twig-templates)

---

## Basic Field Setup

### Example 1: Simple Field with All Identifier Types

1. Navigate to: Structure → Content types → Article → Manage fields
2. Add field:
   - Label: "Identifiers"
   - Machine name: `field_identifiers`
   - Type: Typed Identifier
3. Field settings:
   - Allowed identifier types: (leave empty for all)
   - Enforce uniqueness: No
4. Save and configure display

### Example 2: Restricted Field for Author IDs Only

1. Add field: `field_author_ids`
2. Field settings:
   - Allowed identifier types: Check only:
     - ✓ ORCID
     - ✓ Scopus Author ID
     - ✓ ResearcherID
     - ✓ OpenAlex ID
   - Enforce uniqueness: Yes
3. Widget settings:
   - Item type selection: Dropdown
4. Manage display:
   - Formatter: Label + Prefix
   - Display as link: Yes
   - Open in new window: Yes

---

## Author/Researcher Identifiers

### Scenario: User Profile with Researcher IDs

**Setup:**
- Entity: User
- Field: `field_researcher_ids`
- Allowed types: ORCID, Scopus, ResearcherID, OpenAlex
- Cardinality: Unlimited
- Enforce uniqueness: Yes

**Configuration:**
```
Field Settings:
  ✓ Enforce uniqueness
  Allowed types: orcid, scopus, researcherid, openalex

Widget:
  Display: Dropdown

Formatter (Default display):
  Type: Label + Prefix
  ✓ Display as link
  ✓ Open in new window
```

**Result:**
```
ORCID: https://orcid.org/0000-0003-4777-5172
Scopus Author ID: https://www.scopus.com/authid/detail.uri?authorId=12345678900
ResearcherID: https://www.webofscience.com/wos/author/record/A-1234-2023
OpenAlex ID: https://openalex.org/A1234567890
```

---

## Publication Identifiers

### Scenario: Article Node with Output IDs

**Setup:**
- Entity: Node (Article)
- Field: `field_output_ids`
- Allowed types: DOI, ISBN, ISSN, PubMed, URN
- Cardinality: Unlimited
- Enforce uniqueness: No (same article may appear in multiple systems)

**Configuration:**
```
Field Settings:
  Allowed types: doi, isbn, issn, pubmed, urn

Widget:
  Display: Dropdown
  Placeholder (type): Select identifier type
  Placeholder (value): Enter identifier value

Formatter (Full content):
  Type: Prefix
  ✓ Display as link

Formatter (Teaser):
  Type: Label
  Display as link: No
```

**Example Data:**
```
Type: doi        Value: 10.1021/acs.joc.3c02691
Type: pubmed     Value: 38234567
Type: isbn       Value: 9780123456789
```

---

## Views Configuration

### Example 1: List of Researchers by ORCID

**View Type:** Table

**Fields:**
- User: Name
- field_researcher_ids (itemtype) - Display: Formatted label
- field_researcher_ids (itemvalue) - Display: Formatted as link

**Filter:**
- field_researcher_ids (itemtype) - Operator: Is one of - Value: ORCID
- Expose this filter to visitors: Yes

**Sort:**
- User: Name (Ascending)

**Result:**
```
| Name          | ID Type | ID Value                                  |
|---------------|---------|-------------------------------------------|
| Jane Smith    | ORCID   | https://orcid.org/0000-0003-4777-5172    |
| John Doe      | ORCID   | https://orcid.org/0000-0002-1234-5678    |
```

### Example 2: Publications with Contextual Filter

**View Type:** Unformatted list
**Path:** `/publications/{itemtype}/{itemvalue}`

**Fields:**
- Content: Title (linked)
- field_output_ids (itemtype)
- field_output_ids (itemvalue)

**Contextual Filters:**
1. field_output_ids (itemtype)
   - When missing: Display all results
   - Validator: None
2. field_output_ids (itemvalue)
   - When missing: Display all results
   - Validator: None

**Usage:**
- `/publications/doi/10.1021/acs.joc.3c02691` - Shows articles with that DOI
- `/publications/pubmed/38234567` - Shows articles with that PubMed ID
- `/publications` - Shows all publications

### Example 3: Cross-Entity Relationship (Static JOIN)

**Scenario:** Show all Publications authored by Users with matching ORCID

**Base Entity:** User

**Fields:**
- User: Name
- Content: Title (via relationship)

**Relationships:**
1. Add: field_researcher_ids → Typed Identifier relationship
   - Match on identifier type: ORCID
   - Target entity type: Content
   - Target field: field_author_ids
   - Relationship ID: publications_via_orcid

**Filters:**
- (Publications) Content: Published - Yes

**Result:**
Shows all published articles where the author's ORCID in `field_author_ids` matches the user's ORCID in `field_researcher_ids`.

### Example 4: Contextual Filter - Research Outputs Block on Profile Pages

**Scenario:** Show research outputs that match the viewed profile's identifiers

**View Type:** Block
**Base Entity:** Node (research_output content type)

**Fields:**
- Content: Title (linked)
- Content: Created

**Contextual Filter (Argument):**
1. Add: field_author_typed_ids → Typed Identifier: Entity Match
   - **Identifier type to match**: openalex (or orcid, scopus, etc.)
   - **Source entity type**: node
   - **Source field**: field_profile_typed_ids
   - **Match all values**: No (match ANY)
   - **When the filter value is NOT available**:
     - Action: **Display all results** (the plugin handles invalid cases automatically)
   - **Provide default value**: Yes
     - Type: Content ID from URL
   - **Validator**: None (the plugin validates and excludes results for invalid entities)

**Filters:**
- Content: Published - Yes
- Content: Type - research_output

**Sort:**
- Content: Created (descending)

**Display:** Block

**Block Placement:**
- Region: Content
- Pages: `/node/*` (or use Visibility conditions for basic_profile content type)

**How it Works:**
1. User visits profile page at `/node/123` (basic_profile node)
2. The contextual filter extracts node ID `123` from the URL
3. It loads the profile node and reads `field_profile_typed_ids`
4. It finds all OpenAlex IDs in that field (e.g., `A5000537533`)
5. The view filters to show research_output nodes where `field_author_typed_ids` contains that OpenAlex ID
6. Only matching research outputs are displayed

**Important Configuration Notes:**
- The contextual filter must be added on the **field being filtered** (in the view's base entity)
- For research_output view: Use `field_author_typed_ids` table/field in the contextual filter
- The `source_field` setting points to the **context entity's field** (profile's `field_profile_typed_ids`)
- This creates a dynamic filter based on the URL's node
- **The plugin automatically excludes results** when the entity is invalid or doesn't have the required field
- Use block visibility settings to restrict the block to the appropriate content types for better performance

**Result:**
Each profile page automatically shows only the research outputs where the author IDs match the profile's IDs.

---

## Programmatic Usage

### Load and Display Identifier Values

```php
// Load an entity.
$node = \Drupal\node\Entity\Node::load(123);

// Get typed identifier field values.
$identifiers = $node->get('field_output_ids')->getValue();

foreach ($identifiers as $identifier) {
  $type = $identifier['itemtype'];
  $value = $identifier['itemvalue'];

  // Get plugin instance.
  $plugin_manager = \Drupal::service('plugin.manager.typed_identifier.identifier_type');
  $plugin = $plugin_manager->createInstance($type);

  // Build URL.
  $url = $plugin->buildUrl($value);
  $label = $plugin->getLabel();

  print "{$label}: {$url}\n";
}
```

### Validate Identifier Before Saving

```php
use Drupal\node\Entity\Node;

// Create node with identifier.
$node = Node::create([
  'type' => 'article',
  'title' => 'My Article',
  'field_output_ids' => [
    ['itemtype' => 'doi', 'itemvalue' => '10.1021/acs.joc.3c02691'],
    ['itemtype' => 'pubmed', 'itemvalue' => '38234567'],
  ],
]);

// Validation happens automatically on save.
$violations = $node->validate();

if ($violations->count() > 0) {
  foreach ($violations as $violation) {
    \Drupal::messenger()->addError($violation->getMessage());
  }
}
else {
  $node->save();
}
```

### Query Entities by Identifier

```php
// Find all articles with a specific DOI.
$query = \Drupal::entityQuery('node')
  ->condition('type', 'article')
  ->condition('field_output_ids.itemtype', 'doi')
  ->condition('field_output_ids.itemvalue', '10.1021/acs.joc.3c02691')
  ->accessCheck(TRUE);

$nids = $query->execute();
$nodes = \Drupal\node\Entity\Node::loadMultiple($nids);
```

### Check for Duplicate Identifiers

```php
/**
 * Check if identifier already exists.
 */
function check_identifier_exists($entity_type, $bundle, $field_name, $itemtype, $itemvalue) {
  $query = \Drupal::entityQuery($entity_type)
    ->condition('type', $bundle)
    ->condition("{$field_name}.itemtype", $itemtype)
    ->condition("{$field_name}.itemvalue", $itemvalue)
    ->accessCheck(FALSE);

  $ids = $query->execute();

  return !empty($ids);
}

// Usage
$exists = check_identifier_exists('node', 'article', 'field_output_ids', 'doi', '10.1021/test');
```

---

## Twig Templates

### Display Identifiers in Node Template

```twig
{# node--article.html.twig #}

{% if content.field_output_ids %}
  <div class="identifiers">
    <h3>{{ 'Identifiers'|t }}</h3>
    {{ content.field_output_ids }}
  </div>
{% endif %}
```

### Custom Identifier Display

```twig
{# In a custom template #}

{% if node.field_researcher_ids %}
  <div class="researcher-ids">
    <h4>{{ 'Researcher Profiles'|t }}</h4>
    <ul>
    {% for item in node.field_researcher_ids %}
      <li>
        <strong>{{ item.itemtype }}:</strong>
        <span>{{ item.itemvalue }}</span>
      </li>
    {% endfor %}
    </ul>
  </div>
{% endif %}
```

### Conditional Display by Type

```twig
{% for item in node.field_output_ids %}
  {% if item.itemtype == 'doi' %}
    <div class="doi-badge">
      DOI: <a href="https://doi.org/{{ item.itemvalue }}">{{ item.itemvalue }}</a>
    </div>
  {% endif %}
{% endfor %}
```

---

## Advanced Examples

### Example: Auto-populate ORCID from User Login

```php
/**
 * Implements hook_user_login().
 */
function my_module_user_login($account) {
  // If user logged in via ORCID OAuth, populate field_researcher_ids.
  $orcid = get_orcid_from_session(); // Your OAuth logic

  if ($orcid) {
    $user = \Drupal\user\Entity\User::load($account->id());

    // Check if ORCID already exists.
    $existing = FALSE;
    foreach ($user->get('field_researcher_ids') as $item) {
      if ($item->itemtype === 'orcid' && $item->itemvalue === $orcid) {
        $existing = TRUE;
        break;
      }
    }

    if (!$existing) {
      $user->field_researcher_ids->appendItem([
        'itemtype' => 'orcid',
        'itemvalue' => $orcid,
      ]);
      $user->save();
    }
  }
}
```

### Example: Display Badge Based on Identifier Type

```php
/**
 * Implements hook_preprocess_node().
 */
function my_module_preprocess_node(&$variables) {
  $node = $variables['node'];

  if ($node->hasField('field_output_ids')) {
    $badges = [];

    foreach ($node->get('field_output_ids') as $item) {
      $plugin_manager = \Drupal::service('plugin.manager.typed_identifier.identifier_type');
      $plugin = $plugin_manager->createInstance($item->itemtype);

      $badges[] = [
        'type' => $item->itemtype,
        'label' => $plugin->getLabel(),
        'url' => $plugin->buildUrl($item->itemvalue),
        'value' => $item->itemvalue,
      ];
    }

    $variables['identifier_badges'] = $badges;
  }
}
```

```twig
{# In node template #}
{% if identifier_badges %}
  <div class="identifier-badges">
    {% for badge in identifier_badges %}
      <a href="{{ badge.url }}" class="badge badge-{{ badge.type }}">
        {{ badge.label }}
      </a>
    {% endfor %}
  </div>
{% endif %}
```

---

## Tips and Best Practices

1. **Use unique field names** for different identifier purposes (e.g., `field_author_ids` vs `field_output_ids`)
2. **Enable uniqueness** for author/researcher identifiers to prevent duplicates
3. **Disable uniqueness** for publication identifiers (same article in multiple databases)
4. **Use Views relationships** to connect entities via shared identifiers
5. **Expose filters** in Views for user-facing identifier searches
6. **Use contextual filters** for clean URLs like `/researcher/orcid/0000-0003-4777-5172`
7. **Choose appropriate formatters** - use "Link" for clickable identifiers, "Label" for compact display
