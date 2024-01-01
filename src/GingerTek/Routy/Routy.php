<?php

/**
 * @author      GingerTek
 * @copyright   Copyright (c), 2023 GingerTek
 * @license     MIT public license
 */

namespace GingerTek\Routy;

/**
 * Class Routy
 */
class Routy
{
  /**
   * @var string The URI of the incoming request
   */
  public string $uri;

  /**
   * @var string The HTTP method of the incoming request
   */
  public string $method;

  /**
   * @var object Available route parameters parsed from the URI
   */
  public ?object $params;

  /**
   * @var array Internal array of URI parts for handling grouped/nested matching
   */
  private array $path;

  function __construct()
  {
    $this->uri = rtrim(parse_url($_SERVER['REQUEST_URI'])['path'], '/') ?: '/';
    $this->method = $_SERVER['REQUEST_METHOD'];
    $this->path = [];
    $this->params = null;
  }

  /**
   * Defines a route on which to match the incoming URI and HTTP method(s) against
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
   * Defines nested group of routes on which to match the incoming URI and HTTP method against
   *
   * @param string   $base     Base of the group route, i.e. /products
   * @param callable $handlers The handling function(s) to be executed
   */
  public function group(string $base, callable ...$handlers): void
  {
    $this->path[] = $base;
    if (str_contains($this->uri, join('', $this->path))) {
      foreach ($handlers as $handler)
        $handler($this);
    }
    array_pop($this->path);
  }

  /**
   * Defines an HTTP GET route on which to match the incoming URI against
   *
   * @param string   $route    A route pattern, i.e. /api/things
   * @param callable $handlers The handling function(s) to be executed
   */
  public function get(string $route, callable ...$handlers): void
  {
    $this->route('GET', $route, ...$handlers);
  }

  /**
   * Defines an HTTP POST route on which to match the incoming URI against
   *
   * @param string   $route    A route pattern, i.e. /api/things
   * @param callable $handlers The handling function(s) to be executed
   */
  public function post(string $route, callable ...$handlers): void
  {
    $this->route('POST', $route, ...$handlers);
  }

  /**
   * Defines an HTTP PUT route on which to match the incoming URI against
   *
   * @param string   $route    A route pattern, i.e. /api/things
   * @param callable $handlers The handling function(s) to be executed
   */
  public function put(string $route, callable ...$handlers): void
  {
    $this->route('PUT', $route, ...$handlers);
  }

  /**
   * Defines an HTTP PATCH route on which to match the incoming URI against
   *
   * @param string   $route    A route pattern, i.e. /api/things
   * @param callable $handlers The handling function(s) to be executed
   */
  public function patch(string $route, callable ...$handlers): void
  {
    $this->route('PATCH', $route, ...$handlers);
  }

  /**
   * Defines an HTTP DELETE route on which to match the incoming URI against
   *
   * @param string   $route    A route pattern, i.e. /api/things
   * @param callable $handlers The handling function(s) to be executed
   */
  public function delete(string $route, callable ...$handlers): void
  {
    $this->route('DELETE', $route, ...$handlers);
  }

  /**
   * Defines a route for any common HTTP method on which to match the incoming URI against.
   * The matching HTTP methods include: GET|POST|PUT|PATCH|DELETE|HEAD|OPTIONS
   *
   * @param string   $route    A route pattern, i.e. /api/things
   * @param callable $handlers The handling function(s) to be executed
   */
  public function any(string $route, callable ...$handlers): void
  {
    $this->route('GET|POST|PUT|PATCH|DELETE|HEAD|OPTIONS', $route, ...$handlers);
  }

  /**
   * Serves static files from a directory. Useful for SPA's to serve JS/CSS/image/font assets.
   * Must be set after all other routes/nested groups to work properly
   *
   * @param string $dir  Path to directory to serve
   * @param string $path URI path on which to serve
   */
  public function static(string $dir, string $path = '/'): void
  {
    if (!str_contains('GET|HEAD|OPTIONS', $this->method)) {
      header('Allow: GET, HEAD');
      header('Content-Length: 0');
      exit;
    }
    if ($path == '/' || preg_match("#^$path#", $this->uri)) {
      $item = $dir . $this->uri;
      if (file_exists($item) && is_file($item)) {
        header('Content-Type: ' . match (pathinfo($item, PATHINFO_EXTENSION)) {
          'js' => 'application/javascript',
          'css' => 'text/css',
          'woff' => 'font/woff',
          'woff2' => 'font/woff2',
          'json' => 'application/json',
          'xml' => 'application/xml',
          'png' => 'image/png',
          'jpeg', 'jpg' => 'image/jpeg',
          'webp' => 'image/webp',
          'webm' => 'image/webm',
          'gif' => 'image/gif',
          'bmp' => 'image/bmp',
          'ico' => 'image/ico',
          'tiff', 'tif' => 'image/tiff',
          'svg', 'svgz' => 'image/svg+xml',
          default => mime_content_type($item)
        });
        echo file_get_contents($item);
      } else echo file_get_contents("$dir/index.html");
      exit;
    }
  }

  /**
   * Returns an associative array of HTTP headers on the incoming request.
   * All the keys are lower-cased to standardize referencing
   */
  public function getHeaders(): array
  {
    $headers = [];
    foreach (getallheaders() as $k => $v) {
      $headers[strtolower($k)] = $v;
    }
    return $headers;
  }

  /**
   * Returns the body of the incoming request.
   * The return type is determined by the Content-Type header, otherwise the raw data is returned
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
   * Sends an HTTP 302 redirect repsonse.
   * If the second argument is true, an HTTP 301 redirect will be returned instead.
   * Immediately stops execution and returns to client
   * 
   * @param string $uri       The new location URI
   * @param bool   $permanent If set, will perform a 301 (permanent) redirect
   */
  public function sendRedirect(string $uri, bool $permanent = false): void
  {
    http_response_code($permanent ? 301 : 302);
    header("location: $uri");
    exit;
  }

  /**
   * Sends string data as the response. The content type on the response can be overridden via the optional second argument.
   * If the string data is a path to a file, the contents of the file will be sent and the content type will be the file's detected MIME type, unless specified explicitly by the second argument.
   * Immediately stops execution and returns to client
   * 
   * @param string $data      The string data to send
   * @param bool   $permanent If set, will perform a 301 (permanent) redirect
   */
  public function sendData(string $data, string $contentType = null): void
  {
    if (file_exists($data)) {
      header('content-type: ' . ($contentType ?? mime_content_type($data)));
      echo file_get_contents($data);
    } else {
      if ($contentType) header("content-type: $contentType");
      echo $data;
    }
    exit;
  }

  /**
   * Sends any data as a JSON string as the response.
   * Immediately stops execution and returns to client
   * 
   * @param int $code The HTTP response code to send
   */
  public function sendJson(mixed $data): void
  {
    $this->sendData(json_encode($data), 'application/json');
  }

  /**
   * Sets the HTTP response code on the response.
   * Returns the current instance of Routy for method chaining
   * 
   * @param int $code The HTTP response code to send
   */
  public function setStatus(int $code): Routy
  {
    http_response_code($code);
    return $this;
  }

  /**
   * Sends an HTTP response code as the response.
   * Immediately stops execution and returns to client
   * 
   * @param int $code The HTTP response code to send
   */
  public function sendStatus(int $code): void
  {
    $this->setStatus($code);
    exit;
  }

  /**
   * Sends a custom HTTP 404 response based on current route scope.
   * Immediately stops execution and returns to client if the requesting URI matches the current route scope
   * 
   * @param int $code The HTTP response code to send
   */
  public function notFound(callable $handler): void
  {
    $path = rtrim(join('', $this->path), '/') ?: '/';
    if ($path == '/' || preg_match("#^$path#", $this->uri)) {
      http_response_code(404);
      $handler($this);
      exit;
    }
  }
}
