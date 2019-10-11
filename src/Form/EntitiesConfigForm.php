<?php

namespace Drupal\splio\Form;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeBundleInfo;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Entity\EntityTypeRepository;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Menu\LocalTaskManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class EntitiesConfigForm.
 *
 * @property \Drupal\Core\Config\ConfigFactoryInterface config_factory
 * @property \Drupal\Core\Entity\EntityTypeRepository entityTypeRepository
 * @property \Drupal\Core\Entity\EntityTypeBundleInfo entityTypeBundleInfo
 * @property \Drupal\Core\Entity\EntityTypeManager entityTypeManager
 * @property \Drupal\Core\Menu\LocalTaskManager localTaskManager
 * @property \Drupal\Core\Cache\CacheBackendInterface cache
 * @package Drupal\splio\Form
 */
class EntitiesConfigForm extends ConfigFormBase {

  /**
   * The Splio settings.
   *
   * @var string
   */
  const SETTINGS = 'splio.entity.config';

  /**
   * The Splio entity types.
   *
   * @var array
   */
  const SPLIO_ENTITIES = [
    'contacts',
    'products',
    'receipts',
    'order_lines',
    'stores',
  ];

  /**
   * The Splio entity label.
   *
   * @var array
   */
  protected $splioEntityLabel;

  /**
   * The current Splio entity.
   *
   * @var string
   */
  protected $currentSplioEntity;

  /**
   * Stores the entity type labels.
   *
   * @var array
   */
  protected $contentEntities;

  /**
   * EntityConfigForm constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   Config factory to manage local configuration.
   * @param \Drupal\Core\Entity\EntityTypeRepository $entityTypeRepository
   *   Entity type repository to get access to the entity repository.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfo $entityTypeBundleInfo
   *   Entity type bundle info to get access to the bundles of each entity.
   * @param \Drupal\Core\Entity\EntityTypeManager $entityTypeManager
   *   Entity type manager to get access to the entity manager.
   * @param \Drupal\Core\Menu\LocalTaskManager $localTaskManager
   *   Local task manager to clear the tasks cache.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache
   *   Drupal's cache to manage the cached data.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    EntityTypeRepository $entityTypeRepository,
    EntityTypeBundleInfo $entityTypeBundleInfo,
    EntityTypeManager $entityTypeManager,
    LocalTaskManager $localTaskManager,
    CacheBackendInterface $cache) {

    parent::__construct($config_factory);
    $this->entityTypeRepository = $entityTypeRepository;
    $this->entityTypeBundleInfo = $entityTypeBundleInfo;
    $this->entityTypeManager = $entityTypeManager;
    $this->localTaskManager = $localTaskManager;
    $this->cache = $cache;
    $this->splioEntityLabel = [
      'contacts' => $this->t('Contacts'),
      'products' => $this->t('Products'),
      'receipts' => $this->t('Receipts'),
      'order_lines' => $this->t('Order lines'),
      'stores' => $this->t('Stores'),
      'contacts_lists' => $this->t('Contacts lists'),
    ];
  }

  /**
   * EntityConfigForm container creator.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   Global container for EntityConfigForm.
   *
   * @return \Drupal\Core\Form\ConfigFormBase|\Drupal\splio\Form\EntitiesConfigForm
   *   Returns the config form with the injected services.
   */
  public static function create(ContainerInterface $container) {
    return new static(
    // Load the services required to construct this class.
      $container->get('config.factory'),
      $container->get('entity_type.repository'),
      $container->get('entity_type.bundle.info'),
      $container->get('entity_type.manager'),
      $container->get('plugin.manager.menu.local_task'),
      $container->get('cache.default')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      static::SETTINGS,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'splio_entity_config_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $config = $this
      ->config(static::SETTINGS)
      ->get('splio_entities');

    $form['entity_config'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Entity configuration'),
    ];

    $form['entity_config']['entity_config_table'] = [
      '#type' => 'table',
      '#caption' => $this->t("Configure the mapping between Splio and your site's entities."),
      '#header' => [
        'splio_entity' => $this->t('Splio entity'),
        'local_entity' => $this->t('Local entity'),
        'local_entity_bundle' => $this->t('Local entity bundle'),
      ],
      '#attributes' => [
        'id' => 'local_entity',
      ],
    ];


    $splioEntities = static::SPLIO_ENTITIES;
    $this->contentEntities = $this
      ->entityTypeRepository
      ->getEntityTypeLabels(TRUE)['Content'];

    // For some reason, the select in the last row of the table isn't
    // processed correctly, which causes the '- None -' option to not display.
    // This small workaround fixes it.
    array_push($splioEntities, 'fix');

    foreach ($splioEntities as $splioEntity) {

      $this->currentSplioEntity = $splioEntity;

      $form['entity_config']['entity_config_table'][$splioEntity]['splio_entity'] = [
        '#type' => 'label',
        '#title' => empty($this->splioEntityLabel[$splioEntity]) ?:
        $this->splioEntityLabel[$splioEntity],
      ];

      $form['entity_config']['entity_config_table'][$splioEntity]['local_entity'] = [
        '#type' => 'select',
        '#options' => $this->contentEntities,
        '#empty_value' => '',
        '#default_value' => empty($config[$splioEntity]['local_entity']) ?:
        $config[$splioEntity]['local_entity'],
        '#ajax' => [
          'callback' => [$this, 'updateBundleSelect'],
          'event' => 'change',
          'wrapper' => 'local_entity',
          'progress' => [
            'type' => NULL,
            'message' => NULL,
          ],
        ],
      ];

      $entityUserInput = empty($form_state->getUserInput()['entity_config_table'][$splioEntity]['local_entity']) ?
        (empty($config[$splioEntity]['local_entity']) ?: $config[$splioEntity]['local_entity'])
        : $form_state->getUserInput()['entity_config_table'][$splioEntity]['local_entity'];

      $form['entity_config']['entity_config_table'][$splioEntity]['local_entity_bundle'] = [
        '#type' => 'select',
        '#options' => $bundles = $this->getEntityBundles($entityUserInput),
        '#empty_value' => '',
        '#default_value' => empty($form_state
          ->getUserInput()['entity_config_table'][$splioEntity]['local_entity']) ?
        (empty($config[$splioEntity]['local_entity_bundle']) ?:
            $config[$splioEntity]['local_entity_bundle']) : '',
        '#validated' => TRUE,
        '#disabled' => empty($bundles) ? TRUE : FALSE,
      ];
      !empty($bundles) ?:
      $form['entity_config']['entity_config_table'][$splioEntity]['local_entity_bundle']['#attributes'] = ['style' => 'color:grey;'];
    }

    // D8_BUG??: Removes the fix element from the form after creating the form.
    array_pop($form['entity_config']['entity_config_table']);

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    $splioEntities = static::SPLIO_ENTITIES;
    $splioCurrentConfig = $this->configFactory
      ->getEditable(static::SETTINGS)
      ->get('splio_entities');
    $splioEntitiesArray = [];

    foreach ($splioEntities as $splioEntity) {
      $splioEntitiesArray[$splioEntity]['label'] = $this->splioEntityLabel[$splioEntity];
      $splioEntitiesArray[$splioEntity]['local_entity'] =
        empty($form_state
          ->getUserInput()['entity_config_table'][$splioEntity]['local_entity']) ?
          '' :
          $form_state
            ->getUserInput()['entity_config_table'][$splioEntity]['local_entity'];
      $splioEntitiesArray[$splioEntity]['local_entity_bundle'] =
        empty($form_state
          ->getUserInput()['entity_config_table'][$splioEntity]['local_entity_bundle']) ?
          '' :
          $form_state
            ->getUserInput()['entity_config_table'][$splioEntity]['local_entity_bundle'];

      // Sets a default key_field if none has been set before.
      if (empty($splioCurrentConfig['splio_entity_key_field'])) {
        if ($splioEntity == 'contacts') {
          $splioEntitiesArray[$splioEntity]['splio_entity_key_field'] = 'email_' . $splioEntity;
        }
        else {
          $splioEntitiesArray[$splioEntity]['splio_entity_key_field'] = 'extid_' . $splioEntity;
        }
      }

      if ($splioEntity == 'contacts' && !empty($form_state->getUserInput()['entity_config_table']['contacts']['local_entity'])) {
        $splioEntitiesArray['contacts_lists']['label'] = $this->splioEntityLabel['contacts_lists'];
        $splioEntitiesArray['contacts_lists']['local_entity'] = $splioEntitiesArray['contacts']['local_entity'];
        $splioEntitiesArray['contacts_lists']['local_entity_bundle'] = $splioEntitiesArray['contacts']['local_entity_bundle'];
        $splioEntitiesArray['contacts_lists']['splio_entity_key_field'] = NULL;
      }

      // Remove the SplioFields in case a SplioEntity is set to none.
      if ((empty($form_state->getUserInput()['entity_config_table'][$splioEntity]['local_entity']))
        ||  $form_state->getUserInput()['entity_config_table'][$splioEntity]['local_entity'] != $splioCurrentConfig[$splioEntity]['local_entity']) {
        $entityFields = $this->entityTypeManager
          ->getStorage('splio_field')
          ->loadByProperties(['splio_entity' => $splioEntity]);
        $this->entityTypeManager
          ->getStorage('splio_field')
          ->delete($entityFields);
        $splioEntitiesArray[$splioEntity]['splio_entity_key_field'] = '';
        $this->cache
          ->delete('entity_fields_' . $splioEntity);
      }
    }

    $this->configFactory->getEditable(static::SETTINGS)
      ->set('splio_entities', $splioEntitiesArray)->save();

    $this->localTaskManager->clearCachedDefinitions();

    parent::submitForm($form, $form_state);
  }

  /**
   * Updates the select bundle items based on the entity selected by the user.
   *
   * @param array $form
   *   The current form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form-state.
   *
   * @return array
   *   Returns the updated form.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function updateBundleSelect(array $form, FormStateInterface $form_state) {
    $splioEntities = static::SPLIO_ENTITIES;
    $config = $this
      ->config(static::SETTINGS)
      ->get('splio_entities');

    foreach ($splioEntities as $splioEntity) {
      $entityUserInput = $form_state
        ->getUserInput()['entity_config_table'][$splioEntity]['local_entity'];

      if ($splioEntity == $this->currentSplioEntity) {
        $form['entity_config']['entity_config_table'][$splioEntity]['local_entity_bundle'] = [
          '#type' => 'select',
          '#options' => empty($bundles) ? '' : $bundles,
          '#empty_value' => '',
          '#default_value' => '',
          '#disabled' => empty($bundles) ? TRUE : FALSE,
        ];
        !empty($bundles) ?:
          $form['entity_config']['entity_config_table'][$splioEntity]['local_entity_bundle']['#attributes'] = ['style' => 'color:grey;'];
      }

      // Some UI changes so the form is more intuitive.
      if ((empty($entityUserInput) && !empty($config[$splioEntity]['local_entity']))
        || ($entityUserInput != $config[$splioEntity]['local_entity'] && !empty($config[$splioEntity]['local_entity']))) {
        $form['entity_config']['entity_config_table'][$splioEntity]['splio_entity']['#attributes'] = ['style' => 'color:red;'];
        $form['entity_config']['entity_config_table'][$splioEntity]['local_entity']['#description'] = '<span>' . $this
          ->t("Saved config for %splioEntity will be lost!", ['%splioEntity' => $this->splioEntityLabel[$splioEntity]]) . '</span>';
        $form['entity_config']['entity_config_table'][$splioEntity]['local_entity']['#attributes'] = ['style' => 'color:red;'];
        if (empty($bundles)) {
          $form['entity_config']['entity_config_table'][$splioEntity]['local_entity_bundle']['#disabled'] = TRUE;
        }
        else {
          $form['entity_config']['entity_config_table'][$splioEntity]['local_entity_bundle']['#attributes'] = ['style' => 'color:red;'];
        }
      }
    }

    return $form['entity_config']['entity_config_table'];
  }

  /**
   * Returns the bundles for the selected Entity.
   *
   * In case no bundles are returned, returns an empty array.
   *
   * @param string $entity_type_id
   *   Entity id.
   *
   * @return array
   *   Array containing the bundles.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  private function getEntityBundles($entity_type_id) {

    $bundles = [];

    if (empty($entity_type_id) || is_array($entity_type_id)) {
      return $bundles;
    }

    $allBundles = $this->entityTypeBundleInfo->getBundleInfo($entity_type_id);

    foreach ($allBundles as $key => $bundle) {
      $label = end($bundle);
      if (!is_object($label)) {
        $bundles[$this->entityTypeManager->getDefinition($entity_type_id)
          ->getBundleLabel()][$key] = $label;
      }
      else {
        return [];
      }
    }

    return $bundles;
  }

}
