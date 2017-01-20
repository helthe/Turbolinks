# Helthe Turbolinks [![Build Status](https://secure.travis-ci.org/helthe/Turbolinks.png?branch=master)](http://travis-ci.org/helthe/Turbolinks) [![Scrutinizer Quality Score](https://scrutinizer-ci.com/g/helthe/Turbolinks/badges/quality-score.png?s=2c3e7fd5d4df03c96c978a3c62813f6b1a6c62b1)](https://scrutinizer-ci.com/g/helthe/Turbolinks/)

Helthe Turbolinks is a direct port of the rails [turbolinks](https://github.com/turbolinks/turbolinks) gem for
projects using the Symfony [HttpFoundation Component](http://symfony.com/doc/current/components/http_foundation/introduction.html).

## Versions

Current versions of the following gems are used:

 * turbolinks: v5.0.0

## Performance

Turbolinks makes following links in your web application faster. Instead of letting
the browser recompile the JavaScript and CSS between each page change, it keeps
the current page instance alive and replaces only the body and the title in the head.

Performance improvements will vary depending on the amount of CSS and Javascript
you are using. You can get up to a 2X increase when using a lot of Javascript and
CSS. You can find the rails benchmarks [here](https://github.com/steveklabnik/turbolinks_test).

## Installation

### Using Composer

#### Manually

Add the following in your `composer.json`:

```json
{
    "require": {
        // ...
        "helthe/turbolinks": "~2.0"
    }
}
```

#### Using the command line

```bash
$ composer require 'helthe/turbolinks=~2.0'
```

## Usage

Using turbolinks requires both the usage of the javascript library and modifying
the PHP response so that it can be properly processed by turbolinks.

### PHP

There are multiple ways to decorate the PHP response for turbolinks.

#### Manually

You can manually decorate the response with the `Turbolinks`object.

```php
<?php
use Helthe\Component\Turbolinks\Turbolinks;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

// ...

$turbolinks = new Turbolinks();
$turbolinks->decorateResponse($request, $response);
```

#### Event Listeners

You can add an event listener to the dispatcher that is passed to the HttpKernel.

```php
<?php
use Helthe\Component\Turbolinks\EventListener\TurbolinksListener;
use Helthe\Component\Turbolinks\Turbolinks;

// ...

// Symfony\Component\EventDispatcher\EventDispatcherInterface
$dispatcher->addSubscriber(new TurbolinksListener(new Turbolinks()));
```

#### Stack Middleware

You can decorate the response using the supplied [Stack](http://stackphp.com/) middleware.

```php
<?php
use Helthe\Component\Turbolinks\StackTurbolinks;
use Helthe\Component\Turbolinks\Turbolinks;

// ...

$app = new StackTurbolinks($app, new Turbolinks());
```

### Javascripts

Both the original coffeescript version and compiled version of each script are available for use.

#### Using turbolinks.js

To enable turbolinks, all you need to do is add the compiled turbolinks javascript to your layout in the `<head>`section.

## Integrating turbolinks with another PHP project

The goal of the component is to offer a base package for integration with other PHP projects using the Symfony [HttpFoundation](https://github.com/symfony/HttpFoundation) or [HttpKernel](https://github.com/symfony/HttpKernel) components.

You can do so by either using the supplied [middleware](https://github.com/helthe/Turbolinks/blob/master/StackTurbolinks.php) that follows the [Stack](http://stackphp.com) convention or by adding the supplied [event listenter](https://github.com/helthe/Turbolinks/blob/master/EventListener/TurbolinksListener.php) to the HttpKernel event dispatcher.

Instead of copying over the compiled javascripts to your projects, try to use your project tools to copy them over. You should always be able to fallback on the composer script hooks like `post-install-cmd` and `post-update-cmd`.

### List of integrations

 * [HeltheTurbolinksBundle](https://github.com/helthe/TurbolinksBundle) (Symfony 2)
 * [Frenzy Turbolinks Package](https://github.com/frenzyapp/turbolinks) (Laravel)
 * [Silex Turbolinks](https://github.com/flagshipcompany/silex-turbolinks) (Silex)

## Compatibility

The turbolinks javascript is designed to work with any browser that fully supports
pushState and all the related APIs. This includes Safari 6.0+ (but not Safari 5.1.x!),
IE10, and latest Chromes and Firefoxes.

Do note that existing JavaScript libraries may not all be compatible with
Turbolinks out of the box due to the change in instantiation cycle. You might
very well have to modify them to work with Turbolinks' new set of events. For
help with this, check out the [Turbolinks Compatibility project](http://reed.github.io/turbolinks-compatibility).

## Additional Resources

Please refer to the [turbolinks](https://github.com/rails/turbolinks) project
if you require additional information on the javascript libraries and their usage.

## Bugs

For bugs or feature requests, please [create an issue](https://github.com/helthe/Turbolinks/issues/new).
