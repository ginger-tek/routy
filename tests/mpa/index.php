<?php

require '../../GingerTek/Routy.php';

use GingerTek\Routy;

$app = new Routy(['layout' => 'views/_layout.php']);

$app->setCtx('db', new PDO('sqlite:test.db', null, null, [
  PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
  PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ
]));

$app->get('/', fn($app) => $app->render('home'));

$app->get('/ajax', fn($app) => $app->render('ajax'));

$app->group('/api', function (Routy $app) {
  $app->get('/ajax', function (Routy $app) {
    $db = $app->getCtx('db');
    $stmt = $db->prepare("select 'Hello from SQLite!' as text");
    $stmt->execute();
    $app->sendJson(['msg' => $stmt->fetch()->text]);
  });

  $app->status(404)->sendJson(['error' => 'API not found']);
});

$app->route('GET|POST', '/form', function (Routy $app) {
  if ($app->method == 'POST')
    $data = $_POST['test'];
  $app->render('form', ['model' => ['data' => $data ?? null]]);
});

$app->route('GET|POST', '/form-multipart', function (Routy $app) {
  if ($app->method == 'POST') {
    $data = $_POST['test'];
    $files = $app->getFiles('files');
  }
  $app->render('multipart', ['model' => ['data' => $data ?? null, 'files' => $files ?? []]]);
});

$app->status(404)->sendData('<h1>Page not found</h4>');
