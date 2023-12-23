<?php

namespace GingerTek\Routy;

class Routy
{
  public string $uri;
  public string $method;
  public ?object $params;
  private array $path;

  function __construct()
  {
    $this->uri = '/' . trim(parse_url($_SERVER['REQUEST_URI'])['path'], '/');
    $this->method = $_SERVER['REQUEST_METHOD'];
    $this->path = [];
    $this->params = null;
  }

  function route($method, $route, ...$handlers)
  {
    if ($this->method != $method) return;
    $path = '/' . trim(join('', $this->path) . $route, '/');
    if ($path == $this->uri || preg_match('#^' . preg_replace('#:(\w+)#', '(?<$1>[\w\-]+)', $path) . '$#', $this->uri, $params)) {
      foreach ($handlers as $handler) {
        if (isset($params)) $this->params = (object)$params;
        $handler($this);
      }
    }
  }

  function with($base, ...$items)
  {
    $this->path[] = $base;
    if (str_contains($this->uri, join('', $this->path))) {
      foreach ($items as $item)
        $item($this);
    }
    array_pop($this->path);
  }

  function get($route, ...$handlers)
  {
    $this->route('GET', $route, ...$handlers);
  }

  function post($route, ...$handlers)
  {
    $this->route('POST', $route, ...$handlers);
  }

  function put($route, ...$handlers)
  {
    $this->route('PUT', $route, ...$handlers);
  }

  function patch($route, ...$handlers)
  {
    $this->route('PATCH', $route, ...$handlers);
  }

  function delete($route, ...$handlers)
  {
    $this->route('DELETE', $route, ...$handlers);
  }

  function head($route, ...$handlers)
  {
    $this->route('HEAD', $route, ...$handlers);
  }

  function static($path): void
  {
    $item = $path . $this->uri;
    echo file_get_contents(file_exists($item) && is_file($item) ? $item : "$path/index.html");
    exit;
  }

  function getHeaders(): array
  {
    $headers = [];
    foreach (getallheaders() as $k => $v) {
      $headers[strtolower($k)] = $v;
    };
    return $headers;
  }

  function getBody(): mixed
  {
    $body = file_get_contents('php://input');
    if ($this->getHeaders()['content-type'] == 'application/json')
      return json_decode($body);
    return $body;
  }

  function sendRedirect(string $uri, bool $permanent = false): void
  {
    http_response_code($permanent ? 301 : 302);
    header("location: $uri");
  }

  function sendData(string $data = null): void
  {
    if ($data) {
      if (file_exists($data)) {
        if (pathinfo($data, PATHINFO_EXTENSION) == 'php')
          include $data;
        else {
          header('content-type: ' . mime_content_type($data));
          echo file_get_contents($data);
        }
      } else echo $data;
    }
    exit;
  }

  function setStatus(int $code): Routy
  {
    http_response_code($code);
    return $this;
  }

  function sendStatus(int $code): void
  {
    $this->setStatus($code);
    exit;
  }

  function sendJson(mixed $data): void
  {
    header('content-type: application/json');
    $this->sendData(json_encode($data));
  }

  function notFound(?callable $handler = null)
  {
    $path = '/' . trim(join('', $this->path), '/');
    if ($path == '/' || preg_match("#^$path#", $this->uri)) {
      http_response_code(404);
      if ($handler) $handler($this);
      exit;
    }
  }
}
