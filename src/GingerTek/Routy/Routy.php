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
   * @var object Available URL query parameters from the URL.
   */
  public ?object $query;

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
    $this->query = (object) $_GET;
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
    if ($path === $this->uri || $path === '*' || preg_match('#^' . preg_replace('#:(\w+)#', '(?<$1>[\w\-\+\%]+)', $path) . '$#', $this->uri, $params)) {
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
    if ($base != '/')
      $this->path[] = '/' . trim($base, '/');
    if (str_starts_with($this->uri, join($this->path))) {
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
    if (substr($headers['content-type'], 0, 19) === 'multipart/form-data')
      return (object) $_POST;
    return match ($headers['content-type']) {
      'application/json' => json_decode($body),
      'application/x-www-form-urlencoded' => (object) $_POST,
      default => $body
    };
  }

  /**
   * Returns uploaded file(s) by field name as an object if single-file upload and object array if multi-file upload.
   * 
   * @return mixed
   */
  public function getFiles(string $name): object|array
  {
    $files = [];
    $vector = @$_FILES[$name] ?? [];
    if (!is_array($vector['name']))
      return (object) $vector;
    foreach ($vector as $key1 => $value1) {
      foreach ($value1 as $key2 => $value2) {
        $files[$key2][$key1] = $value2;
      }
    }
    return $files;
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
   * Renders a view using standard PHP templating via includes.
   * Options:
   * - layout = Optional; Overrides default layout. If set to false, will render without layout
   * - model  = Optional; Array of variables to expose to the template context
   * 
   * @param string $view
   * @param array  $options
   * @return void
   */
  public function render(string $view, array $options = []): void
  {
    $options['layout'] ??= $this->layout ?? null;
    if (@$options['layout']) {
      $options['view'] = $view;
      extract($options, EXTR_OVERWRITE);
      include $options['layout'];
    } else {
      extract($options, EXTR_OVERWRITE);
      include $view;
    }
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

  /**
   * Serve static files at the base URI from a specified directory.
   * NOTE: This may not be as performant as serving files from your web server directly.
   * NOTE: Use at your discretion with consideration for the speed of your application.
   * NOTE: MIME types referenced from https://svn.apache.org/repos/asf/httpd/httpd/trunk/docs/conf/mime.types.
   * 
   * @return void
   */
  public function serveStatic(string $path, bool $fallback = true): void
  {
    $path .= $this->uri;
    if (file_exists($path)) {
      if (is_dir($path))
        $path .= 'index.html';
      $mime = match (pathinfo($path, PATHINFO_EXTENSION)) {
        'aw' => 'application/applixware',
        'ecma' => 'application/ecmascript',
        'exi' => 'application/exi',
        'gxf' => 'application/gxf',
        'stk' => 'application/hyperstudio',
        'ipfix' => 'application/ipfix',
        'json' => 'application/json',
        'mrc' => 'application/marc',
        'ma', 'nb', 'mb' => 'application/mathematica',
        'mbox' => 'application/mbox',
        'm21' => 'application/mp21',
        'mp21' => 'application/mp21',
        'mp4s' => 'application/mp4',
        'doc', 'dot' => 'application/msword',
        'mxf' => 'application/mxf',
        'oda' => 'application/oda',
        'ogx' => 'application/ogg',
        'onetoc', 'onetoc2', 'onetmp', 'onepkg' => 'application/onenote',
        'oxps' => 'application/oxps',
        'pdf' => 'application/pdf',
        'p10' => 'application/pkcs10',
        'p8' => 'application/pkcs8',
        'pki' => 'application/pkixcmp',
        'ai', 'eps', 'ps' => 'application/postscript',
        'rtf' => 'application/rtf',
        'sdp' => 'application/sdp',
        'gram' => 'application/srgs',
        'wasm' => 'application/wasm',
        'wgt' => 'application/widget',
        'hlp' => 'application/winhlp',
        'xml', 'xsl' => 'application/xml',
        'yang' => 'application/yang',
        'zip' => 'application/zip',
        'adp' => 'audio/adpcm',
        'au', 'snd' => 'audio/basic',
        'mid', 'midi', 'kar', 'rmi' => 'audio/midi',
        'm4a', 'mp4a' => 'audio/mp4',
        'mpga', 'mp2', 'mp2a', 'mp3', 'm2a', 'm3a' => 'audio/mpeg',
        'oga', 'ogg', 'spx', 'opus' => 'audio/ogg',
        's3m' => 'audio/s3m',
        'sil' => 'audio/silk',
        'weba' => 'audio/webm',
        'xm' => 'audio/xm',
        'ttc' => 'font/collection',
        'otf' => 'font/otf',
        'ttf' => 'font/ttf',
        'woff' => 'font/woff',
        'woff2' => 'font/woff2',
        'bmp' => 'image/bmp',
        'cgm' => 'image/cgm',
        'g3' => 'image/g3fax',
        'gif' => 'image/gif',
        'ief' => 'image/ief',
        'jpeg', 'jpg', 'jpe' => 'image/jpeg',
        'ktx' => 'image/ktx',
        'png' => 'image/png',
        'sgi' => 'image/sgi',
        'tiff', 'tif' => 'image/tiff',
        'webp' => 'image/webp',
        'eml', 'mime' => 'message/rfc822',
        'igs', 'iges' => 'model/iges',
        'msh', 'mesh', 'silo' => 'model/mesh',
        'wrl', 'vrml' => 'model/vrml',
        'ics', 'ifb' => 'text/calendar',
        'css' => 'text/css',
        'csv' => 'text/csv',
        'html' => 'text/html',
        'htm' => 'text/html',
        'js', 'mjs' => 'text/javascript',
        'n3' => 'text/n3',
        'txt', 'text', 'conf', 'def', 'list', 'log', 'in' => 'text/plain',
        'rtx' => 'text/richtext',
        'sgml', 'sgm' => 'text/sgml',
        't', 'tr', 'roff', 'man', 'me', 'ms' => 'text/troff',
        'ttl' => 'text/turtle',
        'vcard' => 'text/vcard',
        '3gp' => 'video/3gpp',
        '3g2' => 'video/3gpp2',
        'h261' => 'video/h261',
        'h263' => 'video/h263',
        'h264' => 'video/h264',
        'jpgv' => 'video/jpeg',
        'jpm', 'jpgm' => 'video/jpm',
        'mj2', 'mjp2', 'ts', 'm2t', 'm2ts', 'mts' => 'video/mp2t',
        'mp4', 'mp4v', 'mpg4', 'mpeg', 'mpg', 'mpe', 'm1v', 'm2v' => 'video/mpeg',
        'ogv' => 'video/ogg',
        'qt', 'mov' => 'video/quicktime',
        'webm' => 'video/webm',
      };
      $this->sendData($path, $mime);
    }
    if ($fallback)
      $this->end(404);
  }
}
