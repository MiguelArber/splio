<?php

namespace Drupal\splio\Plugin\QueueWorker;

use Drupal\Component\EventDispatcher\ContainerAwareEventDispatcher;
use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\splio\Services\SplioConnector;
use Drupal\Core\Queue\SuspendQueueException;
use Drupal\splio\Event\SplioQueueEvent;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Manages the splio's entity queue and starts the sync process on CRON run.
 *
 * @property \Drupal\Core\Entity\EntityTypeManager entityManager
 * @property \Drupal\splio\Services\SplioConnector splioConnector
 * @property \Drupal\Core\Config\ConfigFactory config
 * @property \Drupal\Component\EventDispatcher\ContainerAwareEventDispatcher eventDispatcher
 * @property \Psr\Log\LoggerInterface logger
 * @QueueWorker(
 *   id = "cron_splio_sync",
 *   title = @Translation("Cron Splio sync process"),
 *   cron = {"time" = 40}
 * )
 */
class SplioQueueController extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * SplioQueueController constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManager $entityManager
   *   The entityManager service.
   * @param \Drupal\splio\Services\SplioConnector $splioConnector
   *   The splioConnector service.
   * @param \Drupal\Core\Config\ConfigFactory $config
   *   The configFactory service.
   * @param \Drupal\Component\EventDispatcher\ContainerAwareEventDispatcher $eventDispatcher
   *   The event dispatched for Splio requests.
   * @param \Psr\Log\LoggerInterface $logger
   *   The loggerInterface service.
   */
  public function __construct(EntityTypeManager $entityManager, SplioConnector $splioConnector, ConfigFactory $config, ContainerAwareEventDispatcher $eventDispatcher, LoggerInterface $logger) {
    $this->entityManager = $entityManager;
    $this->splioConnector = $splioConnector;
    $this->eventDispatcher = $eventDispatcher;
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
      $container->get('entity_type.manager'),
      $container->get('splio.splio_connector'),
      $container->get('config.factory'),
      $container->get('event_dispatcher'),
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

    try {

      // Load the entity based on its Drupal id.
      $entity = $this->entityManager
        ->getStorage($entityDrupalType)
        ->load($data['id']);

      // Maintain the language context that was queued.
      if (isset($data['lang']) && !empty($entity) && $entity->hasTranslation($data['lang'])) {
        $entity = $entity->getTranslation($data['lang']);
      }

      // In delete actions, the $entity might not exist, in these cases the
      // provided data['original'] entity will be used as the current entity.
      if (empty($entity) && !(empty($data['original']))) {
        $entity = $data['original'];
      }
    }
    catch (\Exception $exception) {
      $this->logger
        ->error("A problem occurred when trying to process the item: %message",
          [
            '%message' => $exception->getMessage(),
          ]);
      return;
    }

    // Manage the event to be dispatched.
    $queueEvent = new SplioQueueEvent($data);
    $this->eventDispatcher
      ->dispatch(SplioQueueEvent::SPLIO_DEQUEUE, $queueEvent);

    // In case someone captured the event and made changes in the item,
    // update the item before inserting it into the queue.
    $data = $queueEvent->getSplioQueueItem();

    // If there is any, add the original entity to the current entity.
    empty($data['original']) ?: $entity->original = $data['original'];

    // Set the CRUD action to be performed by the SplioConnector service.
    $action = $data['action'] . 'Entities';

    if (method_exists($this->splioConnector, $action)) {

      // If the received action is valid, execute it.
      $result = $this->splioConnector->$action([$entity]);

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
          // Mind that the exception below will cause the queue to stop running
          // in case it was executed via drush queue-run. The queue is meant to
          // be handled by cron.
          throw new \Exception("A problem occurred, the " . $data['splioEntityType'] . " " . $data['id'] . " could not be processed.");
        }
      }
    }

  }

}
