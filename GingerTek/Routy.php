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
   * @var object Available route parameters parsed from the URI.
   */
  public readonly ?object $params;

  /**
   * @var array General purpose array to use for passing around resources and references.
   */
  private array $ctx = [];

  /**
   * @var array Internal array of URI parts for handling grouped/nested matching.
   */
  private array $path = [];

  /**
   * @var array Internal array for configuration settings.
   */
  private array $config;

  /**
   * @var array Map of file upload error codes to user-friendly messages.
   */
  private readonly array $uploadErrMap;

  /**
   * Takes an optional argument array for configurations.
   * - layout = Set a default layout template by name to use in toView() method
   * 
   * @param array $config
   */
  public function __construct(?array $config = []) {
    $this->method = $_SERVER['REQUEST_METHOD'];
    $this->uri = rtrim(parse_url($_SERVER['REQUEST_URI'])['path'], '/') ?: '/';
    $this->config = $config ?? [];
  }

  /**
   * Override configuration settings.
   * 
   * @param string $key
   * @param string $value
   */
  public function setConfig(string $key, string $value): void {
    $this->config[$key] = $value;
  }
  
  /**
   * Set a context key/value set to use throughout the current Routy instance
   * 
   * @param string $key
   * @param mixed $value
   * @return void
   */
  public function setCtx(string $key, mixed $value): void {
    $this->ctx[$key] = $value;
  }

  /**
   * Retrieve a context value by key from the current Routy instance.
   * 
   * @param string $key
   * @return bool|mixed
   */
  public function getCtx(string $key): mixed {
    return $this->ctx[$key] ?? false;
  }

  /**
   * Defines a route on which to match the incoming URI and HTTP method(s) against.
   * If matched, handlers are executed in the order they appear, output is buffered, and execution is stopped immediately, returning output to the client.
   *
   * @param string $method
   * @param string $route
   * @param callable $handlers
   */
  public function route(string $method, string $route, callable ...$handlers): void {
    if (!str_contains($method, $this->method))
      return;
    $path = rtrim(join('', $this->path) . $route, '/') ?: '/';
    if ($path === $this->uri || $path === '*' || preg_match('#^' . preg_replace('#:(\w+)#', '(?<$1>[\w\-\+\%\&\;]+)', $path) . '$#', $this->uri, $params)) {
      if (isset($params))
        $this->params = (object) array_map(fn($value) => urldecode($value), $params);
      ob_start();
      foreach ($handlers as $handler)
        if ($res = $handler($this))
          echo $res;
      exit(ob_get_clean() ?? '');
    }
  }

  /**
   * Defines a middleware, which must be a function that accepts the current Routy class instance as its sole argument.
   *
   * @param callable $middleware
   * @return void
   */
  public function use(callable $middleware): void {
    $middleware($this);
  }

  /**
   * Defines nested group of routes on which to match the incoming URI and HTTP method against.
   *
   * @param string $base
   * @param callable $handlers
   * @return void
   */
  public function group(string $base, callable ...$handlers): void {
    if ($base != '/')
      $this->path[] = '/' . trim($base, '/');
    if (preg_match('#^' . join($this->path) . '(?:\/|$)#', $this->uri)) {
      foreach ($handlers as $handler)
        $handler($this);
    }
    array_pop($this->path);
  }

  /**
   * Defines an HTTP GET route on which to match the incoming URI against.
   *
   * @param string $route
   * @param callable $handlers
   * @return void
   */
  public function get(string $route, callable ...$handlers): void {
    $this->route('GET', $route, ...$handlers);
  }

  /**
   * Defines an HTTP POST route on which to match the incoming URI against.
   *
   * @param string $route
   * @param callable $handlers
   * @return void
   */
  public function post(string $route, callable ...$handlers): void {
    $this->route('POST', $route, ...$handlers);
  }

  /**
   * Defines an HTTP PUT route on which to match the incoming URI against.
   *
   * @param string $route
   * @param callable $handlers
   * @return void
   */
  public function put(string $route, callable ...$handlers): void {
    $this->route('PUT', $route, ...$handlers);
  }

  /**
   * Defines an HTTP PATCH route on which to match the incoming URI against.
   *
   * @param string $route
   * @param callable $handlers
   * @return void
   */
  public function patch(string $route, callable ...$handlers): void {
    $this->route('PATCH', $route, ...$handlers);
  }

  /**
   * Defines an HTTP DELETE route on which to match the incoming URI against.
   *
   * @param string $route
   * @param callable $handlers
   * @return void
   */
  public function delete(string $route, callable ...$handlers): void {
    $this->route('DELETE', $route, ...$handlers);
  }

  /**
   * Defines a route for any standard HTTP method on which to match the incoming URI against.
   *
   * @param string $route
   * @param callable $handlers
   * @return void
   */
  public function any(string $route, callable ...$handlers): void {
    $this->route('GET|POST|PUT|PATCH|DELETE|HEAD|OPTIONS', $route, ...$handlers);
  }

  /**
   * Returns the value of a specific HTTP header on the incoming request.
   * Key lookup is case-insensitive.
   * 
   * @return array
   */
  public function getHeader(string $key): string {
    $key = strtoupper(str_replace('-', '_', $key));
    return $_SERVER[$key] ?? $_SERVER["HTTP_$key"] ?? '';
  }

  /**
   * Returns the body of the incoming request.
   * The return type is determined by the Content-Type header, otherwise the raw data is returned.
   * 
   * @return object|array|string
   */
  public function getBody(): object|array|string {
    $contentType = $this->getHeader('content-type');
    if (isset($_POST) && (str_contains($contentType, 'multipart/form-data') || str_contains($contentType, 'application/x-www-form-urlencoded')))
      return (object) $_POST;
    $body = file_get_contents('php://input');
    if (str_contains($contentType, 'application/json'))
      return json_decode($body);
    return $body;
  }

  /**
   * Returns uploaded file(s) by field name as an object array.
   * Returns null if not a multipart/form-data submission or if field is not found/empty.
   * Second parameter returns the first file in the array for single file uploads.
   * 
   * @return array|null
   */
  public function getFiles(string $field): array|null {
    $arr = $_FILES[$field] ?? false;
    if (!$arr || !isset($arr['name']) || !$arr['name'][0])
      return null;
    $result = [];
    $count = count($arr['name']);
    $this->uploadErrMap ??= array_flip(array_filter(
      get_defined_constants(), 
      fn($k, $v) => str_starts_with($v, 'UPLOAD_ERR_'),
      ARRAY_FILTER_USE_BOTH
    ));
    for ($i = 0; $i < $count; $i++)
      $result[] = (object) [
        'name' => $arr['name'][$i],
        'type' => $arr['type'][$i],
        'tmp_name' => $arr['tmp_name'][$i],
        'error' => $arr['error'][$i] > 0 ? $this->uploadErrMap[$arr['error'][$i]] ?? 'UPLOAD_ERR_UNKNOWN' : false,
        'size' => $arr['size'][$i]
      ];
    return $result;
  }

  /**
   * Sends contents of a file as the response as a buffered output. The content type on the response can be overridden via the optional second argument.
   * Immediately stops execution and returns to client.
   * 
   * @param string $path
   * @param ?string $contentType
   * @return void
   */
  public function sendFile(string $path, ?string $contentType = null): void {
    if (!is_file($path))
      $this->end(404);
    header('Content-Type: ' . ($contentType ?? mime_content_type($path)));
    header('Content-Length: ' . filesize($path));
    ob_clean();
    flush();
    readfile($path);
    exit();
  }

  /**
   * Converts any data into a JSON string.
   * 
   * @param mixed $data
   * @return string
   */
  public function toJson(mixed $data): string {
    header('Content-Type: application/json');
    return json_encode($data);
  }

  /**
   * Renders a view file from the views directory utilizing standard PHP templating and returns the output as a string.
   * Options:
   * - layout = Optional; Overrides default layout. If set to false, will render without layout
   * - model  = Optional; Array of variables to expose to the template context
   * 
   * @param string $view
   * @param ?array $options
   * @return string
   */
  public function toView(string $view, ?array $options = []): string {
    $view = "views/" . basename($view, '.php') . '.php';
    $layout = $this->config['layout'] ?? $options['layout'] ?? false;
    $options['app'] = $this;
    ob_start();
    if ($layout) {
      $options['view'] = $view;
      extract($options, EXTR_OVERWRITE);
      include $layout;
    } else {
      extract($options, EXTR_OVERWRITE);
      include $view;
    }
    return ob_get_clean();
  }

  /**
   * Sets the HTTP response code on the response.
   * Returns the current instance of Routy for method chaining.
   * 
   * @param int $code
   * @return Routy;
   */
  public function status(int $code): Routy {
    http_response_code($code);
    return $this;
  }
  
  /**
   * Sends an HTTP 301 (permanent) or 304 (temporary) redirect response to the specified URL location.
   * Immediately stops execution and returns to client.
   * 
   * @param string $uri
   * @param bool $isPermanent
   * @return void
   */
  public function redirect(string $uri, ?bool $isPermanent = false): void {
    http_response_code($isPermanent ? 301 : 302);
    header("Location: $uri");
    exit();
  }

  /**
   * Sends an HTTP response code as the response.
   * Immediately stops execution and returns to client.
   * 
   * @param int $code
   * @return void
   */
  public function end(?int $code = 200): void {
    $this->status($code);
    exit();
  }

  /**
   * Shorthand for sending a custom HTTP 404 response based on current route.
   * Immediately stops execution and returns to client.
   * 
   * @param callable $handler
   * @return void
   */
  public function fallback(callable $handler): void {
    $this->status(404);
    exit($handler($this));
  }

  /**
   * Serve static files from a specified directory via a proxy route.
   * Will fallback to a generic 404 for file URI and index.html if a directory URI.
   * Use options associative array to extende the MIME types mapping or to adjust the caching limit.
   * - maxAge (number of minutes to cache static files)
   * - mimeTypes (assoc. array of file extensions to MIME types)
   * 
   * NOTE: This may not be as performant as serving files directly from your web server. Use with discretion in consideration of your application performance requirements.
   * NOTE: MIME types referenced from https://svn.apache.org/repos/asf/httpd/httpd/trunk/docs/conf/mime.types.
   * 
   * @param string $route
   * @param string $directory
   * @param array $options
   * @return void
   */
  public function serveStatic(string $route, string $directory, ?array $options = []): void {
    $this->group($route, function (Routy $app) use ($route, $directory, $options) {
      $file = join('/', [$directory, trim(str_replace(trim($route, '/'), '', $app->uri), '/')]);
      $ext = pathinfo($file, PATHINFO_EXTENSION);
      if (!$ext && is_file("$directory/index.html"))
        return $app->sendFile("$directory/index.html");
      else if (!is_file($file))
        return $app->end(404);
      $mime = match ($ext) {
        'json' => 'application/json',
        'doc', 'docx' => 'application/msword',
        'pdf', 'ai' => 'application/pdf',
        'xml', 'xsl', 'xlsx' => 'application/xml',
        'zip' => 'application/zip',
        'm4a', 'mp4a' => 'audio/mp4',
        'mp3' => 'audio/mpeg',
        'oga', 'ogg', 'opus' => 'audio/ogg',
        'weba' => 'audio/webm',
        'otf' => 'font/otf',
        'ttf' => 'font/ttf',
        'woff' => 'font/woff',
        'woff2' => 'font/woff2',
        'bmp' => 'image/bmp',
        'gif' => 'image/gif',
        'jpeg', 'jpg' => 'image/jpeg',
        'png' => 'image/png',
        'tiff', 'tif' => 'image/tiff',
        'webp' => 'image/webp',
        'ics', 'ifb' => 'text/calendar',
        'css' => 'text/css',
        'csv' => 'text/csv',
        'html', 'htm' => 'text/html',
        'js' => 'text/javascript',
        'txt', 'text', 'conf', 'log', 'ini' => 'text/plain',
        'rtf' => 'text/richtext',
        'mp4', 'mp4v', 'mpg4', 'mpeg', 'ts' => 'video/mpeg',
        'webm' => 'video/webm',
        default => $options['mimeTypes'][$ext] ?? finfo_file(finfo_open(FILEINFO_MIME_TYPE), $file)
      };
      header_remove('Expires');
      header_remove('Pragma');
      header('Cache-Control: max-age=' . (($options['maxAge'] ?? 60) * 1000));
      return $this->sendFile($file, $mime);
    });
  }
}
