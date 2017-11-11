<?php
class HelpersTest extends PHPUnit_Framework_TestCase {

  public function testLowercaseHostname() {
    $url = 'http://Example.com/';
    $result = p3k\XRay\normalize_url($url);
    $this->assertEquals('http://example.com/', $result);
  }

  public function testAddsSlashToBareDomain() {
    $url = 'http://example.com';
    $result = p3k\XRay\normalize_url($url);
    $this->assertEquals('http://example.com/', $result);
  }

  public function testDoesNotModify() {
    $url = 'https://example.com/';
    $result = p3k\XRay\normalize_url($url);
    $this->assertEquals('https://example.com/', $result);
  }

  public function testURLEquality() {
    $url1 = 'https://example.com/';
    $url2 = 'https://example.com';
    $result = p3k\XRay\urls_are_equal($url1, $url2);
  }

}
