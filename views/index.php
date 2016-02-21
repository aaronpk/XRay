<?php $this->layout('layout', ['title' => $title]); ?>

<div class="ui middle aligned center aligned grid">
  <div class="column">
    <form class="ui large form">
      <div class="ui stacked segment">
        <div class="field">
          <div class="ui left icon input">
            <i class="linkify icon"></i>
            <input type="text" name="url" placeholder="http://example.com">
          </div>
        </div>
        <div class="ui fluid large teal submit button">Go</div>
      </div>

    </form>
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
