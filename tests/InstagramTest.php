<?php
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class InstagramTest extends PHPUnit_Framework_TestCase {

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

  public function testInstagramPhoto() {
    // Original URL: https://www.instagram.com/p/BO5rYVElvJq/
    $url = 'https://www.instagram.com/p/BO5rYVElvJq/';
    $response = $this->parse(['url' => $url]);

    $body = $response->getContent();
    $this->assertEquals(200, $response->getStatusCode());
    $data = json_decode($body, true);

    $this->assertEquals(200, $data['code']);
    $this->assertEquals('instagram', $data['source-format']);
    $this->assertEquals('entry', $data['data']['type']);
    $this->assertEquals('photo', $data['data']['post-type']);
    $this->assertEquals('2017-01-05T23:31:32+00:00', $data['data']['published']);
    $this->assertContains('planning', $data['data']['category']);
    $this->assertContains('2017', $data['data']['category']);
    $this->assertEquals('Kind of crazy to see the whole year laid out like this. #planning #2017', $data['data']['content']['text']);
    $this->assertEquals(1, count($data['data']['photo']));
    $this->assertEquals(['https://instagram.fsea1-1.fna.fbcdn.net/vp/214e719b6026ef54e0545f2ed70d4c83/5B56795F/t51.2885-15/e35/15803256_1832278043695907_4846092951052353536_n.jpg'], $data['data']['photo']);
    $this->assertEquals('https://aaronparecki.com/', $data['data']['author']['url']);
    $this->assertEquals('Aaron Parecki', $data['data']['author']['name']);
    $this->assertEquals('https://instagram.fsea1-1.fna.fbcdn.net/vp/0dc6166cbd4ec6782453d36cd07fec06/5B67568E/t51.2885-19/s320x320/14240576_268350536897085_1129715662_a.jpg', $data['data']['author']['photo']);
  }

  public function testBGDpqNoiMJ0() {
    // https://www.instagram.com/p/BGDpqNoiMJ0/
    $url = 'http://www.instagram.com/BGDpqNoiMJ0';
    $response = $this->parse(['url' => $url]);

    $body = $response->getContent();
    $this->assertEquals(200, $response->getStatusCode());
    $data = json_decode($body, true);

    $this->assertEquals(200, $data['code']);
    $this->assertEquals('instagram', $data['source-format']);

    $this->assertEquals('entry', $data['data']['type']);
    $this->assertEquals('photo', $data['data']['post-type']);
    $this->assertSame([
      'type' => 'card',
      'name' => 'pk_spam',
      'url' => 'https://aaronparecki.com/',
      'photo' => 'https://instagram.fhel2-1.fna.fbcdn.net/vp/f17e1275a70fc32e93cbf434ddc32bcd/5B6CCC7A/t51.2885-19/11906329_960233084022564_1448528159_a.jpg',
      'note' => 'My website is https://aaronparecki.com.dev/ and http://aaronpk.micro.blog/about/ and https://tiny.xyz.dev/'
    ], $data['data']['author']);

    $this->assertSame([
      'muffins',
      'https://indiewebcat.com/'
    ], $data['data']['category']);

    $this->assertEquals('Meow #muffins', $data['data']['content']['text']);
    $this->assertSame(['https://instagram.fsea1-1.fna.fbcdn.net/vp/9433ea494a8b055bebabf70fd81cfa32/5B51F092/t51.2885-15/e35/13266755_877794672348882_1908663476_n.jpg'], $data['data']['photo']);
    $this->assertEquals('2016-05-30T20:46:22-07:00', $data['data']['published']);

    $this->assertEquals('https://www.instagram.com/explore/locations/359000003/', $data['data']['location'][0]);

    $this->assertSame([
      'type' => 'card',
      'name' => 'Burnside 26',
      'url' => 'https://www.instagram.com/explore/locations/359000003/',
      'latitude' => 45.5228640678,
      'longitude' => -122.6389405085
    ], $data['data']['refs']['https://www.instagram.com/explore/locations/359000003/']);
  }

  public function testInstagramVideo() {
    // Original URL: https://www.instagram.com/p/BO_RN8AFZSx/
    $url = 'https://www.instagram.com/p/BO_RN8AFZSx/';
    $response = $this->parse(['url' => $url]);

    $body = $response->getContent();
    $this->assertEquals(200, $response->getStatusCode());
    $data = json_decode($body, true);

    $this->assertEquals(200, $data['code']);
    $this->assertEquals('instagram', $data['source-format']);

    $this->assertEquals('entry', $data['data']['type']);
    $this->assertEquals('video', $data['data']['post-type']);
    $this->assertContains('100daysofmusic', $data['data']['category']);
    $this->assertEquals('Day 18. Maple and Spruce #100daysofmusic #100daysproject #the100dayproject https://aaronparecki.com/2017/01/07/14/day18', $data['data']['content']['text']);
    $this->assertEquals(1, count($data['data']['photo']));
    $this->assertEquals(['https://instagram.fsea1-1.fna.fbcdn.net/vp/32890db04701c4ab4fa7da05a6e9de93/5ADB9BDF/t51.2885-15/e15/15624670_548881701986735_8264383763249627136_n.jpg'], $data['data']['photo']);
    $this->assertEquals(1, count($data['data']['video']));
    $this->assertEquals(['https://instagram.fsea1-1.fna.fbcdn.net/vp/46c7118509146b978fb7bfc497eeb16f/5ADB639E/t50.2886-16/15921147_1074837002642259_2269307616507199488_n.mp4'], $data['data']['video']);
    $this->assertEquals('https://aaronparecki.com/', $data['data']['author']['url']);
    $this->assertEquals('Aaron Parecki', $data['data']['author']['name']);
    $this->assertEquals('https://instagram.fsea1-1.fna.fbcdn.net/vp/0dc6166cbd4ec6782453d36cd07fec06/5B67568E/t51.2885-19/s320x320/14240576_268350536897085_1129715662_a.jpg', $data['data']['author']['photo']);
  }

  public function testInstagramPhotoWithPersonTag() {
    // Original URL: https://www.instagram.com/p/BNfqVfVlmkj/
    $url = 'https://www.instagram.com/p/BNfqVfVlmkj/';
    $response = $this->parse(['url' => $url]);

    $body = $response->getContent();
    $this->assertEquals(200, $response->getStatusCode());
    $data = json_decode($body, true);

    $this->assertEquals(200, $data['code']);
    $this->assertEquals('instagram', $data['source-format']);

    $this->assertEquals(2, count($data['data']['category']));
    $this->assertContains('http://www.kmikeym.com/', $data['data']['category']);
    $this->assertArrayHasKey('http://www.kmikeym.com/', $data['data']['refs']);
    $this->assertEquals(['type'=>'card','name'=>'Mike Merrill','url'=>'http://www.kmikeym.com/','photo'=>'https://instagram.fsea1-1.fna.fbcdn.net/vp/dea521b3000a53d2d9a6845f5b066256/5B66D2FC/t51.2885-19/s320x320/20634957_814691788710973_2275383796935163904_a.jpg','note'=>'The world’s first Publicly Traded Person and working on things at @sandwichvideo'], $data['data']['refs']['http://www.kmikeym.com/']);
  }

  public function testInstagramPhotoWithVenue() {
    // Original URL: https://www.instagram.com/p/BN3Z5salSys/
    $url = 'https://www.instagram.com/p/BN3Z5salSys/';
    $response = $this->parse(['url' => $url]);

    $body = $response->getContent();
    $this->assertEquals(200, $response->getStatusCode());
    $data = json_decode($body, true);

    $this->assertEquals(200, $data['code']);
    $this->assertEquals('instagram', $data['source-format']);

    $this->assertEquals(1, count($data['data']['location']));
    $this->assertContains('https://www.instagram.com/explore/locations/109284789535230/', $data['data']['location']);
    $this->assertArrayHasKey('https://www.instagram.com/explore/locations/109284789535230/', $data['data']['refs']);
    $venue = $data['data']['refs']['https://www.instagram.com/explore/locations/109284789535230/'];
    $this->assertEquals('XOXO Outpost', $venue['name']);
    $this->assertEquals('45.5261002', $venue['latitude']);
    $this->assertEquals('-122.6558081', $venue['longitude']);
    // Setting a venue should set the timezone
    $this->assertEquals('2016-12-10T21:48:56-08:00', $data['data']['published']);
  }

  public function testTwoPhotos() {
    // Original URL: https://www.instagram.com/p/BZWmUB_DVtp/
    $url = 'https://www.instagram.com/p/BZWmUB_DVtp/';
    $response = $this->parse(['url' => $url]);

    $body = $response->getContent();
    $this->assertEquals(200, $response->getStatusCode());
    $data = json_decode($body, true);

    $this->assertEquals(200, $data['code']);
    $this->assertEquals('instagram', $data['source-format']);

    $this->assertEquals(2, count($data['data']['photo']));
    $this->assertEquals('https://instagram.fsea1-1.fna.fbcdn.net/vp/406101ff9601ab78147e121b65ce3eea/5B5BC738/t51.2885-15/e35/21827424_134752690591737_8093088291252862976_n.jpg', $data['data']['photo'][0]);
    $this->assertEquals('https://instagram.fsea1-1.fna.fbcdn.net/vp/03ddc8c03c8708439dae29663b8c2305/5B5EDE4D/t51.2885-15/e35/21909774_347707439021016_5237540582556958720_n.jpg', $data['data']['photo'][1]);
    $this->assertArrayNotHasKey('video', $data['data']);
    $this->assertEquals(2, count($data['data']['category']));
  }

  public function testMixPhotosAndVideos() {
    // Original URL: https://www.instagram.com/p/BZWmpecjBwN/
    $url = 'https://www.instagram.com/p/BZWmpecjBwN/';
    $response = $this->parse(['url' => $url]);

    $body = $response->getContent();
    $this->assertEquals(200, $response->getStatusCode());
    $data = json_decode($body, true);

    $this->assertEquals(200, $data['code']);
    $this->assertEquals('instagram', $data['source-format']);

    $this->assertEquals('photo', $data['data']['post-type']); // we discard videos in this case right now
    $this->assertEquals(3, count($data['data']['photo']));
    $this->assertEquals('https://instagram.fsea1-1.fna.fbcdn.net/vp/b0f6cd9dc4d5c3371efe9f412a0d7f0b/5B6BC5B8/t51.2885-15/e35/21878922_686481254874005_8468823712617988096_n.jpg', $data['data']['photo'][0]);
    $this->assertEquals('https://instagram.fsea1-1.fna.fbcdn.net/vp/f8939cca504f97931fd4768b77d2c152/5ADB3CC9/t51.2885-15/e15/21910026_1507234999368159_6974261907783942144_n.jpg', $data['data']['photo'][1]);
    $this->assertEquals('https://instagram.fsea1-1.fna.fbcdn.net/vp/254c313bdcac37c19da5e10be8222a88/5B689788/t51.2885-15/e35/21878800_273567963151023_7672178549897297920_n.jpg', $data['data']['photo'][2]);
    $this->assertArrayNotHasKey('video', $data['data']);
    $this->assertEquals(2, count($data['data']['category']));
  }

  public function testInstagramProfile() {
    $url = 'https://www.instagram.com/aaronpk/';
    $response = $this->parse(['url' => $url]);

    $body = $response->getContent();
    $this->assertEquals(200, $response->getStatusCode());
    $data = json_decode($body, true);

    $this->assertEquals(200, $data['code']);
    $this->assertEquals('instagram', $data['source-format']);

    $this->assertSame([
      'type' => 'card',
      'name' => 'Aaron Parecki',
      'url' => 'https://aaronparecki.com/',
      'photo' => 'https://instagram.fsea1-1.fna.fbcdn.net/vp/0dc6166cbd4ec6782453d36cd07fec06/5B67568E/t51.2885-19/s320x320/14240576_268350536897085_1129715662_a.jpg',
      'note' => '📡 w7apk.com 🔒 oauth.net 🎥 backpedal.tv 🎙 streampdx.com'
    ], $data['data']);
  }

  public function testInstagramProfileWithBio() {
    $url = 'https://www.instagram.com/pk_spam/';
    $response = $this->parse(['url' => $url]);

    $body = $response->getContent();
    $this->assertEquals(200, $response->getStatusCode());
    $data = json_decode($body, true);

    $this->assertEquals(200, $data['code']);
    $this->assertEquals('instagram', $data['source-format']);

    $this->assertSame([
      'type' => 'card',
      'name' => 'pk_spam',
      'url' => 'https://aaronparecki.com/',
      'photo' => 'https://instagram.fhel2-1.fna.fbcdn.net/vp/f17e1275a70fc32e93cbf434ddc32bcd/5B6CCC7A/t51.2885-19/11906329_960233084022564_1448528159_a.jpg',
      'note' => 'My website is https://aaronparecki.com.dev/ and http://aaronpk.micro.blog/about/ and https://tiny.xyz.dev/'
    ], $data['data']);
  }

  public function testInstagramProfileFeed() {
    $url = 'https://www.instagram.com/pk_spam/';
    $response = $this->parse(['url' => $url, 'expect' => 'feed']);

    $body = $response->getContent();
    $this->assertEquals(200, $response->getStatusCode());
    $data = json_decode($body, true);

    $this->assertEquals(200, $data['code']);
    $this->assertEquals('instagram', $data['source-format']);

    $this->assertEquals('feed', $data['data']['type']);
    $this->assertEquals(12, count($data['data']['items']));
    $this->assertEquals('https://www.instagram.com/p/Be0lBpGDncI/', $data['data']['items'][0]['url']);
    $this->assertEquals('https://www.instagram.com/p/BGC8l_ZCMKb/', $data['data']['items'][11]['url']);
  }

}
