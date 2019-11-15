<?php

namespace Drupal\splio\Services;

use Drupal\Component\EventDispatcher\ContainerAwareEventDispatcher;
use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\key\KeyRepository;
use Drupal\splio\Entity\SplioField;
use Drupal\splio\Event\SplioQueueEvent;
use Drupal\splio\Event\SplioRequestEvent;
use Drupal\splio\Event\SplioResponseEvent;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Pool;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class SplioConnector.
 *
 * Manages the data synchronization with Splio. Allows the user to add an
 * entity
 * to the process queue or directly sync it with Splio platform. Performs the
 * CRUD actions for any entity or set of entities received and send the
 * requests
 * concurrently to Splio.
 *
 * @property \Drupal\Core\Config\ConfigFactory config
 * @property \Drupal\key\KeyRepository keyManager
 * @property \Drupal\Core\Entity\EntityTypeManagerInterface entityTypeManager
 * @property \Drupal\Core\Queue\QueueFactory queueFactory
 * @property \Psr\Log\LoggerInterface logger
 * @property \Symfony\Component\EventDispatcher\ContainerAwareEventDispatcher
 * @property \Drupal\Component\EventDispatcher\ContainerAwareEventDispatcher eventDispatcher
 * @package Drupal\splio\Services
 */
class SplioConnector {

  protected $baseUri;

  const SPLIO_URI = [
    'splio_data' => 'data/1.9/',
    'contacts' => 'contact/',
    'products' => 'product/',
    'receipts' => 'order/',
    'stores' => 'store/',
  ];

  protected $client;

  protected const DATE_FORMAT = 'Y-m-d H:i:s';

  /**
   * SplioConnector constructor.
   *
   * @param \Drupal\key\KeyRepository $keyManager
   *   KeyManager dependency.
   * @param \Drupal\Core\Config\ConfigFactory $config
   *   Drupal's config factory.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entityTypeManager.
   * @param \Drupal\Core\Queue\QueueFactory $queueFactory
   *   The queueFactory.
   * @param \Drupal\Component\EventDispatcher\ContainerAwareEventDispatcher $eventDispatcher
   *   The event dispatched for Splio requests.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger. Loads the 'splio' channel.
   */
  public function __construct(
    KeyRepository $keyManager,
    ConfigFactory $config,
    EntityTypeManagerInterface $entityTypeManager,
    QueueFactory $queueFactory,
    ContainerAwareEventDispatcher $eventDispatcher,
    LoggerInterface $logger
  ) {

    $this->keyManager = $keyManager;
    $this->config = $config;
    $this->baseUri = $this->generateBaseUri();
    $this->entityTypeManager = $entityTypeManager;
    $this->queueFactory = $queueFactory;
    $this->eventDispatcher = $eventDispatcher;
    $this->logger = $logger;
    $this->client = new Client([$this->baseUri]);
  }

  /**
   * SplioConnector create method.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   Main container.
   *
   * @return \Drupal\splio\Services\SplioConnector
   *   Returns an instance of SplioConnector with the injected services.
   */
  public static function create(ContainerInterface $container) {
    /** @var \Psr\Log\LoggerInterface $logger */
    $logger = $container->get('logger.channel.splio');

    return new static(
    // Load the service required to construct this class.
      $container->get('key.repository'),
      $container->get('config.factory'),
      $container->get('entity_type.manager'),
      $container->get('queue'),
      $container->get('event_dispatcher'),
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
   * Returns true if can reach Splio API server, if not will return false.
   *
   * @param array|null $options
   *   Applies a specific apiKey and server if received as parameter.
   *
   * @return bool
   *   True or false depending if there is connection the Splio API.
   */
  public function hasConnection(array $options = NULL) {
    $successfulConnection = FALSE;

    try {
      if (!empty($options)) {
        $key = empty($this->keyManager->getKey($options['key'])) ?
          ''
          : $this->keyManager
            ->getKey($options['key'])
            ->getKeyValues();

        $server = $options['server'];
        $server .= static::SPLIO_URI['splio_data'];
        $apiKey = $key['apiKey'];
        $universe = $key['universe'];
        $this->client->get("https://$universe:$apiKey@$server");
      }
      else {
        $this->client->request('GET');
      }
      $successfulConnection = TRUE;
    }
    catch (GuzzleException $e) {
      return $successfulConnection;
    }

    return $successfulConnection;
  }

  /**
   * Determines if an entity is configured by the user as a Splio entity.
   *
   * Helper function that determines if a received entity belongs to the Splio
   * configured entities. Returns the Splio entity type if true, returns FALSE
   * in any other case.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity that may belong to Splio.
   *
   * @return string|bool
   *   Returns a string with the SplioEntity type in case this entity is
   *   configured as a Splio entity. Returns FALSE otherwise.
   */
  public function isSplioEntity(EntityInterface $entity) {
    // Load the current Splio config.
    $splioEntities = ($this->config
        ->get('splio.entity.config')
        ->get('splio_entities')) ?? NULL;

    if (!empty($splioEntities)) {
      foreach ($splioEntities as $splioEntityType => $splioEntityDef) {

        $splioEntity = $splioEntityDef['local_entity'];
        $splioBundle = $splioEntityDef['local_entity_bundle'];
        $entityType = $entity->bundle();

        if ($splioEntity != '' &&
          ($splioEntity == $entityType || $splioBundle == $entityType)) {
          return $splioEntityType;
        }
      }
    }

    return FALSE;
  }

  /**
   * Returns an array containing the contact lists defined in Splio.
   *
   * @return array
   *   Array containing the contact lists.
   */
  public function getContactLists() {
    $lists = [];

    try {
      $uri = $this->baseUri . 'lists';
      $lists = json_decode($this->client->get($uri)->getBody(), TRUE);
    }
    catch (RequestException $exception) {
      $this->logger
        ->error("Unable to get Splio contact lists: " . $exception);
    }

    return $lists;
  }

  /**
   * Creates new users in the Splio platform.
   *
   * Receives an array of Drupal entities which will be created in the Splio
   * platform. Finds the proper Splio entity type for each Drupal entity
   * received, generates the proper JSON structure and makes a POST request to
   * Splio.
   *
   * @param array $entities
   *   Receives an array of Drupal entities.
   * @param int $concurrency
   *   Defines how many requests should be sent concurrently. By default, 10.
   *
   * @return array
   *   The result of the request.
   */
  public function createEntities(array $entities, $concurrency = 10) {
    $requests = function ($entities) {
      foreach ($entities as $entity) {

        $entityType = empty($entity) ? NULL : $this->isSplioEntity($entity);

        if ($entityType) {
          // If an order_line is received, then the whole receipt (order)
          // which it belongs to will be created.
          if ($entityType == 'order_lines') {
            $orderEntity = $this->getOrderForOrderLine($entity);
            $entityType = 'receipts';
            if (!empty($orderEntity)) {
              $entity = $orderEntity;
            }
            else {
              $this->logger
                ->error('Could not retrieve an order for the received %entity order_lines entity.',
                  [
                    '%entity' => $entity->getEntityTypeId(),
                  ]);
              continue;
            }
          }

          // Generate the base entity structure.
          $currentEntityType = static::SPLIO_URI[$entityType];
          $entityStructure = $this->generateEntityStructure($entity);

          // Add the type to the $entityStructure so it is more accessible.
          $entityStructure['splioEntityType'] = $entityType;

          // Manage the event to be dispatched.
          $requestEvent = new SplioRequestEvent($entityStructure);
          $this->eventDispatcher
            ->dispatch(SplioRequestEvent::SPLIO_CREATE, $requestEvent);

          // In case someone captured the event and made changes in the
          // entityStructure, update the entityStructure.
          $entityStructure = $requestEvent->getSplioEntity();

          // Manage the entity key.
          $keyField = (key($entityStructure['keyField'])) ?? NULL;
          $keyFieldValue = $entityStructure['keyField'][$keyField];

          // If the entity has no keyField it cannot be synced with Splio.
          if (empty($keyFieldValue)) {
            continue;
          }

          // Generate the URI based on the variables that have been just set.
          $uri = $this->baseUri . $currentEntityType;

          // Returns a promise once the function has finished.
          yield function () use ($uri, $entityStructure) {

            return $this->client->postAsync($uri,
              [
                'body' => json_encode($entityStructure),
              ]
            )->then(
              function (ResponseInterface $response) use ($entityStructure) {

                // Manage the event to be dispatched.
                $responseEvent = new SplioResponseEvent($response, $entityStructure);
                $this->eventDispatcher
                  ->dispatch(SplioResponseEvent::SPLIO_EVENT, $responseEvent);

                return $response;
              },
              function (RequestException $exception) use ($entityStructure) {
                $this->logger
                  ->error("Unable to fetch/send data from Splio API. %message. JSON body: %entityStructure",
                    [
                      '%message' => $exception->getMessage(),
                      '%entityStructure' => json_encode($entityStructure),
                    ]);
                throw $exception;
              }
            );
          };
        }
      }
    };

    $result = Pool::batch($this->client, $requests($entities), ['concurrency' => $concurrency]);

    // Return the results received by the server.
    return $result;
  }

  /**
   * Returns a list of existing users from the Splio platform.
   *
   * Receives an array of users which will be retrieved from the Splio platform.
   * Returns an array with the desired entities. The structure of each entity
   * will be the one Splio returns for the GET requests.
   *
   * @param array $entities
   *   Receives an array of Drupal entities.
   * @param int $concurrency
   *   Defines how many requests should be sent concurrently. By default, 10.
   *
   * @return array
   *   Returns an array containing the entities requested to Splio.
   */
  public function readEntities(array $entities, $concurrency = 10) {
    $receivedSplioEntities = [];

    $requests = function ($entities) {
      foreach ($entities as $entity) {

        $entityType = empty($entity) ? NULL : $this->isSplioEntity($entity);

        if ($entityType) {
          // If an order_line is received, then the whole receipt (order)
          // which it belongs to will be requested.
          if ($entityType == 'order_lines') {
            $orderEntity = $this->getOrderForOrderLine($entity);
            $entityType = 'receipts';
            if (!empty($orderEntity)) {
              $entity = $orderEntity;
            }
            else {
              $this->logger
                ->error('Could not retrieve an order for the received %entity order_lines entity.',
                  [
                    '%entity' => $entity->getEntityTypeId(),
                  ]);
              continue;
            }
          }

          // Generate the base entity structure.
          $currentEntity = static::SPLIO_URI[$entityType];
          $entityStructure = $this->generateEntityStructure($entity);

          // Add the type to the $entityStructure so it is more accessible.
          $entityStructure['splioEntityType'] = $entityType;

          // Manage the event to be dispatched.
          $requestEvent = new SplioRequestEvent($entityStructure);
          $this->eventDispatcher
            ->dispatch(SplioRequestEvent::SPLIO_READ, $requestEvent);

          // In case someone captured the event and made changes in the
          // entityStructure, update the entityStructure.
          $entityStructure = $requestEvent->getSplioEntity();

          // Manage the key that will be used to make the request.
          $keyField = (key($entityStructure['keyField'])) ?? NULL;
          $keyFieldValue = $entityStructure['keyField'][$keyField];

          // If the entity has no keyField it cannot be synced with Splio.
          if (empty($keyFieldValue)) {
            continue;
          }

          // Generate the URI based on the variables that have been just set.
          $uri = $this->baseUri . $currentEntity . $keyFieldValue;

          // Returns a promise once the function has finished.
          yield function () use ($uri, $keyField) {
            return $this->client->getAsync($uri)->then(
              function (ResponseInterface $response) use ($keyField) {
                try {

                  // Manage the event to be dispatched.
                  $responseEvent = new SplioResponseEvent($response);
                  $this->eventDispatcher
                    ->dispatch(SplioResponseEvent::SPLIO_EVENT, $responseEvent);

                  // Decode the received response and add the proper keyField.
                  $response = json_decode($response->getBody(), TRUE);
                  $response['keyField'] = $keyField;
                }
                catch (\Error $exception) {
                  $this->logger
                    ->error("Unable to decode Splio API response: $exception");
                }
                return $response;
              },
              function (RequestException $exception) {
                $this->logger
                  ->notice("Unable to retrieve data from Splio API. %message.",
                    [
                      '%message' => $exception->getMessage(),
                    ]);
                throw $exception;
              }
            );
          };
        }
      }
    };

    // Contains a pool of promises inside an array.
    $requestedSplioEntities = Pool::batch($this->client, $requests($entities), ['concurrency' => $concurrency]);

    // Iterate, unpack and add to an array the Splio response.
    foreach ($requestedSplioEntities as $requestedEntity) {
      (is_array($requestedEntity)) ?
        $receivedSplioEntities[$requestedEntity[array_pop($requestedEntity)]] = $requestedEntity
        : array_push($receivedSplioEntities, $requestedEntity);
    }

    return $receivedSplioEntities;
  }

  /**
   * Updates a set of entities in the Splio platform.
   *
   * Receives an array of Drupal entities which will be updated in the Splio
   * platform. Finds the proper Splio entity type for each Drupal entity
   * received, generates the proper JSON structure and makes a PUT request
   * to Splio.
   *
   * @param array $entities
   *   Receives an array of Drupal entities.
   * @param int $concurrency
   *   Defines how many requests should be sent concurrently. By default, 10.
   *
   * @return array
   *   The result of the request.
   */
  public function updateEntities(array $entities, $concurrency = 10) {

    $requests = function ($entities) {
      foreach ($entities as $entity) {

        $entityType = empty($entity) ? NULL : $this->isSplioEntity($entity);

        if ($entityType) {

          if ($entityType == 'order_lines') {

            // If an order_line is received, then the whole receipt (order)
            // which it belongs to will be updated.
            $orderEntity = $this->getOrderForOrderLine($entity);
            if (!empty($orderEntity)) {
              $entity = $orderEntity;
            }
            else {
              $this->logger
                ->error('Could not retrieve an order for the received %entity order_lines entity.',
                  [
                    '%entity' => $entity->getEntityTypeId(),
                  ]);
              continue;
            }

            $entityType = 'receipts';
          }

          // Get the entity type URI.
          $currentEntity = static::SPLIO_URI[$entityType];
          $entityStructure = $this->generateEntityStructure($entity);

          // Add the type to the $entityStructure so it is more accessible.
          $entityStructure['splioEntityType'] = $entityType;

          // Manage the event to be dispatched.
          $requestEvent = new SplioRequestEvent($entityStructure);
          $this->eventDispatcher->dispatch(SplioRequestEvent::SPLIO_UPDATE, $requestEvent);

          // In case someone captured the event and made changes in the
          // entityStructure, update the entityStructure.
          $entityStructure = $requestEvent->getSplioEntity();

          // Manage the keyValue for updates. In case the received entity
          // contains an original object, use the original object's key instead.
          $keyField = (key($entityStructure['keyField'])) ?? NULL;

          // Load the current entity's fields.
          $entityFields = $this->entityTypeManager
            ->getStorage('splio_field')
            ->loadByProperties([
              'splio_entity' => $entityType,
            ]);

          // Get the local field that stores the key field.
          $drupalKeyField = $entityFields[$keyField . '_' . $entityType]
            ->getDrupalField();

          // In case an original object is contained in the received entity use
          // the original's id. In any other case, use the received's entity id.
          $keyFieldValue = !isset($entity->original) ?
            $entityStructure['keyField'][$keyField]
            : end($entity->original->get($drupalKeyField)->getValue()[0]);

          // If the entity has no keyField it cannot be synced with Splio.
          if (empty($keyFieldValue)) {
            continue;
          }

          // Generate the URI based on the variables that have been just set.
          $uri = $this->baseUri . $currentEntity . $keyFieldValue;

          // Returns a promise once the function has finished.
          yield function () use ($uri, $entityStructure) {
            return $this->client->putAsync($uri,
              [
                'body' => json_encode($entityStructure),
              ]
            )->then(
              function (ResponseInterface $response) use ($entityStructure) {

                // Manage the event to be dispatched.
                $responseEvent = new SplioResponseEvent($response, $entityStructure);
                $this->eventDispatcher
                  ->dispatch(SplioResponseEvent::SPLIO_EVENT, $responseEvent);

                return $response;
              },
              function (RequestException $exception) use ($entityStructure) {
                $this->logger
                  ->error("Unable to fetch/send data from Splio API. %message. JSON body: %entityStructure",
                    [
                      '%message' => $exception->getMessage(),
                      '%entityStructure' => json_encode($entityStructure),
                    ]);
                throw $exception;
              }
            );
          };
        }
      }
    };

    $result = Pool::batch($this->client, $requests($entities), ['concurrency' => $concurrency]);

    // Return the results received by the server.
    return $result;
  }

  /**
   * Deletes a set of users from the Splio platform.
   *
   * Receives an array of entities to be deleted from the Splio platform.
   *
   * @param array $entities
   *   Receives an array of Drupal entities.
   * @param int $concurrency
   *   Defines how many requests should be sent concurrently. By default, 10.
   *
   * @return array
   *   The result of the request.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function deleteEntities(array $entities, $concurrency = 10) {

    $requests = function ($entities) {
      foreach ($entities as $entity) {

        $entityType = empty($entity) ? NULL : $this->isSplioEntity($entity);

        if ($entityType) {

          // In case a contact is received, the delete method is used to
          // remove the user from the Splio platform.
          if ($entityType == 'contacts') {

            // Generate the base entity structure.
            $currentEntity = static::SPLIO_URI[$entityType];
            $entityStructure = $this->generateEntityStructure($entity);

            // Add the type to the $entityStructure so it is more accessible.
            $entityStructure['splioEntityType'] = $entityType;

            // Manage the event to be dispatched.
            $requestEvent = new SplioRequestEvent($entityStructure);
            $this->eventDispatcher
              ->dispatch(SplioRequestEvent::SPLIO_DELETE, $requestEvent);

            // In case someone captured the event and made changes in the
            // entityStructure, update the entityStructure.
            $entityStructure = $requestEvent->getSplioEntity();

            // Manage the key that will be used to make the request.
            $keyField = (key($entityStructure['keyField'])) ?? NULL;
            $keyFieldValue = $entityStructure['keyField'][$keyField];

            // If the entity has no keyField it cannot be synced with Splio.
            if (empty($keyFieldValue)) {
              continue;
            }

            // Generate the URI based on the variables that have been just set.
            $uri = $this->baseUri . $currentEntity . $keyFieldValue;

            // Returns a promise once the function has finished.
            yield function () use ($uri, $entityStructure) {
              return $this->client->deleteAsync($uri)->then(
                function (ResponseInterface $response) use ($entityStructure) {

                  // Manage the event to be dispatched.
                  $responseEvent = new SplioResponseEvent($response, $entityStructure);
                  $this->eventDispatcher
                    ->dispatch(SplioResponseEvent::SPLIO_EVENT, $responseEvent);

                  return $response;
                },
                function (RequestException $exception) use ($entityStructure) {
                  $this->logger
                    ->error("Unable to fetch/send data from Splio API. %message. JSON body: %entityStructure",
                      [
                        '%message' => $exception->getMessage(),
                        '%entityStructure' => json_encode($entityStructure),
                      ]);
                  throw $exception;
                }
              );
            };
          }
          else {
            // Due to Splio's API limitations, the products and stores cannot be
            // removed through Splio's API.
            $this->logger
              ->error("Delete method not accepted for %entityType entity type.",
                [
                  "%entityType" => $entityType,
                ]);
          }
        }
        else {
          $this->logger
            ->error("%entity is not a valid entity. Make sure you have mapped properly the splio entities from the module config.",
              [
                "%entity" => $entity->getId(),
              ]);
        }
      }
    };

    // Due to Splio's API limitations, if an order_line or a receipt is
    // received, it will be removed from Splio by setting to zero a
    // specific set of fields and removing any order_line from the order.
    // For this purpose, the 'PUT' HTTP method will be used.
    $orderEntities = [];

    // First, filter all the entities typed as 'receipts' or 'order_lines'.
    // These types will be managed through the 'PUT' HTTP method.
    foreach ($entities as $entityKey => $entityValue) {
      $entityType = $this->isSplioEntity($entityValue);
      if ($entityType == 'receipts' || $entityType == 'order_lines') {

        // Get the receipt with its values set to zero; or, get the receipt
        // which contains the received order_line.
        $orderEntities[$entityKey] = $this->getRemovedOrder($entityValue);

        // Remove it form the $entities array since it will be managed through
        // the 'updateEntites' method.
        unset($entities[$entityKey]);
      }
    }

    // If there is any, remove the $orderEntities through the 'updateEntities'
    // method.
    $ordersResult = empty($orderEntities) ?
      NULL
      : $this->updateEntities($orderEntities);

    // The rest of the entities are processed as usual. Mind that, due to
    // Splio's API limitations, only the 'contacts' entities will be deleted
    // through the 'DELETE' method, any other entity will throw an exception.
    $result = Pool::batch($this->client, $requests($entities), ['concurrency' => $concurrency]);

    // Return the results received by the server.
    return empty($ordersResult) ? $result : array_merge($ordersResult, $result);
  }

  /**
   * Adds the received splio entity to a queue to be processed later.
   *
   * Receives a splio entity and and the CRUD action to perform with it.
   * The entity will be added to a queue where it will be processed on the
   * next cron run.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity which will be added to the queue.
   * @param string $action
   *   CRUD action that will be performed when the received entity is processed.
   *   Accepts the following parameters: create, update, delete and dequeue.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function addEntityToQueue(EntityInterface $entity, $action) {

    // Check if the received entity is configured as a Splio entity.
    $splioEntityType = $this->isSplioEntity($entity);

    // Defines the valid actions that this method may receive.
    define('VALID_ACTIONS', [
      'create' => 'create',
      'update' => 'update',
      'delete' => 'delete',
      'dequeue' => 'dequeue',
    ]);

    if ($splioEntityType) {

      // The item that will later be queued.
      $item = [];

      // If an order_line is received, then the whole receipt (order)
      // which it belongs to will be updated.
      if ($splioEntityType == 'order_lines') {
        $orderEntity = $this->getOrderForOrderLine($entity);
        if (!empty($orderEntity)) {
          $entity = $orderEntity;
        }
        else {
          $this->logger
            ->error('Could not retrieve an order for the received %entity order_lines entity.',
              [
                '%entity' => $entity->getEntityTypeId(),
              ]);
        }
        $splioEntityType = 'receipts';
        $item += ['originalSplioEntityType' => 'order_lines'];
      }

      // Load the key field for the received entity.
      $entitySplioKeyField = $this->config->get('splio.entity.config')
        ->get('splio_entities')[$splioEntityType]['splio_entity_key_field'];

      // Load the splio entity fields the received entity.
      $entityFields = $this->entityTypeManager
        ->getStorage('splio_field')
        ->loadByProperties([
          'splio_entity' => $splioEntityType,
        ]);

      // Load the drupal field defined as the splio key field.
      $entityKeyField = $entityFields[$entitySplioKeyField]
        ->getDrupalField();

      $queue = $this->queueFactory->get('cron_splio_sync');

      // Create an item.
      $item += [
        'id' => end($entity
          ->get($entityKeyField)
          ->getValue()[0]),
        'original' => $entity->original ?? ($action == 'delete' ? $entity : NULL),
        'splioEntityType' => $splioEntityType,
        'action' => $action,
      ];

      // Manage the event to be dispatched.
      $queueEvent = new SplioQueueEvent($item);
      $this->eventDispatcher
        ->dispatch(SplioQueueEvent::SPLIO_ENQUEUE, $queueEvent);

      // In case someone captured the event and made changes in the item,
      // update the item before inserting it into the queue.
      $item = $queueEvent->getSplioQueueItem();

      // Finally, perform a last check to ensure the set action is valid.
      if ($item['action'] != 'dequeue') {
        if (!in_array($item['action'], VALID_ACTIONS)) {
          $this->logger->error("The %type[%id] entity will not be queued. Action type received: %action. Only 'create', 'update' and 'delete' actions are queued.",
            [
              '%action' => $item['action'],
              '%type' => $item['splioEntityType'],
              '%id' => $item['id'],
            ]);
        }
        else {
          // Add the item to the queue.
          $queue->createItem($item);
        }
      }
    }
  }

  /**
   * Creates the Splio entity's JSON structure to make requests to Splio's API.
   *
   * Receives a Drupal entity and generates the proper JSON structure.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   Receives a Drupal Entity.
   *
   * @return array
   *   Returns an array containing the entity structure ready to be encoded
   *   to JSON.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  private function generateEntityStructure(EntityInterface $entity) {

    $entityStructure = [];
    $entityType = $this->isSplioEntity($entity);
    $entityFields = $this->entityTypeManager
      ->getStorage('splio_field')
      ->loadByProperties([
        'splio_entity' => $entityType,
      ]);

    foreach ($entityFields as $field) {

      $drupalField = $field->getDrupalField();
      $splioField = $field->getSplioField();
      $fieldValue = $this->getFieldValue($drupalField, $entity);

      $fieldValue = $this->formatField($field, $fieldValue);

      if ($field->isDefaultField()) {
        // Arrays are not allowed as field values.
        $entityStructure[$splioField] = is_array($fieldValue) ?
          end($fieldValue)
          : $fieldValue;
      }
      else {
        $entityStructure['fields'][$splioField] = [
          'name' => $splioField,
          'value' => is_array($fieldValue) ? end($fieldValue) : $fieldValue,
        ];
      }

      if ($field->isKeyField()) {
        $entityStructure['keyField'][$splioField] = $fieldValue;
      }
    }

    if ($entityType == 'receipts') {

      // If the following values are set to zero, this receipt will be deleted,
      // there's no need to load it's order_lines.
      if (($entityStructure['shipping_amount'] == 0
        && $entityStructure['discount_amount'] == 0
        && $entityStructure['total_price'] == 0
        && $entityStructure['order_completed'] == 0)) {
        $entityStructure['products'] = [];
      }
      else {
        $entityStructure['products'] =
          $this->generateOrderLinesStructure($entity);

        // Perform a check to ensure there are no order_lines with empty key.
        foreach ($entityStructure['products'] as $productKey => $productDef) {
          if (empty($productDef['extid'])) {
            unset($entityStructure['products'][$productKey]);
          }
        }

      }
    }

    if ($entityType == 'contacts') {
      $entityStructure['lists'] =
        $this->generateContactListStructure($entity);
    }

    return $entityStructure;
  }

  /**
   * Receives a splio field for a particular entity and returns its value.
   *
   * @param string $field
   *   The splio field which value is being requested.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The current entity to which the received field belongs to.
   *
   * @return mixed
   *   Returns a string with the requested value.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  private function getFieldValue(string $field, EntityInterface $entity) {

    $value = [];

    if (!empty($field)) {
      // If the received field field turns out to be an entity reference...
      if (strncmp($field, "{{", 2) == 0) {

        // Remove the two first and last chars which are "{{ }}".
        $field = substr($field, 2, -2);
        $value = $this->getEntityReferenceValue($field, $entity);
      }
      else {
        // In case the received field contains an array of elements, send them
        // as a comma-separated string.
        $fieldValues = $entity->get($field)->getValue();

        if (is_array($fieldValues) && count($fieldValues) > 1) {
          foreach ($fieldValues as $fieldDef) {
            array_push($value, end($fieldDef));
          }
          $value = implode(", ", $value);
        }
        else {
          // In other cases, just unpack the value.
          $value = $entity->get($field)->getValue() ?
            end($entity->get($field)->getValue()[0])
            : $value;
        }
      }
    }

    return $value;
  }

  /**
   * Formats the received value to its field type.
   *
   * @param \Drupal\splio\Entity\SplioField $field
   *   The Splio received field.
   * @param mixed $fieldValue
   *   The current value for the received SPlio field.
   *
   * @return mixed
   *   Returns the value formatted to the configured type.
   */
  private function formatField(SplioField $field, $fieldValue) {
    if ($field->getTypeField() == 'date') {
      if (is_numeric($fieldValue)) {
        $fieldValue = date(static::DATE_FORMAT, $fieldValue);
      }
    }

    return $fieldValue;
  }

  /**
   * Generates the 'contacts_lists' JSON structure for any contact received.
   *
   * Receives a contact, generates the proper JSON structure for the lists
   * it belongs to and returns it as an array.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The contact entity that will be used as reference to generate the inner
   *   contacts_list structure.
   *
   * @return array
   *   Returns an array containing the inner 'contacts_list' structure
   *   for the given contact.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  private function generateContactListStructure(EntityInterface $entity) {
    $entitiesStructure = [];

    // Load the splio entity fields for order_lines.
    $contactsLists = $this->entityTypeManager
      ->getStorage('splio_field')
      ->loadByProperties([
        'splio_entity' => 'contacts_lists',
      ]);

    foreach ($contactsLists as $list) {

      // Drupal field storing the subscription of the user for the current list.
      $contactsListsDrupalField = $list->getDrupalField();
      $contactFieldValue = empty($contactsListsDrupalField) ?
        NULL
        : $this->getFieldValue($contactsListsDrupalField, $entity);

      // If the value is not empty and it is TRUE, 1 or it contains the name of
      // the current list, the user will be subscribed to that list. In any
      // other case, the user will be unsubscribed from that particular list,
      // except when the local list field is set to none by the user.
      if (!empty($contactFieldValue)) {
        if ($contactFieldValue === TRUE
          || $contactFieldValue == 1
          || $contactFieldValue == $list->getSplioField()
          || (is_array($contactFieldValue)
            && in_array($list->getSplioField(), $contactFieldValue))) {
          $entitiesStructure[] = ["name" => $list->getSplioField()];
        }
        else {
          $entitiesStructure[] = [
            "name" => $list->getSplioField(),
            "action" => "unsubscribe",
          ];
        }
      }
    }

    return $entitiesStructure;
  }

  /**
   * Generates the inner 'order_lines' JSON structure for any order received.
   *
   * Receives a receipt and loads all the order lines associated to it.
   * Generates the proper JSON structure and returns it as an array.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The receipt entity that will be used as reference to generate the inner
   *   order_lines structure.
   *
   * @return array
   *   Returns an array containing the inner 'order_lines structure
   *   for the given receipt.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  private function generateOrderLinesStructure(EntityInterface $entity) {
    $entitiesStructure = [];

    // Load the local entity configured as splio's order_lines.
    $entityType = $this->config
      ->get('splio.entity.config')
      ->get('splio_entities')['order_lines']['local_entity'];

    // Load the splio entity fields for order_lines.
    $entityFields = $this->entityTypeManager
      ->getStorage('splio_field')
      ->loadByProperties([
        'splio_entity' => 'order_lines',
      ]);

    // Get the local field configured as order_id_order_lines.
    $orderIdField = $entityFields['order_id_order_lines']->getDrupalField();

    // If the value turns to be an entity reference then store it's key.
    $orderIdField = (strncmp($orderIdField, "{{", 2) == 0) ?
      array_keys($this->getFieldValue($orderIdField, $entity))[0]
      : $orderIdField;

    // Load the splio entity fields for orders (receipts).
    $orderFields = $this->entityTypeManager
      ->getStorage('splio_field')
      ->loadByProperties([
        'splio_entity' => 'receipts',
      ]);

    // Load the splio field configured as a key field for the orders (receipts).
    $ordersKeyField = $this->config
      ->get('splio.entity.config')
      ->get('splio_entities')['receipts']['splio_entity_key_field'];

    // Get the local field that stores the key field.
    $drupalFieldOrderKey = $orderFields[$ordersKeyField]->getDrupalField();

    // If the orderId field is an entity reference...
    $orderId = $this->getFieldValue($drupalFieldOrderKey, $entity);

    // If the value is an entity reference, store the key that contains it.
    $orderId = is_array($orderId) ?
      end($orderId)
      : $orderId;

    // Load all the order_lines that belong to that orderId.
    $orderLinesEntities = $this->entityTypeManager
      ->getStorage($entityType)
      ->loadByProperties([$orderIdField => $orderId]);

    foreach ($orderLinesEntities as $entityKey => $entity) {
      $orderLineFields = [];
      foreach ($entityFields as $field) {

        $drupalField = $field->getDrupalField();
        $splioField = $field->getSplioField();
        $fieldValue = $this->getFieldValue($drupalField, $entity);
        if (!empty($fieldValue)) {
          $fieldValue = is_array($fieldValue) ? end($fieldValue) : $fieldValue;
        }

        if ($field->isDefaultField()) {
          $orderLineFields[$entityKey][$splioField] = $fieldValue;
        }
        else {
          $orderLineFields[$entityKey]['fields'][] = [
            'name' => $splioField,
            'value' => $fieldValue,
          ];
        }
      }
      $entitiesStructure[] = array_pop($orderLineFields);
    }

    return $entitiesStructure;
  }

  /**
   * Receives an order_line entity and returns the receipt that it belongs to.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The received order_line entity.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   *   Returns an receipt (order) entity.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  private function getOrderForOrderLine(EntityInterface $entity) {
    // Retrieve the Drupal field that contains the Order Id for this
    // order line item.
    $orderIdDrupalField = $this->entityTypeManager
      ->getStorage('splio_field')
      ->loadByProperties([
        'splio_entity' => 'order_lines',
      ])['order_id_order_lines']
      ->getDrupalField();

    // Next, obtain the value of the Order Id which this order line
    // belongs to.
    if (!empty($orderIdDrupalField)) {
      $orderIdValue = $this->getFieldValue($orderIdDrupalField, $entity);
    }
    else {
      $this->logger
        ->error("Error trying to fetch Receipts key field. Check that your Receipts key field is configured properly.");
      return NULL;
    }

    $orderIdValue = is_array($orderIdValue) ?
      $orderIdValue[array_keys($orderIdValue)[0]]
      : $orderIdValue;

    // Finally, load the order entity by passing the obtained order id
    // as the configured key field.
    if (!empty($orderIdValue)) {

      // First, check which is the key field and the drupal entity
      // mapped by the user as an splio order.
      $ordersSplioConfig = $this->config
        ->get('splio.entity.config')
        ->get('splio_entities')['receipts'];

      $orderKeyField = $ordersSplioConfig['splio_entity_key_field'];
      $orderEntityType = $ordersSplioConfig['local_entity'];

      // Then, load the Drupal field which is mapped as the key field
      // for the order entity.
      try {
        $orderKeyDrupalField = $this->entityTypeManager
          ->getStorage('splio_field')
          ->loadByProperties(['splio_entity' => 'receipts'])[$orderKeyField]
          ->getDrupalField();
      }
      catch (\Exception $exception) {
        $this->logger
          ->error("Error trying to fetch Receipts key field. Check that your Receipts key field, and the 'order_id' field from Order Lines, are configured properly.");
      }

      // Finally, load the proper $orderEntityType by passing the
      // $orderIdValue as the $orderKeyField.
      if (!empty($orderKeyDrupalField)) {
        $entity = $this->entityTypeManager
          ->getStorage($orderEntityType)
          ->loadByProperties([
            $orderKeyDrupalField => $orderIdValue,
          ])[$orderIdValue];
        return $entity;
      }
    }
    else {
      return NULL;
    }
  }

  /**
   * Receives an order or order_line entity and removes it from Splio.
   *
   * Due to API limitations, the 'DELETE' method can only be used with contacts
   * entities. According to Splio's developers team, in order to remove a
   * receipt from Splio, a set of specific fields must be set to zero and all
   * its order_lines must be removed. If an order_line is received, it will be
   * simply removed from the receipt it belongs to. These actions are performed
   * through the 'entitiesUpdate' method.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The received order_line entity.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   *   The order structure that will be sent to splio.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getRemovedOrder(EntityInterface $entity) {

    // Check the entity splio type.
    $entityType = $this->isSplioEntity($entity);

    // In case the received entity is an order_line, return the order which it
    // belongs to. If so, there is no need to make any change in the receipt:
    // at the time the entity structure is created, it will be generated without
    // the received order_line, since it will already have been deleted by
    // Drupal.
    $orderEntity = ($entityType == 'order_lines') ?
      $this->getOrderForOrderLine($entity)
      : $entity;

    if (empty($orderEntity)) {
      $this->logger
        ->error('Could not retrieve an order for the received %entity entity.',
          [
            '%entity' => $entity->getEntityTypeId(),
          ]);

      return NULL;
    }
    else {

      // Load the splioEntity entity for receipts.
      $entityFields = $this->entityTypeManager
        ->getStorage('splio_field')
        ->loadByProperties([
          'splio_entity' => $entityType,
        ]);

      foreach ($entityFields as $field) {
        $drupalField = $field->getDrupalField();
        $splioField = $field->getSplioField();

        // If the field is one of the following, set it to 0.
        if ($field->isDefaultField()) {
          if (($splioField == 'shipping_amount'
              || $splioField == 'discount_amount'
              || $splioField == 'total_price'
              || $splioField == 'order_completed')
            && !empty($drupalField)) {
            $orderEntity->set($field->getDrupalField(), '0');
          }
        }
      }
    }

    return $orderEntity;
  }

  /**
   * Returns the value of the referenced entity fields.
   *
   * Receives a string containing the current entity attribute which contains:
   * {current entity field}.{referenced entity type}.{referenced entity field}
   * Also receives the current entity. Returns the final value for the
   * received reference entity. Makes a recursive call in case the referenced
   * field contains another reference entity.
   *
   * @param string $drupalField
   *   String containing the current entity attributes following the structure:
   *   {this entity field}.{referenced entity type}.{referenced entity field}.
   * @param object $entity
   *   Current entity that contains an entity reference in one of it's fields.
   *
   * @return mixed
   *   Returns a string containing the value binded to the entity reference.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   *
   * TODO: This method may need some small rework in the future when the
   *   setupForm() method from SplioFieldForm is updated to allow an undefined
   *   number of entityReferences in a field. This may lead to  SplioField
   *   schema updates as well (string -> array).
   */
  private function getEntityReferenceValue(string $drupalField, $entity) {

    $entityRefField = NULL;

    $drupalFieldEntityReference = explode(".", $drupalField);

    $entityRefId = $entity
      ->get($drupalFieldEntityReference[0])
      ->getValue();

    $entityRef = $this->entityTypeManager
      ->getStorage($drupalFieldEntityReference[1])
      ->load(end($entityRefId[0]));

    if (!empty($entityRef)) {
      try {
        $entityRefField = $entityRef->get($drupalFieldEntityReference[2]);
      }
      catch (\Error $error) {
        $this->logger
          ->error("Error trying to fetch the
          %drupalFieldEntityReference[1] entity referenced from the field %drupalFieldEntityReference[0] of the %entity->getEntityTypeId() entity. Check that your fields are configured properly.",
            [
              '%drupalFieldEntityReference[1]' => $drupalFieldEntityReference[1],
              '%drupalFieldEntityReference[0]' => $drupalFieldEntityReference[0],
              '%entity->getEntityTypeId()' => $entity->getEntityTypeId(),
            ]
          );
        return [];
      }

      $entityRefFieldType = $entityRefField->getFieldDefinition()->getType();

      if ($entityRefFieldType == "entity_reference") {
        $fieldName = $entityRefField
          ->getFieldDefinition()
          ->getname();
        $entityRefType = $entityRefField
          ->getFieldDefinition()
          ->getSettings()['target_type'];
        $entityRefFieldName = key(end($entityRefField->getValue()));
        $entityRefField = "$fieldName.$entityRefType.$entityRefFieldName";
        $this->getEntityReferenceValue($entityRefField, $entityRef);
      }
      else {
        $entityRefField = [
          $entityRefField->getName() => end($entityRefField->getValue()[0]),
        ];
      }
    }

    return $entityRefField;
  }

}
