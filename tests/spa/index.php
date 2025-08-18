<?php

require '../../GingerTek/Routy.php';

use GingerTek\Routy;

$app = new Routy;

$app->group('/api', function (Routy $app) {
  $app->get('/', fn($app) => $app->toJson(['test' => 'data']));

  $app->group('/users', function (Routy $app) {
    $app->get('/', fn($app) => $app->toJson(['users' => ['Alice', 'Bob']]));
    $app->get('/:id', fn() => $app->toJson(['user' => ['id' => $app->params->id, 'name' => 'User ' . $app->params->id]]));
    $app->fallback(fn() => $app->toJson(['error' => 'User route not found']));
  });

  $app->fallback(fn() => $app->toJson(['error' => 'API Route not found']));
});
$app->serveStatic('/', 'public');