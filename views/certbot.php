<?php $this->layout('layout', ['title' => $title]); ?>

<div class="column">

  <h1>X-Ray Certificate Setup</h1>

  <?php if(isset($_SESSION['me'])): ?>
    <?php if(isset($verified) && $verified): ?>
      <div class="section">
        <p>The challenge was saved and is now accessible via the <code>.well-known</code> path.</p>
        <p><a href="/.well-known/acme-challenge/<?= $token ?>">view challenge</a></p>
      </div>
    <?php else: ?>
      <div class="section">
        <form class="" action="/cert/save-challenge" method="post">
          <div class="field"><input type="text" name="token" placeholder="http://xray.p3k.io/.well-known/acme-challenge/_Tzyxwvut..." value="<?= isset($token) ? $token : '' ?>"></div>
          <div class="field"><textarea name="challenge" rows="4" placeholder="challenge value"><?= isset($challenge) ? $challenge : '' ?></textarea></div>
          <div class="field"><button type="submit" class="button">Save</button></div>
        </form>
      </div>
    <?php endif ?>

    <div style="margin-top: 1em; font-size: 12px;">
      Signed in as <?= $_SESSION['me'] ?> <a href="/cert/logout">Sign Out</a>.
    </div>
  <?php else: ?>
    <div class="section">
      <form class="" action="/cert/auth" method="get">
        <div class="field"><input type="url" name="me" placeholder="https://you.example.com"></div>
        <div class="field"><button type="submit" class="button">Sign In</button></div>

        <input type="hidden" name="client_id" value="https://<?= $_SERVER['SERVER_NAME'] ?>/">
        <input type="hidden" name="redirect_uri" value="https://<?= $_SERVER['SERVER_NAME'] ?>/cert/redirect">
        <input type="hidden" name="state" value="<?= isset($state) ? $state : '' ?>">
      </form>
    </div>
  <?php endif ?>

</div>
<script>
var base = window.location.protocol + "//" + window.location.hostname + "/";
document.querySelector("input[name=client_id]").value = base;
document.querySelector("input[name=redirect_uri]").value = base+"cert/redirect";
</script>
<style type="text/css">
  body {
    color: #212121;
    font-family: "Helvetica Neue", "Calibri Light", Roboto, sans-serif;
    -webkit-font-smoothing: antialiased;
    -moz-osx-font-smoothing: grayscale;
  }
  body {
    background-color: #e9e9e9;
    font-size: 16px;
  }
  h1 {
    padding-top: 6rem;
    padding-bottom: 1rem;
    text-align: center;
  }

  a {
    color: #4183c4;
    text-decoration: none;
  }

  .column {
    max-width: 450px;
    margin: 0 auto;
  }

  .section {
    border: 1px #ccc solid;
    border-radius: 6px;
    background: white;
    padding: 12px;
    margin-top: 2em;
  }
  .help {
    text-align: center;
    font-size: 0.9rem;
  }

  form .field {
    margin-bottom: .5rem;
    display: flex;
  }
  form input, form textarea, form button {
    width: 100%;
    border: 1px #ccc solid;
    border-radius: 4px;
    flex: 1 0;
    font-size: 1rem;
  }
  form input, form textarea {
    padding: .5rem;
  }
  form .button {
    background-color: #009c95;
    border: 0;
    border-radius: 4px;
    color: white;
    font-weight: bold;
    font-size: 1rem;
    cursor: pointer;
    padding: 0.5rem;
  }


</style>