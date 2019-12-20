# laravel-cors

[![Latest Version on Packagist][ico-version]][link-packagist]
[![Software License][ico-license]](LICENSE.txt)
[![Build Status][ico-travis]][link-travis]
[![Coverage Status][ico-scrutinizer]][link-scrutinizer]
[![Quality Score][ico-code-quality]][link-code-quality]
[![Total Downloads][ico-downloads]][link-downloads]

This Laravel package provides support for enabling CORS requests in a Laravel application. If basic CORS support is needed, there are two other popular packages that are widely used ([barryvdh/laravel-cors](https://github.com/barryvdh/laravel-cors) and [spatie/laravel-cors](https://github.com/spatie/laravel-cors)).

This package attempts to solve a couple issues with the other packages. These issues are not show stoppers, so you may want to look at the other packages if these issues are not important to you.

#### 1. Multiple Configurations

While the other packages do support applying the CORS middleware to specific routes, they still only support one global configuration. So, for example, if you wanted one set of routes to be available to only one origin, and another set of routes to be available for a different origin, you can't create that configuration without making both sets of routes available to both origins.

This package allows specifying multiple configurations, which can be applied as needed per route.

#### 2. Error Handling

In the other packages, if an error occurred before the CORS middleware was applied, the CORS headers would not be added to the error response. In this case, instead of getting a response with the error, you would get an invalid CORS request error and not have access to the response.

This package works to ensure the CORS configuration is applied to the request, even in the case of an error.

#### 3. Preflight Issues

- Response Status Codes - Both packages return a 4xx status code for a rejected preflight request. The preflight request should still return a 2xx status code, it just needs to have the CORS response headers set (or not set) properly. This package returns a 204 No Body response for all preflight requests.

- Response Headers - Both packages don't set any headers for any rejected preflight request. This is appropriate for an invalid origin, but if the preflight is rejected due to the method or headers, the appropriate response headers should be added for the client to determine the proper rejection reason. Without any response headers, all rejection reasons will be due to an invalid origin. This package attempts to set the response headers smartly depending on the rejection scenario.

## Versions

This package is meant for Laravel 5.1 through Laravel 6.x. It does not yet support Lumen. This package uses middleware, and therefore does not support Laravel < 5.0. This package requires the `Kernel::hasMiddleware()` method, and therefore does not support Laravel 5.0.

## Install

Via Composer

``` bash
$ composer require shiftonelabs/laravel-cors
```

Once composer has been updated and the package has been installed, the service provider will need to be loaded.

#### Laravel 5.5+, 6.x (5.5, 5.6, 5.7, 5.8, 6.x)

This package uses auto package discovery. The service provider will automatically be registered.

#### Laravel 5.1 - 5.4

Open `config/app.php` and add following line to the providers array:

``` php
ShiftOneLabs\LaravelCors\LaravelCorsServiceProvider::class,
```

## Configuration

This package comes with a default configuration file that can be published in order to modify it.

``` bash
php artisan vendor:publish --provider="ShiftOneLabs\LaravelCors\LaravelCorsServiceProvider" --tag="config"
```

This will publish the config file to `config/cors.php`. Modify this config file to change the default profile and to add new custom profiles.

## Usage

#### Global Middleware

To apply a global CORS policy, add the `\ShiftOneLabs\LaravelCors\Http\Middleware\ApplyCorsPolicy` middleware to the global `$middleware` property in the `app/Http/Kernel.php` file. This global CORS policy will use whatever profile is defined as the default profile in the `cors.php` config file. By default, this is an open policy that will allow all CORS requests.

``` php
protected $middleware = [
    /* ... */,
    \ShiftOneLabs\LaravelCors\Http\Middleware\ApplyCorsPolicy::class,
]
```

#### Route Middleware

To apply a route-specific CORS policy, first add a middleware alias for the policy middleware to the `$routeMiddleware` property in the `app/Http/Kernel.php` file.

``` php
protected $routeMiddleware = [
    /* ... */,
    'cors.policy' => \ShiftOneLabs\LaravelCors\Http\Middleware\ApplyCorsPolicy::class,
]
```

Now you can add this middleware to any route or route group. Additionally, this route middleware can take a parameter to specify a CORS profile configuration defined in the `cors.php` config file.

Assume you add the following profile to the config file:

``` php
    'profiles' => [
        /* ... */,

        // add a new CORS profile named "app1"
        'app1' => [
            'allowedMethods' => ['*'],
            'allowedOrigins' => ['example.com'],
            'allowedOriginsPatterns' => [],
            'allowedHeaders' => ['*'],
            'exposedHeaders' => [],
            'maxAge' => 0,
            'supportsCredentials' => false,
        ],
    ],
```

This new CORS profile can be applied to any route or route group using route middleware:

``` php
Route::get('example-route', 'ExampleController@index')->middleware('cors.policy:app1');
```

This route will now only allow CORS requests from `example.com`. A CORS request from any other origin will be rejected.

#### Multiple Middleware

It is possible for a request to have to pass through multiple defined policies. For example, if you have a global CORS policy, a group middleware policy, and a route specific policy, the request must pass the requirements for all three policies.

If the request fails, the response headers will be built using the first policy that failed, starting with the least specific (global) to the most specific (route).

If the request is successful, the response headers will be built using the last policy that the request went through.

## Contributing

Contributions are welcome. Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security

If you discover any security related issues, please email patrick@shiftonelabs.com instead of using the issue tracker.

## Alternatives

- [barryvdh/laravel-cors](https://github.com/barryvdh/laravel-cors): one of the most popular packages for adding CORS support to Laravel/Lumen applications. If you need simple support, go with this one.
- [spatie/laravel-cors](https://github.com/spatie/laravel-cors): another Laravel/Lumen CORS implementation from a well known package provider in the community. This package adds a little more flexibility than is available with the barryvdh/laravel-cors package.

## Credits

- [Patrick Carlo-Hickman][link-author]
- [All Contributors][link-contributors]

## License

The MIT License (MIT). Please see [License File](LICENSE.txt) for more information.

[ico-version]: https://img.shields.io/packagist/v/shiftonelabs/laravel-cors.svg?style=flat-square
[ico-license]: https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square
[ico-travis]: https://img.shields.io/travis/shiftonelabs/laravel-cors/master.svg?style=flat-square
[ico-scrutinizer]: https://img.shields.io/scrutinizer/coverage/g/shiftonelabs/laravel-cors.svg?style=flat-square
[ico-code-quality]: https://img.shields.io/scrutinizer/g/shiftonelabs/laravel-cors.svg?style=flat-square
[ico-downloads]: https://img.shields.io/packagist/dt/shiftonelabs/laravel-cors.svg?style=flat-square

[link-packagist]: https://packagist.org/packages/shiftonelabs/laravel-cors
[link-travis]: https://travis-ci.org/shiftonelabs/laravel-cors
[link-scrutinizer]: https://scrutinizer-ci.com/g/shiftonelabs/laravel-cors/code-structure
[link-code-quality]: https://scrutinizer-ci.com/g/shiftonelabs/laravel-cors
[link-downloads]: https://packagist.org/packages/shiftonelabs/laravel-cors
[link-author]: https://github.com/patrickcarlohickman
[link-contributors]: ../../contributors
