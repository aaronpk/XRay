<?php
namespace p3k;

class HTTP {

  public $timeout = 4;
  public $max_redirects = 8;

  public function get($url) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_MAXREDIRS, $this->max_redirects);
    curl_setopt($ch, CURLOPT_TIMEOUT_MS, round($this->timeout * 1000));
    $response = curl_exec($ch);
    $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    return array(
      'code' => curl_getinfo($ch, CURLINFO_HTTP_CODE),
      'headers' => self::parse_headers(trim(substr($response, 0, $header_size))),
      'body' => substr($response, $header_size),
      'error' => self::error_string_from_code(curl_errno($ch)),
      'error_description' => curl_error($ch),
      'error_code' => curl_errno($ch),
    );
  }

  public function post($url, $body, $headers=array()) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT_MS, round($this->timeout * 1000));
    $response = curl_exec($ch);
    $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    return array(
      'code' => curl_getinfo($ch, CURLINFO_HTTP_CODE),
      'headers' => self::parse_headers(trim(substr($response, 0, $header_size))),
      'body' => substr($response, $header_size),
      'error' => self::error_string_from_code(curl_errno($ch)),
      'error_description' => curl_error($ch),
      'error_code' => curl_errno($ch),
    );
  }

  public function head($url) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_NOBODY, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_MAXREDIRS, $this->max_redirects);
    curl_setopt($ch, CURLOPT_TIMEOUT_MS, round($this->timeout * 1000));
    $response = curl_exec($ch);
    return array(
      'code' => curl_getinfo($ch, CURLINFO_HTTP_CODE),
      'headers' => self::parse_headers(trim($response)),
      'error' => self::error_string_from_code(curl_errno($ch)),
      'error_description' => curl_error($ch),
      'error_code' => curl_errno($ch),
    );
  }

  public static function error_string_from_code($code) {
    switch($code) {
      case 0:
        return '';
      case CURLE_COULDNT_RESOLVE_HOST:
        return 'dns_error';
      case CURLE_COULDNT_CONNECT:
        return 'connect_error';
      case CURLE_OPERATION_TIMEDOUT:
        return 'timeout';
      case CURLE_SSL_CONNECT_ERROR:
        return 'ssl_error';
      case CURLE_SSL_CERTPROBLEM:
        return 'ssl_cert_error';
      case CURLE_SSL_CIPHER:
        return 'ssl_unsupported_cipher';
      case CURLE_SSL_CACERT:
        return 'ssl_cert_error';
      case CURLE_TOO_MANY_REDIRECTS:
        return 'too_many_redirects';
      default:
        return 'unknown';
    }
  }

  public static function parse_headers($headers) {
    $retVal = array();
    $fields = explode("\r\n", preg_replace('/\x0D\x0A[\x09\x20]+/', ' ', $headers));
    foreach($fields as $field) {
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
