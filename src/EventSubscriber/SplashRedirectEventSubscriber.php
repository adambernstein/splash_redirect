<?php

namespace Drupal\splash_redirect\EventSubscriber;

use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\Core\Url;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

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
   * The route match.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * {@inheritdoc}
   */
  public function __construct(ConfigFactoryInterface $config_factory, RouteMatchInterface $route_match) {
    $this->configFactory = $config_factory;
    $this->routeMatch = $route_match;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory')
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
      $request = clone $event->getRequest();
      $current_uri = $request->getRequestUri();
      $http_host = $request->getHost();
      $route = ($this->routeMatch->getParameter('node')) ? $this->routeMatch->getParameter('node')->id() : NULL;
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
        $redir = new RedirectResponse($destination->setAbsolute()->toString(), '302');
        $cookie = new Cookie($config_cookie, 'true', strtotime('now  ' . $config_duration . 'days'), '/', '.' . $http_host, TRUE, FALSE);
        $redir->headers->setCookie($cookie);
        $redir->headers->set('Cache-Control', 'public, max-age=0');
        $event->setResponse($redir);
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
