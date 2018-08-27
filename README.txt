CONTENTS OF THIS FILE
---------------------

 * Introduction
 * Requirements
 * Installation
 * Configuration
 * Maintainers


INTRODUCTION
------------

The Splash Redirect module allows a site builder to configure a single,
conditional page redirect, for use in a "splash page" type scenario.
For example, you may want to automatically redirect users arriving on the normal
homepage to a different page, perhaps to highlight promotional or limited-time
content instead. You may point the splash page at any page you like, internal or
external, and therefore your splash page is not restricted to any particular
template or node type. *The page you wish to redirect to should be
created separately, and should be already available.* 

 * For a full description of the module visit:
   https://www.drupal.org/project/splash_redirect

 * To submit bug reports and feature suggestions, or to track changes visit:
   https://www.drupal.org/project/issues/splash_redirect


REQUIREMENTS
------------

This module requires no modules outside of Drupal core.


INSTALLATION
------------

 * Install the Splash Redirect module as you would normally install a
   contributed Drupal module. Visit https://www.drupal.org/node/1897420 for
   further information.


CONFIGURATION
-------------

    1. Navigate to Administration > Extend and enable the module.
    2. Navigate to Administration > Configuration > Search and Metadata > Splash
       Page Settings and toggle the splash page redirect on.
    3. Enter the source page and destination.
    4. In the Advanced Settings field group enter the Cookie name and duration.
       Save configuration.

The main difference between this module and ones like Splashify is that Splash
Page Redirect allows the user to set a cookie to "remember" whether or not a
user has seen your splash page. If this cookie is set, the browser will not
redirect the user to the splash page.

Note: it is recommended that you place a link back to the original page on
the splash page for general usability purposes.


MAINTAINERS
-----------

 * Adam Bernstein (AdamBernstein) - https://www.drupal.org/u/adambernstein
