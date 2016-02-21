<?php $this->layout('layout', ['title' => $title]); ?>

<div class="ui middle aligned center aligned grid">
  <div class="column">

    <h1>X-Ray</h1>

    <form class="ui large form" action="/parse" method="get">
      <div class="ui stacked segment">
        <div class="field">
          <div class="ui left icon input">
            <i class="linkify icon"></i>
            <input type="url" name="url" placeholder="http://example.com">
          </div>
        </div>
        <button class="ui fluid large teal submit button">Go</button>
      </div>
    </form>

    <p><a href="https://github.com/aaronpk/XRay">Read Me</a>. Please <a href="https://github.com/aaronpk/XRay/issues">file an issue</a> if you encounter any issues.</p>

  </div>
</div>


<style type="text/css">
  body {
    background-color: #e9e9e9;
  }
  body > .grid {
    height: 100%;
  }
  .image {
    margin-top: -100px;
  }
  .column {
    max-width: 450px;
  }
</style>
