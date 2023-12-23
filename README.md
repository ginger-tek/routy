# routy
A simple but robust PHP router for fast application and REST API development, with dynamic routing, nested routes, and middleware support

# Getting Started
To add Routy to your project, just download the latest release and extract the `routy.php` to your project directory

## Simple Example
```php
require 'routy.php';

$app = new Routy();

$app->get('/', fn($req, $res) => $res->json(['msg'=>'Hello!']));
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
By default, if a route is not matched, a standard HTTP 404 status code response is returned. To set a custom 404 response, use the `notFound()` method to set a handler function.
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

**Note that fallbacks will be reached in the order they are added, so be aware of your nesting order**

# Docs

## `Routy (class)`

### Methods
- `get(string $uri, callable ...$handlers)` - Add a GET route
- `post(string $uri, callable ...$handlers)` - Add a POST route
- `put(string $uri, callable ...$handlers)` - Add a PUT route
- `patch(string $uri, callable ...$handlers)` - Add a PATCH route
- `delete(string $uri, callable ...$handlers)` - Add a DELETE route
- `head(string $uri, callable ...$handlers)` - Add a HEAD route
- `with(string $base, callable ...$handlers)` - Add a nested collections of routes
- `static(string $path)` - Server static files from a directory path (useful for serving JS/CSS/img/font assets)
- `notFound(string $uri, callable ...$handlers)` - Add a route that matches any method

## `App Context ($app)`
### Properties
- `method` - Request HTTP method
- `uri` - Request URI path
- `params` - URI parameters

### Methods
- `getHeaders()` - Returns all HTTP headers
- `getBody()` - If content-type is application/json, body will be parsed into stdClass/array
- `setStatus(int $code)` - Sets HTTP response code and returns app context instance for chaining to other methods
- `sendStatus(int $code)` - Sends HTTP response code
- `sendRedirect(string $uri, bool $permanent)` - Sends a HTTP 302 redirect to the specified route. When second arg is true, sends HTTP 301 instead
- `sendData(string $data)` - Sends string data as text. If path to file, sends file contents. If path to PHP file, renders it as an include
- `sendJson(mixed $data)` - Sends data as JSON string
