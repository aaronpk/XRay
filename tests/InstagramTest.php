<?php
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class InstagramTest extends PHPUnit_Framework_TestCase {

  private $http;

  public function setUp() {
    $this->client = new Parse();
    $this->client->http = new p3k\HTTPTest(dirname(__FILE__).'/data/');
    $this->client->mc = null;
  }

  private function parse($params) {
    $request = new Request($params);
    $response = new Response();
    return $this->client->parse($request, $response);
  }

  public function testInstagramPhoto() {
    $url = 'http://www.instagram.com/photo.html';
    $response = $this->parse(['url' => $url]);

    $body = $response->getContent();
    $this->assertEquals(200, $response->getStatusCode());
    $data = json_decode($body, true);

    $this->assertEquals('entry', $data['data']['type']);
    $this->assertEquals('2017-01-05T23:31:32+00:00', $data['data']['published']);
    $this->assertContains('planning', $data['data']['category']);
    $this->assertContains('2017', $data['data']['category']);
    $this->assertEquals('Kind of crazy to see the whole year laid out like this. #planning #2017', $data['data']['content']['text']);
    $this->assertEquals(1, count($data['data']['photo']));
    $this->assertEquals(['https://scontent.cdninstagram.com/t51.2885-15/e35/15803256_1832278043695907_4846092951052353536_n.jpg?ig_cache_key=MTQyMTM1Nzk0NTMwNTEwMDkwNg%3D%3D.2'], $data['data']['photo']);
    $this->assertEquals('http://aaronparecki.com/', $data['data']['author']['url']);
    $this->assertEquals('Aaron Parecki', $data['data']['author']['name']);
    $this->assertEquals('https://scontent.cdninstagram.com/t51.2885-19/s320x320/14240576_268350536897085_1129715662_a.jpg', $data['data']['author']['photo']);
  }

  public function testInstagramVideo() {
    $url = 'http://www.instagram.com/video.html';
    $response = $this->parse(['url' => $url]);

    $body = $response->getContent();
    $this->assertEquals(200, $response->getStatusCode());
    $data = json_decode($body, true);

    $this->assertEquals('entry', $data['data']['type']);
    $this->assertContains('100daysofmusic', $data['data']['category']);
    $this->assertEquals('Day 18. Maple and Spruce #100daysofmusic #100daysproject #the100dayproject https://aaronparecki.com/2017/01/07/14/day18', $data['data']['content']['text']);
    $this->assertEquals(1, count($data['data']['photo']));
    $this->assertEquals(['https://scontent.cdninstagram.com/t51.2885-15/s640x640/e15/15624670_548881701986735_8264383763249627136_n.jpg?ig_cache_key=MTQyMjkzMTczMTg0MjE3NjE3Nw%3D%3D.2'], $data['data']['photo']);
    $this->assertEquals(1, count($data['data']['video']));
    $this->assertEquals(['https://scontent.cdninstagram.com/t50.2886-16/15921147_1074837002642259_2269307616507199488_n.mp4'], $data['data']['video']);
    $this->assertEquals('http://aaronparecki.com/', $data['data']['author']['url']);
    $this->assertEquals('Aaron Parecki', $data['data']['author']['name']);
    $this->assertEquals('https://scontent.cdninstagram.com/t51.2885-19/s320x320/14240576_268350536897085_1129715662_a.jpg', $data['data']['author']['photo']);
  }

  public function testInstagramPhotoWithPersonTag() {
    $url = 'http://www.instagram.com/photo_with_person_tag.html';
    $response = $this->parse(['url' => $url]);

    $body = $response->getContent();
    $this->assertEquals(200, $response->getStatusCode());
    $data = json_decode($body, true);

    $this->assertEquals(2, count($data['data']['category']));
    $this->assertContains('https://kmikeym.com/', $data['data']['category']);
    $this->assertArrayHasKey('https://kmikeym.com/', $data['refs']);
    $this->assertEquals(['type'=>'card','name'=>'Mike Merrill','url'=>'https://kmikeym.com/','photo'=>'https://scontent.cdninstagram.com/t51.2885-19/s320x320/12627953_686238411518831_1544976311_a.jpg'], $data['refs']['https://kmikeym.com/']);
  }

  public function testInstagramPhotoWithVenue() {
    $url = 'http://www.instagram.com/photo_with_venue.html';
    $response = $this->parse(['url' => $url]);

    $body = $response->getContent();
    $this->assertEquals(200, $response->getStatusCode());
    $data = json_decode($body, true);

    $this->assertEquals(1, count($data['data']['location']));
    $this->assertContains('https://www.instagram.com/explore/locations/109284789535230/', $data['data']['location']);
    $this->assertArrayHasKey('https://www.instagram.com/explore/locations/109284789535230/', $data['refs']);
    $venue = $data['refs']['https://www.instagram.com/explore/locations/109284789535230/'];
    $this->assertEquals('XOXO Outpost', $venue['name']);
    $this->assertEquals('45.5261002', $venue['latitude']);
    $this->assertEquals('-122.6558081', $venue['longitude']);
    // Setting a venue should set the timezone
    $this->assertEquals('2016-12-10T21:48:56-08:00', $data['data']['published']);
  }

}
