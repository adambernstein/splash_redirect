<?php

namespace Drupal\splash_redirect\EventSubscriber;

use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Drupal\Core\Routing\TrustedRedirectResponse;

/**
 * Splash redirect Event Subscriber.
 */
class SplashRedirectEventSubscriber implements EventSubscriberInterface {

  /**
   * Triggered when system sends response.
   */
  public function modifyIntercept(FilterResponseEvent $event) {
    $config = \Drupal::config('splash_redirect.settings');
    $config_enabled = $config->get('splash_redirect.is_enabled');
    $config_source = $config->get('splash_redirect.source');
    $config_destination = $config->get('splash_redirect.destination');
    $config_cookie = $config->get('splash_redirect.cookie_name');
    $config_duration = $config->get('splash_redirect.duration');

    // If splash configuration is not enabled then we don't need to do any of this :).
    if ($config_enabled == 1) {
      // Current request from client.
      $request = \Drupal::request();
      $current_uri = $request->getRequestUri();
      $http_host = $request->getHost();
      // Current response from system.
      $response = $event->getResponse();
      $route = (\Drupal::routeMatch()->getParameter('node')) ? \Drupal::routeMatch()->getParameter('node')->id() : NULL;

      // If splash-cookie has not been set, and user requesting 'source' page, set cookie and redirect to splash page.
      if (!$request->cookies->get($config_cookie) && $config_source == $route) {
        // Issue new response with cookie and redirect location.
        $redir = new TrustedRedirectResponse($config_destination, '302');
        $cookie = new Cookie($config_cookie, 'true', strtotime('now + ' . $config_duration . 'days'), '/', '.' . $http_host, FALSE, TRUE);
        $redir->headers->setCookie($cookie);
        $redir->send();
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    // Listen for response event from system and pass it to modifyIntercept.
    $events[KernelEvents::RESPONSE][] = ['modifyIntercept'];
    return $events;
  }

}