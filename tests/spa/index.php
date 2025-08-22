<?php

require '../../GingerTek/Routy.php';

use GingerTek\Routy;

$app = new Routy;

$app->group('/api', function (Routy $app)  {
  $app->get('/', fn() => $app->sendJson(['test' => 'data']));

  $app->group('/users', function (Routy $app) {
    $app->get('/', fn() => $app->sendJson(['users' => ['Alice', 'Bob']]));
    $app->get('/:id', fn () => $app->sendJson(['user' => ['id' => $app->params->id, 'name' => 'User ' . $app->params->id]]));
    $app->fallback(fn() => $app->sendJson(['error' => 'Users route not found']));
  });
  
  $app->fallback(fn() => $app->sendJson(['error' => 'Route not found']));
});
$app->serveStatic('/', 'public');