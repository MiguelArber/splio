<?php

namespace Drupal\splio\Form;

use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Entity\EntityFieldManager;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Path\CurrentPathStack;
use Drupal\field\Entity\FieldConfig;
use Drupal\splio\Entity\SplioEntity;
use Drupal\splio\Services\SplioConnector;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Form handler for the SplioField to edit fields.
 *
 * @property \Drupal\Core\Entity\EntityFieldManager entityFieldManager
 * @property \Drupal\Core\Config\ConfigFactory config
 * @property \Drupal\Core\Path\CurrentPathStack currentPathStack
 * @property \Drupal\Core\Cache\Cache cache
 * @property \Drupal\splio\Services\SplioConnector splioConnector
 */
class SplioFieldForm extends EntityForm {

  public $splioEntity;

  public $currentEntity;

  public $entityFields = array();

  public $fieldOptions = array();

  public $contactsLists = array();

  public $remoteContactsLists = array();

  /**
   * Constructs an SplioEntityConfigForm object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entityTypeManager.
   * @param \Drupal\Core\Entity\EntityFieldManager $entityFieldManager
   *   The entityFieldManager.
   * @param \Drupal\Core\Config\ConfigFactory $config
   *   The configFactory.
   * @param \Drupal\Core\Path\CurrentPathStack $currentPathStack
   *   The currentPathStack.
   * @param \Drupal\splio\Services\SplioConnector $splioConnector
   *   The splioConnector service.
   */
  public function __construct(
    EntityTypeManagerInterface $entityTypeManager,
    EntityFieldManager $entityFieldManager,
    ConfigFactory $config,
    CurrentPathStack $currentPathStack,
    SplioConnector $splioConnector) {
    $this->entityTypeManager = $entityTypeManager;
    $this->entityFieldManager = $entityFieldManager;
    $this->config = $config;
    $this->currentPathStack = $currentPathStack;
    $this->splioConnector = $splioConnector;
    $this->checkEntityConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('entity_field.manager'),
      $container->get('config.factory'),
      $container->get('path.current'),
      $container->get('splio.splio_connector')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    $this->setupForm($form, $form_state);

    $form['entity_fields'] = [
      '#type' => 'fieldset',
      '#title' => $this->currentEntity . " " . $this->t("fields configuration"),
    ];

    $form['entity_fields']['entity_fields_table'] = [
      '#type' => 'table',
      '#caption' => $this->t(
        "Configure the mapping between the Splio's %entity fields and your site's fields.",
        ['%entity' => $this->currentEntity]),
      '#attributes' => [
        'id' => 'entity_fields',
      ],
    ];

    if ($this->currentEntity == 'order_lines') {
      $form['entity_fields']['entity_fields_table']['#header'] = [
        $splio_field = $this->t('Splio field'),
        $drupal_field = $this->t('Local field'),
        $type_field = $this->t('Type'),
        $actions = $this->t('Actions'),
      ];
    }
    else {
      $form['entity_fields']['entity_fields_table']['#header'] = [
        $splio_field = $this->t('Splio field'),
        $drupal_field = $this->t('Local field'),
        $type_field = $this->t('Type'),
        $is_key_field = $this->t('Is key'),
        $actions = $this->t('Actions'),
      ];
    }

    if ($this->currentEntity != 'order_lines') {
      $form['entity_fields']['radios'] = [
        '#type' => 'tableselect',
        '#multiple' => FALSE,
        '#header' => [],
      ];
    }

    foreach ($this->entityFields as $splioField => $splioFieldDef) {

      if ($splioFieldDef->isDefaultField()) {
        $form['entity_fields']['entity_fields_table'][$splioField]['splio_field'] = [
          '#type' => 'label',
          '#title' => $splioFieldDef->getSplioField(),
          '#description' => '',
        ];
      }
      else {
        $form['entity_fields']['entity_fields_table'][$splioField]['splio_field'] = [
          '#type' => 'textfield',
          '#maxlength' => 64,
          '#size' => 30,
          '#default_value' => empty($splioFieldDef->getSplioField()) ?
          $form_state->getUserInput()['entity_fields']['entity_fields_table'][$splioField]['splio_field']
          : $splioFieldDef->getSplioField(),
        ];
      }

      $form['entity_fields']['entity_fields_table'][$splioField]['drupal_field'] = [
        '#type' => 'select',
        '#options' => $this->fieldOptions,
        '#empty_value' => '',
        '#default_value' => empty($splioFieldDef->getDrupalField()) ?:
        $splioFieldDef->getDrupalField(),
      ];

      $form['entity_fields']['entity_fields_table'][$splioField]['type_field'] = [
        '#type' => 'select',
        '#options' => $splioFieldDef->getFieldTypes(),
        '#default_value' => empty($splioFieldDef->getTypeField()) ?:
        $splioFieldDef->getTypeField(),
      ];

      if ($this->currentEntity != 'order_lines') {
        $form['entity_fields']['entity_fields_table'][$splioField]['is_key_field'] = [
          '#type' => 'radio',
          '#default_value' => empty($splioFieldDef->isKeyField()) ?: $splioFieldDef->isKeyField(),
          '#attributes' => [
            'name' => 'radios',
            'value' => $splioField,
            (!$splioFieldDef->isKeyField()) ?: 'checked' => 'checked',
          ],
        ];
      }

      $form['entity_fields']['entity_fields_table'][$splioField]['actions'] = [
        '#type' => 'actions',
      ];

      $form['entity_fields']['entity_fields_table'][$splioField]['actions']['remove_splio_field_' . $splioField] = [
        '#type' => 'button',
        '#value' => 'Remove',
        '#disabled' => $splioFieldDef->isDefaultField() ? TRUE : FALSE,
        '#executes_submit_callback' => FALSE,
        '#id' => $splioField,
        '#name' => $splioField,
        '#ajax' => [
          'callback' => [$this, 'removeField'],
          'wrapper' => 'splio-field-form-wrapper',
          'progress' => [
            'type' => NULL,
            'message' => NULL,
          ],
        ],
      ];
    }

    $form['entity_fields']['actions'] = [
      '#type' => 'actions',
    ];

    $form['entity_fields']['actions']['add_splio_field'] = [
      '#type' => 'button',
      '#value' => 'Add field',
      '#executes_submit_callback' => FALSE,
      '#ajax' => [
        'callback' => [$this, 'addField'],
        'wrapper' => 'splio-field-form-wrapper',
        'progress' => [
          'type' => NULL,
          'message' => NULL,
        ],
      ],
    ];

    if ($this->currentEntity == 'contacts') {
      $form += $this->generateContactsListForm($form, $form_state);
    }

    $form['#prefix'] = "<div id='splio-field-form-wrapper'>";
    $form['#suffix'] = "</div>";

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {

    $this->currentEntity = $this->splioEntity->getSplioType();

    foreach ($this->entityFields as $splioField => $splioFieldDef) {
      $splioFieldType = '_' . $this->currentEntity;

      $splioFieldDef->setId(
          empty($form_state->getUserInput()['entity_fields_table'][$splioField]['splio_field']) ?
          $splioField
          : $form_state->getUserInput()['entity_fields_table'][$splioField]['splio_field'] . $splioFieldType
        );

      $splioFieldDef->setSplioEntity($this->currentEntity);
      $splioFieldDef->setSplioField(
        empty($form_state->getUserInput()['entity_fields_table'][$splioField]['splio_field']) ?
        $splioFieldDef->getSplioField()
        : $form_state->getUserInput()['entity_fields_table'][$splioField]['splio_field']
      );

      $splioFieldDef
        ->setDrupalField($form_state
          ->getUserInput()['entity_fields_table'][$splioField]['drupal_field']);
      $splioFieldDef
        ->setTypeField($form_state
          ->getUserInput()['entity_fields_table'][$splioField]['type_field']);

      ($this->currentEntity == 'order_lines')?:
      $splioFieldDef
        ->setIsKeyField(($form_state
          ->getUserInput()['radios'] == $splioField) ? TRUE : FALSE);

      if ($this->currentEntity != 'order_lines') {
        if ($form_state->getUserInput()['radios'] == $splioField) {
          $currentEntityConfig = $this->config
            ->get('splio.entity.config')
            ->get('splio_entities');
          $currentEntityConfig[$this->currentEntity]['splio_entity_key_field'] = $splioFieldDef->getSplioField() . $splioFieldType;

          $this->config
            ->getEditable('splio.entity.config')
            ->set('splio_entities', $currentEntityConfig)
            ->save();
        }
      }

      if ($splioFieldDef->isNew()) {
        if ($this->exist($splioFieldDef->getId())) {
          $splioFieldDef->setId($splioField . $splioFieldType);
          $this->messenger()
            ->addMessage($this->t(
              "The %splioField field could not be saved. The field ID must be unique. Another field with the same ID already exists.",
              [
                '%splioField' => $form_state
                  ->getUserInput()
                ['entity_fields_table'][$splioField]['splio_field'],
              ]
            ), MessengerInterface::TYPE_WARNING);
        }
        else {
          $status = $splioFieldDef->save();
        }
      }
      else {
        $status = $splioFieldDef->save();
      }

      if ($status) {
        $this->messenger()
          ->addMessage($this->t('Fields saved successfully.'));
      }
      else {
        $this->messenger()
          ->addMessage($this->t('Something went wrong, the fields could not not saved. Try again.'), MessengerInterface::TYPE_ERROR);
      }
    }

    $entityFields = $this->splioEntity->getEntityFields();

    foreach ($entityFields as $splioField => $splioFieldDef) {
      if (!array_key_exists($splioField, $this->entityFields)) {
        $splioFieldDef->delete();
      }
    }

    if ($this->currentEntity == 'contacts') {
      $this->saveContactsLists($form, $form_state);
    }

    $form_state->setRedirect('entity.splio.' . $this->currentEntity);
  }

  /**
   * Helper function to check whether an Example configuration entity exists.
   */
  public function exist($id) {
    $entity = $this->entityTypeManager
      ->getStorage('splio_field')
      ->getQuery()
      ->condition('id', $id)
      ->execute();
    return (bool) $entity;
  }

  /**
   * Sets up the variables that will be used in the form.
   *
   * @param array $form
   *   The current form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form_state.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   *
   * TODO: At the moment, only one-level entityReferences are allowed to be set
   *   in each field. In the future could be interesting to allow an undefined
   *   number of recursive entityReferences for every field.
   */
  private function setupForm(array $form, FormStateInterface $form_state) {
    $this->currentEntity = $this->splioEntity->getSplioType();
    $storage = $form_state->getStorage();
    $values = $form_state->getValues();

    $form_state_fields =
      $storage['entity_fields'] ??
      $values['entity_fields'] ?? [];

    $this->entityFields =
      empty($form_state_fields) ?
        $this->splioEntity->getEntityFields() ??
        $this->entityTypeManager->getStorage('splio_field')
          ->loadByProperties(
            ['splio_entity' => $this->splioEntity->getSplioType()]
          )
        : $form_state_fields;

    $localEntity = $this->splioEntity->getLocalType();
    $localBundleEntity = $this->splioEntity->getLocalBundleType();

    $fieldOptions = [];
    $baseFieldDefinitions = $this->entityFieldManager
      ->getFieldDefinitions($localEntity, $localBundleEntity);

    foreach ($baseFieldDefinitions as $fieldName => $fieldDef) {
      $fieldOptions['Fields'][$fieldDef->getName()] = $fieldDef->getName();

      // In case the current field is an entity reference, load all the
      // subfields that belong to the entity reference.
      if ($fieldDef->getType() == "entity_reference") {

        // Get the target type of the field.
        $entityReferenceType = $fieldDef
          ->getFieldStorageDefinition()
          ->getSetting('target_type');

        // If the current field is a FieldConfig, load its bundle.
        $entityReferenceBundle = ($fieldDef instanceof FieldConfig) ?
          $fieldDef->get('bundle')
          : NULL;

        // Try to get the base field definitions.
        try {
          $this->entityFieldManager->getBaseFieldDefinitions($entityReferenceType);
        }
        // If a logic exception is thrown use the target entity type id instead
        // of the target_type setting.
        catch (\LogicException $logicException) {
          $entityReferenceType = $fieldDef
            ->getFieldStorageDefinition()
            ->getTargetEntityTypeId();
        }
        finally {
          // Finally, after properly defining the entity reference type, if the
          // field has no referenceBundle, load its base fields.
          if (empty($entityReferenceBundle)) {
            $entityReferenceFields = $this
              ->entityFieldManager
              ->getBaseFieldDefinitions($entityReferenceType);
          }
          // In any other case, the field has a referenceBundle so load its
          // field definitions for the entity and its bundle.
          else {
            $entityReferenceFields = $this
              ->entityFieldManager
              ->getFieldDefinitions($entityReferenceType, $entityReferenceBundle);
          }
        }

        // Since the current entity is an entity reference, add each field of
        // the referenced entity to the array.
        foreach ($entityReferenceFields as $entityReferenceDef) {
          $fieldOptions['Entity: ' . $entityReferenceType . " ({$fieldName})"]
          ["{{{$fieldName}.{$entityReferenceType}.{$entityReferenceDef->getName()}}}"]
            = $entityReferenceType . ': ' . $entityReferenceDef->getName();
        }
      }
      // In case the field is not an entity reference, just add the field to the
      // array as a regular one.
      else {
        $fieldOptions['Fields'][$fieldDef->getName()] = $fieldDef->getName();
      }
    }

    $fieldOptions = ['Fields' => $fieldOptions['Fields']] + $fieldOptions;
    $this->fieldOptions = $fieldOptions;
  }

  /**
   * Adds a new field to the table.
   *
   * @param array $form
   *   The current form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form-state.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   Returns the updated form.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function addField(array $form, FormStateInterface $form_state) {

    $storage = $form_state->getStorage();

    $entityFields = isset($storage['entity_fields']) ?
      $storage['entity_fields']
      : $this->entityFields;
    $splioField = $this->entityTypeManager
      ->getStorage('splio_field')->create();

    $splioFieldDefaultId = 'field_' . $this->currentEntity . '_' . count($entityFields);
    $splioField->setId($splioFieldDefaultId);
    $splioField->setSplioEntity($this->currentEntity);
    $splioField->setIsDefaultField(FALSE);
    $splioField->setIsNew(TRUE);

    $values = $form_state->getValues();

    $entityFields[$splioFieldDefaultId] = $splioField;
    $values['entity_fields'] = $entityFields;

    try {
      \Drupal::service('entity.form_builder')
        ->getForm($this->entity, 'edit', $values);
    }
    catch (\Exception $e) {
      $errors = $e->getFormState()->getErrors();
      foreach ($errors as $error) {
        $this->messenger()->addError((string) $error);
      }
      $form = $e->getForm();
    }

    return $form;
  }

  /**
   * Adds a new field to the table.
   *
   * @param array $form
   *   The current form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form-state.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   Returns the updated form.
   */
  public function removeField(array $form, FormStateInterface $form_state) {

    $fieldToDelete = $form_state->getTriggeringElement()['#id'];
    $entityFields = $this->entityFields;

    unset($entityFields[$fieldToDelete]);

    $values = $form_state->getValues();
    $values['entity_fields'] = $entityFields;

    try {
      \Drupal::service('entity.form_builder')
        ->getForm($this->entity, 'edit', $values);
    }
    catch (\Exception $e) {
      $errors = $e->getFormState()->getErrors();
      foreach ($errors as $error) {
        $this->messenger()->addError((string) $error);
      }
      $form = $e->getForm();
    }

    return $form;
  }

  /**
   * Manages the 'contacts_lists' form under the 'contacts' entity form.
   *
   * Requests the contacts lists to Splio and creates a form so the user can
   * configure each list. If it is not possible to retrieve tghe lists from
   * Splio's server it will load the local lists.
   *
   * @param array $form
   *   The current form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form-state.
   *
   * @return array
   *   The $form updated including the 'contacts_list' form.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  private function generateContactsListForm(array $form, FormStateInterface $form_state) {
    $form['contacts_lists'] = [
      '#type' => 'fieldset',
      '#title' => $this->currentEntity . " " . $this->t("Lists configuration"),
    ];

    $form['contacts_lists']['lists_table'] = [
      '#type' => 'table',
      '#caption' => $this->t(
        "A contact <b>will be included</b> in a Splio list <b>only</b> in case the <i>Local List Field</i> contains the <b>exact name</b> of one (or multiple) of the lists below <b>or</b> a <b>TRUE</b> value (integer 1 or boolean TRUE) are stored as a value for the selected field. Mind that the configured local entity for <i>contacts</i> must define a field/s that contains the available lists in Splio.",
        ['%entity' => $this->currentEntity]),
      '#attributes' => [
        'id' => 'list_fields',
      ],
      '#header' => [
        $splio_list = $this->t('Contacts list'),
        $drupal_list_field = $this->t('Local list field'),
      ],
    ];

    $this->remoteContactsLists = $this->splioConnector->getContactLists();
    $this->remoteContactsLists = array_pop($this->remoteContactsLists);

    $listsFields = $this->entityTypeManager->getStorage('splio_field')
      ->loadByProperties(
        ['splio_entity' => $this->contactsLists->getSplioType()]
      );

    $lists = empty($this->remoteContactsLists) ? $listsFields : $this->remoteContactsLists;

    foreach ($lists as $listKey => $listDef) {
      $listKey = empty($this->remoteContactsLists) ? $listKey : $listDef['name'];

      $form['contacts_lists']['lists_table'][$listKey]['splio_list'] = [
        '#type' => 'label',
        '#title' => $listKey,
        '#description' => '',
      ];

      $form['contacts_lists']['lists_table'][$listKey]['drupal_list'] = [
        '#type' => 'select',
        '#options' => $this->fieldOptions,
        '#empty_value' => '',
        '#default_value' => empty($listsFields[$listKey]) ?: $listsFields[$listKey]->getDrupalField(),
      ];
    }

    return $form;
  }

  /**
   * Manages the contacts_lists entity type.
   *
   * Iterates over the existing splio contact lists and saves the configuration
   * set by the user in the contacts lists form.
   *
   * @param array $form
   *   The current form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form-state.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  private function saveContactsLists(array $form, FormStateInterface $form_state) {
    $listsFields = $this->entityTypeManager->getStorage('splio_field')
      ->loadByProperties(
        ['splio_entity' => $this->contactsLists->getSplioType()]
      );

    $lists = empty($this->remoteContactsLists) ? $listsFields : $this->remoteContactsLists;

    foreach ($lists as $listKey => $listDef) {

      $listKey = empty($this->remoteContactsLists) ? $listKey : $listDef['name'];

      if (empty($listsFields)) {
        $listsFields[$listKey] = $this->entityTypeManager
          ->getStorage('splio_field')->create();
        $listsFields[$listKey]->setIsNew(TRUE);
      }

      $listsFields[$listKey]->setId($listKey);
      $listsFields[$listKey]->setSplioEntity('contacts_lists');
      $listsFields[$listKey]->setSplioField($listKey);
      $listsFields[$listKey]
        ->setDrupalField($form_state
          ->getUserInput()['lists_table'][$listKey]['drupal_list']);
      $listsFields[$listKey]->setTypeField('string');
      $listsFields[$listKey]->setIsKeyField(FALSE);

      if ($listsFields[$listKey]->isNew()) {
        if ($this->exist($listsFields[$listKey]->getId())) {
          $this->messenger()
            ->addMessage($this->t(
              "The %splioField list could not be saved. The list ID must be unique. Another list with the same ID already exists.",
              [
                '%splioField' => $listKey,
              ]
            ), MessengerInterface::TYPE_WARNING);
        }
        else {
          $status = $listsFields[$listKey]->save();
        }
      }
      else {
        $status = $listsFields[$listKey]->save();
      }

      if ($status) {
        $this->messenger()
          ->addMessage($this->t('Lists saved successfully.'));
      }
      else {
        $this->messenger()
          ->addMessage($this->t('Something went wrong, the lists could not not saved. Try again.'), MessengerInterface::TYPE_ERROR);
      }
    }
  }

  /**
   * Checks whether the requested entity is configured correctly.
   *
   * In case the requested entity is configured correctly a SplioEntity instance
   * will be created. Otherwise, the user will be redirected to
   * EntitiesConfigForm in order to setup the entity configuration.
   */
  private function checkEntityConfiguration() {
    $currentPath = explode("/", $this->currentPathStack->getPath());
    $currentEntity = end($currentPath);
    $response = new RedirectResponse('/admin/config/splio/entity');

    if (empty($this->config->get('splio.entity.config')
      ->get('splio_entities')[$currentEntity]['local_entity'])) {
      $this->messenger()
        ->addMessage(
          $this->t('The %entity entity has not been configured yet. A mapping between your local entity and the Splio entity must be defined first.', [
            '%entity' => $currentEntity,
          ]), MessengerInterface::TYPE_WARNING);
      $response->send();
    }
    else {
      $this->splioEntity = new SplioEntity($this->entityTypeManager, $this->config, $currentEntity);
      ($currentEntity != 'contacts') ?: $this->contactsLists = new SplioEntity($this->entityTypeManager, $this->config, 'contacts_lists');
    }
  }

}
