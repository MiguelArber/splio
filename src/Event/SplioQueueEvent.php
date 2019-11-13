<?php

namespace Drupal\splio\Event;

use Symfony\Component\EventDispatcher\Event;

/**
 * Defines the events related to the Splio queue activity.
 *
 * This class is meant to dispatch an event whenever an item is going to be
 * added to the Splio module queue.
 *
 * @property array item
 *   Splio queue item ready to be inserted in its queue.
 *
 * @property bool changed
 *   Determines if the original item has been changed.
 */
class SplioQueueEvent extends Event {

  const SPLIO_ENQUEUE = 'splio_event.enqueue';
  const SPLIO_DEQUEUE = 'splio_event.dequeue';

  /**
   * SplioQueueEvent constructor.
   *
   * @param array $item
   *   Receives an entity in the form of an array, formatted with the structure
   *   that the Splio API expects to receive for Splio entities.
   */
  public function __construct(array $item) {
    $this->item = $item;
    $this->changed = FALSE;
  }

  /**
   * Alters the item that will be added to the Splio queue.
   *
   * @param array $item
   *   Receives a Splio entity formatted inside an array.
   */
  public function alterSplioQueueItem(array $item) {
    if ($this->changed === FALSE) {
      $this->changed = ($this->item === $item) ? FALSE : TRUE;
    }
    $this->item = $item;
  }

  /**
   * Returns the object that will be inserted into the Splio queue.
   *
   * @return array
   *   Receives the item that will be inserted into the Splio queue.
   */
  public function getSplioQueueItem() {
    return $this->item;
  }

  /**
   * Determines whether the item to be inserted in the queue has been altered.
   *
   * @return bool
   *   Returns true if the item has change, false in any other case.
   */
  public function hasChangedItem() {
    return $this->changed;
  }

}
