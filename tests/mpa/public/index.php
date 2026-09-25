<?php

require '../../../GingerTek/Routy.php';

const ROOT = __DIR__ . '/..';

class DB
{
  public static ?PDO $pdo = null;
  public static function connect(): PDO
  {
    if (!self::$pdo)
      self::$pdo = new PDO('sqlite:' . ROOT . '/test.db', null, null, [
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ,
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
      ]);
    return self::$pdo;
  }

  public static function run(string $sql, ?array $params = []): \PDOStatement
  {
    $stmt = self::connect()->prepare($sql);
    $stmt->execute($params);
    return $stmt;
  }
}

use GingerTek\Routy;

$app = new Routy([
  'render' => function (string $view, array $context, Routy $app): string {
    ob_start();
    $context['view'] = ROOT . "/views/$view.php";
    extract($context, EXTR_OVERWRITE);
    include ROOT . '/views/_layout.php';
    return ob_get_clean();
  }
]);

$app->use(fn() => $app->removeHeader('x-powered-by'));

$app->get('/', fn() => $app->render('home', ['title' => 'Home']));

$app->get('/ajax', fn() => $app->render('ajax'));

$app->group('/api', function (Routy $app) {
  $app->get('/ajax', function (Routy $app) {
    $res = DB::run("select 'Hello from SQLite!' as text")->fetch();
    $app->sendJson(['msg' => $res->text]);
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
