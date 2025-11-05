<?php

namespace Drupal\typed_identifier;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;

/**
 * Provides the IdentifierType plugin manager.
 */
class IdentifierTypePluginManager extends DefaultPluginManager {

  /**
   * Constructs a new IdentifierTypePluginManager object.
   *
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   Cache backend instance to use.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler to invoke the alter hook with.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    parent::__construct(
      'Plugin/IdentifierType',
      $namespaces,
      $module_handler,
      'Drupal\typed_identifier\IdentifierTypeInterface',
      'Drupal\typed_identifier\Annotation\IdentifierType'
    );

    $this->alterInfo('typed_identifier_identifier_type_info');
    $this->setCacheBackend($cache_backend, 'typed_identifier_identifier_types');
  }

}
