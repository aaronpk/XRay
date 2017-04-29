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

    if(Formats\XKCD::matches($url)) {
      return Formats\XKCD::parse($body, $url);
    }

    return [
      'data' => [
        'type' => 'unknown'
      ]
    ];
  }

}
