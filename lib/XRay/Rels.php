<?php
namespace p3k\XRay;

class Rels {
  private $http;

  public function __construct($http) {
    $this->http = $http;
  }

  public function parse($url, $opts=[]) {
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

    $html = $result['body'];
    $mf2 = \mf2\Parse($html, $result['url']);

    $rels = $result['rels'];
    if(isset($mf2['rels'])) {
      $rels = array_merge($rels, $mf2['rels']);
    }

    // Resolve all relative URLs
    foreach($rels as $rel=>$values) {
      foreach($values as $i=>$value) {
        $value = \mf2\resolveUrl($result['url'], $value);
        $rels[$rel][$i] = $value;
      }
    }

    if(count($rels) == 0)
      $rels = new \StdClass;

    return [
      'url' => $result['url'],
      'code' => $result['code'],
      'rels' => $rels
    ];
  }

}
