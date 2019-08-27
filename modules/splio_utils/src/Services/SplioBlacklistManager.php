<?php

namespace Drupal\splio_utils\Services;

use Drupal\Core\Config\ConfigFactory;
use Drupal\key\KeyRepository;
use Drupal\rest\Plugin\ResourceBase;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class SplioBlacklistManager.
 *
 * Manages the blacklist of the Splio platform. Allows to check if an email
 * address is blacklisted in Splio and add any email address to the Blacklist.
 * Due to Splio API limitations, an email address cannot be removed form the
 * blacklist through this module.
 *
 * @property \Drupal\key\KeyRepository keyManager
 * @property \Drupal\Core\Config\ConfigFactory config
 * @property \Psr\Log\LoggerInterface logger
 * @package Drupal\splio_utils\Services
 *
 * TODO: Create a parent class with all the similarities with SplioConnector.
 */
class SplioBlacklistManager {

  protected $baseUri;

  private $client;

  const SPLIO_URI = [
    'splio_data' => 'data/1.9/',
    'blacklist' => 'blacklist/',
  ];

  /**
   * SplioBlacklistManager constructor.
   *
   * @param \Drupal\key\KeyRepository $keyManager
   *   KeyManager dependency.
   * @param \Drupal\Core\Config\ConfigFactory $config
   *   Drupal's config factory.
   *   The event dispatched for Splio requests.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger. Loads the 'splio' channel.
   */
  public function __construct(
    KeyRepository $keyManager,
    ConfigFactory $config,
    LoggerInterface $logger
  ) {
    $this->keyManager = $keyManager;
    $this->config = $config;
    $this->baseUri = $this->generateBaseUri();
    $this->logger = $logger;
    $this->client = new Client([$this->baseUri]);
  }

  /**
   * SplioBlacklistManager create method.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   Main container.
   *
   * @return \Drupal\splio_utils\Services\SplioBlacklistManager
   *   Returns an instance of SplioBlacklistManager with the injected services.
   */
  public static function create(ContainerInterface $container) {
    /** @var \Psr\Log\LoggerInterface $logger */
    $logger = $container->get('logger.channel.splio');

    return new static(
    // Load the service required to construct this class.
      $container->get('key.repository'),
      $container->get('config.factory'),
      $logger
    );
  }

  /**
   * Generates the client's base_uri based on the key file selected by the user.
   *
   * @return string
   *   The base_uri generated string. Will return an empty string in case the
   *   selected key is not compatible.
   */
  protected function generateBaseUri() {
    $savedKey = ($this->config
      ->get('splio.settings')
      ->get('splio_config')) ?? '';

    $key = empty($this->keyManager->getKey($savedKey)) ?
      ''
      : $this->keyManager
        ->getKey($savedKey)
        ->getKeyValues();

    $server = $this->config
      ->get('splio.settings')
      ->get('splio_server');

    $server .= static::SPLIO_URI['splio_data'];

    $url = '';
    $apiKey = ($key['apiKey']) ?? '';
    $universe = ($key['universe']) ?? '';

    empty($apiKey) ?:
      empty($universe) ?:
        empty($server) ?:
          $url = "https://$universe:$apiKey@$server";

    return $url;
  }

  /**
   * Returns the Guzzle client used by the SplioConnector service.
   *
   * @return \GuzzleHttp\Client
   *   The current Guzzle client.
   */
  public function getClient(): Client {
    return $this->client;
  }

  /**
   * Sets a custom Guzzle client to be used by the SplioConnector service.
   *
   * @param \GuzzleHttp\Client $client
   *   The custom Guzzle client.
   */
  public function setClient(Client $client): void {
    $this->client = $client;
  }

  /**
   * Checks whether an email address is Blacklisted in Splio or not.
   *
   * @param string $email
   *   The email address to check.
   *
   * @return bool
   *   Returns TRUE in case the received email address is Blacklisted in Splio.
   *   It will return FALSE in any other case.
   */
  public function isEmailBlacklisted(string $email): bool {
    // Initially we assume that an email is not blacklisted.
    $isBlacklisted = FALSE;

    // Generate the URI based on the received email.
    $uri = $this->baseUri . static::SPLIO_URI['blacklist'] . $email;

    try {
      // If a 200 code is received, then the address is in the blacklist.
      $this->client->get($uri);
      $isBlacklisted = TRUE;
    }
    catch (RequestException $exception) {
      // If an 404 code is received the address is not in the blacklist.
      // If the error code isn't 404, a connection error might have occurred.
      ($exception->getCode() == 404) ?:
      $this->logger
        ->error("Unable to decode Splio API response: $exception");
    }

    return $isBlacklisted;
  }

  /**
   * Adds an email address to the user's configured universe Blacklist in Splio.
   *
   * @param string $email
   *   The email address to be blacklisted.
   *
   * @return bool
   *   Returns TRUE in case the received email address was successfully
   *   blacklisted. Return FALSE in any other case.
   */
  public function addEmailToBlacklist(string $email): bool {
    // Initially we assume that an email is not blacklisted.
    $isBlacklisted = FALSE;

    // Generate the URI based on the received email.
    $uri = $this->baseUri . static::SPLIO_URI['blacklist'] . $email;

    try {
      // If a 200 code is received, then the address was added to the blacklist.
      $this->client->put($uri);
      $isBlacklisted = TRUE;
    }
    catch (RequestException $exception) {
      // In any other case, a connection error might have occurred.
      $this->logger
        ->error("Unable to decode Splio API response: $exception");
    }

    return $isBlacklisted;
  }

}
