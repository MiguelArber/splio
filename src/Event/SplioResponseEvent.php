<?php

namespace Drupal\splio\Event;

use Drupal\Core\Entity\EntityInterface;
use Symfony\Component\EventDispatcher\Event;

/**
 * Defines the events related to the sync activity of the Splio module.
 *
 * This class dispatches events related to the responses received by Splio.
 * These events are meant to be dispatched right after a response from Splio is
 * received.
 *
 * @property mixed response
 *   Splio's received response. May be a response or an exception.
 * @property array entityStructure
 *   Splio's sent entity structure.
 * @property \Drupal\Core\Entity\EntityInterface entity
 *   Entity used to generate the Splio entity structure.
 * @property bool isSilentException
 *   In case an exception occurred while trying to sync with Splio, and this
 *   param is set to TRUE, the exception will not be thrown. No traces in the
 *   log will appear either. By default, this param is set to FALSE.
 */
class SplioResponseEvent extends Event {

  const SPLIO_EVENT = 'splio_event.response';

  /**
   * SplioResponseEvent constructor.
   *
   * @param mixed $response
   *   Receives the response received from Splio.
   * @param array $entityStructure
   *   Receives the sent Splio entity structure.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   Receives the entity used to generate the Splio entity structure.
   */
  public function __construct($response, array $entityStructure = NULL, EntityInterface $entity = NULL) {
    $this->response = $response;
    $this->entityStructure = $entityStructure;
    $this->entity = $entity;

    // By default, if an exception is received, it will be thrown.
    $this->isSilentException = FALSE;
  }

  /**
   * Returns the response received from the Splio API.
   *
   * @return mixed
   *   Returns the result of the HTTP request made to Splio's API.
   */
  public function getSplioResponse() {
    return $this->response;
  }

  /**
   * Returns the Splio entity structure sent to Splio's API.
   *
   * @return array
   *   Returns a Splio entity formatted inside an array.
   */
  public function getSplioEntityStructure() {
    return $this->entityStructure;
  }

  /**
   * Returns the Drupal entity used to generate the structure sent to Splio API.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   The Drupal entity used to generate the structure sent to Splio API.
   */
  public function getSplioEntity() {
    return $this->entity;
  }

  /**
   * Returns the property isSilentException.
   *
   * @return bool
   *   Returns a bool defining if an exception should be thrown after the event
   *   dispatch.
   */
  public function isSilentException() {
    return $this->isSilentException;
  }

  /**
   * Sets the property isSilentException.
   *
   * @param bool $isSilentException
   *   Defines if an exception should be thrown after the event dispatch.
   */
  public function setIsSilentException(bool $isSilentException) {
    $this->isSilentException = $isSilentException;
  }

}
