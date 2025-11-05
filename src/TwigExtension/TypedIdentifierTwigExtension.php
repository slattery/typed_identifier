<?php

namespace Drupal\typed_identifier\TwigExtension;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

/**
 * Twig extension for typed identifier field filtering.
 */
class TypedIdentifierTwigExtension extends AbstractExtension {

  /**
   * {@inheritdoc}
   */
  public function getFilters() {
    return [
      new TwigFilter('pluck_types', [$this, 'pluckTypes']),
    ];
  }

  /**
   * Filters a typed identifier field by specific identifier types.
   *
   * This filter accepts a field item list and returns only items that match
   * the specified identifier type(s). The result can be chained with other
   * Twig filters or field formatters.
   *
   * @param mixed $field_items
   *   The field item list (FieldItemListInterface) or renderable array.
   * @param string|array $types
   *   A single type string (e.g., 'doi') or array of types (e.g., ['doi',
   *   'openalex']).
   *
   * @return array
   *   Filtered field items as an array.
   *
   * @code
   * {# Filter to show only DOI identifiers #}
   * {{ content.field_identifiers|pluck_types('doi') }}
   *
   * {# Filter to show DOI and OpenAlex identifiers #}
   * {{ content.field_identifiers|pluck_types(['doi', 'openalex']) }}
   *
   * {# Chain with field_value for rendering #}
   * {{ content.field_identifiers|pluck_types('orcid')|field_value }}
   * @endcode
   */
  public function pluckTypes($field_items, $types) {
    // Normalize types to array.
    if (!is_array($types)) {
      $types = [$types];
    }

    // If field_items is empty or not iterable, return empty array.
    if (empty($field_items) || !is_iterable($field_items)) {
      return [];
    }

    $filtered = [];

    // Handle FieldItemListInterface objects.
    if (is_object($field_items) && method_exists($field_items, 'referencedEntities')) {
      // This is likely a field item list.
      foreach ($field_items as $delta => $item) {
        if (isset($item->itemtype) && in_array($item->itemtype, $types)) {
          $filtered[$delta] = $item;
        }
      }
    }
    // Handle renderable arrays (content.field_name format).
    elseif (is_array($field_items)) {
      // Check if this is a render array with #items.
      if (isset($field_items['#items']) && is_iterable($field_items['#items'])) {
        foreach ($field_items['#items'] as $delta => $item) {
          if (isset($item->itemtype) && in_array($item->itemtype, $types)) {
            // Copy the delta item from the render array.
            if (isset($field_items[$delta])) {
              $filtered[$delta] = $field_items[$delta];
            }
          }
        }
        // Preserve render array metadata if present.
        if (isset($field_items['#theme'])) {
          $filtered['#theme'] = $field_items['#theme'];
        }
        if (isset($field_items['#field_type'])) {
          $filtered['#field_type'] = $field_items['#field_type'];
        }
        if (isset($field_items['#field_name'])) {
          $filtered['#field_name'] = $field_items['#field_name'];
        }
      }
      else {
        // Fallback: iterate array items.
        foreach ($field_items as $delta => $item) {
          if (is_numeric($delta) && is_object($item) && isset($item->itemtype) && in_array($item->itemtype, $types)) {
            $filtered[$delta] = $item;
          }
        }
      }
    }

    return $filtered;
  }

  /**
   * {@inheritdoc}
   */
  public function getName() {
    return 'typed_identifier_twig_extension';
  }

}
