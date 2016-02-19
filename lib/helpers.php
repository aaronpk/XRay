<?php

function view($template, $data=[]) {
  global $templates;
  return $templates->render($template, $data);
}
