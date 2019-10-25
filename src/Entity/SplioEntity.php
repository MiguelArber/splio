<?php

namespace Drupal\splio\Entity;

use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class SplioEntity.
 *
 * @property \Drupal\Core\Entity\EntityTypeManagerInterface entityTypeManager
 * @package Drupal\splio\Entity
 */
class SplioEntity {

  // Used to provide dependency injection methods for serialization.
  use DependencySerializationTrait;

  /**
   * Stores the Splio type.
   *
   * @var string
   */
  private $splioType;

  /**
   * Stores the Drupal entity type.
   *
   * @var string
   */
  private $localType;

  /**
   * Stores the Drupal bundle type.
   *
   * @var string
   */
  private $localBundleType;

  /**
   * Stores the name of the Splio entity.
   *
   * @var string
   */
  private $label;

  /**
   * Stores the configured Splio entities.
   *
   * @var array
   */
  private $splioEntities;

  /**
   * Stores the entity fields that belong to a specific Splio entity.
   *
   * @var array
   */
  private $splioEntityFields = [];

  /**
   * Defines the entity default fields for each Splio entity type.
   *
   * These fields are mandatory by Splio so they must be always present in the
   * form (and requests to Splio's API) in order to be configured by the user.
   *
   * @var array
   */
  const ENTITY_DEFAULT_FIELDS = [
    'contacts' => [
      'email',
      'date',
      'firstname',
      'lastname',
      'lang',
      'cellphone',
    ],
    'products' => [
      'extid',
      'date_added',
      'date_updated',
      'name',
      'brand',
      'description',
      'price',
      'sku',
      'category',
      'img_url',
    ],
    'receipts' => [
      'extid',
      'customer',
      'id_store',
      'date_added',
      'date_order',
      'shipping_amount',
      'discount_amount',
      'total_price',
      'order_completed',
      'tax_amount',
      'currency',
      'salesperson',
    ],
    'order_lines' => [
      'extid',
      'order_id',
      'unit_price',
      'quantity',
      'discount_amount',
      'tax_amount',
      'total_line_amount',
      'currency',
    ],
    'stores' => [
      'extid',
      'date_added',
      'date_updated',
      'name',
      'channel',
      'store_type',
      'manager',
    ],
    'contacts_lists' => [],
  ];

  /**
   * SplioEntity constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entityTypeManager.
   * @param \Drupal\Core\Config\ConfigFactory $config
   *   Local config for Splio entities.
   * @param string $splioType
   *   Manages the current path the user is navigating.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, ConfigFactory $config, string $splioType) {
    $this->entityTypeManager = $entityTypeManager;
    $this->splioEntities = $config->get('splio.entity.config')
      ->get('splio_entities');
    $this->splioType = $splioType;
    $this->setupEntity($this->splioEntities[$splioType]);
    $this->setupEntityFields();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
    // Load the services required to construct this class.
      $container->get('entity_type.manager'),
      $container->get('config.factory'),
      $container->get('path.current')
    );
  }

  /**
   * Sets up the proper Splio entity.
   *
   * @param array $splioEntity
   *   Array containing the Local config for Splio entities.
   */
  private function setupEntity(array $splioEntity) {
    $this->localType = empty($splioEntity['local_entity']) ?: $splioEntity['local_entity'];
    $this->localBundleType = empty($splioEntity['local_entity_bundle']) ?: $splioEntity['local_entity_bundle'];
    $this->label = empty($splioEntity['label']) ?: $splioEntity['label'];
  }

  /**
   * Sets up the proper Splio entity fields.
   *
   * Loads the entity fields from DB. In case there are no stored fields for the
   * entity it will create the default fields and will store them.
   */
  private function setupEntityFields() {
    $entityDefaultFields = static::ENTITY_DEFAULT_FIELDS[$this->splioType];
    $entityFields = [];

    // In order to have the default fields always presented first to the user:
    foreach ($entityDefaultFields as $field) {
      // First load the default fields in the predefined order...
      $entityFields += $this->entityTypeManager->getStorage('splio_field')
        ->loadByProperties([
          'splio_entity' => $this->splioType,
          'splio_field' => $field,
        ]);
    }

    // Then load the custom fields.
    $entityFields += $this->entityTypeManager->getStorage('splio_field')
      ->loadByProperties([
        'splio_entity' => $this->splioType,
        'is_default_field' => FALSE,
      ]);

    if (!empty($entityFields)) {
      $this->splioEntityFields = $entityFields;
    }
    else {
      $isSetKeyField = FALSE;

      foreach ($entityDefaultFields as $defaultField) {

        $splioField = $this->entityTypeManager->getStorage('splio_field')
          ->create();
        $id = $defaultField . '_' . $this->splioType;

        $splioField->setId($id);
        $splioField->setSplioEntity($this->splioType);
        $splioField->setSplioField($defaultField);
        $splioField->setIsDefaultField(TRUE);

        // A keyField is always set by default.
        if (!$isSetKeyField) {
          $splioField->setIsKeyField(TRUE);
          $isSetKeyField = TRUE;
        }

        $splioField->save();
      }
    }
  }

  /**
   * Returns the splio type of the entity.
   *
   * @return string
   *   String containing the splio entity type.
   */
  public function getSplioType() {
    return $this->splioType;
  }

  /**
   * Returns the local type of the entity.
   *
   * @return string
   *   String containing the local entity type.
   */
  public function getLocalType() {
    return $this->localType;
  }

  /**
   * Returns the label of the entity.
   *
   * @return string
   *   String containing the entity name.
   */
  public function getLabel() {
    return $this->label;
  }

  /**
   * Returns all the default fields for the given SplioEntity.
   *
   * @return array
   *   Array containing the default splio fields of the entity.
   */
  public function getEntityFields(): array {
    return $this->splioEntityFields;
  }

  /**
   * Returns the local bundle type of the entity.
   *
   * @return string
   *   String containing the local entity type.
   */
  public function getLocalBundleType() {
    return $this->localBundleType;
  }

}
