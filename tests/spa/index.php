<?php

require '../../GingerTek/Routy.php';

use GingerTek\Routy;

$app = new Routy;

class Api
{
  public static function index(Routy $app)
  {
    $app->get('/', fn($app) => $app->sendJson(['test' => 'data']));
  }
}

$app->group('/api', Api::index(...));
$app->serveStatic('/', 'public');