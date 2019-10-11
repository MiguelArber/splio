<?php

namespace Drupal\splio\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\splio\SplioFieldInterface;

/**
 * Defines the SplioField entity.
 *
 * @ConfigEntityType(
 *   id = "splio_field",
 *   splio_field = "",
 *   splio_entity = "",
 *   drupal_field = "",
 *   type_field = "",
 *   is_key_field = "",
 *   is_default_field = "",
 *   handlers = {
 *     "form" = {
 *       "edit" = "Drupal\splio\Form\SplioFieldForm",
 *       "delete" = "Drupal\splio\Form\SplioFieldDeleteForm",
 *     }
 *   },
 *   config_prefix = "splio_field",
 *   admin_permission = "administer site configuration",
 *   entity_keys = {
 *     "id" = "id",
 *   },
 *   config_export = {
 *     "id",
 *     "splio_field",
 *     "splio_entity",
 *     "drupal_field",
 *     "type_field",
 *     "is_key_field",
 *     "is_default_field",
 *   }
 * )
 */
class SplioField extends ConfigEntityBase implements SplioFieldInterface {
  /**
   * Unique id.
   *
   * @var string
   */
  protected $id;

  /**
   * The Splio entity the field belongs to.
   *
   * @var string
   */
  protected $splio_entity;

  /**
   * The Splio field the field matches with.
   *
   * @var string
   */
  protected $splio_field;

  /**
   * The Drupal field the field matches with.
   *
   * @var string
   */
  protected $drupal_field;

  /**
   * Field type (string, integer, double, date).
   *
   * @var string
   */
  protected $type_field;

  /**
   * Defines if the field is considered as a key field by Splio's API.
   *
   * @var string
   */
  protected $is_key_field;

  /**
   * Defines if the field is one of the default fields of the entity.
   *
   * @var string
   */
  protected $is_default_field;

  /**
   * Defines if the field has just been created.
   *
   * @var string
   */
  protected $is_new;

  /**
   * Sets the field id and returns the instance to the user.
   *
   * @param string $id
   *   Entity id.
   *
   * @return \Drupal\splio\Entity\SplioField
   *   Returns the instance of the entity.
   */
  public function setId($id) {
    $this->id = $id;
    return $this;
  }

  /**
   * Sets the field splio entity and returns the instance to the user.
   *
   * @param string $splio_entity
   *   Entity id.
   *
   * @return \Drupal\splio\Entity\SplioField
   *   Returns the instance of the entity.
   */
  public function setSplioEntity($splio_entity) {
    $this->splio_entity = $splio_entity;
    return $this;
  }

  /**
   * Sets the field splio field and returns the instance to the user.
   *
   * @param string $splio_field
   *   Entity splio field.
   *
   * @return \Drupal\splio\Entity\SplioField
   *   Returns the instance of the entity.
   */
  public function setSplioField($splio_field) {
    $this->splio_field = $splio_field;
    return $this;
  }

  /**
   * Sets the field drupal field and returns the instance to the user.
   *
   * @param string $drupal_field
   *   Entity drupal field.
   *
   * @return \Drupal\splio\Entity\SplioField
   *   Returns the instance of the entity.
   */
  public function setDrupalField($drupal_field) {
    $this->drupal_field = $drupal_field;
    return $this;
  }

  /**
   * Sets the field type field and returns the instance to the user.
   *
   * @param string $type_field
   *   Entity type field.
   *
   * @return \Drupal\splio\Entity\SplioField
   *   Returns the instance of the entity.
   */
  public function setTypeField($type_field) {
    $this->type_field = $type_field;
    return $this;
  }

  /**
   * Sets the field is key field and returns the instance to the user.
   *
   * @param string $is_key_field
   *   Entity is key field.
   *
   * @return \Drupal\splio\Entity\SplioField
   *   Returns the instance of the entity.
   */
  public function setIsKeyField($is_key_field) {
    $this->is_key_field = $is_key_field;
    return $this;
  }

  /**
   * Sets the field is a new field that hasn't been saved in the DB yet.
   *
   * @param string $is_new
   *   Entity is default field.
   *
   * @return \Drupal\splio\Entity\SplioField
   *   Returns the instance of the entity.
   */
  public function setIsNew($is_new) {
    $this->is_new = $is_new;
    return $this;
  }

  /**
   * Sets the field is default field and returns the instance to the user.
   *
   * @param string $is_default_field
   *   Entity is default field.
   *
   * @return \Drupal\splio\Entity\SplioField
   *   Returns the instance of the entity.
   */
  public function setIsDefaultField($is_default_field) {
    $this->is_default_field = $is_default_field;
    return $this;
  }

  /**
   * Returns the field's id.
   *
   * @return string
   *   Field's id.
   */
  public function getId() {
    return $this->id;
  }

  /**
   * Returns the field's splio entity.
   *
   * @return string
   *   Field's splio entity.
   */
  public function getSplioEntity() {
    return $this->splio_entity;
  }

  /**
   * Returns the field's splio-field.
   *
   * @return string
   *   Field's splio-field.
   */
  public function getSplioField() {
    return $this->splio_field;
  }

  /**
   * Returns the field's drupal-field.
   *
   * @return string
   *   Field's drupal-field.
   */
  public function getDrupalField() {
    return $this->drupal_field;
  }

  /**
   * Returns the field's type.
   *
   * @return string
   *   Field's type.
   */
  public function getTypeField() {
    return $this->type_field;
  }

  /**
   * Returns whether the field is a key field.
   *
   * @return bool
   *   True in case the current field is a key field, otherwise, false.
   */
  public function isKeyField() {
    return $this->is_key_field;
  }

  /**
   * Returns whether the field is a default field.
   *
   * @return bool
   *   True in case the current field is a default field, otherwise, false.
   */
  public function isDefaultField() {
    return $this->is_default_field;
  }

  /**
   * Returns all the available types for the splio fields.
   *
   * @return array
   *   Array containing the field types.
   */
  public function getFieldTypes() {
    return static::FIELD_TYPES;
  }

  /**
   * Returns whether the field has not been saved to the DB yet.
   *
   * @return bool
   *   True in case the current field is a default field, otherwise, false.
   */
  public function isNew() {
    return $this->is_new;
  }

}
