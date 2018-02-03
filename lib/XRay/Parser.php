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
      return Formats\Instagram::parse($this->http, $body, $url);
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
      return Formats\Mf2::parse($body, $url, $this->http, $opts);
    }

    if(substr($body, 0, 5) == '<?xml') {
      return Formats\XML::parse($body, $url);
    }

    if(substr($body, 0, 1) == '{') {
      $feeddata = json_decode($body, true);
      if($feeddata && isset($feeddata['version']) && $feeddata['version'] == 'https://jsonfeed.org/version/1') {
        return Formats\JSONFeed::parse($feeddata, $url);
      } elseif($feeddata && isset($feeddata['items'][0]['type']) && isset($feeddata['items'][0]['properties'])) {
        // Check if an mf2 JSON object was passed in
        return Formats\Mf2::parse($feeddata, $url, $this->http, $opts);
      }
    }

    // No special parsers matched, parse for Microformats now
    return Formats\HTML::parse($this->http, $body, $url, $opts);
  }

}
