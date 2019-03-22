<?php

namespace Drupal\splash_redirect\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Messenger\MessengerTrait;
use Drupal\Core\Path\AliasManagerInterface;
use Drupal\Core\Path\PathValidatorInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Routing\RequestContext;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines the splash redirect settings form and fields.
 */
class SplashRedirectSettingsForm extends ConfigFormBase {

  use MessengerTrait;

  /**
   * Drupal\Core\Config\ConfigFactoryInterface definition.
   *
   * @var Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The path alias manager.
   *
   * @var \Drupal\Core\Path\AliasManagerInterface
   */
  protected $aliasManager;

  /**
   * The path validator.
   *
   * @var \Drupal\Core\Path\PathValidatorInterface
   */
  protected $pathValidator;

  /**
   * The request context.
   *
   * @var \Drupal\Core\Routing\RequestContext
   */
  protected $requestContext;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(ConfigFactoryInterface $config_factory, AliasManagerInterface $alias_manager, PathValidatorInterface $path_validator, RequestContext $request_context, EntityTypeManagerInterface $entity_type_manager) {
    $this->configFactory = $config_factory;
    $this->aliasManager = $alias_manager;
    $this->pathValidator = $path_validator;
    $this->requestContext = $request_context;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('path.alias_manager'),
      $container->get('path.validator'),
      $container->get('router.request_context'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'splash_redirect_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['splash_redirect.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('splash_redirect.settings');
    $splash_config_source = $config->get('splash_redirect.source');

    $form['splash_redirect_is_enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('&nbsp;'),
      '#default_value' => $config->get('splash_redirect.is_enabled'),
      '#description' => $this->t('Toggle splash page redirect on/off. Redirection will not occur while this is off.'),
    ];

    $form['splash_redirect_source'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Source Page'),
      '#default_value' => $this->aliasManager->getAliasByPath($splash_config_source),
      '#description' => $this->t('&quot;From&quot; page, leave blank for &lt;front&gt; page'),
      '#field_prefix' => $this->requestContext->getCompleteBaseUrl(),
    ];

    $form['splash_redirect_destination'] = [
      '#type' => 'url',
      '#title' => $this->t('Destination'),
      '#default_value' => $config->get('splash_redirect.destination'),
      '#description' => $this->t('Splash page to redirect to. Must be a full url, e.x.<em>https://www.yourpage.com/redirect</em>.'),
    ];

    // Advanced.
    $form['advanced'] = [
      '#type' => 'details',
      '#title' => $this->t('Advanced settings'),
      '#open' => FALSE,
    ];

    $form['advanced']['splash_redirect_cookie_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Cookie Name'),
      '#default_value' => $config->get('splash_redirect.cookie_name'),
      '#description' => $this->t(
        'Sets the name of the cookie. Defaults to "splash". Use a different name here if you want to invalidate the previous cookie. This will reset the splash page triggering on users\' browsers.<br>
        <strong>*Important note for Pantheon customers*: </strong><br>
        Pantheon\'s Global CDN caches the initial redirect and will continue to redirect users even if the splash cookie is set. 
        To work around this limitation, <a href="https://pantheon.io/docs/caching-advanced-topics/#using-your-own-session-style-cookies" target="_blank" rel="nofollow">please prefix your Splash cookie name with "SESS"</a><br>
        (e.g. "<pre>SESSsplash</pre>") to bypass the CDN headers.'
      ),
    ];

    $form['advanced']['splash_redirect_duration'] = [
      '#type' => 'number',
      '#title' => $this->t('Cookie Duration'),
      '#default_value' => $config->get('splash_redirect.duration'),
      '#description' => $this->t('Number of days before cookie expires. Defaults to 7.'),
      '#size' => '3',
    ];

    $form['advanced']['splash_redirect_append_params'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Append original request query parameters'),
      '#default_value' => $config->get('splash_redirect.append_params'),
      '#description' => $this->t(
        'When checked, will forward the original request parameters from the source URL to the redirect URL. <br>
        E.x. if the "Source Page" request is: <em>"https://example.com/?q=1234",</em> 
        the redirect destination will become: <em>"https://example.com/redirect/?q=1234"</em><br>
        Otherwise, you may override the user\'s query parameters with your own in the "Destination" URL field above.'
      ),
    ];

    $form['#attached']['library'][] = 'splash_redirect/splash_redirect.form';
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $enabled = $form_state->getValue('splash_redirect_is_enabled');
    // Only validate if enabled, otherwise we don't need to perform validation.
    if ($enabled == 1) {
      $source = $form_state->getValue('splash_redirect_source');
      $name = $form_state->getValue('splash_redirect_cookie_name');
      $destination = $form_state->getValue('splash_redirect_destination');
      $duration = $form_state->getValue('splash_redirect_duration');
      $front = $this->configFactory->get('system.site')->get('page.front');

      // Assume an input value of '/' means <front>.
      if (empty($source) || $source == '/' || $source == '<front>') {
        if ($front) {
          $form_state->setValue('splash_redirect_source', $front);
        }
        else {
          $form_state->setErrorByName('splash_redirect_source', $this->t('You must configure a default front page node first. Check <em> System >> Basic site settings >> Default front page</em>.'));
        }
      }

      elseif (substr($source, 0, 1) !== '/') {
        $form_state->setErrorByName('splash_redirect_source', $this->t("The path '%path' must begin with a slash.", ['%path' => $source]));
      }

      if (!$this->pathValidator->isValid($source)) {
        $form_state->setErrorByName('splash_redirect)source', $this->t("Either the path '%path' is invalid or you do not have access to it.", ['%path' => $source]));
      }

      if ($source == '<none>') {
        $form_state->setErrorByName('splash_redirect_source', $this->t('Cannot use <none> as source url.'));
      }

      if (empty($destination)) {
        $form_state->setErrorByName('splash_redirect_destination', $this->t('You must specify a destination.'));
      }

      if (empty($name)) {
        $form_state->setValue('splash_redirect_cookie_name', 'splash');
      }
      else {
        $form_state->setValue('splash_redirect_cookie_name', preg_replace('/\s+/', '', $name));
      }
      if (empty($duration) || $duration < 0) {
        $form_state->setValue('splash_redirect_duration', 7);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('splash_redirect.settings');
    $values = $form_state->getValues();
    // Transform source to alias.
    if (!empty($values['splash_redirect_source'])) {
      $source_path = $this->aliasManager->getPathByAlias($values['splash_redirect_source']);
    }
    else {
      $source_path = '/';
    }
    $config->set('splash_redirect.is_enabled', $values['splash_redirect_is_enabled'])
      ->set('splash_redirect.source', $source_path)
      ->set('splash_redirect.destination', $values['splash_redirect_destination'])
      ->set('splash_redirect.cookie_name', $values['splash_redirect_cookie_name'])
      ->set('splash_redirect.duration', $values['splash_redirect_duration'])
      ->set('splash_redirect.append_params', $values['splash_redirect_append_params'])
      ->save();
    $this->messenger()->addMessage('Saved splash page redirect.');
  }

}
