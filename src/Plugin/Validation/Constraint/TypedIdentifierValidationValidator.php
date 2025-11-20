<?php

namespace Drupal\typed_identifier\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates the TypedIdentifier constraint.
 */
class TypedIdentifierValidationValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate($item, Constraint $constraint) {
    $itemtype = $item->get('itemtype')->getValue();
    $itemvalue = $item->get('itemvalue')->getValue();

    // If itemtype is set, itemvalue must not be empty.
    if (!empty($itemtype) && empty($itemvalue)) {
      $this->context->addViolation($constraint->emptyValue);
      return;
    }

    // If itemvalue is set, itemtype must not be empty.
    if (!empty($itemvalue) && empty($itemtype)) {
      $this->context->addViolation($constraint->emptyType);
      return;
    }

    // If both are empty, field is empty (valid).
    if (empty($itemtype) && empty($itemvalue)) {
      return;
    }

    // Parse compound itemtype values for generic type (format: 'generic:key').
    // This needs to happen before plugin lookup.
    $plugin_itemtype = $itemtype;
    if (str_contains($itemtype, ':')) {
      $parts = explode(':', $itemtype, 2);
      if ($parts[0] === 'generic' && !empty($parts[1])) {
        // Use base type for plugin lookup.
        $plugin_itemtype = 'generic';
      }
    }

    // Validate against plugin regex.
    $plugin_manager = \Drupal::service('plugin.manager.typed_identifier.identifier_type');
    try {
      $plugin = $plugin_manager->createInstance($plugin_itemtype);

      // Normalize itemvalue before validation to support URL input.
      // This allows users to enter full URLs which will be stripped later.
      $normalized_value = $this->normalizeItemvalue($itemvalue, $plugin_itemtype, $plugin);

      if (!$plugin->validate($normalized_value)) {
        $this->context->addViolation($constraint->invalidFormat, [
          '%type' => $itemtype,
          '%value' => $itemvalue,
        ]);
      }
    }
    catch (\Exception $e) {
      // Plugin not found.
      $this->context->addViolation($constraint->unknownType, ['%type' => $itemtype]);
    }

    // Check uniqueness based on configured scope.
    // Note: Pass the normalized plugin_itemtype for accurate uniqueness checking.
    // After preSave(), the stored itemtype will be the base type (e.g., 'generic'),
    // not the compound value (e.g., 'generic:arxiv').
    $field_definition = $item->getFieldDefinition();
    $uniqueness_scope = $field_definition->getSetting('uniqueness_scope') ?? 'entity';

    if ($uniqueness_scope === 'entity') {
      $this->validatePerEntityUniqueness($item, $constraint, $plugin_itemtype);
    }
    elseif ($uniqueness_scope === 'bundle') {
      $this->validateBundleUniqueness($item, $constraint, $plugin_itemtype);
    }
  }

  /**
   * Validates per-entity uniqueness of itemtype:itemvalue pair.
   *
   * Prevents duplicate pairs within the same entity's field.
   *
   * @param mixed $item
   *   The field item being validated.
   * @param \Symfony\Component\Validator\Constraint $constraint
   *   The constraint.
   * @param string $normalized_itemtype
   *   The normalized base itemtype (e.g., 'generic' instead of 'generic:key').
   */
  protected function validatePerEntityUniqueness($item, Constraint $constraint, $normalized_itemtype) {
    $entity = $item->getEntity();
    $field_name = $item->getFieldDefinition()->getName();
    $itemvalue = $item->get('itemvalue')->getValue();

    // Get all values in this field on the same entity.
    $field_values = $entity->get($field_name);

    // Count occurrences of this itemtype:itemvalue pair in the field.
    // Note: Compare against normalized type since that's what gets stored.
    $count = 0;
    foreach ($field_values as $field_item) {
      $stored_type = $field_item->get('itemtype')->getValue();

      // Normalize the stored type for comparison (in case of compound values).
      if (str_contains($stored_type, ':')) {
        $parts = explode(':', $stored_type, 2);
        if ($parts[0] === 'generic') {
          $stored_type = 'generic';
        }
      }

      if (
        $stored_type === $normalized_itemtype &&
        $field_item->get('itemvalue')->getValue() === $itemvalue
      ) {
        $count++;
      }
    }

    // If we found more than one occurrence, it's a duplicate.
    if ($count > 1) {
      $this->context->addViolation($constraint->notUniquePerEntity, [
        '%type' => $normalized_itemtype,
        '%value' => $itemvalue,
      ]);
    }
  }

  /**
   * Validates global uniqueness of itemtype:itemvalue pair.
   *
   * Global uniqueness encompasses per-entity uniqueness, so checks both.
   * First validates within the same entity, then across all other entities.
   *
   * @param mixed $item
   *   The field item being validated.
   * @param \Symfony\Component\Validator\Constraint $constraint
   *   The constraint.
   * @param string $normalized_itemtype
   *   The normalized base itemtype (e.g., 'generic' instead of 'generic:key').
   */
  protected function validateBundleUniqueness($item, Constraint $constraint, $normalized_itemtype) {
    // Bundle uniqueness includes per-entity uniqueness.
    // Check within the same entity first.
    $this->validatePerEntityUniqueness($item, $constraint, $normalized_itemtype);

    // If no per-entity violation, check across other entities in the same bundle.
    $entity = $item->getEntity();
    $field_name = $item->getFieldDefinition()->getName();
    $itemvalue = $item->get('itemvalue')->getValue();

    // Query for entities with the same normalized itemtype and itemvalue.
    $query = \Drupal::entityQuery($entity->getEntityTypeId())
      ->condition($field_name . '.itemtype', $normalized_itemtype)
      ->condition($field_name . '.itemvalue', $itemvalue)
      ->accessCheck(FALSE);

    // Exclude current entity.
    if (!$entity->isNew()) {
      $query->condition($entity->getEntityType()->getKey('id'), $entity->id(), '<>');
    }

    // Filter by bundle if entity has bundles.
    if ($entity->getEntityType()->hasKey('bundle')) {
      $query->condition($entity->getEntityType()->getKey('bundle'), $entity->bundle());
    }

    $existing = $query->count()->execute();

    if ($existing > 0) {
      $this->context->addViolation($constraint->notUniqueBundle, [
        '%type' => $normalized_itemtype,
        '%value' => $itemvalue,
      ]);
    }
  }

  /**
   * Normalizes itemvalue by detecting and stripping known formats.
   *
   * This mirrors the normalization logic in TypedIdentifierItem::preSave()
   * to allow validation to work with full URLs and URN formats.
   *
   * @param string $itemvalue
   *   The raw itemvalue input.
   * @param string $itemtype
   *   The plugin ID (itemtype).
   * @param object $plugin
   *   The identifier type plugin instance.
   *
   * @return string
   *   The normalized itemvalue.
   */
  protected function normalizeItemvalue($itemvalue, $itemtype, $plugin) {
    $plugin_id = strtolower($itemtype);
    $prefix = $plugin->getPrefix();

    // Handle four input formats:
    // 1. URN format: "doi:10.1234/example" or "DOI:10.1234/example".
    // 2. URL format (HTTPS): "https://doi.org/10.1234/example".
    // 3. URL format (HTTP): "http://doi.org/10.1234/example".
    // 4. Bare ID: "10.1234/example".
    // Check 1: URN format (plugin_id:value) - case insensitive.
    if (str_starts_with(strtolower($itemvalue), $plugin_id . ':')) {
      return substr($itemvalue, strlen($plugin_id) + 1);
    }
    // Check 2: Exact prefix match (HTTPS).
    elseif (!empty($prefix) && str_starts_with($itemvalue, $prefix)) {
      return substr($itemvalue, strlen($prefix));
    }
    // Check 3: HTTP variant of prefix.
    elseif (!empty($prefix) && str_contains($prefix, 'https://')) {
      $http_prefix = str_replace('https://', 'http://', $prefix);
      if (str_starts_with($itemvalue, $http_prefix)) {
        return substr($itemvalue, strlen($http_prefix));
      }
    }

    // Bare ID format - return as-is.
    return $itemvalue;
  }

}
