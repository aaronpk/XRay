<?php $this->layout('layout', ['title' => $title]); ?>

<div class="ui middle aligned center aligned grid">
  <div class="column">

    <h1>X-Ray</h1>

    <div class="ui top attached tabular menu">
      <a class="item active" data-tab="url">URL</a>
      <a class="item" data-tab="html">HTML</a>
    </div>
    <div class="ui bottom attached tab segment active" data-tab="url">
      <form class="ui large form" action="/parse" method="get">
          <div class="field">
            <div class="ui left icon input">
              <i class="linkify icon"></i>
              <input type="url" name="url" placeholder="http://example.com">
            </div>
          </div>
          <button class="ui fluid large teal submit button">Go</button>
      </form>
    </div>
    <div class="ui bottom attached tab segment" data-tab="html">
      <form class="ui large form" action="/parse" method="post">
          <div class="field">
            <div class="ui left icon input">
              <textarea name="html" placeholder="HTML"></textarea>
            </div>
          </div>
          <div class="field">
            <div class="ui left icon input">
              <i class="linkify icon"></i>
              <input type="url" name="url" placeholder="http://example.com">
            </div>
          </div>
          <button class="ui fluid large teal submit button">Go</button>
      </form>
    </div>

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
<script>
$(function(){
  $('.menu .item').tab();
;
});
</script>