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
    $parser = new XRay\Parser($this->http);
    return $parser->parse($url, $opts);
  }

}

