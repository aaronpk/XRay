<?php
class HelpersTest extends PHPUnit_Framework_TestCase {

  public function testLowercaseHostname() {
    $url = 'http://Example.com/';
    $result = normalize_url($url);
    $this->assertEquals('http://example.com/', $result);
  }

  public function testAddsSlashToBareDomain() {
    $url = 'http://example.com';
    $result = normalize_url($url);
    $this->assertEquals('http://example.com/', $result);
  }

}
