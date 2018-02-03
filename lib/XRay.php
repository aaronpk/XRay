<?php
namespace p3k;

class XRay {
  public $http;

  public function __construct() {
    $this->http = new HTTP();
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

    $result = $parser->parse($body, $url, $opts);
    if(!isset($opts['include_original']) || !$opts['include_original'])
      unset($result['original']);
    $result['url'] = $url;
    $result['code'] = isset($result['code']) ? $result['code'] : $code;
    return $result;
  }

  public function process($url, $mf2json, $opts=[]) {
    $parser = new XRay\Parser($this->http);
    $result = $parser->parse($mf2json, $url, $opts);
    if(!isset($opts['include_original']) || !$opts['include_original'])
      unset($result['original']);
    $result['url'] = $url;
    return $result;
  }

}

