<?php
namespace p3k\XRay;

use p3k\XRay\Formats;

class Feeds {
  private $http;

  public function __construct($http) {
    $this->http = $http;
  }

  public function find($url, $opts=[]) {
    if(isset($opts['timeout']))
      $this->http->set_timeout($opts['timeout']);
    if(isset($opts['max_redirects']))
      $this->http->set_max_redirects($opts['max_redirects']);

    $scheme = parse_url($url, PHP_URL_SCHEME);
    if(!in_array($scheme, ['http','https'])) {
      return [
        'error' => 'invalid_url',
        'error_description' => 'Only http and https URLs are supported'
      ];
    }

    $host = parse_url($url, PHP_URL_HOST);
    if(!$host) {
      return [
        'error' => 'invalid_url',
        'error_description' => 'The URL provided was not valid'
      ];
    }

    $url = normalize_url($url);

    $result = $this->http->get($url);

    if(isset($result['error']) && $result['error']) {
      return [
        'error' => $result['error'],
        'error_description' => $result['error_description']
      ];
    }

    $body = $result['body'];

    $feeds = [];

    // First check the content type of the response
    $contentType = isset($result['headers']['Content-Type']) ? $result['headers']['Content-Type'] : '';

    if(is_array($contentType))
      $contentType = $contentType[count($contentType)-1];

    if(strpos($contentType, 'application/atom+xml') !== false) {
      $feeds[] = [
        'url' => $result['url'],
        'type' => 'atom'
      ];
    } elseif(strpos($contentType, 'application/rss+xml') !== false || strpos($contentType, 'text/xml') !== false) {
      $feeds[] = [
        'url' => $result['url'],
        'type' => 'rss'
      ];
    } elseif(strpos($contentType, 'application/json') !== false && substr($body, 0, 1) == '{') {
      $feeddata = json_decode($body, true);
      if($feeddata && isset($feeddata['version']) && $feeddata['version'] == 'https://jsonfeed.org/version/1') {
        $feeds[] = [
          'url' => $result['url'],
          'type' => 'jsonfeed'
        ];
      }
    } else {
      // Some other document was returned, parse the HTML and look for rel alternates and Microformats

      $mf2 = \mf2\Parse($body, $result['url']);
      if(isset($mf2['alternates'])) {
        foreach($mf2['alternates'] as $alt) {
          if(isset($alt['type'])) {
            if(strpos($alt['type'], 'application/json') !== false) {
              $feeds[] = [
                'url' => $alt['url'],
                'type' => 'jsonfeed'
              ];
            }
            if(strpos($alt['type'], 'application/atom+xml') !== false) {
              $feeds[] = [
                'url' => $alt['url'],
                'type' => 'atom'
              ];
            }
            if(strpos($alt['type'], 'application/rss+xml') !== false) {
              $feeds[] = [
                'url' => $alt['url'],
                'type' => 'rss'
              ];
            }
          }
        }
      }

      $parsed = Formats\HTML::parse($this->http, $body, $result['url'], array_merge($opts, ['expect'=>'feed']));
      if($parsed && isset($parsed['data']['type']) && $parsed['data']['type'] == 'feed') {
        $feeds[] = [
          'url' => $result['url'],
          'type' => 'microformats'
        ];
      }
    }

    // Sort feeds by priority
    $rank = ['microformats'=>0,'jsonfeed'=>1,'atom'=>2,'rss'=>3];
    usort($feeds, function($a, $b) use($rank) {
      return $rank[$a['type']] > $rank[$b['type']];
    });

    return [
      'url' => $result['url'],
      'code' => $result['code'],
      'feeds' => $feeds,
    ];
  }

}
