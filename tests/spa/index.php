<?php

require '../../GingerTek/Routy.php';

use GingerTek\Routy;

$app = new Routy;

class Api
{
  public static function index(Routy $app)
  {
    $app->get('/', fn($app) => $app->sendJson(['test' => 'data']));

    $app->group('/users', function (Routy $app) {
      $app->get('/', fn($app) => $app->sendJson(['users' => ['Alice', 'Bob']]));
      $app->get('/{id}', function (Routy $app, $id) {
        return $app->sendJson(['user' => ['id' => $id, 'name' => 'User ' . $id]]);
      });
    });
  }
}

$app->group('/api', Api::index(...));
$app->serveStatic('/', 'public');