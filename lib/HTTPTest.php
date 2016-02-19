<?php
namespace p3k;

class HTTPTest extends HTTP {

  private $_testDataPath;

  public function __construct($testDataPath) {
    $this->_testDataPath = $testDataPath;
  }

  public function get($url) {
    return $this->_read_file($url);
  }

  public function post($url, $body, $headers=array()) {
    return $this->_read_file($url);
  }

  public function head($url) {
    $response = $this->_read_file($url);
    return array(
      'code' => $response['code'],
      'headers' => $response['headers'],
      'error' => '',
      'error_description' => ''
    );
  }

  private function _read_file($url) {
    $filename = $this->_testDataPath.preg_replace('/https?:\/\//', '', $url);
    if(!file_exists($filename)) {
      $filename = $this->_testDataPath.'404.response.txt';
    }
    $response = file_get_contents($filename);

    $split = explode("\r\n\r\n", $response);
    if(count($split) != 2) {
      throw new \Exception("Invalid file contents in test data, check that newlines are CRLF: $url");
    }
    list($headers, $body) = $split;

    if(preg_match('/HTTP\/1\.1 (\d+)/', $headers, $match)) {
      $code = $match[1];
    }

    $headers = preg_replace('/HTTP\/1\.1 \d+ .+/', '', $headers);

    return array(
      'code' => $code,
      'headers' => self::parse_headers($headers),
      'body' => $body,
      'error' => '',
      'error_description' => ''
    );
  }

}
