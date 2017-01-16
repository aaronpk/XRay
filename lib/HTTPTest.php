<?php
namespace p3k;

class HTTPTest extends HTTPCurl {

  private $_testDataPath;
  private $_redirects_remaining;

  public function __construct($testDataPath) {
    $this->_testDataPath = $testDataPath;
  }

  public function get($url, $headers=[]) {
    $this->_redirects_remaining = $this->max_redirects;
    $parts = parse_url($url);
    unset($parts['fragment']);
    $url = \build_url($parts);
    return $this->_read_file($url);
  }

  public function post($url, $body, $headers=[]) {
    return $this->_read_file($url);
  }

  public function head($url) {
    $response = $this->_read_file($url);
    return array(
      'code' => $response['code'],
      'headers' => $response['headers'],
      'error' => '',
      'error_description' => '',
      'url' => $response['url']
    );
  }

  private function _read_file($url) {
    $parts = parse_url($url);
    if($parts['path']) {
      $parts['path'] = '/'.str_replace('/','_',substr($parts['path'],1));
      $url = \build_url($parts);
    }

    $filename = $this->_testDataPath.preg_replace('/https?:\/\//', '', $url);
    if(!file_exists($filename)) {
      $filename = $this->_testDataPath.'404.response.txt';
    }
    $response = file_get_contents($filename);

    $split = explode("\r\n\r\n", $response);
    if(count($split) < 2) {
      throw new \Exception("Invalid file contents in test data, check that newlines are CRLF: $url");
    }
    $headers = array_shift($split);
    $body = implode("\r\n", $split);

    if(preg_match('/HTTP\/1\.1 (\d+)/', $headers, $match)) {
      $code = $match[1];
    }

    $headers = preg_replace('/HTTP\/1\.1 \d+ .+/', '', $headers);
    $parsedHeaders = self::parse_headers($headers);

    if(array_key_exists('Location', $parsedHeaders)) {
      $effectiveUrl = \mf2\resolveUrl($url, $parsedHeaders['Location']);
      if($this->_redirects_remaining > 0) {
        $this->_redirects_remaining--;
        return $this->_read_file($effectiveUrl);
      } else {
        return [
          'code' => 0,
          'headers' => $parsedHeaders,
          'body' => $body,
          'error' => 'too_many_redirects',
          'error_description' => '',
          'url' => $effectiveUrl
        ];
      }
    } else {
      $effectiveUrl = $url;
    }

    return array(
      'code' => $code,
      'headers' => $parsedHeaders,
      'body' => $body,
      'error' => (isset($parsedHeaders['X-Test-Error']) ? $parsedHeaders['X-Test-Error'] : ''),
      'error_description' => '',
      'url' => $effectiveUrl
    );
  }

}
