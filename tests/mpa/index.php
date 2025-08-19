<?php

require '../../GingerTek/Routy.php';

use GingerTek\Routy;

$app = new Routy(['layout' => 'views/_layout.php']);

$app->setCtx('db', new PDO('sqlite:test.db', null, null, [
  PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
  PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ
]));

$app->get('/', fn() => $app->render('home'));

$app->get('/ajax', fn() => $app->render('ajax'));

$app->group('/api', function (Routy $app) {
  $app->get('/ajax', function (Routy $app) {
    $db = $app->getCtx('db');
    $stmt = $db->prepare("select 'Hello from SQLite!' as text");
    $stmt->execute();
    $app->sendJson(['msg' => $stmt->fetch()->text]);
  });

  $app->fallback(fn() => $app->sendJson(['error' => 'API not found']));
});

$app->route('GET|POST', '/form', function (Routy $app) {
  if ($app->method == 'POST')
    $data = $app->getBody();
  $app->render('form', ['model' => ['data' => $data->test ?? null]]);
});

$app->route('GET|POST', '/form-multipart', function (Routy $app) {
  if ($app->method == 'POST') {
    $data = $app->getBody();
    $files = $app->getFiles('files');
  }
  $app->render('multipart', ['model' => ['data' => $data->test ?? null, 'files' => $files ?? []]]);
});

$app->fallback(fn() => $app->sendData('<h1>Page not found</h1>'));