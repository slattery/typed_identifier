<?php

namespace Drupal\typed_identifier\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines an IdentifierType annotation object.
 *
 * @Annotation
 */
class IdentifierType extends Plugin {

  /**
   * The plugin ID.
   *
   * @var string
   */
  public $id;

  /**
   * The human-readable name of the identifier type.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $label;

  /**
   * The URL prefix for the identifier.
   *
   * @var string
   */
  public $prefix;

  /**
   * The validation regex pattern.
   *
   * @var string
   */
  public $validation_regex;

  /**
   * A description of the identifier type.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $description;

}
