<?php
namespace p3k;

class HTTPTest extends HTTPCurl {

  private $_testDataPath;

  public function __construct($testDataPath) {
    $this->_testDataPath = $testDataPath;
  }

  public function get($url, $headers=[]) {
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
