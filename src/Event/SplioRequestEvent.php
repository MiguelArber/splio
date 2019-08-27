<?php

namespace Drupal\splio\Event;

use Symfony\Component\EventDispatcher\Event;

/**
 * Defines the events related to the sync activity of the Splio module.
 *
 * This class dispatches events related to the requests made to the Splio API.
 * These events are meant to be dispatched just before a request to Splio is
 * sent.
 *
 * @property array entity
 *   Splio entity formatted inside an array ready to be sent to Splio.
 *
 * @property bool changed
 *   Determines if the original entity has been changed.
 */
class SplioRequestEvent extends Event {

  const SPLIO_EVENT = 'splio_event.request';
  const SPLIO_CREATE = 'splio_event.create';
  const SPLIO_READ = 'splio_event.read';
  const SPLIO_UPDATE = 'splio_event.update';
  const SPLIO_DELETE = 'splio_event.delete';

  /**
   * SplioEvent constructor.
   *
   * @param array $entity
   *   Receives an entity in the form of an array, formatted with the structure
   *   that the Splio API expects to receive for Splio entities.
   */
  public function __construct(array $entity) {
    $this->entity = $entity;
    $this->changed = FALSE;
  }

  /**
   * Alters the object that will be sent to the Splio API.
   *
   * @param array $entity
   *   Receives a Splio entity formatted inside an array.
   */
  public function alterSplioEntity(array $entity) {
    $this->changed = ($this->entity === $entity) ? FALSE : TRUE;
    $this->entity = $entity;
  }

  /**
   * Returns the object that will be sent to the Splio API.
   *
   * @return array
   *   Receives a Splio entity formatted inside an array.
   */
  public function getSplioEntity() {
    return $this->entity;
  }

  /**
   * Determines whether the entity to be sent to the Splio API has been altered.
   *
   * @return bool
   *   Returns true if the item has change, false in any other case.
   */
  public function hasChangedEntity() {
    return $this->changed;
  }

}
