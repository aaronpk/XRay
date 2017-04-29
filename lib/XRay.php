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

  public function parse($url, $opts=[]) {
    $fetch = new XRay\Fetch($this->http);
    $response = $fetch->fetch($url, $opts);
    return $this->parse_doc($response, $url, $opts);
  }

  public function parse_doc($response, $url=false, $opts=[]) {
    
    
  }

}

