<?php

namespace Drupal\splio_utils\Plugin\rest\resource;

use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;

/**
 * Class SplioTriggerResource.
 *
 * Provides a REST service for the SplioTriggerManager service.
 *
 * @RestResource(
 *   id = "splio_trigger_manager",
 *   label = @Translation("Splio trigger manager"),
 *   uri_paths = {
 *     "https://www.drupal.org/link-relations/create" =
 *     "/splio/trigger",
 *   }
 * )
 */
class SplioTriggerResource extends ResourceBase {

  // TODO: INJECT SERVICE INSTEAD OF USING /DRUPAL::SERVICE

  /**
   * Sends a trigger to Splio so the platform sends a message to the recipients.
   *
   * @param array $trigger
   *   The trigger data to be fired in Splio.
   *
   * @return \Drupal\rest\ResourceResponse
   *   Returns TRUE if the trigger was successfully fired in Splio.
   *   Returns FALSE in any other case.
   */
  public function post(array $trigger) {
    $resourceResponse = new ResourceResponse();

    $isTriggered = \Drupal::service('splio_utils.splio_trigger_manager')
      ->triggerMessage($trigger['message_id'], $trigger['recipients'], empty($trigger['options']) ? NULL : $trigger['options']);

    $response = [
      "triggered" => boolval($isTriggered),
    ];

    // If the triggered was fired, return a 200.
    // In other any case the trigger could not be fired in Splio, return an 400
    // status code instead.
    ($isTriggered) ?
      $resourceResponse->setStatusCode(200)
      : $resourceResponse->setStatusCode(400);

    // Prepare the response formatted as JSON.
    $resourceResponse->setContent(
      json_encode($response)
    );

    return $resourceResponse;
  }

}
