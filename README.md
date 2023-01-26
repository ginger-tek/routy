# routy
Simple Express-like PHP router for fast REST API development

# Getting Started

## Quick Example
```php
require 'routy.php';

$app = new Routy();

$app->get('/', fn($req, $res) => $res->json(['msg'=>'Hello!']));

$app->run();
```

## Dynamic Routes
To define dynamic route parameters, use the `:param` syntax and access them via the `params` property on the `Request` object
```php
$app->get('/products/:id', function($req, $res) {
  $id = $req->params->id;
  // ...
});
```

## Middleware
Similar to Express, all arguments set after the URI string are considered middleware funtions, so you can define as many as needed. Use the `ctx` (context) property to pass data between middleware/handlers
```php
function authenticate($req, $res) {
  if(!@$req->headers->authorization) return $res->status(401);
  $req->ctx['user'] = getUser($req->headers->authorization);
}

$app->post('/products', 'authenticate', function($req, $res) {
  $userId = $req->ctx['user']->id;
  $items = getProductsByUser($id);
  $res->json($items);
});
```

## Nested Routes (now with middleware!)
You can define multiple routers and nest them within each other with the `use` method
```php
$app = new Routy();
$products = new Routy();

$products->get('/:id', fn($req, $res) => $res->json());
$app->use('/products', $products);
```

You can also add middleware to your nested routes
```php
$products = new Routy();
$products->get('/:id', fn($req, $res) => $res->json());

$app->use('/products', 'authenticate', $products);
```

# Docs

## `Routy (class)`
### Constructor
- `base` - Optional; base of the URL of the app if in a sub-directory

### Methods
- `get(string $uri, callable ...$handlers)` - Add a GET route
- `post(string $uri, callable ...$handlers)` - Add a POST route
- `put(string $uri, callable ...$handlers)` - Add a PUT route
- `patch(string $uri, callable ...$handlers)` - Add a PATCH route
- `delete(string $uri, callable ...$handlers)` - Add a DELETE route
- `use(string $uri, mixed ...$handlers)` - Adds a nested collections of routes/middleware
- `run()` - Executes the router and processes the routes

## `Request ($req)`
### Properties
- `method` - Request HTTP method
- `uri` - Request URI path
- `params` - URI parameters
- `query` - Parsed URL parameters
- `ctx` - (context) Empty array to use for passing data between middleware/handlers

### Methods
- `headers()` - Returns all HTTP headers
- `body()` - If content-type is application/json, body will be parsed into object

## `Response ($res)`
### Methods
- `status(int $code)` - Sets HTTP response code and passes Response instance for chaining
- `sendStatus(int $code)` - Sends HTTP response code
- `send(mixed $data, string $contentType)` - Sends data as is. If a file path, will render page as response. Optional second argument sets Content-Type header
- `json(mixed $data)` - Sends data as JSON string