<?php


namespace Drupal\splio_utils\Services;

use Drupal\Core\Config\ConfigFactory;
use Drupal\key\KeyRepository;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class SplioTriggerManager.
 *
 * Manages the emailing triggers of the Splio platform.
 *
 * @property \Drupal\key\KeyRepository keyManager
 * @property \Drupal\Core\Config\ConfigFactory config
 * @property \Psr\Log\LoggerInterface logger
 * @package Drupal\splio_utils\Services
 *
 * TODO: Create a parent class with all the similarities with SplioConnector.
 */
class SplioTriggerManager {

  protected $baseUri;

  private $client;

  const SPLIO_URI = [
    'splio_trigger' => 'trigger/nph-9.pl/',
  ];

  private $triggerOptions = array();

  /**
   * SplioTriggerManager constructor.
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
   * SplioTriggerManager create method.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   Main container.
   *
   * @return \Drupal\splio_utils\Services\SplioTriggerManager
   *   Returns an instance of SplioTriggerManager with the injected services.
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

    $server .= static::SPLIO_URI['splio_trigger'];

    $url = '';
    $apiKey = ($key['apiTrigger']) ?? '';
    $universe = ($key['universe']) ?? '';

    if (!empty($apiKey) && !empty($universe) && !empty($server)) {
      $url = "https://$server";
      $this->triggerOptions = [
        'universe' => $universe,
        'key' => $apiKey,
      ];
    }



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
   * Sends a trigger to Splio so the platform sends a message to the recipients.
   *
   * Receives a message_id and a set of users (a string of recipients with its
   * own properties formatted as JSON). Sends a request to Splio to trigger the
   * message delivery.
   *
   * @param string $message_id
   *   The id of the predefined message in the Splio platform that will be send
   *   the contacts.
   * @param array $recipients
   *   An array of recipients and its properties (at least an email address is
   *   mandatory). The message corresponding to the received id will be sent to
   *   this set of customers.
   * @param array $options
   *   An array containing key-values params (such as op_code, category...) that
   *   will be included in the request.
   *
   * @return bool
   *   Returns TRUE if the trigger was successfully fired in Splio.
   *   Returns FALSE in any other case.
   */
  public function triggerMessage(string $message_id, array $recipients, array $options = NULL) {
    // In the beginning, the trigger has not been fired yet.
    $triggered = FALSE;

    // Generate the form params that will be sent in the POST request based in
    // the received mandatory params.
    $form_params = [
      'message' => $message_id,
      'rcpts' => json_encode($recipients),
    ];

    // Add the universe and the key to the $form_params.
    $form_params = array_merge($this->triggerOptions, $form_params);

    // If any optional param was received, it will be added to the request too.
    empty($options) ?: $form_params = array_merge($form_params, $options);

    try {
      // Send the POST request. If no error is received, the the trigger was
      // successfully fired.
      $this->client->request('POST', $this->baseUri, [
        'form_params' => $form_params,
      ]);
      $triggered = TRUE;
    }
    catch (GuzzleException $exception) {
      // In any other case, a connection error might have occurred.
      $this->logger
        ->error("Unable to decode Splio API response: $exception");
    }

    // Finally return the result of the operation.
    return $triggered;
  }

  /**
   * Checks whether the received string has a valid JSON structure.
   *
   * @param string $string
   *   The string to be validated.
   *
   * @return bool
   *   Returns TRUE in case the received string is JSON formatted. Returns FALSE
   *   in any other case.
   */
  private function isValidJson(string $string): bool {
    json_decode($string);
    return (json_last_error() == JSON_ERROR_NONE);
  }

}
