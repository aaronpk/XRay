<?php
namespace p3k;

class HTTP {

  public $timeout = 4;
  public $max_redirects = 8;

  public function get($url) {
    $class = $this->_class($url);
    $http = new $class($url);
    $http->timeout = $this->timeout;
    $http->max_redirects = $this->max_redirects;
    return $http->get($url);
  }

  public function post($url, $body, $headers=array()) {
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

}
