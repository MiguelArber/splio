<?php

namespace Drupal\splio\Event;

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
   */
  public function __construct($response, array $entityStructure = NULL) {
    $this->response = $response;
    $this->entityStructure = $entityStructure;
  }

  /**
   * Returns the response received from the Splio API.
   *
   * @return \Psr\Http\Message\ResponseInterface
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

}
