<?php

/**
 * @author      GingerTek
 * @copyright   Copyright (c), GingerTek
 * @license     MIT public license
 */

namespace GingerTek\Routy;

/**
 * Class Routy
 */
class Routy
{
  /**
   * @var string The URI of the incoming request.
   */
  public string $uri;

  /**
   * @var string The HTTP method of the incoming request.
   */
  public string $method;

  /**
   * @var object Available route parameters parsed from the URI.
   */
  public ?object $params;

  /**
   * @var array Internal array of URI parts for handling grouped/nested matching.
   */
  private array $path;

  /**
   * @var string Internal string path to default layout template file to use for render() method.
   */
  private ?string $layout;

  /**
   * Takes an optional argument array for configurations.
   * - base = set a global base URI when running from a sub-directory
   * - layout = set a default layout template file to use in render() method
   * 
   * @param array $config
   */
  function __construct(array $config = [])
  {
    $this->uri = rtrim(parse_url($_SERVER['REQUEST_URI'])['path'], '/') ?: '/';
    $this->method = $_SERVER['REQUEST_METHOD'];
    $this->path = isset($config['base']) ? [$config['base']] : [];
    $this->params = null;
    $this->layout = $config['layout'] ?? null;
  }

  /**
   * Defines a route on which to match the incoming URI and HTTP method(s) against.
   *
   * @param string   $method   Allowed methods, | delimited
   * @param string   $route    A route pattern, i.e. /api/things
   * @param callable $handlers The handling function(s) to be executed
   */
  public function route(string $method, string $route, callable ...$handlers): void
  {
    if (!str_contains($method, $this->method))
      return;
    $path = rtrim(join('', $this->path) . $route, '/') ?: '/';
    if ($path == $this->uri || $path == '*' || preg_match('#^' . preg_replace('#:(\w+)#', '(?<$1>[\w\-]+)', $path) . '$#', $this->uri, $params)) {
      foreach ($handlers as $handler) {
        if (isset($params))
          $this->params = (object) $params;
        $handler($this);
      }
    }
  }

  /**
   * Defines nested group of routes on which to match the incoming URI and HTTP method against.
   *
   * @param string   $base     Base of the group route, i.e. /products
   * @param callable $handlers The handling function(s) to be executed
   * @return void
   */
  public function group(string $base, callable ...$handlers): void
  {
    $this->path[] = $base;
    if (str_starts_with($this->uri, $base)) {
      foreach ($handlers as $handler)
        $handler($this);
    }
    array_pop($this->path);
  }

  /**
   * Defines an HTTP GET route on which to match the incoming URI against.
   *
   * @param string   $route    A route pattern, i.e. /api/things
   * @param callable $handlers The handling function(s) to be executed
   * @return void
   */
  public function get(string $route, callable ...$handlers): void
  {
    $this->route('GET', $route, ...$handlers);
  }

  /**
   * Defines an HTTP POST route on which to match the incoming URI against.
   *
   * @param string   $route    A route pattern, i.e. /api/things
   * @param callable $handlers The handling function(s) to be executed
   * @return void
   */
  public function post(string $route, callable ...$handlers): void
  {
    $this->route('POST', $route, ...$handlers);
  }

  /**
   * Defines an HTTP PUT route on which to match the incoming URI against.
   *
   * @param string   $route    A route pattern, i.e. /api/things
   * @param callable $handlers The handling function(s) to be executed
   * @return void
   */
  public function put(string $route, callable ...$handlers): void
  {
    $this->route('PUT', $route, ...$handlers);
  }

  /**
   * Defines an HTTP PATCH route on which to match the incoming URI against.
   *
   * @param string   $route    A route pattern, i.e. /api/things
   * @param callable $handlers The handling function(s) to be executed
   * @return void
   */
  public function patch(string $route, callable ...$handlers): void
  {
    $this->route('PATCH', $route, ...$handlers);
  }

  /**
   * Defines an HTTP DELETE route on which to match the incoming URI against.
   *
   * @param string   $route    A route pattern, i.e. /api/things
   * @param callable $handlers The handling function(s) to be executed
   * @return void
   */
  public function delete(string $route, callable ...$handlers): void
  {
    $this->route('DELETE', $route, ...$handlers);
  }

  /**
   * Defines a route for any standard HTTP method on which to match the incoming URI against.
   *
   * @param string   $route    A route pattern, i.e. /api/things
   * @param callable $handlers The handling function(s) to be executed
   * @return void
   */
  public function any(string $route, callable ...$handlers): void
  {
    $this->route('GET|POST|PUT|PATCH|DELETE|HEAD|OPTIONS', $route, ...$handlers);
  }

  /**
   * Returns an associative array of HTTP headers on the incoming request.
   * All keys are lower-cased to standardize referencing.
   * 
   * @return array
   */
  public function getHeaders(): array
  {
    $headers = getallheaders();
    return array_combine(array_map('strtolower', array_keys($headers)), array_values($headers));
  }

  /**
   * Returns the body of the incoming request.
   * The return type is determined by the Content-Type header, otherwise the raw data is returned.
   * 
   * @return mixed
   */
  public function getBody(): mixed
  {
    $body = file_get_contents('php://input');
    $headers = $this->getHeaders();
    return match ($headers['content-type']) {
      'application/json' => json_decode($body),
      'application/x-www-form-urlencoded' => (object) $_POST,
      default => $body
    };
  }

  /**
   * Sends an HTTP 301 (permanent) or 304 (temporary) redirect response to the specified URL location.
   * Immediately stops execution and returns to client.
   * 
   * @param string $uri         The new location URI
   * @param bool   $isPermanent If set, will perform a 301 (permanent) redirect
   * @return void
   */
  public function redirect(string $uri, bool $isPermanent = false): void
  {
    http_response_code($isPermanent ? 301 : 302);
    header("location: $uri");
    exit;
  }

  /**
   * Sends string data as the response. The content type on the response can be overridden via the optional second argument.
   * If the string data is a path to a file, the contents of the file will be sent and the content type will be the file's detected MIME type, unless specified explicitly by the second argument.
   * Immediately stops execution and returns to client.
   * 
   * @param string $data      The string data to send
   * @param bool   $permanent If set, will perform a 301 (permanent) redirect
   * @return void
   */
  public function sendData(string $data, string $contentType = null): void
  {
    if (file_exists($data)) {
      header('content-type: ' . ($contentType ?? mime_content_type($data)));
      echo file_get_contents($data);
    } else {
      if ($contentType)
        header("content-type: $contentType");
      echo $data;
    }
    exit;
  }

  /**
   * Sends any data as a JSON string as the response.
   * Immediately stops execution and returns to client.
   * 
   * @param int $code The HTTP response code to send
   * @return void
   */
  public function sendJson(mixed $data): void
  {
    $this->sendData(json_encode($data), 'application/json');
  }

  /**
   * Renders layout file using standard PHP templating via includes.
   * All other variables in argument array are extracted to and made available within the rendering scope.
   * - layout = overrides the default layout file
   * - view   = path to view file
   * 
   * @param array $options
   * @return void
   */
  public function render(array $options): void
  {
    $options['layout'] ??= $this->layout;
    if (!@$options['layout'])
      throw new \Exception('Missing layout argument');
    if (!@$options['view'])
      throw new \Exception('Missing view argument');
    extract($options, EXTR_OVERWRITE);
    include $options['layout'];
    exit;
  }

  /**
   * Sets the HTTP response code on the response.
   * Returns the current instance of Routy for method chaining
   * 
   * @param int $code The HTTP response code to set
   * @return Routy;
   */
  public function status(int $code): Routy
  {
    http_response_code($code);
    return $this;
  }

  /**
   * Sends an HTTP response code as the response.
   * Immediately stops execution and returns to client.
   * 
   * @param int $code The HTTP response code to send
   * @return void
   */
  public function end(int $code = 200): void
  {
    $this->status($code);
    exit;
  }

  /**
   * Shorthand for sending a custom HTTP 404 response based on current route.
   * Immediately stops execution and returns to client.
   * 
   * @return void
   */
  public function notFound(callable $handler): void
  {
    $this->status(404);
    $handler($this);
    exit;
  }
}
