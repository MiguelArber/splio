<?php

namespace Drupal\splio\Commands;

use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\splio\Services\SplioConnector;
use Drush\Commands\DrushCommands;

/**
 * A Drush commandfile.
 *
 * In addition to this file, you need a drush.services.yml
 * in root of your module, and a composer.json file that provides the name
 * of the services file to use.
 *
 * @property \Drupal\splio\Services\SplioConnector splio
 * @property \Drupal\Core\Entity\EntityTypeManagerInterface entityTypeManager
 * @property \Drupal\Core\Config\ConfigFactory splioConfig
 */
class SplioCommands extends DrushCommands {

  /**
   * SplioConnector constructor.
   *
   * @param \Drupal\splio\Services\SplioConnector $splio
   *   KeyManager dependency.
   * @param \Drupal\Core\Config\ConfigFactory $config
   *   Drupal's config factory.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entityTypeManager.
   */
  public function __construct(SplioConnector $splio, ConfigFactory $config, EntityTypeManagerInterface $entityTypeManager) {
    $this->splio = $splio;
    $this->splioConfig = $config;
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * Adds the specified entity to the Splio queue.
   *
   * @param array $options
   *   An associative array of options whose values come from cli, aliases,
   *   config, etc.
   *
   * @option entity_type
   *   The type of the entity.
   * @option entity_id
   *   The id of the entity.
   * @option action
   *   The desired action. Accepted values: create, update, delete.
   * @usage drush splio-entity-enqueue --entity_type=user --entity_id=123456
   *   --action=create Adds to the queue: 'create' the 'user' with id '123456'.
   * @usage drush spl-queue --entity_type=node --entity_id=654321
   *   --action=delete Adds to the queue: 'delete' the 'node' with id '654321'.
   *
   * @command splio:entity-enqueue
   * @aliases spl-queue,splio-entity-enqueue
   */
  public function entityEnqueue(array $options = [
    'entity_type' => NULL,
    'entity_id' => NULL,
    'action' => NULL,
  ]) {

    // The Drupal entity that will later be loaded.
    $entity = NULL;

    // Validate option: entity_type.
    if (empty($options['entity_type']) || !$this->validateOptionEntityType($options['entity_type'])) {
      $error = TRUE;
      drush_set_error(dt('--entity_type is not valid. Check your Splio module configuration and try again.'));
    }

    // Load the requested entity through the entity type manager service.
    try {
      $entity = $this->entityTypeManager
        ->getStorage($options['entity_type'])
        ->load($options['entity_id']);
    }
    catch (\Exception $e) {
      $error = TRUE;
      drush_set_error(dt("The requested entity could not be loaded. Aborting..."));
    }

    // Validate option: entity_id.
    if (!isset($entity)) {
      $error = TRUE;
      drush_set_error(dt("--entity_id is not valid. Ensure the entity exists."));
    }

    // Validate option: action.
    if (empty($options['action']) || !$this->validateOptionAction($options['action'])) {
      $error = TRUE;
      drush_set_error(dt('--action option is not valid. The only allowed values are "create", "update", "delete".'));
    }

    // If no errors were detected.
    if (!isset($error)) {

      // Add the item to the queue through the Splio connector service.
      try {
        $this->splio->addEntityToQueue($entity, $options['action']);
      }
      catch (\Exception $e) {
        drush_set_error(dt("Entity could not be added to Splio queue."));
      }

      // Final output.
      drush_print(dt("Entity added to Splio queue."));
    }
  }

  /**
   * Validate that the type entity_type is valid.
   *
   * @param string $entity_type
   *   Entity type value from --entity_type command option.
   *
   * @return bool
   *   TRUE if valid, FALSE if invalid.
   */
  private function validateOptionEntityType($entity_type) {

    // Load the current Splio config.
    $splioEntities = ($this->splioConfig
      ->get('splio.entity.config')
      ->get('splio_entities')) ?? NULL;

    if (!empty($splioEntities)) {
      foreach ($splioEntities as $splioEntityType => $splioEntityDef) {

        $splioEntity = $splioEntityDef['local_entity'];
        $splioBundle = $splioEntityDef['local_entity_bundle'];

        if ($splioEntity != '' &&
          ($splioEntity == $entity_type || $splioBundle == $entity_type)) {
          return TRUE;
        }
      }
    }

    return FALSE;
  }

  /**
   * Validate that the action option is valid.
   *
   * @param string $action
   *   Action value from --action command option.
   *
   * @return bool
   *   TRUE if valid, FALSE if invalid.
   */
  private function validateOptionAction($action) {

    // Define the valid actions that can be performed in the Splio queue.
    define('VALID_ACTIONS', [
      'create' => 'create',
      'update' => 'update',
      'delete' => 'delete',
    ]);

    return in_array($action, VALID_ACTIONS);
  }

}
