<?php

require '../../GingerTek/Routy.php';

use GingerTek\Routy;

$app = new Routy(['layout' => 'views/_layout.php']);

$app->setCtx('db', new PDO('sqlite:test.db', null, null, [
  PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
  PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ
]));

$app->get('/', fn($app) => $app->toView('home'));

$app->get('/ajax', fn($app) => $app->toView('ajax'));

$app->group('/api', function (Routy $app) {
  $app->get('/ajax', function (Routy $app) {
    $db = $app->getCtx('db');
    $stmt = $db->prepare("select 'Hello from SQLite!' as text");
    $stmt->execute();
    return $app->toJson(['msg' => $stmt->fetch()->text]);
  });

  $app->fallback(fn() => $app->toJson(['error' => 'API not found']));
});

$app->get('/raw-data', function() {
  header('Content-Type: image/png');
  return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAEAAAABACAIAAAAlC+aJAAAAh0lEQVR4nOzRUQkCYRBGUZE/hZjADJawgeC7FYxhExMZZFPMXhbOCfANl1n39+c06fZ9jO6fR9d3IKAmoCagJqAmoCagJqAmoCagJqAmoCagJqAmoCagtv6vy+iB3/U5un/4DwioCagJqAmoCagJqAmoCagJqAmoCagJqAmoCagJqG0BAAD//+KVBVPtq2UsAAAAAElFTkSuQmCC');
});

$app->route('GET|POST', '/form', function (Routy $app) {
  if ($app->method == 'POST')
    $data = $app->getBody()->test;
  return $app->toView('form', ['model' => ['data' => $data ?? null]]);
});

$app->route('GET|POST', '/form-multipart', function (Routy $app) {
  if ($app->method == 'POST') {
    $data = $app->getBody()->test;
    $files = $app->getFiles('files');
  }
  return $app->toView('multipart', ['model' => ['data' => $data ?? null, 'files' => $files ?? []]]);
});

$app->fallback(fn() => '<h1>Page not found</h1>');
