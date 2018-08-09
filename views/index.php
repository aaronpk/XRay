<?php $this->layout('layout', ['title' => $title]); ?>

<div class="column">

  <h1>X-Ray</h1>

  <div class="section url-form">
    <form class="" action="parse" method="get">
      <div class="field"><input type="url" name="url" placeholder="http://example.com"></div>
      <div class="field"><button type="submit" class="button" value="Go">Go</button></div>
      <input type="hidden" name="pretty" value="true">
    </form>
  </div>

  <div class="section html-form">
    <form class="" action="parse" method="post">
      <div class="field"><textarea name="html" rows="4" placeholder="HTML"></textarea></div>
      <div class="field"><input type="url" name="url" placeholder="http://example.com"></div>
      <div class="field"><input type="submit" class="button" value="Go"></div>
      <input type="hidden" name="pretty" value="true">
    </form>
  </div>

  <p class="help"><a href="https://github.com/aaronpk/XRay">Read Me</a>. Please <a href="https://github.com/aaronpk/XRay/issues">file an issue</a> if you encounter any issues.</p>

</div>

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
