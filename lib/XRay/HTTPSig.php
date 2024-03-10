<?php
namespace p3k\XRay;

trait HTTPSig {

  protected function _headersToSigningString($headers) {
    return implode("\n", array_map(function($k, $v){
             return strtolower($k).': '.$v;
           }, array_keys($headers), $headers));
  }
  
  protected function _headersToCurlArray($headers) {
    return array_map(function($k, $v){
             return "$k: $v";
           }, array_keys($headers), $headers);
  }
  
  protected function _digest($payload) {
    return base64_encode(hash('sha256', $payload, true));
  }
  
  protected function _headersToSign($target, $date) {
    $headers = [
      '(request-target)' => 'get '.parse_url($target, PHP_URL_PATH),
      'Date' => $date,
      'Host' => parse_url($target, PHP_URL_HOST),
      'Content-Type' => 'application/activity+json',
    ];
  
    return $headers;
  }
  
  protected function _httpSign(&$headers, $key) {
    $stringToSign = $this->_headersToSigningString($headers);
  
    $signedHeaders = implode(' ', array_map('strtolower', array_keys($headers)));
  
    $privateKey = openssl_pkey_get_private('file://'.$key['key']);
  
    openssl_sign($stringToSign, $signature, $privateKey, OPENSSL_ALGO_SHA256);
    $signature = base64_encode($signature);
  
    $signatureHeader = 'keyId="'.$key['keyId'].'",headers="'.$signedHeaders.'",algorithm="rsa-sha256",signature="'.$signature.'"';
  
    unset($headers['(request-target)']);
  
    $headers['Signature'] = $signatureHeader;
  }
  
}
