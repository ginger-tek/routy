# routy
Simple Express-like PHP router for really fast REST API development.

Requires PHP 8 or later

# Getting Started

## Quick example
```php
require 'routy.php';

$app = new Routy();

$app->get('/', fn($req, $res) => $res->json(['msg'=>'Hello!']));

$app->run();
```

## Dynamic Routes
To define router parameters, use the `:param` syntax; access them via the `params` property on the `Request` object.

```php
$app->get('/products/:id', function($req, $res) {
  $id = $req->params->id;
  // ...
});
```

## Nested Routes
You can define multiple routers and nest them within each other with the `use` method.

```php
$products = new Routy();
$products->get('/:id', fn($req, $res) => $res->json());

$app->use('/products', $products);
```

## Middleware
Similar to Express, all arguments set after the URI string are considered middleware funtions, so you can define as many as needed.

```php
function identify($req, $res) {
  if(!@$req->headers->authorization) return $res->status(401);
  $req->data['user'] = getUser($req->headers->authorization);
}

$app->post('/products', 'identify', function($req, $res) {
  $userId = $req->data['user']->id;
  $items = getProductsByUser($id);
  $res->json($items);
});
```

# Docs


## `Routy`
### Properties
- `base` - Define a subroute under which all routes will be defined
- `routes` - Array of all routes

### Methods
- `route(method, uri, ...handler/middleware)`
- `get(uri, ...handler/middleware)`
- `post(uri, ...handler/middleware)`
- `put(uri, ...handler/middleware)`
- `patch(uri, ...handler/middleware)`
- `delete(uri, ...handler/middleware)`
- `run()` - Executes the router and processes the routes

## `Request`
### Properties
- `method`
- `uri`
- `params` - URI parameters
- `query` - Parsed URL parameters
- `headers`
- `data` - Empty array to use for passing around data, such as user identity from middleware

### Methods
- `body()` - If content-type is application/json, body will be parsed into object

## `Response`
### Methods
- `send(data, httpCode)`
- `json(JSONString, httpCode)`
- `status(httpCode)`