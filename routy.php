<?php

class Routy
{
  public object $req;
  public object $res;
  private array $routes = [];
  function __construct(string $base = '')
  {
    $this->req = new class
    {
      public string $method;
      public string $uri;
      public array $params = [];
      public array $query = [];
      public array $ctx = [];
      function __construct()
      {
        $this->method = $_SERVER['REQUEST_METHOD'];
        $parts = parse_url($_SERVER['REQUEST_URI']);
        $this->uri = $parts['path'];
        parse_str(@$parts['query'] ?? '', $this->query);
      }
      public function headers(): array
      {
        return getallheaders();
      }
      public function body(): mixed
      {
        $d = file_get_contents('php://input');
        return $this->headers()['Content-Type'] == 'application/json' ? json_decode($d) : $d;
      }
    };
    $this->res = new class
    {
      public function status(int $code = 200): mixed
      {
        http_response_code($code);
        return $this;
      }
      public function json(mixed $data): void
      {
        header('content-type: application/json');
        echo json_encode($data);
        exit;
      }
      public function send(mixed $data, string $type = null): void
      {
        if ($type) header("content-type: $type");
        if (file_exists($data)) @include($data);
        else echo $data;
        exit;
      }
      public function sendStatus(int $code = 200): void
      {
        http_response_code($code);
        exit;
      }
    };
    $this->req->uri = '/' . trim(str_replace($base, '', $this->req->uri), '/');
  }
  public function post(string $path, callable ...$handlers): void
  {
    $this->routes[] = (object)['method' => 'POST', 'path' => $path, 'handlers' => $handlers];
  }
  public function get(string $path, callable ...$handlers): void
  {
    $this->routes[] = (object)['method' => 'GET', 'path' => $path, 'handlers' => $handlers];
  }
  public function put(string $path, callable ...$handlers): void
  {
    $this->routes[] = (object)['method' => 'PUT', 'path' => $path, 'handlers' => $handlers];
  }
  public function patch(string $path, callable ...$handlers): void
  {
    $this->routes[] = (object)['method' => 'PATCH', 'path' => $path, 'handlers' => $handlers];
  }
  public function delete(string $path, callable ...$handlers): void
  {
    $this->routes[] = (object)['method' => 'DELETE', 'path' => $path, 'handlers' => $handlers];
  }
  public function all(string $path, callable ...$handlers): void
  {
    $this->routes[] = (object)['method' => null, 'path' => $path, 'handlers' => $handlers];
  }
  public function use(string $path, mixed ...$handlers): void
  {
    $callables = [];
    foreach ($handlers as $h) {
      if (is_callable($h)) $callables[] = $h;
      elseif ($h instanceof Routy) {
        foreach ($h->routes as $r) {
          $r->path = '/' . trim($path . $r->path, '/');
          array_unshift($r->handlers, ...$callables);
          $this->routes[] = $r;
        }
      }
    }
  }
  private function parse(string $p): bool
  {
    if ($p == $this->req->uri) return true;
    if (strpos($p, ':')) {
      $rgx = "#^" . preg_replace('/:(\w+)/', '([\w_-]+)', $p) . "$#";
      if (preg_match($rgx, $this->req->uri, $matches)) {
        preg_match_all('/:(\w+)/', $p, $keys);
        $this->req->params = array_combine($keys[1], array_slice($matches, 1));
        return true;
      }
    }
    return false;
  }
  public function run(): void
  {
    try {
      foreach ($this->routes as $r) {
        if ($r->method && $r->method != $this->req->method) continue;
        if ($this->parse($r->path) || $r->path == '/:notfound') {
          foreach ($r->handlers as $h) ($h)($this->req, $this->res);
          break;
        }
      }
      $this->res->sendStatus(404);
    } catch (Exception $ex) {
      $this->res->status(500)->send('<pre>' . $ex->__toString());
    }
  }
}
