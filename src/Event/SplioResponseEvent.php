<?php

namespace Drupal\splio\Event;

use Psr\Http\Message\ResponseInterface;
use Symfony\Component\EventDispatcher\Event;

/**
 * Defines the events related to the sync activity of the Splio module.
 *
 * This class dispatches events related to the responses received by Splio.
 * These events are meant to be dispatched just after a response from Splio is
 * received.
 *
 * @property Psr\Http\Message\ResponseInterface response
 *   Splio's received response.
 */
class SplioResponseEvent extends Event {

  const SPLIO_EVENT = 'splio_event.response';

  /**
   * SplioResponseEvent constructor.
   *
   * @param Psr\Http\Message\ResponseInterface $response
   *   Receives the response sent by Splio.
   */
  public function __construct(ResponseInterface $response) {
    $this->response = $response;
  }

  /**
   * Returns the response received from the Splio API.
   *
   * @return Psr\Http\Message\ResponseInterface
   *   Receives a Splio entity formatted inside an array.
   */
  public function getSplioResponse() {
    return $this->response;
  }

}
