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
    $this->assertObjectHasAttribute('error', $data);
    $this->assertEquals('missing_url', $data->error);
  }

  public function testInvalidURL() {
    $url = 'ftp://example.com/foo';
    $response = $this->parse(['url' => $url]);

    $body = $response->getContent();
    $this->assertEquals(400, $response->getStatusCode());
    $data = json_decode($body);
    $this->assertObjectHasAttribute('error', $data);
    $this->assertEquals('invalid_url', $data->error);
  }

  public function testTargetNotFound() {
    $url = 'http://source.example.com/baseictest';
    $response = $this->parse(['url' => $url, 'target' => 'http://example.net']);

    $body = $response->getContent();
    $this->assertEquals(400, $response->getStatusCode());
    $data = json_decode($body);
    $this->assertObjectHasAttribute('error', $data);
    $this->assertEquals('no_link_found', $data->error);
  }

  public function testTargetFound() {
    $url = 'http://source.example.com/basictest';
    $response = $this->parse(['url' => $url, 'target' => 'http://target.example.com']);

    $body = $response->getContent();
    $this->assertEquals(200, $response->getStatusCode());
    $data = json_decode($body);
    $this->assertObjectNotHasAttribute('error', $data);
    $this->assertObjectNotHasAttribute('error', $data);
  }

  public function testHTMLContent() {
    $url = 'http://source.example.com/html-content';
    $response = $this->parse(['url' => $url]);

    $body = $response->getContent();
    $this->assertEquals(200, $response->getStatusCode());
    $data = json_decode($body);
    $this->assertObjectNotHasAttribute('name', $data->data);
    $this->assertEquals('This page has a link to target.example.com and some formatted text.', $data->data->content->text);
    $this->assertEquals('This page has a link to <a href="http://target.example.com">target.example.com</a> and some <b>formatted text</b>.', $data->data->content->html);
  }

  public function testTextContent() {
    $url = 'http://source.example.com/text-content';
    $response = $this->parse(['url' => $url]);

    $body = $response->getContent();
    $this->assertEquals(200, $response->getStatusCode());
    $data = json_decode($body);
    $this->assertObjectNotHasAttribute('name', $data->data);
    $this->assertEquals('This page has a link to target.example.com and some formatted text but is in a p-content element so is plaintext.', $data->data->content->text);
  }

  public function testContentWithPrefixedName() {
    $url = 'http://source.example.com/content-with-prefixed-name';
    $response = $this->parse(['url' => $url]);

    $body = $response->getContent();
    $this->assertEquals(200, $response->getStatusCode());
    $data = json_decode($body);
    $this->assertObjectNotHasAttribute('name', $data->data);
    $this->assertEquals('This page has a link to target.example.com and some formatted text.', $data->data->content->text);
    $this->assertEquals('This page has a link to <a href="http://target.example.com">target.example.com</a> and some <b>formatted text</b>.', $data->data->content->html);
  }

  public function testContentWithDistinctName() {
    $url = 'http://source.example.com/content-with-distinct-name';
    $response = $this->parse(['url' => $url]);

    $body = $response->getContent();
    $this->assertEquals(200, $response->getStatusCode());
    $data = json_decode($body);
    $this->assertEquals('Hello World', $data->data->name);
    $this->assertEquals('This page has a link to target.example.com and some formatted text.', $data->data->content->text);
    $this->assertEquals('This page has a link to <a href="http://target.example.com">target.example.com</a> and some <b>formatted text</b>.', $data->data->content->html);
  }

  public function testNameWithNoContent() {
    $url = 'http://source.example.com/name-no-content';
    $response = $this->parse(['url' => $url]);

    $body = $response->getContent();
    $this->assertEquals(200, $response->getStatusCode());
    $data = json_decode($body);
    $this->assertEquals('Hello World', $data->data->name);
    $this->assertObjectNotHasAttribute('content', $data->data);
  }

}