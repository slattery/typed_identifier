# Field Formatters for Typed Identifier Fields

This document describes the available field formatters for typed_identifier fields and how to use them in the "Manage Display" UI.

## Table of Contents

1. [Overview](#overview)
2. [Basic Display Formatters](#basic-display-formatters)
3. [Entity Reference Formatters](#entity-reference-formatters)
4. [Configuration Guide](#configuration-guide)
5. [Common Use Cases](#common-use-cases)

---

## Overview

Typed identifier fields support multiple formatters for displaying identifier values. Formatters fall into two categories:

1. **Basic Display Formatters** - Display the identifier itself (type, value, or both)
2. **Entity Reference Formatters** - Find and display entities that match the typed identifier via URN matching

The entity reference formatters work similarly to the Views relationship plugin, enabling you to display entities in the "Manage Display" UI that share matching typed identifiers with the current entity.

---

## Basic Display Formatters

### Prefix Formatter

**Formatter ID:** `typed_identifier_prefix`

**Display:** Full URL with identifier prefix
- Example: `https://orcid.org/0000-0001-2345-6789`

**Settings:**
- **Display as link** - Render the prefix as a clickable hyperlink
- **Open in new window** - Add `target="_blank"` to links
- **Filter by identifier types** - Show only selected identifier types (leave empty for all)

**Use Cases:**
- Simple display of identifier URLs
- Quick access to identifier registration systems

---

### Label Formatter

**Formatter ID:** `typed_identifier_label`

**Display:** Identifier type label + value
- Example: `ORCID: 0000-0001-2345-6789`

**Settings:**
- **Display as link** - Render as a clickable hyperlink
- **Open in new window** - Add `target="_blank"` to links
- **Filter by identifier types** - Show only selected identifier types

**Use Cases:**
- Display human-readable identifier names with values
- Distinguish between different identifier types in multi-value fields

---

### Label + Prefix Formatter

**Formatter ID:** `typed_identifier_label_prefix`

**Display:** Identifier type label + full URL
- Example: `ORCID: https://orcid.org/0000-0001-2345-6789`

**Settings:**
- **Display as link** - Render as a clickable hyperlink
- **Open in new window** - Add `target="_blank"` to links
- **Filter by identifier types** - Show only selected identifier types

**Use Cases:**
- Comprehensive display with both human-readable label and full URL
- Print-friendly output that shows complete information

---

## Entity Reference Formatters

These formatters find entities that share matching typed identifiers with the current entity and display them in the Manage Display UI. They use URN-based matching, the same mechanism as the Views relationship plugin.

### Entity Reference Formatter (Rendered Entity)

**Formatter ID:** `typed_identifier_entity`

**Display:** Full rendered entity using a selected view mode

**Settings:**
- **Identifier type** - Optionally filter to match only a specific identifier type. Leave empty to match all types via URN.
- **Target bundle** - The content type to match entities from (required)
- **Target field** - The typed_identifier field on the target entity to match against (required). Fields are grouped by content type.
- **View mode** - Choose how to display the matched entity (default, teaser, card, etc.)
- **Link to matched entity** - Optionally wrap the entity title with a link (applies mainly to simple view modes)

**Behavior:**
- For each identifier in the field, queries the target field table for entities with matching URNs
- Returns the **first matching entity only** per field item
- Renders the matched entity using the selected view mode
- If no match is found, no output is displayed for that item
- Includes proper cache tags for cache invalidation when matched entities change

**Example Configuration:**
```
Target Bundle: Profile
Target Field: field_profile_typed_ids
Identifier Type: (any type)
View Mode: card
```

**Result:** If a "Research Output" node has an author ORCID that matches a "Profile" node's ORCID in its `field_profile_typed_ids` field, the Profile will be displayed using the "card" view mode.

**Use Cases:**
- Display author profiles/information matched by researcher IDs
- Show related research outputs matched by publication IDs
- Create networked content displays based on identifier relationships
- Display full entity information without needing Views relationships

---

### Entity Label/Link Formatter

**Formatter ID:** `typed_identifier_entity_label`

**Display:** Matched entity title as a link to the entity

**Settings:**
- **Identifier type** - Optionally filter to match only a specific identifier type. Leave empty to match all types.
- **Target bundle** - The content type to match entities from (required)
- **Target field** - The typed_identifier field on the target entity to match against (required)

**Behavior:**
- For each identifier in the field, finds the first matching entity via URN matching
- Displays only the entity title as a clickable link
- Lightweight alternative to the full rendered entity formatter
- Includes proper cache tags for invalidation

**Example Configuration:**
```
Target Bundle: Publication
Target Field: field_publication_ids
Identifier Type: (any type)
```

**Result:** If a "Publication" node has a DOI that matches a "Dataset" node's DOI in `field_publication_ids`, the Publication title will be displayed as a link.

**Use Cases:**
- Quick links to related entities
- Compact display of matched entities
- Lists of related publications or authors

---

### Entity ID Formatter

**Formatter ID:** `typed_identifier_entity_id`

**Display:** Matched entity ID (node ID, user ID, etc.)

**Settings:**
- **Identifier type** - Optionally filter to match only a specific identifier type
- **Target bundle** - The content type to match entities from (required)
- **Target field** - The typed_identifier field on the target entity to match against (required)

**Behavior:**
- For each identifier in the field, finds the first matching entity via URN matching
- Displays only the entity ID (numeric)
- Most lightweight option, useful for programmatic use
- Includes proper cache tags

**Example Configuration:**
```
Target Bundle: Article
Target Field: field_article_ids
Identifier Type: doi
```

**Result:** Displays the node ID of matched articles.

**Use Cases:**
- Generating machine-readable relationships
- Passing entity IDs to JavaScript or APIs
- Lightweight data export

---

## Configuration Guide

### Setting Up an Entity Reference Formatter

1. **Navigate to Manage Display:**
   - Go to: Structure → Content types → [Your Type] → Manage display

2. **Configure the Formatter:**
   - Click the formatter settings icon for a typed_identifier field
   - Select one of the entity reference formatters

3. **Select Target Configuration:**
   - **Target bundle** (required): The content type of entities to match
   - **Target field** (required): A typed_identifier field on that content type to match against
   - **Identifier type** (optional): Filter to a specific identifier type (e.g., "orcid", "doi")

4. **Select Display Options:**
   - For "Entity Reference (rendered)" formatter: Choose the view mode
   - Other formatters have fewer options

5. **Save and Test:**
   - View your entity to see matched results
   - Matched entities appear inline where the formatter is configured

### Troubleshooting

**No matched entities showing:**
- Verify the target field has values with matching URNs
- Check that the target bundle and field names are correct
- Ensure the entity with the typed_identifier field has content

**Cache not updating:**
- The formatters include proper cache tags
- Clear caches after making configuration changes: `drush cache:rebuild`

---

## Common Use Cases

### Use Case 1: Display Author Profiles on Publication Pages

**Scenario:** Show author profile cards on a publication node

**Setup:**
```
Entity: Publication (node)
Field: field_author_ids (typed_identifier)
Target: Profile content type with field_profile_ids field

Formatter: Entity Reference (Rendered Entity)
  - Target Bundle: Profile
  - Target Field: field_profile_ids
  - View Mode: card
```

**Result:** Each author ORCID on the publication is matched against profile nodes, and the matching profile is displayed as a card.

---

### Use Case 2: Link Publications on Author Profiles

**Scenario:** Show linked list of publications authored by a researcher

**Setup:**
```
Entity: Profile (node)
Field: field_author_ids (typed_identifier)
Target: Publication content type with field_author_ids field

Formatter: Entity Label (via typed identifier)
  - Target Bundle: Publication
  - Target Field: field_author_ids
  - Identifier Type: (leave blank for all types)
```

**Result:** Each researcher ID on the profile is matched against publication nodes, and matching publications are displayed as links.

---

### Use Case 3: Cross-Reference Research Outputs by Publication ID

**Scenario:** Show all datasets that cite a publication

**Setup:**
```
Entity: Publication (node)
Field: field_publication_ids (typed_identifier, contains DOI)
Target: Dataset content type with field_cited_publication_ids field

Formatter: Entity ID (via typed identifier)
  - Target Bundle: Dataset
  - Target Field: field_cited_publication_ids
  - Identifier Type: doi
```

**Result:** The DOI from the publication is matched against datasets, and the matching dataset IDs are displayed.

---

### Use Case 4: Display All Related Identifiers with Filter

**Scenario:** Show only publications with a specific identifier type

**Setup:**
```
Entity: Author Profile (node)
Field: field_work_ids (typed_identifier, mixed types)
Target: Publication content type with field_work_ids

Formatter: Entity Label (via typed identifier)
  - Target Bundle: Publication
  - Target Field: field_work_ids
  - Identifier Type: doi (only match DOIs)
```

**Result:** Only publications whose DOI matches one of the author's DOI identifiers will be displayed.

---

## Relationship Between Field Formatters and Views Relationships

Both the entity reference formatters and the Views relationship plugin use the same URN-based matching mechanism:

1. **Views Relationship Plugin:** Used in Views to join entities for filtering/sorting/field exposure
2. **Field Formatters:** Used in Manage Display to render related entities inline

**When to use each:**
- Use **Views relationships** when you need to filter views, expose related entity fields, or sort by related entity data
- Use **Field formatters** when you want to display related entities directly on the entity page (Manage Display)
- You can use both together: display a entity reference formatter for inline display, and also create a view showing more related content

---

## Performance Considerations

- **Entity Reference Formatters** query the target field table for each identifier value. For fields with many values, this can result in multiple database queries.
- All formatters include appropriate **cache tags** that automatically invalidate when the matched entity is modified
- For display-heavy pages with many relationships, consider using Views blocks as an alternative for better query optimization

---

## Notes on Caching

- All entity reference formatters include proper cache tags for the matched entities
- Cache is tagged with: `node:123` (for the matched entity) and `field_config:node.BUNDLE.FIELD` (for the field configuration)
- Drupal automatically invalidates the display when matched entities are modified or deleted
