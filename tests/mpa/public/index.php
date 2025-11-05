<?php

require '../../../GingerTek/Routy.php';

use GingerTek\Routy;

$app = new Routy([
  'root' => '../',
  'render' => function (string $view, array $context, Routy $app): string {
    ob_start();
    $context['view'] = $app->getConfig('root') . "views/$view.php";
    extract($context, EXTR_OVERWRITE);
    include $app->getConfig('root') . 'views/_layout.php';
    return ob_get_clean();
  }
]);

$app->setCtx('db', new PDO('sqlite:' . $app->getConfig('root') . '/test.db', null, null, [
  PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ,
  PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
]));

$app->get('/', fn() => $app->render('home', ['title' => 'Home']));

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
