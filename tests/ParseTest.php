<?php
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class ParseTest extends PHPUnit_Framework_TestCase {

  private $http;

  public function setUp() {
    $this->client = new Parse();
    $this->client->http = new p3k\HTTPTest(dirname(__FILE__).'/data/');
  }

  private function parse($params) {
    $request = new Request($params);
    $response = new Response();
    return $this->client->parse($request, $response);
  }

  public function testMissingURL() {
    $response = $this->parse([]);

    $body = $response->getContent();
    $this->assertEquals(400, $response->getStatusCode());
    $data = json_decode($body);
    $this->assertEquals('error', $data->type);
    $this->assertEquals('missing_url', $data->error);
  }

  public function testInvalidURL() {
    $url = 'ftp://example.com/foo';
    $response = $this->parse(['url' => $url]);

    $body = $response->getContent();
    $this->assertEquals(400, $response->getStatusCode());
    $data = json_decode($body);
    $this->assertEquals('error', $data->type);
    $this->assertEquals('invalid_url', $data->error);
  }

  public function testTargetNotFound() {
    $url = 'http://source.example.com/baseictest';
    $response = $this->parse(['url' => $url, 'target' => 'http://example.net']);

    $body = $response->getContent();
    $this->assertEquals(400, $response->getStatusCode());
    $data = json_decode($body);
    $this->assertEquals('error', $data->type);
    $this->assertEquals('no_link_found', $data->error);
  }

  public function testTargetFound() {
    $url = 'http://source.example.com/basictest';
    $response = $this->parse(['url' => $url, 'target' => 'http://target.example.com']);

    $body = $response->getContent();
    $this->assertEquals(200, $response->getStatusCode());
    $data = json_decode($body);
    $this->assertNotEquals('error', $data->type);
    $this->assertObjectNotHasAttribute('error', $data);
  }

}