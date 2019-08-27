<?php

namespace Drupal\splio;

use Drupal\Core\Config\Entity\ConfigEntityInterface;

/**
 * Interface SplioFieldInterface.
 *
 * @package Drupal\splio
 */
interface SplioFieldInterface extends ConfigEntityInterface {

  const FIELD_TYPES = [
    'string' => 'string',
    'integer' => 'integer',
    'double' => 'double',
    'date' => 'date',
  ];

  /**
   * Sets the field id and returns the instance to the user.
   *
   * @param string $id
   *   Entity id.
   *
   * @return mixed
   *   Returns the instance of the entity.
   */
  public function setId($id);

  /**
   * Sets the field splio entity and returns the instance to the user.
   *
   * @param string $splio_entity
   *   Entity splio entity.
   *
   * @return \Drupal\splio\Entity\SplioField
   *   Returns the instance of the entity.
   */
  public function setSplioEntity($splio_entity);

  /**
   * Sets the field splio field and returns the instance to the user.
   *
   * @param string $splio_field
   *   Entity splio field.
   *
   * @return \Drupal\splio\Entity\SplioField
   *   Returns the instance of the entity.
   */
  public function setSplioField($splio_field);

  /**
   * Sets the field drupal field and returns the instance to the user.
   *
   * @param string $drupal_field
   *   Entity drupal field.
   *
   * @return \Drupal\splio\Entity\SplioField
   *   Returns the instance of the entity.
   */
  public function setDrupalField($drupal_field);

  /**
   * Sets the field type field and returns the instance to the user.
   *
   * @param string $type_field
   *   Entity field type.
   *
   * @return \Drupal\splio\Entity\SplioField
   *   Returns the instance of the entity.
   */
  public function setTypeField($type_field);

  /**
   * Sets the field is key field and returns the instance to the user.
   *
   * @param string $is_key_field
   *   Entity is key field.
   *
   * @return \Drupal\splio\Entity\SplioField
   *   Returns the instance of the entity.
   */
  public function setIsKeyField($is_key_field);

  /**
   * Sets the field is default field and returns the instance to the user.
   *
   * @param string $is_default_field
   *   Entity is default field.
   *
   * @return \Drupal\splio\Entity\SplioField
   *   Returns the instance of the entity.
   */
  public function setIsDefaultField($is_default_field);

  /**
   * Returns the field's id.
   *
   * @return string
   *   Field's id.
   */
  public function getId();

  /**
   * Returns the field's splio entity.
   *
   * @return string
   *   Field's splio entity.
   */
  public function getSplioEntity();

  /**
   * Returns the field's splio-field.
   *
   * @return string
   *   Field's splio-field.
   */
  public function getSplioField();

  /**
   * Returns the field's drupal-field.
   *
   * @return string
   *   Field's drupal-field.
   */
  public function getDrupalField();

  /**
   * Returns the field's type.
   *
   * @return string
   *   Field's type.
   */
  public function getTypeField();

  /**
   * Returns whether the field is a key field.
   *
   * @return bool
   *   True in case the current field is a key field, otherwise, false.
   */
  public function isKeyField();

  /**
   * Returns whether the field is a default field.
   *
   * @return bool
   *   True in case the current field is a default field, otherwise, false.
   */
  public function isDefaultField();

  /**
   * Returns all the available types for the splio fields.
   *
   * @return array
   *   Array containing the field types.
   */
  public function getFieldTypes();

}
