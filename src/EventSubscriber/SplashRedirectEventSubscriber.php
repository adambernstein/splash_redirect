<?php

namespace Drupal\splash_redirect\EventSubscriber;

use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\Url;
use Drupal\Core\Routing\TrustedRedirectResponse;

/**
 * Splash redirect Event Subscriber.
 */
class SplashRedirectEventSubscriber implements EventSubscriberInterface {

  /**
   * Triggered when system sends response.
   */
  public function modifyIntercept(GetResponseEvent $event) {
    $config = \Drupal::config('splash_redirect.settings');
    $config_enabled = $config->get('splash_redirect.is_enabled');
    $config_source = $config->get('splash_redirect.source');
    $config_destination = $config->get('splash_redirect.destination');
    $config_cookie = $config->get('splash_redirect.cookie_name');
    $config_duration = $config->get('splash_redirect.duration');
    $destination = Url::fromUri($config_destination);
    $config_append_params = $config->get('splash_redirect.append_params');
    // If splash config is not enabled then we don't need to do any of this.
    if ($config_enabled == 1) {
      // Current request from client.
      $request = \Drupal::request();
      $current_uri = $request->getRequestUri();
      $http_host = $request->getHost();
      // Current response from system.
      $response = $event->getResponse();
      $route = (\Drupal::routeMatch()->getParameter('node')) ? \Drupal::routeMatch()->getParameter('node')->id() : NULL;
      parse_str($request->getQueryString(), $query);

      // If splash-cookie has not been set, and user requesting 'source' page,
      // set cookie and redirect to splash page.
      if (!$request->cookies->get($config_cookie) && $config_source == $route) {
        \Drupal::service('page_cache_kill_switch')->trigger();
        // Set redirect response with cookie and redirect location,
        // optionally append query string.
        if ($config_append_params == 1) {
          $destination->setOption('query', $query);
        }
        $redir = new TrustedRedirectResponse($destination->setAbsolute()->toString(), '302');
        $cookie = new Cookie($config_cookie, 'true', strtotime('now + ' . $config_duration . 'days'), '/', '.' . $http_host, FALSE, TRUE);
        $redir->headers->setCookie($cookie);
        $redir->headers->set('Cache-Control', 'public, max-age=0');
        $redir->addCacheableDependency($destination);
        $event->setResponse($redir);

      }
      elseif ($config_source == $route) {
        // Kill cache on this route or else cookie might not be read with VCL.
        \Drupal::service('page_cache_kill_switch')->trigger();
        // $response->headers->set('Cache-Control', 'public, max-age=0');
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    // Listen for response event from system and intercept.
    $events[KernelEvents::REQUEST][] = ['modifyIntercept'];
    return $events;
  }

}
