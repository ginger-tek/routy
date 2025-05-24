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
</script>