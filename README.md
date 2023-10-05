# routy
An Express-like PHP router for fast application and REST API development, with dynamic routing, template rendering, nested routers, and more!

# Getting Started
To add Routy to your project, just download the latest release and extract the `routy.php` to your project directory. Then, simply `include` or `require` the file to start building.

## Simple Example
```php
require 'routy.php';

$app = new Routy();

$app->get('/', fn($req, $res) => $res->json(['msg'=>'Hello!']));

$app->run();
```

# Features

## Dynamic Routes
To define dynamic route parameters, use the `:param` syntax and access them via the `params` property on the `Request` object
```php
$app->get('/products/:id', function($req, $res) {
  $id = $req->params->id;
  // ...
});
```

URL query parameters are also available through the `query` property
```php
// URI: /products?search=thing
$app->get('/products', function($req, $res) {
  $search = @$req->query?->search;
  // $search = 'thing'
})
```

## Layout Rendering
Routy comes with basic layout rendering using PHP's built-in templating functionality. Use the `render()` method on the Response object to pass a layout file path that contains at least one include for a page/view file path variable to render. You can also pass any other variables from the callback scope to the template/page scope
```php
$app->get('/about', fn($req, $res) => $res->render('layout.php', ['page' => 'pages/about.php']));
$app->get('/product/:id', function($req, $res) {
  $product = GetProduct($req->params->id); // user-defined function
  $res->render('layout.php', ['page' => 'pages/about.php', 'product' => $product]);
});
```

### layout.php
```php
<html>
  ...
  <?php include $page ?>
  ...
</html>
```

### pages/product.php
```php
  ...
  <h3><?= $product->title ?></h3>
  ...
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

## Nested Routes
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

## Custom Fallback Routes (404)
By default, if a route is not matched, a standard HTTP 404 status code response is returned. To set a custom 404 response, add a `/:notfound` route for all methods at the end of your instance's route definitions
```php
$router = new Routy();
//... other routes
$router->any('/:notfound', fn($req, $res) => $res->json('error' => 'Resource not found'));
```

### Nested Fallback Routes
You can also define separate 404 fallbacks when nesting routers within each other
```php
$app = new Routy();

$sub = new Routy();
$sub->get('/', ...);
$sub->any('/:notfound', ...); // GET /sub-route/asdf will end up here

$app->get('/', ...);
$app->use('/sub-route', $sub);
$app->any('/:notfound', ...); // GET /asdf will end up here

$app->run();
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
- `any(string $uri, callable ...$handlers)` - Add a route that matches any method
- `use(string $base, mixed ...$handlers)` - Add a nested collections of routes/middleware
- `run()` - Executes the router and processes the routes

## `Request ($req)`
### Properties
- `method` - Request HTTP method
- `uri` - Request URI path
- `query` - Parsed URL parameters
- `params` - URI parameters
- `ctx` - (context) Empty array to use for passing data between middleware/handlers

### Methods
- `headers()` - Returns all HTTP headers
- `body()` - If content-type is application/json, body will be parsed into object

## `Response ($res)`
### Methods
- `render(string $layout, ?array $variables = [])` - Render a templated layout with a page/view and/or variables
- `status(int $code)` - Sets HTTP response code and returns Response instance for chaining to other methods
- `sendStatus(int $code)` - Sends HTTP response code
- `redirect(string $uri)` - Sends a HTTP 302 redirect to the specified route
- `send(mixed $data, ?string $contentType = 'text/html')` - Sends data as HTML. Optional second argument sets Content-Type header for other data types
- `json(mixed $data)` - Sends data as JSON string