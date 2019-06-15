<?php
namespace p3k\XRay;

use p3k\XRay\Formats;

class Parser {
  private $http;

  public function __construct($http) {
    $this->http = $http;
  }

  public function parse($http_response, $opts=[]) {
    if(isset($opts['timeout']))
      $this->http->set_timeout($opts['timeout']);
    if(isset($opts['max_redirects']))
      $this->http->set_max_redirects($opts['max_redirects']);

    // Check if the URL matches a special parser
    $url = $http_response['url'];

    if(Formats\Instagram::matches($url)) {
      return Formats\Instagram::parse($this->http, $http_response, $opts);
    }

    if(Formats\GitHub::matches($url)) {
      return Formats\GitHub::parse($http_response);
    }

    if(Formats\Twitter::matches($url)) {
      return Formats\Twitter::parse($http_response);
    }

    if(Formats\Facebook::matches($url)) {
      return Formats\Facebook::parse($http_response);
    }

    if(Formats\XKCD::matches($url)) {
      return Formats\XKCD::parse($http_response);
    }

    if(Formats\Hackernews::matches($url)) {
      return Formats\Hackernews::parse($http_response);
    }

    $body = $http_response['body'];

    // Check if an mf2 JSON object was passed in
    if(is_array($body) && isset($body['items'][0]['type']) && isset($body['items'][0]['properties'])) {
      $data = Formats\Mf2::parse($http_response, $this->http, $opts);
      $data['source-format'] = 'mf2+json';
      return $data;
    }

    // Check if an ActivityStreams JSON object was passed in
    if(Formats\ActivityStreams::is_as2_json($body)) {
      $data = Formats\ActivityStreams::parse($http_response, $this->http, $opts);
      $data['source-format'] = 'activity+json';
      return $data;
    }

    if(substr($body, 0, 5) == '<?xml') {
      return Formats\XML::parse($http_response);
    }

    if(substr($body, 0, 1) == '{') {
      $parsed = json_decode($body, true);
      if($parsed && isset($parsed['version']) && $parsed['version'] == 'https://jsonfeed.org/version/1') {
        $http_response['body'] = $parsed;
        return Formats\JSONFeed::parse($http_response);
      } elseif($parsed && isset($parsed['items'][0]['type']) && isset($parsed['items'][0]['properties'])) {
        // Check if an mf2 JSON string was passed in
        $http_response['body'] = $parsed;
        $data = Formats\Mf2::parse($http_response, $this->http, $opts);
        $data['source-format'] = 'mf2+json';
        return $data;
      } elseif($parsed && Formats\ActivityStreams::is_as2_json($parsed)) {
        // Check if an ActivityStreams JSON string was passed in
        $http_response['body'] = $parsed;
        $data = Formats\ActivityStreams::parse($http_response, $this->http, $opts);
        $data['source-format'] = 'activity+json';
        return $data;
      }
    }

    // No special parsers matched, parse for Microformats now
    $data = Formats\HTML::parse($this->http, $http_response, $opts);
    if(!isset($data['source-format']) && isset($data['type']) && $data['type'] != 'unknown')
      $data['source-format'] = 'mf2+html';
    return $data;
  }

}
