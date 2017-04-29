<?php
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class FeedTest extends PHPUnit_Framework_TestCase {

  private $http;

  public function setUp() {
    $this->client = new Parse();
    $this->client->http = new p3k\HTTP\Test(dirname(__FILE__).'/data/');
    $this->client->mc = null;
  }

  private function parse($params) {
    $request = new Request($params);
    $response = new Response();
    return $this->client->parse($request, $response);
  }

  public function testListOfHEntrys() {
    $url = 'http://feed.example.com/list-of-hentrys';
    $response = $this->parse(['url' => $url]);

    $body = $response->getContent();
    $this->assertEquals(200, $response->getStatusCode());
    $data = json_decode($body);

    $this->assertEquals('feed', $data->data->type);
  }

  public function testListOfHEntrysWithHCard() {
    $url = 'http://feed.example.com/list-of-hentrys-with-h-card';
    $response = $this->parse(['url' => $url]);

    $body = $response->getContent();
    $this->assertEquals(200, $response->getStatusCode());
    $data = json_decode($body);

    $this->assertEquals('feed', $data->data->type);
  }

  public function testShortListOfHEntrysWithHCard() {
    $url = 'http://feed.example.com/short-list-of-hentrys-with-h-card';
    $response = $this->parse(['url' => $url]);

    $body = $response->getContent();
    $this->assertEquals(200, $response->getStatusCode());
    $data = json_decode($body);

    $this->assertEquals('entry', $data->data->type);
  }

  public function testTopLevelHFeed() {
    $url = 'http://feed.example.com/top-level-h-feed';
    $response = $this->parse(['url' => $url]);

    $body = $response->getContent();
    $this->assertEquals(200, $response->getStatusCode());
    $data = json_decode($body);

    $this->assertEquals('feed', $data->data->type);
  }

  public function testHCardWithChildHEntrys() {
    $url = 'http://feed.example.com/h-card-with-child-h-entrys';
    $response = $this->parse(['url' => $url]);

    $body = $response->getContent();
    $this->assertEquals(200, $response->getStatusCode());
    $data = json_decode($body);

    $this->assertEquals('card', $data->data->type);
  }

  public function testHCardWithChildHFeed() {
    $url = 'http://feed.example.com/h-card-with-child-h-feed';
    $response = $this->parse(['url' => $url]);

    $body = $response->getContent();
    $this->assertEquals(200, $response->getStatusCode());
    $data = json_decode($body);

    $this->assertEquals('card', $data->data->type);
  }

}