<?php

require '../../GingerTek/Routy.php';

use GingerTek\Routy;

$app = new Routy(['layout' => 'layout.php']);

$app->get('/*', function (Routy $app) {
  $app->sendData('<h1>Tests</h1>
  <ul>
    <li><a href="/form">POST Form</a></li>
    <li><a href="/form-multipart">POST Form (multipart)</a></li>
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

  $app->status(404)->sendJson(['error' => 'API not found']);
});

$app->route('GET|POST', '/form', function (Routy $app) {
  if ($app->method == 'POST') {
    $data = $_POST['test'];
  }
  $app->sendData('<h1>Tests</h1>
  <p><a href="/">Back</a></p>
  <form method="post">
    <input name="test" required>
    <button type="submit">Submit</button>
  </form>
  <p>Submitted data: ' . @$data . '</p>');
});

$app->route('GET|POST', '/form-multipart', function (Routy $app) {
  if ($app->method == 'POST') {
    $data = $_POST['test'];
    $files = $app->getFiles('files');
  }
  $app->sendData('<h1>Tests</h1>
  <p><a href="/">Back</a></p>
  <form method="post" enctype="multipart/form-data">
    <input name="test" required>
    <input name="files[]" type="file" multiple required>
    <button type="submit">Submit</button>
  </form>
  <p>Submitted data: ' . @$data . '</p>
  <p style="font-family:monospace;white-space:pre-wrap">File(s) uploaded: ' . json_encode(@$files, JSON_PRETTY_PRINT) . '</p>');
});

$app->status(404)->sendData('<h1>Page not found</h4>');
