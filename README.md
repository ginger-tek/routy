<div align=center>
<h1>Routy</h1>
<p>A simple, robust PHP router for fast app and API development</p>
</div>

# Getting Started
## Composer
```
composer require ginger-tek/routy
```

```php
require 'vendor/autoload.php';

use GingerTek\Routy;

$app = new Routy;
```

## Starter Example
Handlers for each route can be any kind of callable, such as regular functions, arrow functions, closure variables, or static class methods.
```php
$app = new Routy;

// Standard Function
$app->get('/things', function (Routy $app) {
  $app->sendData('Hello!');
});

// Arrow Function
$app->get('/', fn (Routy $app) => $app->sendData('Hello!'););

// Closure
$handler = function (Routy $app) {
  $app->sendData('Hello!');
};

$app->get('/closure', $handler);

// Static Class Method
class ProductsController {
  static function getAll($app) {
    $app->sendData('Hello!');
  }
}

$app->get('/products', \ProductsController::getAll(...));
```

# Configurations
You can pass an associative array of optional configurations to the constructor.

- `base` to set a global base URI when running from a sub-directory
- `layout` to set a default layout template file to use in the `render()` reponse method
- `views` to set a default views directory to use in the `render()` reponse method; if not set, defaults to 'views' in the project root
```php
$app = new Routy([
  'base' => '/api',
  'layout' => 'path/to/layout.php',
  'views' => 'path/to/views/dir'
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
### Global Middleware
If you want to define global middleware, you can use the `use()` method.
Any middleware or route handler callable must have one argument to accept the current Routy instance.

(See [Context Sharing](#context-sharing) about sharing data between middleware/handlers)
```php
function authenticate(Routy $app) {
  if(!($token = @$app->getHeaders()['authorization']))
    $app->end(401);
  $app->setCtx('user', parseToken($token));
}

$app->use(authenticate(...));
```

### Route Middleware
All arguments set after the URI string argument are considered middleware functions, including the route handler, so you can define as many as needed.
```php
$app->get('/products', authenticate(...), function (Routy $app) {
  $userId = $app->getCtx('user')->id;
  $items = getProductsByUser($userId);
  $app->sendJson($items);
});
```

## Context Sharing
To share data between handlers/middleware or provide a global resource to the instance, use the `setCtx()` and `getCtx()`. Any data type can be passed in for the value.
```php
$app->setCtx('db', new PDO('sqlite:myData.db'));
...
$app->get('/products', function (Routy $app) {
  $db = $app->getCtx('db');
  $stmt = $db->prepare('select * from products');
  $stmt->execute();
  $result = $stmt->fetch();
  ...
})
```

## Route Groups
You can define route groups using the `group()` method.
```php
$app = new Routy;

$app->group('/products', function (Routy $app) {
  $app->post('/', ...);
  $app->get('/', ...);
  $app->get('/:id', ...);
  $app->patch('/:id', ...);
});
```

You can also add middleware to your nested routes
```php
$app->group('/products', authenticate(...), function (Routy $app) {
  $app->get('/', ...);
});
```

## Fallback Routes
Fallbacks are used for returning custom 404 responses, or to perform other logic before returning.

To set a fallback route, use the `notFound()` method to set a handler function that will have the HTTP 404 response header already set.

Fallback routes are scoped to wherever they are defined, and will only be reached if they match the incoming URI's parent path.
```php
$app = new Routy;                        

$app->group('/products', function (Routy $app) {
  $app->get('/', fn (Routy $app) => $app->sendJson([]));

  // GET /products/asdf will end up here
  $app->notFound(function (Routy $app) { ... });
});

// GET /asdf will end up here
$app->notFound(function (Routy $app) { ... });
```

## Serve Static Files (SPA)
To serve static files from a specified directory via a proxy route, use the `serveStatic()` method ***after*** all other normal route definitions.
You can use this to serve asset files or a whole SPA directory. If the requested URI is a directory, an `index.html` file will be served, if one exists. Otherwise, if any requested file is not found, a generic 404 response with be sent back.

**NOTE: Serving static files is typically best performed by a web server (Apache/nginx/Caddy) via rewrite rules. Consider your performance requirements in production scenarios when using this feature.**

```php
$app = new Routy;

$app->group('/api', ApiController::index(...));
$app->serveStatic('/nm', 'node_modules');
$app->serveStatic('/', 'public');
```

## Request Properties
You can access the incoming HTTP method and URI via the `uri`, `method`, and `params`, and `query` properties on the `$app` instance.
```php
$app->get('/', function (Routy $app) {
  $app->uri;
  $app->method;
  $app->params;
  $app->query;
});
```

## Request Helper Methods
There are few helper methods for handling incoming request payloads.

### `getBody()`
Use to retrieve the incoming payload data. JSON data will automatically be decoded, and form URL encoded data will be accessible as a standard object.
```php
$app->get('/products', function (Routy $app) {
  $body = $app->getBody();

  // JSON
  // { "someProperty": "asdf" }
  $body->someProperty;
  $body->username; // From: <input name="username">
});
```

### `getHeaders()`
Use to retrieve the incoming HTTP headers in an associative array, each header key is auto-lowercased for standardization.
```php
$app->get('/products', function (Routy $app) {
  $headers = $app->getHeaders();
  $headers->authorization; // Bearer eyhdgs9d8fg9s7d87f...
});
```

### `getFiles()`
Use to retrieve uploaded files from multipart/form-data requests. Returns an object array for multi-file uploads. To return the first object for single-file uploads, set the second parameter to `true`.
```html
<form method="POST" action="/upload" enctype="multipart/form-data">
  <input type="file" name="multi[]" multiple required>
  <input type="file" name="single" required>
  <button type="submit">Submit</button>
</form>
```
```php
$app->post('/upload', function (Routy $app) {
  $multipleFiles = $app->getFiles('multi'); // object array
  $singleFile = $app->getFiles('single', true); // object
});
```

## Response Helper Methods
There are plenty of helper methods for handling responses.

### `sendJson()`
Use to return data as a JSON string
```php
$app->sendJson(['prop' => 'value']); // { "prop": "value" }
$app->sendJson([1, 2, ['three' => 4], 5]); // [1, 2, { "three: 4 }, 5]
```

### `sendData()`
Use to return string data or a file's raw contents
```php
$app->sendData('<h1>Raw HTML</h1>');
$app->sendData('path/to/file.html');
```

### `redirect()`
Use to send a temporary or permanent redirect to a new URL
```php
$app->redirect('/go/here'); // HTTP 302
$app->redirect('/new/permanent/location', true); // HTTP 301
```

### `render()`
Use to render a PHP view file, using standard PHP includes and variable scope extraction for MVC modeling

You can set a default layout and/or views directory to use via the constructor config
```php
$app = new Routy(['layout' => 'path/to/layout.php']);
...
$app->render('home'); // views/home.php
$app->render('about'); // views/about.php
```

You can also override the default by settings the `layout` option to another path
```php
$app = new Routy(['layout' => 'path/to/layout1.php']);
...
$app->render('view', ['layout' => 'path/to/layout2.php']);
```

Or you can use no layout by setting the `layout` option to `false`
```php
$app = new Routy(['layout' => 'path/to/layout.php']);
...
$app->render('view', ['layout' => false]);
```

You may also not specify a layout at all, and just render files as is
```php
$app = new Routy();
...
$app->render('view');
```

Finally, set the `model` option to pass in a data model to expose to the template context in your view files. The current app instance is also exposed to the template context automatically
```php
$app->render('path/to/view.php', [
  'model' => [
    'someProperty' => 'some data'
  ]
]);

// view.php
<div><?= $model['someProperty'] ?></div>
<?php if ($app->uri == '/some-route'): ?>
  ...
<?php endif ?>
```

### `status()`
Use to set the HTTP status code. This can be used for method chaining to other response methods
```php
$app->post('/products', function (Routy $app) {
  $app->status(400)->sendJson(['error' => 'Bad payload']);
  // or
  $app->status(201)->sendData('Successfully created!');
});
```

### `end()`
Use to return immediately with an optional HTTP status code
```php
$app->end(); // Success
$app->end(401); // Unauthorized
$app->end(404); // Not Found
```
