<?php

namespace Drupal\splio_utils\Plugin\rest\resource;

use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;

/**
 * Class SplioBlacklistResource.
 *
 * Provides a REST service for the SplioBlacklistManager service.
 *
 * @RestResource(
 *   id = "splio_blacklist_manager",
 *   label = @Translation("Splio blacklist manager"),
 *   uri_paths = {
 *     "canonical" = "/splio/blacklist/{email}",
 *     "https://www.drupal.org/link-relations/create" =
 *     "/splio/blacklist",
 *   }
 * )
 */
class SplioBlacklistResource extends ResourceBase {

  /**
   * Returns whether an email address is blacklisted in Splio or not.
   *
   * @param string $email
   *   The email address to be checked.
   *
   * @return \Drupal\rest\ResourceResponse
   *   Returns TRUE in case the received email address is Blacklisted in Splio.
   *   It will return FALSE in any other case.
   */
  public function get(string $email) {
    $resourceResponse = new ResourceResponse();

    // Check if the email address is in Splio's blacklist.
    $isBlacklisted = \Drupal::service('splio_utils.splio_blacklist_manager')
      ->isEmailBlacklisted($email);

    // If it is in the blacklist, return an 200 code.
    // In other any case the email address was not found in Splio's blacklist,
    // return an 404 status code.
    ($isBlacklisted) ?
      $resourceResponse->setStatusCode(200)
      : $resourceResponse->setStatusCode(404);

    // Prepare the response formatted as JSON.
    $resourceResponse->setContent(
        json_encode(["blacklisted" => $isBlacklisted])
      );

    return $resourceResponse;
  }

  /**
   * Adds an email address to the user's configured universe Blacklist in Splio.
   *
   * @param array $addresses
   *   The email addresses to be blacklisted.
   *
   * @return \Drupal\rest\ResourceResponse
   *   Returns TRUE in case the received email address was successfully
   *   blacklisted. Return FALSE in any other case.
   */
  public function post(array $addresses) {
    $resourceResponse = new ResourceResponse();

    $isBlacklisted = TRUE;
    $errorEmail = "";

    // Add the email address is in Splio's blacklist.
    foreach ($addresses as $email) {
      $isBlacklisted &= \Drupal::service('splio_utils.splio_blacklist_manager')
        ->addEmailToBlacklist(end($email));
      if (!$isBlacklisted) {
        $errorEmail = $email;
        break;
      }
    }

    $response = [
      "blacklisted" => boolval($isBlacklisted),
      "errors" => ($errorEmail) ? $errorEmail : 0,
    ];

    // If it is in the blacklist, return an 200 code.
    // In other any case the email could not be added to Splio's blacklist,
    // return an 400 status code.
    ($isBlacklisted) ?
      $resourceResponse->setStatusCode(200)
      : $resourceResponse->setStatusCode(400);

    // Prepare the response formatted as JSON.
    $resourceResponse->setContent(
      json_encode($response)
    );

    return $resourceResponse;
  }

}
