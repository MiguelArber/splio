<?php

namespace Drupal\splio\Plugin\QueueWorker;

use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Entity\EntityManager;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\splio\Services\SplioConnector;
use Drupal\Core\Queue\SuspendQueueException;
use Drupal\Core\Queue\RequeueException;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Manages the splio's entity queue and starts the sync process on CRON run.
 *
 * @property \Drupal\Core\Entity\EntityManager entityManager
 * @property \Drupal\splio\Services\SplioConnector splioConnector
 * @property \Drupal\Core\Config\ConfigFactory config
 * @property \Psr\Log\LoggerInterface logger
 * @QueueWorker(
 *   id = "cron_splio_sync",
 *   title = @Translation("Cron Splio sync process"),
 *   cron = {"time" = 60}
 * )
 */
class SplioQueueController extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * SplioQueueController constructor.
   *
   * @param \Drupal\Core\Entity\EntityManager $entityManager
   *   The entityManager service.
   * @param \Drupal\splio\Services\SplioConnector $splioConnector
   *   The splioConnector service.
   * @param \Drupal\Core\Config\ConfigFactory $config
   *   The configFactory service.
   * @param \Psr\Log\LoggerInterface $logger
   *   The loggerInterface service.
   */
  public function __construct(EntityManager $entityManager, SplioConnector $splioConnector, ConfigFactory $config, LoggerInterface $logger) {
    $this->entityManager = $entityManager;
    $this->splioConnector = $splioConnector;
    $this->config = $config;
    $this->logger = $logger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    /** @var \Psr\Log\LoggerInterface $logger */
    $logger = $container->get('logger.channel.splio');

    return new static(
      $container->get('entity.manager'),
      $container->get('splio.splio_connector'),
      $container->get('config.factory'),
      $logger
    );
  }

  /**
   * Works on a single queue item.
   *
   * @param mixed $data
   *   The data that was passed to
   *   \Drupal\Core\Queue\QueueInterface::createItem() when the item was queued.
   *
   * @throws \Drupal\Core\Queue\RequeueException
   *   Processing is not yet finished. This will allow another process to claim
   *   the item immediately.
   * @throws \Exception
   *   A QueueWorker plugin may throw an exception to indicate there was a
   *   problem. The cron process will log the exception, and leave the item in
   *   the queue to be processed again later.
   * @throws \Drupal\Core\Queue\SuspendQueueException
   *   More specifically, a SuspendQueueException should be thrown when a
   *   QueueWorker plugin is aware that the problem will affect all subsequent
   *   workers of its queue. For example, a callback that makes HTTP requests
   *   may find that the remote server is not responding. The cron process will
   *   behave as with a normal Exception, and in addition will not attempt to
   *   process further items from the current item's queue during the current
   *   cron run.
   *
   * @see \Drupal\Core\Cron::processQueues()
   */
  public function processItem($data) {
    // Load the Drupal entity type/bundle for the received data.
    $entityDrupalType = $this->config->get('splio.entity.config')
      ->get('splio_entities')[$data['splioEntityType']]['local_entity'];

    // Load the configured key field for the Splio entity.
    $entitySplioKeyField = $this->config->get('splio.entity.config')
      ->get('splio_entities')[$data['splioEntityType']]['splio_entity_key_field'];

    try {
      // Load the drupal field mapped to the previously loaded key field.
      $entityDrupalField = $this->entityManager
        ->getStorage('splio_field')
        ->loadByProperties([
          'splio_entity' => $data['splioEntityType'],
        ])[$entitySplioKeyField]->getDrupalField();

      // Load the entity based on the obtained key field.
      $entity = $this->entityManager
        ->getStorage($entityDrupalType)
        ->loadByProperties([
          $entityDrupalField => $data['id'],
        ]);
    }
    catch (\Exception $exception) {
      $this->logger
        ->error("A problem occurred when trying to process the item: %message",
          [
            '%message' => $exception->getMessage(),
          ]);
      return;
    }

    // If there is any, add the original entity to the current entity.
    empty($data['original']) ?: end($entity)->original = $data['original'];

    // Set the CRUD action to be performed by the SplioConnector service.
    $action = $data['action'] . 'Entities';

    // Execute the action.
    $result = $this->splioConnector->$action($entity);

    // In case there the element in the result array turns to be an exception
    // object, throw it!
    if (is_subclass_of(end($result), 'Exception')) {

      // Get the exception.
      $exception = end($result);

      // If the exception code is 500 try again later.
      if ($exception->getCode() == 500) {
        throw new SuspendQueueException('Splio server is not responding. Aborting sync...');
      }
      else {
        // Mind that the exception below will cause the queue to stop running in
        // case it was executed via drush queue-run. The queue is meant to be
        // handled by cron.
        throw new \Exception("A problem occurred, the " . $data['id'] . " item cannot not be processed at this moment.");
      }
    }

  }

}
