<?php

/**
 * Routy
 * v1.0
 * https://github.com/ginger-tek/routy
 */

class Request
{
  public $method;
  public $uri;
  public $params = [];
  public $query = [];
  public $headers = [];
  public $data = [];

  public function __construct(string $base)
  {
    $this->method = $_SERVER['REQUEST_METHOD'];
    $upts = explode('?', $_SERVER['REQUEST_URI']);
    $this->uri = $upts[0] != '/' ? str_replace($base, '', rtrim($upts[0], '/')) : '/';
    if (@$upts[1]) parse_str($upts[1], $this->query);
    $this->headers = (object)getallheaders();
  }

  public function body()
  {
    $d = file_get_contents('php://input');
    if ($_SERVER["CONTENT_TYPE"] == 'application/json') return json_decode($d);
    return $d;
  }
}

class Response
{
  public function send(mixed $data, int $code = 200)
  {
    http_response_code($code);
    if (@file_exists($data)) include $data;
    else if ($data) echo $data;
    exit;
  }

  public function json(mixed $data, int $code = 200)
  {
    $this->send(json_encode($data), $code);
  }

  public function status(int $code = 200)
  {
    $this->send(null, $code);
  }
}

class Routy
{
  public $base = '';
  public $routes = [];

  public function route(string $method, string $route, callable ...$handlers)
  {
    $this->routes[] = [
      $method,
      $route,
      $handlers
    ];
  }

  public function get(string $route, callable ...$handlers)
  {
    $this->route('GET', $route, ...$handlers);
  }

  public function post(string $route, callable ...$handlers)
  {
    $this->route('POST', $route, ...$handlers);
  }

  public function patch(string $route, callable ...$handlers)
  {
    $this->route('PATCH', $route, ...$handlers);
  }

  public function put(string $route, callable ...$handlers)
  {
    $this->route('PUT', $route, ...$handlers);
  }

  public function delete(string $route, callable ...$handlers)
  {
    $this->route('DELETE', $route, ...$handlers);
  }

  private function matchRoute(string $url, string $route)
  {
    if ($route == $url) return true;
    $rpts = array_slice(explode('/', $route), 1);
    $upts = array_slice(explode('/', $url), 1);
    if (count($rpts) != count($upts)) return false;
    $p = [];
    $c = 0;
    for ($i = 0; $i < count($rpts); $i++) {
      if ($rpts[$i] == $upts[$i]) {
        $c++;
      } elseif (@$rpts[$i][0] == ':') {
        $p[str_replace(':', '', $rpts[$i])] = $upts[$i];
        $c++;
      }
    }
    if ($c == count($rpts) && count($p) > 0) return (object)$p;
    elseif ($c == count($rpts)) return true;
    return false;
  }

  public function use(string $base, mixed ...$useables)
  {
    $callables = [];
    for ($u = 0; $u < count($useables); $u++) {
      if (is_callable($useables[$u])) $callables[] = $useables[$u];
      elseif ($useables[$u] instanceof Routy) {
        $routes = $useables[$u]->routes;
        for ($i = 0; $i < count($routes); $i++) {
          $routes[$i][1] = rtrim($base . $routes[$i][1], '/');
          array_unshift($routes[$i][2], ...$callables);
          $this->routes[] = $routes[$i];
        }
      }
    }
  }

  public function run()
  {
    $req = new Request($this->base);
    $res = new Response();
    for ($i = 0; $i < count($this->routes); $i++) {
      if ($this->routes[$i][0] != '*' && $this->routes[$i][0] != $req->method) continue;
      if (!($r = $this->matchRoute($req->uri, $this->routes[$i][1]))) continue;
      $req->params = $r;
      foreach ($this->routes[$i][2] as $c) ($c)($req, $res);
      exit;
    }
    $res->status(404);
  }
}
