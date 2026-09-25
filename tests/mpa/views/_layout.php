<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>MPA App - <?= $title ?? '' ?></title>
</head>

<body>
  <header>
    <h1>MPA App</h1>
    <ul>
      <li><a href="/form">POST Form</a></li>
      <li><a href="/form-multipart">POST Form (multipart)</a></li>
      <li><a href="/ajax">AJAX Form</a></li>
    </ul>
  </header>
  <main>
    <?php include $view; ?>
  </main>
</body>

</html>