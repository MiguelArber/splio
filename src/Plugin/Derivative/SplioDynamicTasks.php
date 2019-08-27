<?php

namespace Drupal\splio\Plugin\Derivative;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines dynamic tasks depending on the active entities.
 *
 * @property \Drupal\Core\Config\ConfigFactoryInterface config
 */
class SplioDynamicTasks extends DeriverBase implements ContainerDeriverInterface {

  /**
   * SplioDynamicTasks constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactory $config
   *   Config factory.
   */
  public function __construct(ConfigFactory $config) {
    $this->config = $config->get('splio.entity.config');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id) {
    return new static(
    // Load the services required to construct this class.
      $container->get('config.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {

    $splioEntities = $this->config->get('splio_entities');

    // General configuration task.
    $this->derivatives['system.config.splio.entity_config'] = $base_plugin_definition;
    $this->derivatives['system.config.splio.entity_config']['title'] = "General configuration";
    $this->derivatives['system.config.splio.entity_config']['route_name'] = 'system.config.splio.entity_config';
    $this->derivatives['system.config.splio.entity_config']['base_route'] = 'system.config.splio.entity_config';

    foreach ($splioEntities as $splioEntity => $splioEntityContent) {

      // The rest of tasks. They will appear depending on the user's config.
      if (!empty($splioEntityContent['local_entity'])) {
        // The 'contacts_lists' are not treated as a regular entity since it is
        // configured under the contacts tab.
        if ($splioEntity != 'contacts_lists') {
          $this->derivatives['splio.entity_config.' . $splioEntity] = $base_plugin_definition;
          $this->derivatives['splio.entity_config.' . $splioEntity]['title'] = $splioEntityContent['label'];
          $this->derivatives['splio.entity_config.' . $splioEntity]['route_name'] = 'entity.splio.' . $splioEntity;
          $this->derivatives['splio.entity_config.' . $splioEntity]['base_route'] = 'system.config.splio.entity_config';
          /* $this->derivatives['splio.entity_config.' . $splioEntity]['route_parameters'] = ['splio' => $splioEntity]; */
        }
      }
    }

    return parent::getDerivativeDefinitions($base_plugin_definition);
  }

}
