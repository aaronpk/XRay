<?php

function view($template, $data=[]) {
  global $templates;
  return $templates->render($template, $data);
}

// Adds slash if no path is in the URL
function normalize_url($url) {
  return parse_url($url, PHP_URL_PATH) == '' ? $url.'/' : $url;
}

function should_follow_redirects($url) {
  $host = parse_url($url, PHP_URL_HOST);
  if(preg_match('/brid\.gy|appspot\.com|blogspot\.com|youtube\.com/', $host)) {
    return false;
  } else {
    return true;
  }
}