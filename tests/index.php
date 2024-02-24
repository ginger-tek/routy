<?php

require '../src/GingerTek/Routy/Routy.php';

use GingerTek\Routy\Routy;

$app = new Routy(['layout' => 'layout.php']);

$app->get('/*', function (Routy $app) {
  $app->sendData('<h1>Tests</h1>
  <ul>
    <li><a href="/form">POST Form</a></li>
    <li><a href="/ajax">AJAX Form</a></li>
    <li><a href="/ajax">AJAX Form</a></li>
  </ul>');
});

$handler = function (Routy $app) {
  $app->sendData('Response from closure variable handler');
};

$app->get('/closure', $handler);

$app->get('/ajax', function (Routy $app) {
  $app->sendData('<h1>Tests</h1>
  <p><a href="/">Back</a></p>
  <button type="button" onclick="getData()">Submit AJAX</button>
  <p>Response data: <span id="response"></span></p>
  <script>
    async function getData() {
      const res = await fetch("/api/ajax")
      if (res.ok) {
        const data = await res.json()
        response.innerText = data.msg
      } else response.innerText = "Error!"
    }
  </script>');
});

$app->group('/api', function (Routy $app) {
  $app->get('/ajax', function (Routy $app) {
    $app->sendJson(['msg' => 'Hello, world!']);
  });

  $app->status(404)->sendJson(['error'=> 'API not found']);
});

$app->route('GET|POST', '/form', function (Routy $app) {
  if ($app->method == 'POST') {
    $data = $_POST['test'];
  }
  $app->sendData('<h1>Tests</h1>
  <p><a href="/">Back</a></p>
  <form method="post">
    <input name="test">
    <button type="submit">Submit</button>
  </form>
  <p>Submitted data: ' . @$data . '</p>');
});

$app->status(404)->sendData('<h1>Page not found</h4>');