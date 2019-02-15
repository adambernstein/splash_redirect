<?php

namespace Drupal\splash_redirect\EventSubscriber;

use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Url;
use Drupal\Core\PageCache\ResponsePolicy\KillSwitch;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\Core\Routing\CurrentRouteMatch;
use Drupal\Core\Config\ConfigFactoryInterface;

/**
 * Splash redirect Event Subscriber.
 */
class SplashRedirectEventSubscriber implements EventSubscriberInterface {
  /**
   * Drupal\Core\Config\ConfigFactoryInterface definition.
   *
   * @var Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The current route match.
   *
   * @var \Drupal\Core\Routing\CurrentRouteMatch
   */
  protected $currentRouteMatch;

  /**
   * The page cache kill switch.
   *
   * @var Drupal\Core\PageCache\ResponsePolicy\KillSwitch
   */
  protected $killSwitch;

  /**
   * {@inheritdoc}
   */
  public function __construct(ConfigFactoryInterface $config_factory, CurrentRouteMatch $current_route_match, KillSwitch $kill_switch) {
    $this->configFactory = $config_factory;
    $this->currentRouteMatch = $current_route_match;
    $this->killSwitch = $kill_switch;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('current_route_match')
    );
  }

  /**
   * Kernel request event handler.
   *
   * @param \Symfony\Component\HttpKernel\Event\GetResponseEvent $event
   *   Response event.
   */
  public function onKernelRequest(GetResponseEvent $event) {
    $config = $this->configFactory->get('splash_redirect.settings');
    $config_enabled = $config->get('splash_redirect.is_enabled');
    $config_source = $config->get('splash_redirect.source');
    $config_destination = $config->get('splash_redirect.destination') ?: 'internal:/node/1';
    $config_cookie = $config->get('splash_redirect.cookie_name');
    $config_duration = $config->get('splash_redirect.duration');
    $destination = Url::fromUri($config_destination);
    $config_append_params = $config->get('splash_redirect.append_params');
    // If splash config is not enabled then we don't need to do any of this.
    if ($config_enabled == 1) {
      // Current request from client.
      if (!$event->isMasterRequest()) {
        return;
      }
      $request = $event->getRequest();
      $http_host = $request->getHost();
      $route = ($this->currentRouteMatch->getParameter('node')) ?
        '/node/' . $this->currentRouteMatch->getParameter('node')->id() :
        $this->currentRouteMatch->getRouteObject()->getPath();
      parse_str($request->getQueryString(), $query);

      // If splash-cookie has not been set,
      // and the user is requesting the 'source' page,
      // set cookie and redirect to splash page.
      if (!$request->cookies->get($config_cookie) && $config_source == $route) {
        // Set redirect response with cookie and redirect location,
        // optionally append query string.
        if ($config_append_params == 1) {
          $destination->setOption('query', $query);
        }
        // MUST use service to turn of Internal Page Cache,
        // or else anonymous users will not ever be able to reach source page.
        $this->killSwitch->trigger();
        $redir = new TrustedRedirectResponse($destination->setAbsolute()->toString(), '302');
        $cookie = new Cookie($config_cookie, 'true', strtotime('now + ' . $config_duration . 'days'), '/', '.' . $http_host, FALSE, FALSE);
        $redir->headers->setCookie($cookie);
        $redir->headers->set('Cache-Control', 'public, max-age=0');
        $redir->addCacheableDependency($destination);
        $redir->addCacheableDependency($cookie);
        $event->setResponse($redir);
      }
      else {
        $this->killSwitch->trigger();
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[KernelEvents::REQUEST][] = ['onKernelRequest', 31];
    return $events;
  }

}
