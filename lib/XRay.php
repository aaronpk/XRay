<?php
namespace p3k;

class XRay {
  public $http;

  private $defaultOptions = [];

  public function __construct($options=[]) {
    $this->http = new HTTP('Mozilla/5.0 (Macintosh; Intel Mac OS X 10_12_6) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/76.0.3809.132 Safari/537.36 p3k/XRay');
    if (is_array($options)) {
      $this->defaultOptions = $options;
    }
  }

  public function rels($url, $opts=[]) {
    $rels = new XRay\Rels($this->http);
    // Merge provided options with default options, allowing provided options to override defaults.
    $opts = array_merge($this->defaultOptions, $opts);
    return $rels->parse($url, $opts);
  }

  public function feeds($url, $opts=[]) {
    $feeds = new XRay\Feeds($this->http);
    // Merge provided options with default options, allowing provided options to override defaults.
    $opts = array_merge($this->defaultOptions, $opts);
    return $feeds->find($url, $opts);
  }

  public function parse($url, $opts_or_body=false, $opts_for_body=[]) {
    if(!$opts_or_body || is_array($opts_or_body)) {
      $fetch = new XRay\Fetcher($this->http);
      if(is_array($opts_or_body)) {
        $fetch_opts = array_merge($this->defaultOptions, $opts_or_body);
      } else {
        $fetch_opts = $this->defaultOptions;
      }
      if(is_array($fetch_opts) && isset($fetch_opts['httpsig'])) {
        $fetch->httpsig($fetch_opts['httpsig']);
      }
      $response = $fetch->fetch($url, $fetch_opts);
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
      $fetch = null;
    }
    $parser = new XRay\Parser($this->http, $fetch);

    // Merge provided options with default options, allowing provided options to override defaults.
    $opts = array_merge($this->defaultOptions, $opts);

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
    // Merge provided options with default options, allowing provided options to override defaults.
    $opts = array_merge($this->defaultOptions, $opts);
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
  
  public function httpsig($opts) {
    $this->defaultOptions = array_merge($this->defaultOptions, ['httpsig' => $opts]);
  }

}
