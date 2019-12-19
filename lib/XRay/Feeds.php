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

    if(strpos($contentType, 'application/atom+xml') !== false || strpos(substr($body, 0, 50), '<feed ') !== false) {
      $feeds[] = [
        'url' => $result['url'],
        'type' => 'atom'
      ];
    } elseif(strpos($contentType, 'application/rss+xml') !== false || strpos($contentType, 'text/xml') !== false
             || strpos($contentType, 'application/xml') !== false || strpos(substr($body, 0, 50), '<rss ') !== false) {
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
    } elseif((strpos($contentType, 'application/mf2+json') !== false || strpos($contentType, 'application/microformats2+json') !== false ) && substr($body, 0, 1) == '{') {
      $feeddata = json_decode($body, true);
      if($feeddata && isset($feeddata['items']) && !empty($feeddata['items'])) {
        // assume that the first element in the array is the feed object
        $item0 = $feeddata['items'][0];

        if (isset($item0['type']) && $item0['type'][0] == 'h-feed') {
          $feeds[] = [
            'url' => $result['url'],
            'type' => 'microformats'
          ];
        }
      }
    } else {
      // Some other document was returned, parse the HTML and look for rel alternates and Microformats

      $mf2 = \mf2\Parse($result['body'], $result['url']);
      if(isset($mf2['rel-urls'])) {
        foreach($mf2['rel-urls'] as $rel=>$info) {
          if(isset($info['rels']) && in_array('alternate', $info['rels'])) {
            if(isset($info['type'])) {
              if(strpos($info['type'], 'application/json') !== false) {
                $feeds[] = [
                  'url' => $rel,
                  'type' => 'jsonfeed'
                ];
              }
              if(strpos($info['type'], 'application/atom+xml') !== false) {
                $feeds[] = [
                  'url' => $rel,
                  'type' => 'atom'
                ];
              }
              if(strpos($info['type'], 'application/rss+xml') !== false) {
                $feeds[] = [
                  'url' => $rel,
                  'type' => 'rss'
                ];
              }
            }
          }
        }
      }

      // Check if the feed URL was a temporary redirect
      if($url != $result['url']) {
        // p3k\http doesn't return the intermediate HTTP codes, so we have to fetch the input URL again without following redirects
        $this->http->set_max_redirects(0);
        $check = $this->http->get($url);
        if($check['code'] == 302)
          $result['url'] = $url;
      }

      $parsed = Formats\HTML::parse($this->http, $result, array_merge($opts, ['expect'=>'feed']));
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
