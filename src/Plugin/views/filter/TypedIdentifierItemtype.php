<?php

namespace Drupal\typed_identifier\Plugin\views\filter;

use Drupal\views\Plugin\views\filter\InOperator;

/**
 * Filter handler for typed identifier itemtype.
 *
 * @ViewsFilter("typed_identifier_itemtype")
 */
class TypedIdentifierItemtype extends InOperator {

  /**
   * {@inheritdoc}
   */
  public function getValueOptions() {
    if (isset($this->valueOptions)) {
      return $this->valueOptions;
    }

    $plugin_manager = \Drupal::service('plugin.manager.typed_identifier.identifier_type');
    $definitions = $plugin_manager->getDefinitions();

    $this->valueOptions = [];
    foreach ($definitions as $id => $definition) {
      $this->valueOptions[$id] = $definition['label'];
    }

    return $this->valueOptions;
  }

}
