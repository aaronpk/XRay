<?php
namespace p3k;

class HTTPStream {

  public $timeout = 4;
  public $max_redirects = 8;

  public static function exception_error_handler($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) {
      // This error code is not included in error_reporting
      return;
    }
    throw new \ErrorException($message, 0, $severity, $file, $line);
  }

  public function get($url) {
    set_error_handler("p3k\HTTPStream::exception_error_handler");
    $context = $this->_stream_context('GET', $url);
    return $this->_fetch($url, $context);
  }

  public function post($url, $body, $headers=array()) {
    set_error_handler("p3k\HTTPStream::exception_error_handler");
    $context = $this->_stream_context('POST', $url, $body, $headers);
    return $this->_fetch($url, $context);
  }

  public function head($url) {
    set_error_handler("p3k\HTTPStream::exception_error_handler");
    $context = $this->_stream_context('HEAD', $url);
    return $this->_fetch($url, $context);
  }

  private function _fetch($url, $context) {
    $error = false;

    try {
      $body = file_get_contents($url, false, $context);
    } catch(\Exception $e) {
      $body = false;
      $http_response_header = [];
      $description = str_replace('file_get_contents(): ', '', $e->getMessage());
      $code = 'unknown';

      if(preg_match('/getaddrinfo failed/', $description)) {
        $code = 'dns_error';
        $description = str_replace('php_network_getaddresses: ', '', $description);
      }

      if(preg_match('/timed out|request failed/', $description)) {
        $code = 'timeout';
      }

      if(preg_match('/certificate/', $description)) {
        $code = 'ssl_error';
      }

      $error = [
        'description' => $description,
        'code' => $code
      ];
    }

    return array(
      'code' => self::parse_response_code($http_response_header),
      'headers' => self::parse_headers($http_response_header),
      'body' => $body,
      'error' => $error ? $error['code'] : false,
      'error_description' => $error ? $error['description'] : false,
    );
  }

  private function _stream_context($method, $url, $body=false, $headers=[]) {
    $options = [
      'method' => $method,
      'timeout' => $this->timeout,
      'ignore_errors' => true,
    ];

    if($body) {
      $options['content'] = $body;
    }

    if($headers) {
      $options['header'] = $headers;
    }

    // Special-case appspot.com URLs to not follow redirects.
    // https://cloud.google.com/appengine/docs/php/urlfetch/
    if(should_follow_redirects($url)) {
      $options['follow_location'] = 1;
      $options['max_redirects'] = $this->max_redirects;
    } else {
      $options['follow_location'] = 0;
    }

    return stream_context_create(['http' => $options]);
  }

  public static function parse_response_code($headers) {
    // When a response is a redirect, we want to find the last occurrence of the HTTP code
    $code = false;
    foreach($headers as $field) {
      if(preg_match('/HTTP\/\d\.\d (\d+)/', $field, $match)) {
        $code = $match[1];
      }
    }    
    return $code;
  }

  public static function parse_headers($headers) {
    $retVal = array();
    foreach($headers as $field) {
      if(preg_match('/([^:]+): (.+)/m', $field, $match)) {
        $match[1] = preg_replace_callback('/(?<=^|[\x09\x20\x2D])./', function($m) {
          return strtoupper($m[0]);
        }, strtolower(trim($match[1])));
        // If there's already a value set for the header name being returned, turn it into an array and add the new value
        $match[1] = preg_replace_callback('/(?<=^|[\x09\x20\x2D])./', function($m) {
          return strtoupper($m[0]);
        }, strtolower(trim($match[1])));
        if(isset($retVal[$match[1]])) {
          if(!is_array($retVal[$match[1]]))
            $retVal[$match[1]] = array($retVal[$match[1]]);
          $retVal[$match[1]][] = $match[2];
        } else {
          $retVal[$match[1]] = trim($match[2]);
        }
      }
    }
    return $retVal;
  }

}
