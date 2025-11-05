 Correct Contextual Filter Configuration

  Step 1: Add the Contextual Filter
  1. In the view UI, add a new contextual filter
  2. Select: Content: Author Typed IDs (field_author_typed_ids) - this is the field on research_output nodes
  3. Click "Add and configure contextual filter"

  Step 2: Configure the Filter
  In the configuration form:
  - When the filter value is NOT available: Select "Hide view" (or whatever you prefer)
  - When the filter value IS available:
    - Select "Typed Identifier Entity Match"
    - Configure the plugin settings:
        - Identifier type to match: Select "- Any type -" (or specific type like "orcid")
      - Source entity type: node
      - Source field: field_profile_typed_ids (this is the field on profile nodes to GET values FROM)
      - Match all values: Unchecked (match ANY value)

  Step 3: Provide Default Argument
  - Select "Provide default argument"
  - Type: "Content ID from URL"

  This configuration means:
  - The filter operates on field_author_typed_ids (the research_output's author field)
  - It gets URN values FROM field_profile_typed_ids (the profile node's identifier field)
  - When viewing /node/123 (a profile), it loads that node's field_profile_typed_ids values
  - It filters research_output nodes where field_author_typed_ids URNs match those URN values

  The key insight from your clarification: the contextual filter is added on the target field (field_author_typed_ids)
  but configured to pull values from the source field (field_profile_typed_ids) via the source_field setting.

  Does this match your intended setup? Once configured this way, the view should show research outputs that share
  identifier values with the profile being viewed.