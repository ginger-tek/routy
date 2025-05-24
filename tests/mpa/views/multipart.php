<p><a href="/">Back</a></p>
<form method="post" enctype="multipart/form-data">
  <input name="test" required>
  <input name="files[]" type="file" multiple required>
  <button type="submit">Submit</button>
</form>
<p>Submitted data: <?= @$model['data'] ?></p>
<p style="font-family:monospace;white-space:pre-wrap">File(s) uploaded:
  <?= json_encode(@$model['files'], JSON_PRETTY_PRINT) ?>
</p>