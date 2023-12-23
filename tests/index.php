<?php

require '../src/GingerTek/Routy/Routy.php';

use GingerTek\Routy\Routy;

$app = new Routy();

$app->get('/', function(Routy $app) {
  $app->sendData('<h1>Hello, world!</h1>
  <a href="/form">Standard Form</a>
  <a href="/ajax">AJAX Form</a>');
});

$app->get('/ajax', function(Routy $app) {
  $app->sendData('<form onsubmit="false" id="ajaxForm">
  <button type="submit">Submit AJAX</button>
</form>
<p>Response data: <span id="response"></span></p>
<script>
  ajaxForm.addEventListener("submit", async (ev) => {
    ev.preventDefault()
    const res = await fetch("/api/ajax")
    if (res.ok) {
      const data = await res.json()
      response.innerText = data.msg
    }
  })
</script>');
});

$app->get('/api/ajax', function(Routy $app) {
  $app->sendJson(['msg' => 'Hello, world!']);
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

  $app->getHeaders();
  $app->setStatus(200);
});

$app->sendStatus(404);