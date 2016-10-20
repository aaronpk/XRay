<?php
namespace p3k;

class HTTP {

  public $timeout = 4;
  public $max_redirects = 8;

  public function get($url, $headers=[]) {
    $class = $this->_class($url);
    $http = new $class($url);
    $http->timeout = $this->timeout;
    $http->max_redirects = $this->max_redirects;
    return $http->get($url, $headers);
  }

  public function post($url, $body, $headers=[]) {
    $class = $this->_class($url);
    $http = new $class($url);
    $http->timeout = $this->timeout;
    $http->max_redirects = $this->max_redirects;
    return $http->post($url, $body, $headers);
  }

  public function head($url) {
    $class = $this->_class($url);
    $http = new $class($url);
    $http->timeout = $this->timeout;
    $http->max_redirects = $this->max_redirects;
    return $http->head($url);
  }

  private function _class($url) {
    if(!should_follow_redirects($url)) {
      return 'p3k\HTTPStream';
    } else {
      return 'p3k\HTTPCurl';
    }
  }

  public static function link_rels($header_array) {
    $headers = '';
    foreach($header_array as $k=>$header) {
      if(is_string($header)) {
        $headers .= $k . ': ' . $header . "\r\n";
      } else {
        foreach($header as $h) {
          $headers .= $k . ': ' . $h . "\r\n";
        }
      }
    }
    $rels = \IndieWeb\http_rels($headers);
    return $rels;
  }

}
