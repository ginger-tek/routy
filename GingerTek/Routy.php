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
  public ?object $params;

  /**
   * @var array General purpose array to use for passing around resources and references.
   */
  private array $ctx;

  /**
   * @var array Internal array of URI parts for handling grouped/nested matching.
   */
  private array $path;

  /**
   * @var string Internal string path for default layout template file to use in render() method.
   */
  private array $config;

  /**
   * Takes an optional argument array for configurations.
   * - root = set the root app directory when running from a sub-directory
   * - layout = set a default layout template to use in render() method
   * - base = set a global base URI when running from a sub-directory
   * 
   * @param array $config
   */
  public function __construct(?array $config = []) {
    $this->uri = rtrim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/') ?: '/';
    $this->method = $_SERVER['REQUEST_METHOD'];
    $this->path = isset($config['base']) ? [$config['base']] : [];
    $this->params = null;
    $this->config = [
      'root' => $config['root'] ?? '',
      'layout' => $config['layout'] ?? false,
      'base' => $config['base'] ?? ''
    ];
  }

  /**
   * Get a configuration value by key.
   *
   * @param string $key
   * @return string|null
   */
  public function getConfig(string $key): ?string {
    return $this->config[$key] ?? null;
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
   * If matched, immediately invokes the route handlers, stops execution and returns response.
   *
   * @param string $method
   * @param string $route
   * @param callable $handlers
   */
  public function route(string $method, string $route, callable ...$handlers): void {
    if (!str_contains($method, $this->method))
      return;
    $path = rtrim(join('', $this->path) . $route, '/') ?: '/';
    if ($path === $this->uri || $path === '*' || preg_match('#^' . preg_replace('#:(\w+)#', '(?<$1>[\w\-\+\%\;\&]+)', $path) . '$#', $this->uri, $params)) {
      if (isset($params))
        $this->params = (object) array_map(fn($v) => urldecode($v), $params);
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
   * Key lookup is case insensitive.
   * 
   * @return string|null
   */
  public function getHeader(string $key): string|null {
    $key = strtoupper(str_replace('-', '_', $key));
    return $_SERVER["HTTP_$key"] ?? $_SERVER[$key] ?? null;
  }

  /**
   * Returns the URL-decoded value of a specific query parameter on the incoming request.
   * Key lookup is case-sensitive.
   * 
   * @return string|null
   */
  public function getQuery(string $key): string|null {
    return isset($_GET[$key]) ? urldecode($_GET[$key]) : null;
  }

  /**
   * Returns the body of the incoming request.
   * The return type is determined by the Content-Type header, otherwise the raw body is returned as is.
   * 
   * @return mixed
   */
  public function getBody(): mixed {
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
  public function getFiles(string $name): array|null {
    $arr = $_FILES[$name] ?? false;
    if (!$arr || !$arr['name'] || !$arr['name'][0])
      return null;
    $keys = array_keys($arr);
    $count = count($arr['name']);
    $this->config['fileErrMap'] ??= array_flip(array_filter(
      get_defined_constants(),
      fn($k, $v) => str_contains($v, 'UPLOAD_ERR_'),
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
  public function redirect(string $uri, ?bool $isPermanent = false): void {
    http_response_code($isPermanent ? 301 : 302);
    header("location: $uri");
    exit();
  }

  /**
   * Sends string data as the response. The content type on the response can be overridden via the optional second argument.
   * If the string data is a path to a file, the contents of the file will be sent and the content type will be the file's detected MIME type, unless specified explicitly by the second argument.
   * Immediately stops execution and returns response.
   * 
   * @param string $data
   * @param ?string $contentType
   * @return void
   */
  public function sendData(string $data, ?string $contentType = null): void {
    if (is_file($data))
      header('content-type: ' . ($contentType ?? finfo_file(finfo_open(FILEINFO_MIME_TYPE), $data)));
    elseif($contentType)
      header("content-type: $contentType");
    exit(is_file($data) ? file_get_contents($data) : $data);
  }

  /**
   * Sends any data as a JSON string as the response.
   * Immediately stops execution and returns response.
   * 
   * @param int $code
   * @return void
   */
  public function sendJson(mixed $data): void {
    $this->sendData(json_encode($data), 'application/json');
  }

  /**
   * Renders a view file from the views directory utilizing standard PHP templating includes/requires.
   * Immediately stops execution and returns response.
   * 
   * Options:
   * - layout = Optional; Overrides default layout. If set to false, will render without layout
   * - model  = Optional; Array of variables to expose to the template context
   * 
   * @param string $view
   * @param array $options
   * @return void
   */
  public function render(string $view, ?array $options = []): void {
    $view = $this->config['root'] . "views/$view.php";
    $layout = $options['layout'] ?? $this->config['layout'] ?? false;
    $options['app'] = $this;
    ob_start();
    if ($layout) {
      $options['view'] = $view;
      extract($options, EXTR_OVERWRITE);
      include $this->config['root'] . "layouts/$layout.php";
    } else {
      extract($options, EXTR_OVERWRITE);
      include $view;
    }
    exit(ob_get_clean() ?? '');
  }

  /**
   * Sets the HTTP response code on the response.
   * Returns the current instance of Routy for method chaining
   * 
   * @param int $code
   * @return Routy;
   */
  public function status(int $code): Routy {
    http_response_code($code);
    return $this;
  }

  /**
   * Sends an HTTP response code as the response.
   * Immediately stops execution and returns response.
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
   * Immediately stops execution and returns response.
   * 
   * @param callable $handler
   * @return void
   */
  public function fallback(callable $handler): void {
    $this->status(404);
    $handler($this);
    exit();
  }

  /**
   * Serve static files from a specified directory via a proxy route.
   * Will fallback to a generic 404 for file URI and index.html if a directory URI.
   * Use options array to adjust the caching limit or to extend the MIME types mapping.
   * - maxAge (number of minutes to cache static files)
   * - mimeTypes (associative array of file extensions to MIME types)
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
    $this->group($route, function($app) use ($route, $directory, $options) {
      $file = join('/',[$directory, trim(str_replace(trim($route, '/'), '', $app->uri), '/')]);
      $ext = pathinfo($file, PATHINFO_EXTENSION);
      if (!$ext && is_file("$directory/index.html"))
        $app->sendData("$directory/index.html");
      else if (!is_file($file))
        $app->end(404);
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
      $this->sendData($file, $mime);
    });
  }
}
