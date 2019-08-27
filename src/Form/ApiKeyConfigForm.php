<?php

namespace Drupal\splio\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\RedirectCommand;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\splio\Services\SplioConnector;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class ApiKeyConfigForm.
 *
 * @property \Drupal\Core\Config\ConfigFactoryInterface config_factory
 * @property \Drupal\key\KeyRepository keyManager
 * @property \Drupal\splio\Services\SplioConnector splioConnector
 * @package Drupal\splio\Form
 */
class ApiKeyConfigForm extends ConfigFormBase {

  const SETTINGS = 'splio.settings';

  /**
   * {@inheritdoc}
   */
  public function __construct(ConfigFactoryInterface $config_factory, SplioConnector $splioConnector) {
    parent::__construct($config_factory);
    $this->config_factory = $config_factory;
    $this->splioConnector = $splioConnector;
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
  public static function create(ContainerInterface $container) {
    return new static(
    // Load the services required to construct this class.
      $container->get('config.factory'),
      $container->get('splio.splio_connector')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'splio_api_key_config_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config(static::SETTINGS);

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['key_settings'] = [
      '#type' => 'fieldset',
      '#title' => $this
        ->t('API Key settings'),
    ];

    $form['actions']['key_settings']['splio_config'] = [
      '#type' => 'key_select',
      '#title' => $this->t('Select your Splio API Key:'),
      '#key_filters' => ['type_group' => 'authentication'],
      '#default_value' => $config->get('splio_config'),
      '#required' => TRUE,
    ];

    $form['actions']['server_settings'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Server settings'),
    ];

    $form['actions']['server_settings']['splio_server'] = [
      '#type' => 'radios',
      '#title' => $this->t('Select the Splio API server:'),
      '#default_value' => $config->get('splio_server'),
      '#options' => [
        's3s.fr/api/' => $this->t('Europe (https://s3s.fr/api/)'),
        'api.spl4cn.com/api/' => $this->t('Asia (https://api.spl4cn.com/api/)'),
      ],
      '#required' => TRUE,
    ];

    $form['actions']['test_connection'] = [
      '#type' => 'button',
      '#value' => $this->t('Test connection'),
    ];

    $form['actions']['test_connection']['#ajax'] = [
      'callback' => [$this, 'testApiConnection'],
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Retrieve the configuration from file and set new values.
    $this->configFactory->getEditable(static::SETTINGS)
      ->set('splio_config', $form_state->getValue('splio_config'))
      ->set('splio_server', $form_state->getValue('splio_server'))
      ->save();

    parent::submitForm($form, $form_state);
  }

  /**
   * Tests the connection with the API for the selected configuration.
   *
   * @param array $form
   *   The current form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse|mixed|\Psr\Http\Message\ResponseInterface
   *   Reloads the page printing a message with the test connection result.
   */
  public function testApiConnection(array $form, FormStateInterface $form_state) {

    $config = ['key' => $form_state->getValue('splio_config'), 'server' => $form_state->getValue('splio_server')];

    ($this->splioConnector->hasConnection($config)) ?
      $msg = [$this->messenger()->addStatus($this->t('Successfully connected to Splio!')), "status"]
      : $msg =
      [
        $this
          ->messenger()
          ->addError(
            $this
              ->t('Could not connect to Splio. Check your Splio configuration and try again. Visit the <a href="@help">help page</a> for further information.',
              ['@help' => '/admin/help/splio'])
        ),
        "error",
      ];

    $this->messenger()->addMessage('API Key: ' . $config['key'], end($msg));
    $this->messenger()->addMessage('Server: ' . $config['server'], end($msg));

    $response = new AjaxResponse();
    $currentURL = Url::fromRoute('<current>');
    $response->addCommand(new RedirectCommand($currentURL->toString()));
    return $response;
  }

}
