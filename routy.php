<?php

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
    $this->uri = str_replace($base, '', $upts[0]);
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
  public function json(mixed $data, int $code = 200)
  {
    $this->send(json_encode($data), $code);
  }

  public function send(mixed $data, int $code = 200)
  {
    http_response_code($code);
    if (file_exists($data)) include $data;
    else if ($data) echo $data;
    exit;
  }
}

class Routy
{
  public $base = '';
  public $routes = [];

  function route(string $method, string $route, callable ...$handlers)
  {
    $this->routes[] = [
      $method,
      $route,
      $handlers
    ];
  }

  function get(string $route, callable ...$handlers)
  {
    $this->route('GET', $route, ...$handlers);
  }

  function post(string $route, callable ...$handlers)
  {
    $this->route('POST', $route, ...$handlers);
  }

  function patch(string $route, callable ...$handlers)
  {
    $this->route('PATCH', $route, ...$handlers);
  }

  function put(string $route, callable ...$handlers)
  {
    $this->route('PUT', $route, ...$handlers);
  }

  function delete(string $route, callable ...$handlers)
  {
    $this->route('DELETE', $route, ...$handlers);
  }

  function matchRoute(string $url, string $route)
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

  function group(string $base, mixed ...$useables)
  {
    $callables = [];
    for ($u = 0; $u < count($useables); $u++) {
      if (is_callable($useables[$u])) $callables[] = $useables[$u];
      elseif (is_array($useables[$u])) {
        for ($i = 0; $i < count($useables[$u]); $i++) {
          $useables[$u][$i][1] = rtrim($base . $useables[$u][$i][1], '/');
          array_unshift($useables[$u]->routes[$i][2], ...$callables);
          $this->routes[] = $useables[$u][$i];
        }
      }
    }
  }

  function run()
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
    $res->json(['error' => 'Route not found'], 404);
  }
}
