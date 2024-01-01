# routy
A simple but robust PHP router for fast application and REST API development, with dynamic routing, nested routes, and middleware support.

# Getting Started
## Composer
```
composer require ginger-tek/routy
```

```php
use GingerTek\Routy\Routy;

$app = new Routy();
```
## Vanilla
You can also just download the latest release and require `Routy.php` directly in your files. 
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

## Common Method Wrappers
Use the common method wrappers for routing GET, POST, PUT, PATCH, or DELETE method requests. There is also a catch-all wrapper for matching on all HTTP methods, including HEAD and OPTIONS.
```php
$app->get('/products', ...); // HTTP GET
$app->post('/products/:id', ...); // HTTP POST
$app->put('/products', ...); // HTTP PUT
$app->patch('/products/:id', ...); // HTTP PATCH
$app->delete('/products/:id', ...); // HTTP PATCH
$app->any('/products/:id', ...); // HTTP GET, POST, PUT, PATCH, DELETE, HEAD, and OPTIONS
```

Use `*` for the path argument to match on any route.
```php
$app->get('*', ...); // HTTP GET for all routes
$app->any('*', ...); // Any common HTTP method for all routes
```

## Custom Routing
You can also use the `route()` method directly, which is what the common wrappers use underneath, to craft more specific route conditions on which to match.
```php
$app->route('GET|POST', '/form', ...); // HTTP GET and POST
$app->route('POST|PUT', '*', ...); // HTTP POST, and PUT for all routes
```

## Dynamic Routes
To define dynamic route parameters, use the `:param` syntax and access them via the `params` property on the `$app` context.
```php
$app->get('/products/:id', function($app) {
  $id = $app->params->id;
  // ...
});
```

## Middleware
All arguments set after the URI string argument are considered middleware functions, including the route handler, so you can define as many as needed. Use the native `$_REQUEST` and `$_SESSION` globals to share data between middleware/handlers.
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

## Grouped/Nested Routes
You can define grouped/nested routes using the `group()` method.
```php
$app = new Routy();

$app->group('/products', function ($app) {
  $app->get('/', fn ($app) => $app->sendJson([]));
});
```

You can also add middleware to your nested routes
```php
$app->group('/products', 'authenticate', function ($app) {
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

$app->group('/products', function ($app) {
  $app->get('/', fn ($app) => $app->sendJson([]));

  // GET /products/asdf will end up here
  $app->notFound(function ($app) { ... });
});

// GET /asdf will end up here
$app->notFound(function ($app) { ... });
```

**NOTE: Fallbacks will be reached in the order they are added, so be aware of your nesting order**

## Static Files
You can serve static asset files using the `static()` method. This is useful for serving a SPA app alongside a REST API, which both be served from the same Routy app using this method.
```php
// will serve files from the 'public' dir on the root URI
$app->static('public');

// will serve files from the 'public' dir on the /app URI
$app->static('public', '/app');
```

**NOTE: This must only be defined AFTER all the other routes in order to work as intended**

# Docs
Intellisense should be sufficient, but here is a rudimentary explanation of all the properties and methods.

## `Routy (class)`
### Properties
- `method` - Request HTTP method
- `uri` - Request URI path
- `params` - URI parameters

### Methods
- `get(string $uri, callable ...$handlers)` - Add an HTTP GET route
- `post(string $uri, callable ...$handlers)` - Add an HTTP POST route
- `put(string $uri, callable ...$handlers)` - Add an HTTP PUT route
- `patch(string $uri, callable ...$handlers)` - Add an HTTP PATCH route
- `delete(string $uri, callable ...$handlers)` - Add an HTTP DELETE route
- `any(string $uri, callable ...$handlers)` - Add an HTTP GET|POST|PUT|PATCH|DELETE|HEAD|OPTIONS route
- `route(string $method, string $uri, callable ...$handlers)` - Add a route that matches a method string, delimited by `|`
- `group(string $base, callable ...$handlers)` - Add a grouped/nested collection of routes
- `notFound(string $uri, callable ...$handlers)` - Add a fallback route to provide custom 404 handling
- `static(string $dir, string $uri)` - Server static files from a directory path (useful for serving JS/CSS/img/font assets). Optionally specify what URI under which to serve the files
- `getHeaders()` - Returns request HTTP headers as associative array
- `getBody()` - Returns request body
- `setStatus(int $code)` - Sets HTTP response code and returns app context instance for method chaining
- `sendStatus(int $code)` - Sends HTTP response code
- `sendRedirect(string $uri, bool $permanent)` - Sends a HTTP 302 redirect to the specified route. When second arg is true, sends HTTP 301 instead
- `sendData(string $data)` - Sends string data as text. If path to file, sends file contents
- `sendJson(mixed $data)` - Sends data as JSON string
