<?php
namespace p3k;

class XRay {
  public $http;

  public function __construct() {
    $this->http = new HTTP('Mozilla/5.0 (Macintosh; Intel Mac OS X 10_12_6) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/76.0.3809.132 Safari/537.36 p3k/XRay');
  }

  public function rels($url, $opts=[]) {
    $rels = new XRay\Rels($this->http);
    return $rels->parse($url, $opts);
  }

  public function feeds($url, $opts=[]) {
    $feeds = new XRay\Feeds($this->http);
    return $feeds->find($url, $opts);
  }

  public function parse($url, $opts_or_body=false, $opts_for_body=[]) {
    if(!$opts_or_body || is_array($opts_or_body)) {
      $fetch = new XRay\Fetcher($this->http);
      $response = $fetch->fetch($url, $opts_or_body);
      if(!empty($response['error']))
        return $response;
      $body = $response['body'];
      $url = $response['url'];
      $code = $response['code'];
      $opts = is_array($opts_or_body) ? $opts_or_body : $opts_for_body;
    } else {
      $body = $opts_or_body;
      $opts = $opts_for_body;
      $code = null;
    }
    $parser = new XRay\Parser($this->http);

    $result = $parser->parse([
      'body' => $body,
      'url' => $url,
      'code' => $code,
    ], $opts);

    if(!isset($opts['include_original']) || !$opts['include_original'])
      unset($result['original']);
    if(!isset($result['url'])) $result['url'] = $url;
    if(!isset($result['code'])) $result['code'] = $code;
    if(!isset($result['source-format'])) $result['source-format'] = null;
    return $result;
  }

  public function process($url, $mf2json, $opts=[]) {
    $parser = new XRay\Parser($this->http);
    $result = $parser->parse([
      'body' => $mf2json,
      'url' => $url,
      'code' => null,
    ], $opts);
    if(!isset($opts['include_original']) || !$opts['include_original'])
      unset($result['original']);
    if(!isset($result['url'])) $result['url'] = $url;
    if(!isset($result['source-format'])) $result['source-format'] = null;
    return $result;
  }

}
