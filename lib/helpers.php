<?php

function view($template, $data=[]) {
  global $templates;
  return $templates->render($template, $data);
}

// Adds slash if no path is in the URL
function normalize_url($url) {
  return parse_url($url, PHP_URL_PATH) == '' ? $url.'/' : $url;
}
