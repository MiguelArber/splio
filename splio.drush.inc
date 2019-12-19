<?php

/**
 * @file
 * Drush commands for Splio.
 */

/**
 * Implements hook_drush_command().
 *
 * This implementation is done for older Drush versions (Drush 8.x and earlier).
 */
function splio_drush_command() {
  $items = [];

  $items['splio-entity-enqueue'] = [
    'description' => 'Adds the specified entity to the Splio queue.',
    'examples' => [
      'drush splio-entity-enqueue --entity_type=user entity_id=123456 action=create' => dt("Adds to the queue: 'create' the 'user' with id '123456'."),
      'drush spl-queue --entity_type=node entity_id=654321 action=delete' => dt("Adds to the queue: 'delete' the 'node' with id '654321'."),
    ],
    'options' => [
      'entity_type' => dt('The type of the entity.'),
      'entity_id' => dt('The id of the entity.'),
      'action' => dt('The desired action. Accepted values: create, update, delete.'),
    ],
    'aliases' => ['spl-queue'],
  ];

  return $items;
}

/**
 * Add an entity the queue in order to be processed later by Splio.
 *
 * @param string $entity_type
 *   The entity type.
 * @param string $entity_id
 *   The entity id.
 * @param string $action
 *   The action to be performed.
 */
function drush_splio_entity_enqueue(string $entity_type, string $entity_id, string $action) {

  // The Splio connector service.
  $splio = \Drupal::service('splio.splio_connector');

  // Validate option: entity_type.
  if (empty($entity_type) || !splio_drush_validate_option_entity_type($entity_type)) {
    $error = TRUE;
    drush_set_error(dt('--entity_type is not valid. Please, use an existing entity type.'));
  }

  // Validate option: action.
  if (empty($action) || !splio_drush_validate_option_action($action)) {
    $error = TRUE;
    drush_set_error(dt('--action option is not valid. The only allowed values are "create", "update", "delete".'));
  }

  // Load the desired entity through the entity type manager service.
  $entity = \Drupal::service('entity_type.manager')
    ->getStorage($entity_type)
    ->load($entity_id);

  // Validate option: entity_id.
  if (empty($entity)) {
    $error = TRUE;
    drush_set_error(dt('--entity_id option is not valid. Please, use an existing entity id.'));
  }

  // If no errors were detected.
  if (!isset($error)) {

    // Add the item to the queue through the Splio connector service.
    $splio->addEntityToQueue($entity, $action);

    // Final output.
    drush_log(dt("$entity_type entity with id $entity_id will be $action" . "d in Splio.", 'ok'));
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
function splio_drush_validate_option_entity_type($entity_type) {

  // Load the current Splio config.
  $splioEntities = (\Drupal::service('config.factory')
    ->get('splio.entity.config')
    ->get('splio_entities')) ?? NULL;

  if (!empty($splioEntities)) {
    foreach ($splioEntities as $splioEntityDef) {

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
function splio_drush_validate_option_action($action) {

  // Define the valid actions that can be performed in the Splio queue.
  $validActions = [
    'create' => 'create',
    'update' => 'update',
    'delete' => 'delete',
  ];

  return in_array($action, $validActions);
}
