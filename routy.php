<?php

class Routy
{
  public object $req;
  public object $res;
  private array $routes = [];

  function __construct()
  {
    $this->req = new class
    {
      public string $method;
      public string $uri;
      public object $query;
      public object $params;
      public array $ctx;

      function __construct()
      {
        $url = parse_url($_SERVER['REQUEST_URI']);
        $this->method = $_SERVER['REQUEST_METHOD'];
        $this->uri = rtrim($url['path'], '/') ?: '/';
        $this->query = (object)$_REQUEST;
      }

      public function headers(): object
      {
        return (object)getallheaders();
      }

      public function body(): mixed
      {
        $data = file_get_contents('php://input');
        $type = @$this->headers()->{'Content-Type'} ?? @$this->headers()->{'content-type'};
        return $type == 'application/json' ? json_decode($data) : $data;
      }
    };

    $this->res = new class
    {
      function render(string $template, ?array $variables = []): void
      {
        extract($variables);
        include $template;
        exit;
      }

      function status(int $code): object
      {
        http_response_code($code);
        return $this;
      }

      function send(string $data, ?string $type = 'text/html'): void
      {
        if ($type) header('Content-Type: ' . $type);
        echo $data;
        exit;
      }

      function redirect(string $uri): void
      {
        header('location: ' . $uri);
        exit;
      }

      function sendStatus(int $code): void
      {
        $this->status($code);
        exit;
      }

      function json(mixed $data): void
      {
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
      }
    };
  }

  public function post(string $uri, callable ...$handlers): void
  {
    $this->routes[] = (object)['method' => 'POST', 'uri' => $uri, 'handlers' => $handlers];
  }

  public function get(string $uri, callable ...$handlers): void
  {
    $this->routes[] = (object)['method' => 'GET', 'uri' => $uri, 'handlers' => $handlers];
  }

  public function put(string $uri, callable ...$handlers): void
  {
    $this->routes[] = (object)['method' => 'PUT', 'uri' => $uri, 'handlers' => $handlers];
  }

  public function patch(string $uri, callable ...$handlers): void
  {
    $this->routes[] = (object)['method' => 'PATCH', 'uri' => $uri, 'handlers' => $handlers];
  }

  public function delete(string $uri, callable ...$handlers): void
  {
    $this->routes[] = (object)['method' => 'DELETE', 'uri' => $uri, 'handlers' => $handlers];
  }

  public function any(string $uri, callable ...$handlers): void
  {
    $this->routes[] = (object)['method' => null, 'uri' => $uri, 'handlers' => $handlers];
  }

  public function use(string $base, mixed ...$handlers): void
  {
    $callables = [];
    $sub = (object)['method' => null, 'uri' => $base, 'routes' => []];
    foreach ($handlers as $h) {
      if (is_callable($h)) $callables[] = $h;
      elseif ($h instanceof Routy) {
        foreach ($h->routes as $r) {
          $r->uri = '/' . trim($base . $r->uri, '/');
          array_unshift($r->handlers, ...$callables);
          $this->routes[] = $r;
        }
      }
    }
  }

  private function validate(string $uri): bool
  {
    if ($uri == $this->req->uri) return true;
    if (str_contains($uri, ':') && preg_match('#^' . preg_replace('#:(\w+)#', '(?<$1>[\w\-]+)', $uri) . '$#', $this->req->uri, $params)) {
      $this->req->params = (object)$params;
      return true;
    }
    return false;
  }

  private function matchRoute(array $routes, string $base = ''): void
  {
    try {
      foreach ($routes as $r) {
        if ($r->method && $r->method != $this->req->method) continue;
        if (@$r->routes && preg_match("#^$r->uri#", $this->req->uri)) $this->matchRoute($r->routes, $r->uri);
        if ($this->validate('/' . trim($base . $r->uri, '/')) || str_contains($r->uri, '/:notfound')) {
          foreach ($r->handlers as $h) ($h)($this->req, $this->res);
          break;
        }
      }
      $this->res->sendStatus(404);
    } catch (\Exception $ex) {
      $this->res->status(500)->send('<pre>' . $ex->__toString());
    }
  }
  public function run(): void
  {
    $this->matchRoute($this->routes);
  }
}
