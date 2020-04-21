<?php
namespace p3k\XRay;

function view($template, $data=[]) {
  global $templates;
  return $templates->render($template, $data);
}

// Adds slash if no path is in the URL, and convert hostname to lowercase
function normalize_url($url) {
  $parts = parse_url($url);
  if(empty($parts['path']))
    $parts['path'] = '/';
  if(isset($parts['host']))
    $parts['host'] = strtolower($parts['host']);
  return build_url($parts);
}

function normalize_urls($urls) {
  return array_map('\p3k\XRay\normalize_url', $urls);
}

function urls_are_equal($url1, $url2) {
  $url1 = normalize_url($url1);
  $url2 = normalize_url($url2);
  return $url1 == $url2;
}

function build_url($parsed_url) {
  $scheme   = isset($parsed_url['scheme']) ? $parsed_url['scheme'] . '://' : '';
  $host     = isset($parsed_url['host']) ? $parsed_url['host'] : '';
  $port     = isset($parsed_url['port']) ? ':' . $parsed_url['port'] : '';
  $user     = isset($parsed_url['user']) ? $parsed_url['user'] : '';
  $pass     = isset($parsed_url['pass']) ? ':' . $parsed_url['pass']  : '';
  $pass     = ($user || $pass) ? "$pass@" : '';
  $path     = isset($parsed_url['path']) ? $parsed_url['path'] : '';
  $query    = isset($parsed_url['query']) ? '?' . $parsed_url['query'] : '';
  $fragment = isset($parsed_url['fragment']) ? '#' . $parsed_url['fragment'] : '';
  return "$scheme$user$pass$host$port$path$query$fragment";
}

function should_follow_redirects($url) {
  $host = parse_url($url, PHP_URL_HOST);
  if(preg_match('/brid\.gy|appspot\.com|blogspot\.com|youtube\.com/', $host)) {
    return false;
  } else {
    return true;
  }
}

function phpmf2_version() {
  $composer = json_decode(file_get_contents(dirname(__FILE__).'/../composer.lock'));
  $version = 'unknown';
  foreach($composer->packages as $pkg) {
    if($pkg->name == 'mf2/mf2') {
      $version = $pkg->version;
    }
  }
  return $version;
}

function allow_iframe_video($value = NULL) {
  static $allow_iframe_video = false;

  if (isset($value))
    $allow_iframe_video = $value;

  return $allow_iframe_video;
}