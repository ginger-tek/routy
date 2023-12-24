<?php

require '../src/GingerTek/Routy/Routy.php';

use GingerTek\Routy\Routy;

$app = new Routy();

$app->get('/', function (Routy $app) {
  $app->sendData('<h1>Hello, world!</h1>
  <li><a href="/form">Traditional POST Form</a>
  <li><a href="/ajax">AJAX GET Request</a>
  <li><a href="/app">Static SPA Root</a>');
});

$app->get('/ajax', function (Routy $app) {
  $app->sendData('<button type="button" onclick="getData()">Submit AJAX</button>
  <p>Response data: <span id="response"></span></p>
  <script>
    async function getData() {
      const res = await fetch("/api/ajax")
      if (res.ok) {
        const data = await res.json()
        response.innerText = data.msg
      }
    }
  </script>');
});

$app->with('/api', function (Routy $app) {
  $app->get('/ajax', function (Routy $app) {
    $app->sendJson(['msg' => 'Hello, world!']);
  });
});

$app->route('GET|POST', '/form', function (Routy $app) {
  if ($app->method == 'POST') {
    $data = $_POST['test'];
  }
  $app->sendData('<form method="post">
    <input name="test">
    <button type="submit">Submit</button>
  </form>
  <p>Submitted data: ' . @$data . '</p>');
});

$app->static('./public', '/app');

$app->sendStatus(404);