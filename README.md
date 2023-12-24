# routy
A simple but robust PHP router for fast application and REST API development, with dynamic routing, nested routes, and middleware support

# Getting Started
## Composer
```
composer require ginger-tek/routy
```

```php
use GingerTek\Routy\Routy;

$app = new Routy();
```

You can also download the latest release and require `Routy.php` directly in your project. 
```php
require 'path/to/Routy.php';

$app = new Routy();
```

## Simple Example
Handlers for each route can be any kind of callable, i.e. regular functions, arrow functions, static class methods.
```php
$app = new Routy();

$app->get('/things', function ($app) {
  $app->sendJson([ ... ]);
});

$app->get('/', fn ($app) => $app->sendJson(['msg' => 'Hello, world!']);

class Products {
  static function getAll($app) {
    $app->sendJson([ ... ]);
  }
}

$app->get('/products', '\Products::getAll');
```

# Features

## Dynamic Routes
To define dynamic route parameters, use the `:param` syntax and access them via the `params` property on the `$app` context.
```php
$app->get('/products/:id', function($app) {
  $id = $app->params->id;
  // ...
});
```

## Middleware
All arguments set after the URI string argument are considered middleware functions, including the route handler, so you can define as many as needed. Use the native `$_REQUEST` global to pass data between middleware/handlers.
```php
function authenticate($app) {
  if(!($token = @$app->getHeaders()['authorization']))
    $app->sendStatus(401);
  $_REQUEST['user'] = parseToken($token);
}

$app->get('/products', 'authenticate', function ($app) {
  $userId = $_REQUEST['user']->id;
  $items = getProductsByUser($userId);
  $app->sendJson($items);
});
```

## Nested Routes
You can define nested or grouped routes using the `with()` method.
```php
$app = new Routy();

$app->with('/products', function ($app) {
  $app->get('/', fn ($app) => $app->sendJson([]));
});
```

You can also add middleware to your nested routes
```php
$app->with('/products', 'authenticate', function ($app) {
  $app->get('/', fn ($app) => $app->sendJson([]));
});
```

## Custom Fallback Routes (404)
To set custom 404 responses, use the `notFound()` method to set a handler function.
```php
$app = new Routy();

//... other routes

$app->notFound(function ($app) {
  $app->sendJson(['error' => 'Resource not found']);
});
```

### Nested Fallback Routes
You can also define separate 404 fallbacks for separate nested/grouped routes.
```php
$app = new Routy();                        

$app->with('/products', function ($app) {
  $app->get('/', fn ($app) => $app->sendJson([]));

  // GET /products/asdf will end up here
  $app->notFound(function ($app) { ... });
});

// GET /asdf will end up here
$app->notFound(function ($app) { ... });
```

**NOTE: Fallbacks will be reached in the order they are added, so be aware of your nesting order**

## Static Files
You can serve static asset files using the `static()` method.
```php
// will serve files from the 'public' dir on the root URI
$app->static('public');

// will serve files from the 'public' dir on the /app URI
$app->static('public', '/app');
```

# Docs
Intellisense should be sufficient, but here is a rudimentary explanation of all the properties and methods.

## `Routy (class)`
### Properties
- `method` - Request HTTP method
- `uri` - Request URI path
- `params` - URI parameters

### Methods
- `get(string $uri, callable ...$handlers)` - Add a GET route
- `post(string $uri, callable ...$handlers)` - Add a POST route
- `put(string $uri, callable ...$handlers)` - Add a PUT route
- `patch(string $uri, callable ...$handlers)` - Add a PATCH route
- `delete(string $uri, callable ...$handlers)` - Add a DELETE route
- `options(string $uri, callable ...$handlers)` - Add a OPTIONS route
- `with(string $base, callable ...$handlers)` - Add a nested collections of routes
- `notFound(string $uri, callable ...$handlers)` - Add a route that matches any method
- `static(string $dir, string $uri)` - Server static files from a directory path (useful for serving JS/CSS/img/font assets). Optionally specify what URI under which to serve the files
- `getHeaders()` - Returns request HTTP headers as associative array
- `getBody()` - Returns request body
- `setStatus(int $code)` - Sets HTTP response code and returns app context instance for method chaining
- `sendStatus(int $code)` - Sends HTTP response code
- `sendRedirect(string $uri, bool $permanent)` - Sends a HTTP 302 redirect to the specified route. When second arg is true, sends HTTP 301 instead
- `sendData(string $data)` - Sends string data as text. If path to file, sends file contents
- `sendJson(mixed $data)` - Sends data as JSON string
