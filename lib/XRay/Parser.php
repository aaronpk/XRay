<?php
namespace p3k\XRay;

use p3k\XRay\Formats;

class Parser {
  private $http;

  public function __construct($http) {
    $this->http = $http;
  }

  public function parse($body, $url, $opts=[]) {
    if(isset($opts['timeout']))
      $this->http->set_timeout($opts['timeout']);
    if(isset($opts['max_redirects']))
      $this->http->set_max_redirects($opts['max_redirects']);

    // Check if the URL matches a special parser

    if(Formats\Instagram::matches($url)) {
      return Formats\Instagram::parse($this->http, $body, $url, $opts);
    }

    if(Formats\GitHub::matches($url)) {
      return Formats\GitHub::parse($body, $url);
    }

    if(Formats\Twitter::matches($url)) {
      return Formats\Twitter::parse($body, $url);
    }

    if(Formats\Facebook::matches($url)) {
      return Formats\Facebook::parse($body, $url);
    }

    if(Formats\XKCD::matches($url)) {
      return Formats\XKCD::parse($body, $url);
    }

    if(Formats\Hackernews::matches($url)) {
      return Formats\Hackernews::parse($body, $url);
    }

    // Check if an mf2 JSON object was passed in
    if(is_array($body) && isset($body['items'][0]['type']) && isset($body['items'][0]['properties'])) {
      $data = Formats\Mf2::parse($body, $url, $this->http, $opts);
      $data['source-format'] = 'mf2+json';
      return $data;
    }

    if(substr($body, 0, 5) == '<?xml') {
      return Formats\XML::parse($body, $url);
    }

    if(substr($body, 0, 1) == '{') {
      $parsed = json_decode($body, true);
      if($parsed && isset($parsed['version']) && $parsed['version'] == 'https://jsonfeed.org/version/1') {
        return Formats\JSONFeed::parse($parsed, $url);
      // TODO: check for an activitystreams object too
      } elseif($parsed && isset($parsed['items'][0]['type']) && isset($parsed['items'][0]['properties'])) {
        // Check if an mf2 JSON string was passed in
        $data = Formats\Mf2::parse($parsed, $url, $this->http, $opts);
        $data['source-format'] = 'mf2+json';
        return $data;
      }
    }

    // No special parsers matched, parse for Microformats now
    $data = Formats\HTML::parse($this->http, $body, $url, $opts);
    if(!isset($data['source-format']))
      $data['source-format'] = 'mf2+html';
    return $data;
  }

}
