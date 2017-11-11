<?php
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class FacebookTest extends PHPUnit_Framework_TestCase {

  private $http;

  public function setUp() {
    $this->client = new Parse();
    $this->client->mc = null;
  }

  private function parse($params) {
    $request = new Request($params);
    $response = new Response();

    $result = $this->client->parse($request, $response);
    $body = $result->getContent();
    $this->assertEquals(200, $result->getStatusCode());
    return json_decode($body, true);
  }

  private function loadFbObject($id) {
    return file_get_contents(dirname(__FILE__).'/data/graph.facebook.com/'.$id.'.json');
  }

  public function testFacebookEventWithHCard() {
    $url = 'https://www.facebook.com/events/446197069049722/';
    $json = $this->loadFbObject('446197069049722');

    $data = $this->parse(['url' => $url, 'body' => $json]);

    $this->assertEquals('event', $data['data']['type']);
    $this->assertEquals('IndieWeb Summit', $data['data']['name']);
    $this->assertEquals('2017-06-24T09:00:00-0700', $data['data']['start']);
    $this->assertEquals('2017-06-25T18:00:00-0700', $data['data']['end']);
    $this->assertContains('The seventh annual gathering for independent web creators of all kinds,', $data['data']['summary']);
    $this->assertEquals('https://facebook.com/332204056925945', $data['data']['location']);

    $card = $data['data']['refs'][0];
    $this->assertEquals('card', $card['type']);
    $this->assertEquals('https://facebook.com/332204056925945', $card['url']);
    $this->assertEquals('Mozilla PDX', $card['name']);
    $this->assertEquals('97209', $card['postal-code']);
    $this->assertEquals('Portland', $card['locality']);
    $this->assertEquals('OR', $card['region']);
    $this->assertEquals('1120 NW Couch St, Ste 320', $card['street-address']);
    $this->assertEquals('United States', $card['country']);
    $this->assertEquals('45.5233192', $card['latitude']);
    $this->assertEquals('-122.6824722', $card['longitude']);
  }

  public function testFacebookEvent() {
    $url = 'https://www.facebook.com/events/1596554663924436/';
    $json = $this->loadFbObject('1596554663924436');

    $data = $this->parse(['url' => $url, 'body' => $json]);

    $this->assertEquals('event', $data['data']['type']);
    $this->assertEquals('Homebrew Website Club', $data['data']['name']);
    $this->assertEquals('2015-04-22T19:00:00-0400', $data['data']['start']);
    $this->assertContains('Are you building your own website? Indie reader?', $data['data']['summary']);
    $this->assertEquals('Charging Bull - WeeWork - 25 Broadway, New York, NY 10004', $data['data']['location']);
  }

}
