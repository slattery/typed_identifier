<?php

namespace Drupal\typed_identifier\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Validation constraint for typed identifiers.
 *
 * @Constraint(
 *   id = "TypedIdentifierValidation",
 *   label = @Translation("Typed Identifier Validation", context = "Validation")
 * )
 */
class TypedIdentifierValidation extends Constraint {

  /**
   * Message when itemvalue is empty but itemtype is set.
   *
   * @var string
   */
  public $emptyValue = 'The identifier value cannot be empty when an identifier type is selected.';

  /**
   * Message when itemtype is empty but itemvalue is set.
   *
   * @var string
   */
  public $emptyType = 'The identifier type cannot be empty when an identifier value is provided.';

  /**
   * Message when the format is invalid.
   *
   * @var string
   */
  public $invalidFormat = 'The value "%value" is not a valid %type identifier.';

  /**
   * Message when the identifier type is unknown.
   *
   * @var string
   */
  public $unknownType = 'Unknown identifier type: %type';

  /**
   * Message when the identifier is not unique (per-entity scope).
   *
   * @var string
   */
  public $notUniquePerEntity = 'The identifier %type:%value already exists in this entity\'s field.';

  /**
   * Message when the identifier is not unique (global scope).
   *
   * @var string
   */
  public $notUniqueGlobal = 'The identifier %type:%value already exists in another entity.';

}
