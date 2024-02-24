# routy
A simple but robust PHP router for fast application and REST API development, with dynamic routing, nested routes, and middleware support.

# Getting Started
## Composer
```
composer require ginger-tek/routy
```

```php
require 'vendor/autoload.php';

use GingerTek\Routy\Routy;

$app = new Routy();
```
## Vanilla
You can also just download the latest release and require `Routy.php` directly in your files. 
```php
require 'path/to/Routy.php';

use GingerTek\Routy\Routy;

$app = new Routy();
```

## Simple Example
Handlers for each route can be any kind of callable, such as regular functions, arrow functions, closure variables, or static class methods as string references.
```php
$app = new Routy();

// Standard Function
$app->get('/things', function (Routy $app) {
  $app->sendJson([ ... ]);
});

// Arrow Function
$app->get('/', fn (Routy $app) => $app->sendJson(['msg' => 'Hello, world!']));

// Closure
$handler = function (Routy $app) {
  $app->sendJson([ ... ]);
};

$app->get('/closure', $handler);

// Static Class Method
class ProductsController {
  static function getAll($app) {
    $app->sendJson([ ... ]);
  }
}

$app->get('/products', '\ProductsController::getAll');
```

# Configurations
You can pass an associative array of optional configurations to the constructor.

- `base` to set a global base URI when running from a sub-directory
- `layout` to set a default layout template file to use in the `render()` reponse method
```php
$app = new Routy([
  'base' => '/api',
  'layout' => 'path/to/layout.php'
])
```

# Features

## Method Wrappers
Use the method wrappers for routing GET, POST, PUT, PATCH, or DELETE method requests. There is also a catch-all wrapper for matching on all standard HTTP methods, including HEAD and OPTIONS.
```php
$app->get('/products', ...); // HTTP GET
$app->post('/products/:id', ...); // HTTP POST
$app->put('/products', ...); // HTTP PUT
$app->patch('/products/:id', ...); // HTTP PATCH
$app->delete('/products/:id', ...); // HTTP DELETE
$app->any('/products/:id', ...); // HTTP GET, POST, PUT, PATCH, DELETE, HEAD, and OPTIONS
```

Use `*` for the route argument to match on any route.
```php
$app->get('*', ...); // HTTP GET for all routes
$app->any('*', ...); // Any standard HTTP method for all routes
```

## Custom Routing
You can also use the `route()` method directly, which is what the common wrappers use underneath, to craft more specific route conditions on which to match.
```php
$app->route('GET|POST', '/form', ...); // HTTP GET and POST for the /form route
$app->route('GET|POST|PUT', '/products', ...); // HTTP GET, POST and PUT for the /products route
```

## Dynamic Routes
To define dynamic route parameters, use the `:param` syntax and access them via the `params` object on the `$app` context.
```php
$app->get('/products/:id', function(Routy $app) {
  $id = $app->params->id;
  // ...
});
```

## Middleware
All arguments set after the URI string argument are considered middleware functions, including the route handler, so you can define as many as needed.

Use the native `$_REQUEST` and `$_SESSION` globals to share data between middleware/handlers.
```php
function authenticate(Routy $app) {
  if(!($token = @$app->getHeaders()['authorization']))
    $app->end(401);
  $_REQUEST['user'] = parseToken($token);
}

$app->get('/products', 'authenticate', function (Routy $app) {
  $userId = $_REQUEST['user']->id;
  $items = getProductsByUser($userId);
  $app->sendJson($items);
});
```

## Route Groups
You can define route groups using the `group()` method.
```php
$app = new Routy();

$app->group('/products', function (Routy $app) {
  $app->post('/', ...);
  $app->get('/', ...);
  $app->get('/:id', ...);
  $app->patch('/:id', ...);
});
```

You can also add middleware to your nested routes
```php
$app->group('/products', 'authenticate', function (Routy $app) {
  $app->get('/', ...);
});
```

## Fallback Routes
Fallbacks are used for returning custom 404 responses, or to perform other logic before returning.

To set a fallback route, use the `notFound()` method to set a handler function that will have the HTTP 404 response header already set.

Fallback routes are scoped to wherever they are defined, and will only be reached if they match the incoming URI's parent path.
```php
$app = new Routy();                        

$app->group('/products', function (Routy $app) {
  $app->get('/', fn (Routy $app) => $app->sendJson([]));

  // GET /products/asdf will end up here
  $app->notFound(function (Routy $app) { ... });
});

// GET /asdf will end up here
$app->notFound(function (Routy $app) { ... });
```

## Request Helper Methods
There are few helper methods for handling incoming request payloads.

Use `getBody()` to retrieve the incoming payload data. JSON data will automatically be decoded, and form URL encoded data will be accessible as a standard object.
```php
$app->get('/products', function (Routy $app) {
  $body = $app->getBody();

  // JSON
  // { "someProperty": "asdf" }
  $body->someProperty;

  // Form Data
  // <input name="username">
  $body->username;
});
```

Use `getHeaders()` to retrieve the incoming HTTP headers in an associative array, each header key is auto-lowercased for standardization.
```php
$app->get('/products', function (Routy $app) {
  $headers = $app->getHeaders();
  // Authorization: Bearer eyhdgs9d8fg9s7d87f...
  $headers['authorization'];
});
```

## Response Helper Methods
There are plenty of helper methods for handling responses.

Use `sendJson()` to return data as a JSON string
```php
$app->sendJson(['prop' => 'value']); // { "prop": "value" }
$app->sendJson([1,2,3,4,5]); // [1,2,3,4,5]
```

Use `sendData()` to return string data or a file's raw contents
```php
$app->sendData('<h1>Raw HTML</h1>');
$app->sendData('path/to/file.html');
```
Use `redirect()` to send a temporary or permanent redirect to a new URL
```php
$app->redirect('/go/here');
$app->redirect('/new/permanent/location', true);
```

Use `render()` to render a PHP template file and view file, using standard PHP includes and variable scope extraction
```php
// Using options arguments
$app->render(['layout' => 'path/to/layout.php', 'view' => 'path/to/view.php']);

// Using default layout set via constructor config
$app = new Render(['layout' => 'path/to/layout.php']);
$app->get('/', function (Routy $app) {
  $app->render(['view' => 'views/home.php'])
});
$app->get('/about', function (Routy $app) {
  $app->render(['view' => 'views/about.php'])
});
```

Use `status()` to set the HTTP status code. This can be used for method chaining to other response methods
```php
$app->post('/products', function (Routy $app) {
  $app->status(400)->sendJson(['error' => 'Bad payload']);
  // or
  $app->status(201)->sendData('Successfully created!');
});
```

Use `end()` to return immediately with a specified HTTP status code
```php
$app->end(401); // Unauthorized
```
