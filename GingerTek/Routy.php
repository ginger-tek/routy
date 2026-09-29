<?php

/**
 * @author      GingerTek
 * @copyright   Copyright (c), GingerTek
 * @license     MIT public license
 */

namespace GingerTek;

/**
 * Class Routy
 */
class Routy
{
  /**
   * @var string The URI of the incoming request.
   */
  public readonly string $uri;

  /**
   * @var string The HTTP method of the incoming request.
   */
  public readonly string $method;

  /**
   * @internal Internal route parameters parsed from the URI.
   */
  private array $params;

  /**
   * @internal General purpose array to use for passing around resources and references.
   */
  private array $ctx;

  /**
   * @internal Internal array of URI parts for handling grouped/nested matching.
   */
  private array $path;

  /**
   * @internal Internal string path for default layout template file to use in render() method.
   */
  private array $config;

  /**
   * @internal Internal array of response context.
   */
  private array $res;

  /**
   * @internal Indicates whether the headers have been sent.
   */
  private bool $metadata_sent = false;

  /**
   * Takes an optional argument array for configurations.
   * - base = set a global base URI when running from a sub-directory; defaults to empty string
   * - render = set a render strategy callback; defaults to none
   * 
   * @param array $config
   */
  public function __construct(?array $config = [])
  {
    $this->uri = rtrim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/') ?: '/';
    $this->method = $_SERVER['REQUEST_METHOD'];
    $this->path = isset($config['base']) ? [$config['base']] : [];
    $this->params = [];
    $this->config = [
      'base' => $config['base'] ?? '',
      'render' => $config['render'] ?? null
    ];
    $this->res = [
      'status' => 200,
      'headers' => [],
      'cookies' => [],
      'body' => ''
    ];
  }

  /**
   * Sets headers, cookies and body content as final response.
   * 
   * @internal
   */
  public function __destruct()
  {
    $this->sendMetadata();
    if (!empty($this->res['body']))
      echo $this->res['body'];
  }

  /**
   * Sends HTTP status, headers and cookies for the response.
   *
   * @internal
   */
  private function sendMetadata(): void
  {
    if ($this->metadata_sent)
      return;
    http_response_code($this->res['status'] ?? 404);
    foreach ($this->res['headers'] as $name => $value)
      if ($value !== null)
        header("$name: $value");
    $this->res['headers'] = [];
    foreach ($this->res['cookies'] ?? [] as $key => $cookie)
      if (isset($cookie['value']))
        setcookie($key, $cookie['value'], $cookie['options'] ?? []);
    $this->res['cookies'] = [];
    $this->metadata_sent = true;
  }

  /**
   * Get a configuration value by key.
   *
   * @param string $key
   * @return string|null
   */
  public function getConfig(string $key): ?string
  {
    return $this->config[$key] ?? null;
  }

  /**
   * Set a context key/value set to use throughout the current Routy instance
   * 
   * @param string $key
   * @param mixed $value
   * @return void
   */
  public function setCtx(string $key, mixed $value): void
  {
    $this->ctx[$key] = $value;
  }

  /**
   * Retrieve a context value by key from the current Routy instance. Returns null if not found.
   * 
   * @param string $key
   * @return mixed
   */
  public function getCtx(string $key): mixed
  {
    return $this->ctx[$key] ?? null;
  }

  /**
   * Defines a route on which to match the incoming URI and HTTP method(s) against.
   * If matched, immediately invokes the route handlers, stops execution and returns response.
   *
   * @param string $method
   * @param string $route
   * @param callable $handlers
   */
  public function route(string $method, string $route, callable ...$handlers): void
  {
    if (!str_contains($method, $this->method))
      return;
    $path = rtrim(join('', $this->path) . $route, '/') ?: '/';
    if (
      $path === $this->uri
      || $path === '*'
      || preg_match('#^' . preg_replace('#:(\w+)#', '(?<$1>[\w\@\#\%\&\+\=\_\-]+)', $path) . '$#', $this->uri, $params)
    ) {
      if (isset($params))
        $this->params = array_map(urldecode(...), $params);
      foreach ($handlers as $handler)
        $handler($this);
      exit();
    }
  }

  /**
   * Defines a middleware, which must be a function that accepts the current Routy class instance as its sole argument.
   *
   * @param callable $middleware
   * @return void
   */
  public function use(callable $middleware): void
  {
    $middleware($this);
  }

  /**
   * Defines nested group of routes on which to match the incoming URI and HTTP method against.
   *
   * @param string $base
   * @param callable $handlers
   * @return void
   */
  public function group(string $base, callable ...$handlers): void
  {
    if ($base != '/')
      $this->path[] = '/' . trim($base, '/');
    if (preg_match('#^' . join($this->path) . '(?:\/|$)#', $this->uri))
      foreach ($handlers as $handler)
        $handler($this);
    array_pop($this->path);
  }

  /**
   * Defines an HTTP GET route on which to match the incoming URI against.
   *
   * @param string $route
   * @param callable $handlers
   * @return void
   */
  public function get(string $route, callable ...$handlers): void
  {
    $this->route('GET', $route, ...$handlers);
  }

  /**
   * Defines an HTTP POST route on which to match the incoming URI against.
   *
   * @param string $route
   * @param callable $handlers
   * @return void
   */
  public function post(string $route, callable ...$handlers): void
  {
    $this->route('POST', $route, ...$handlers);
  }

  /**
   * Defines an HTTP PUT route on which to match the incoming URI against.
   *
   * @param string $route
   * @param callable $handlers
   * @return void
   */
  public function put(string $route, callable ...$handlers): void
  {
    $this->route('PUT', $route, ...$handlers);
  }

  /**
   * Defines an HTTP PATCH route on which to match the incoming URI against.
   *
   * @param string $route
   * @param callable $handlers
   * @return void
   */
  public function patch(string $route, callable ...$handlers): void
  {
    $this->route('PATCH', $route, ...$handlers);
  }

  /**
   * Defines an HTTP DELETE route on which to match the incoming URI against.
   *
   * @param string $route
   * @param callable $handlers
   * @return void
   */
  public function delete(string $route, callable ...$handlers): void
  {
    $this->route('DELETE', $route, ...$handlers);
  }

  /**
   * Defines a route for any HTTP method on which to match the incoming URI against.
   *
   * @param string $route
   * @param callable $handlers
   * @return void
   */
  public function any(string $route, callable ...$handlers): void
  {
    $this->route('*', $route, ...$handlers);
  }

  /**
   * Shorthand for sending a custom HTTP 404 response based on current route.
   * Immediately stops execution and returns response.
   * 
   * @param callable $handler
   * @return void
   */
  public function fallback(callable $handler): void
  {
    $this->status(404);
    $handler($this);
    exit();
  }

  /**
   * Returns the value of a specific HTTP header on the incoming request or the value set for the outgoing response. Returns null if not found.
   * Key lookup is case-insensitive.
   * 
   * @return string|null
   */
  public function getHeader(string $key): ?string
  {
    $ukey = strtoupper(str_replace('-', '_', $key));
    return $_SERVER["HTTP_$ukey"] ?? $_SERVER[$ukey] ?? $this->req['headers'][$key] ?? null;
  }

  /**
   * Sets the value of a specific HTTP header for the outgoing response.
   * Returns the current instance of Routy for method chaining.
   * 
   * @param string $key
   * @param string $value
   * @return Routy
   */
  public function setHeader(string $key, string $value): Routy
  {
    if (str_contains($key, ' '))
      throw new \InvalidArgumentException('Header keys cannot contain spaces');
    $this->res['headers'][$key] = $value;
    return $this;
  }

  /**
   * Removes a specific HTTP header from the outgoing response.
   * Returns the current instance of Routy for method chaining.
   * 
   * @param string $key
   * @return Routy
   */
  public function removeHeader(string $key): Routy
  {
    unset($this->res['headers'][$key]);
    header_remove($key);
    return $this;
  }

  /**
   * Returns the value of a request cookie or the value set for the outgoing response. Returns null if not found.
   * @param string $key
   * @return string|null
   */
  public function getCookie(string $key): ?string
  {
    return $_COOKIE[$key] ?? $this->res['cookies'][$key]['value'] ?? null;
  }

  /**
   * Sets a cookie for the outgoing response.
   * Returns the current instance of Routy for method chaining.
   * 
   * @param string $key
   * @param string $value
   * @param array|null $options
   * @return Routy
   */
  public function setCookie(string $key, string $value, ?array $options = []): Routy
  {
    $this->res['cookies'][$key] = ['value' => $value, 'options' => $options];
    return $this;
  }

  /**
   * Removes a cookie from the outgoing response.
   * Returns the current instance of Routy for method chaining.
   *
   * @param string $key
   * @return Routy
   */
  public function removeCookie(string $key): Routy
  {
    unset($this->res['cookies'][$key]);
    setcookie($key, '', time() - 3600);
    return $this;
  }

  /**
   * Returns the value of a specific query parameter on the incoming request. Returns null if not found.
   * Key lookup is case-sensitive.
   * 
   * @return string|array|null
   */
  public function getQuery(string $key): string|array|null
  {
    return $_GET[$key] ?? null;
  }

  /**
   * Returns the value of a specific request parameter on the incoming request. Returns null if not found.
   * Key lookup is case-sensitive.
   * 
   * @return string|null
   */
  public function getParam(string $key): ?string
  {
    return $this->params[$key] ?? null;
  }

  /**
   * Returns the body of the incoming request.
   * The return type is determined by the Content-Type header, otherwise the raw body is returned as is.
   * 
   * @return mixed
   */
  public function getBody(): mixed
  {
    $type = $this->getHeader('content-type');
    if (str_contains($type, 'multipart/form-data') || str_contains($type, 'application/x-www-form-urlencoded'))
      return (object) $_POST;
    $body = file_get_contents('php://input');
    if (str_contains($type, 'application/json'))
      return json_decode($body, null, 512, JSON_THROW_ON_ERROR);
    return $body;
  }

  /**
   * Returns uploaded file(s) by field name as an object array.
   * Returns null if not a multipart/form-data submission, field not found, or if field is empty.
   * 
   * @return array|null
   */
  public function getFiles(string $name): array|null
  {
    $arr = $_FILES[$name] ?? false;
    if (!$arr || !$arr['name'] || !$arr['name'][0])
      return null;
    $keys = array_keys($arr);
    $count = \count($arr['name']);
    $this->config['fileErrMap'] ??= array_flip(array_filter(
      get_defined_constants(),
      fn($_, $v) => str_contains($v, 'UPLOAD_ERR_'),
      ARRAY_FILTER_USE_BOTH
    ));
    for ($i = 0; $i < $count; $i++) {
      $file = array_combine($keys, array_map(fn($k) => $arr[$k][$i], $keys));
      $file['error'] = $file['error'] !== 0 ? $this->config['fileErrMap'][$file['error']] ?? 'UNKNOWN_ERR' : null;
      $files[] = (object) $file;
    }
    return $files;
  }

  /**
   * Sends an HTTP 301 (permanent) or 304 (temporary) redirect response to the specified URL location.
   * Immediately stops execution and returns response.
   * 
   * @param string $uri
   * @param bool   $isPermanent
   * @return void
   */
  public function redirect(string $uri, ?bool $isPermanent = false): void
  {
    $this->status($isPermanent ? 301 : 302)->setHeader('Location', $uri);
    exit();
  }

  /**
   * Sends string data as the response with an optional content type. If no content type is specified, it will be sent as is.
   * Immediately stops execution and returns response.
   * 
   * @param string $data
   * @param string $contentType
   * @return void
   */
  public function sendData(string $data, ?string $contentType = null): void
  {
    if ($contentType)
      $this->setHeader('Content-Type', $contentType);
    $this->res['body'] = $data;
    exit();
  }

  /**
   * Sends any data as a JSON string as the response.
   * Immediately stops execution and returns response.
   * 
   * @param mixed $data
   * @throws \JsonException
   * @return void
   */
  public function sendJson(mixed $data): void
  {
    $this->sendData(json_encode($data, 512, JSON_THROW_ON_ERROR), 'application/json');
  }

  /**
   * Sends a file as the response either all at once or streamed in chunks, which can be toggled using the `$asStream` parameter.
   * However, if the file is too large to fit into memory, it will be always be streamed by default.
   * Immediately stops execution and returns response.
   * 
   * @param string $path
   * @param mixed $contentType
   * @param bool $asStream
   * @return void
   */
  public function sendFile(string $path, ?string $contentType = null, bool $asStream = false): void
  {
    if (!is_file($path))
      $this->end(404);
    ob_get_status(true) && ob_clean();
    $this->setHeader('Content-Type', $contentType ?? finfo_file(finfo_open(FILEINFO_MIME_TYPE), $path))
      ->setHeader('Content-Length', filesize($path));
    $memLimit = (int) ini_get('memory_limit') * 1024 * 1024;
    $realSize = (filesize($path) + memory_get_usage()) * 1.5;
    if ($asStream || $realSize > $memLimit) {
      $size = filesize($path);
      $start = 0;
      $end = $size - 1;
      if ($range = $this->getHeader('Range')) {
        [$start, $end] = explode('-', str_replace('bytes=', '', $range));
        $start = (int) ($start ?: 0);
        $end = (int) ($end ?: $size - 1);
        $start = max(0, min($start, $size - 1));
        $end = max($start, min($end, $size - 1));
        $this->status(206);
      }
      $length = $end - $start + 1;
      $this->setHeader('Accept-Ranges', 'bytes')
        ->setHeader('Content-Length', $length)
        ->setHeader('Content-Range', "bytes $start-$end/$size")
        ->sendMetadata();
      $fp = fopen($path, 'rb');
      fseek($fp, $start);
      $chunkSize = 8192;
      while (!feof($fp) && ftell($fp) <= $end) {
        $readSize = min($chunkSize, $length);
        echo fread($fp, $readSize);
        $length -= $readSize;
        if (connection_status() !== CONNECTION_NORMAL)
          break;
        flush();
      }
      fclose($fp);
    } else
      $this->res['body'] = file_get_contents($path);
    exit();
  }

  /**
   * Renders a view file utilizing the user-configured render strategy callback.
   * Immediately stops execution and returns response.
   * 
   * Options:
   * - context  = Optional; Array of variables to expose to the template context
   * 
   * The render strategy callback must adhere to the following function signature:
   * ```php
   * function (string $view, array $context, Routy $app): string
   * ```
   * 
   * @param string $view
   * @param array $context
   * @throws \BadFunctionCallException
   * @return void
   */
  public function render(string $view, ?array $context = []): void
  {
    if (!$this->config['render'] || !is_callable($this->config['render']))
      throw new \BadFunctionCallException('No render strategy configured or not callable');
    $context['app'] = $this;
    $this->sendData($this->config['render']($view, $context, $this) ?? '');
  }

  /**
   * Sets the HTTP response code on the response.
   * Returns the current instance of Routy for method chaining
   * 
   * @param int $code
   * @throws \InvalidArgumentException
   * @return Routy;
   */
  public function status(int $code): Routy
  {
    if ($code < 100 || $code > 599)
      throw new \InvalidArgumentException('Invalid HTTP status code');
    $this->res['status'] = $code;
    return $this;
  }

  /**
   * Sends an HTTP response code as the response.
   * Immediately stops execution and returns response.
   * 
   * @param int $code
   * @return void
   */
  public function end(?int $code = 200): void
  {
    $this->status($code);
    exit();
  }
}
