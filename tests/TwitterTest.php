<?php
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class TwitterTest extends PHPUnit_Framework_TestCase {

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

  private function loadTweet($id) {
    $url = 'https://twitter.com/_/status/'.$id;
    $json = file_get_contents(dirname(__FILE__).'/data/api.twitter.com/'.$id.'.json');
    $parsed = json_decode($json);
    $url = 'https://twitter.com/'.$parsed->user->screen_name.'/status/'.$id;
    return [$url, $json];
  }

  public function testBasicProfileInfo() {
    list($url, $json) = $this->loadTweet('818912506496229376');

    $data = $this->parse(['url' => $url, 'body' => $json]);

    $this->assertEquals('entry', $data['data']['type']);
    $this->assertEquals('aaronpk dev', $data['data']['author']['name']);
    $this->assertEquals('pkdev', $data['data']['author']['nickname']);
    $this->assertEquals('https://aaronparecki.com/', $data['data']['author']['url']);
    $this->assertEquals('Portland, OR', $data['data']['author']['location']);
    $this->assertEquals('Dev account for testing Twitter things. Follow me here: https://twitter.com/aaronpk', $data['data']['author']['bio']);
    $this->assertEquals('https://pbs.twimg.com/profile_images/638125135904436224/qd_d94Qn_normal.jpg', $data['data']['author']['photo']);
  }

  public function testProfileWithNonExpandedURL() {
    list($url, $json) = $this->loadTweet('791704641046052864');

    $data = $this->parse(['url' => $url, 'body' => $json]);

    $this->assertEquals('http://agiletortoise.com', $data['data']['author']['url']);
  }

  public function testBasicTestStuff() {
    list($url, $json) = $this->loadTweet('818913630569664512');

    $data = $this->parse(['url' => $url, 'body' => $json]);

    $this->assertEquals(null, $data['code']); // no code is expected if we pass in the body
    $this->assertEquals('https://twitter.com/pkdev/status/818913630569664512', $data['url']);
    $this->assertEquals('entry', $data['data']['type']);
    $this->assertEquals('A tweet with a URL https://indieweb.org/ #and #some #hashtags', $data['data']['content']['text']);
    $this->assertContains('and', $data['data']['category']);
    $this->assertContains('some', $data['data']['category']);
    $this->assertContains('hashtags', $data['data']['category']);
    // Published date should be set to the timezone of the user
    $this->assertEquals('2017-01-10T12:13:18-08:00', $data['data']['published']);
  }

  public function testPositiveTimezone() {
    list($url, $json) = $this->loadTweet('719914707566649344');

    $data = $this->parse(['url' => $url, 'body' => $json]);
    $this->assertEquals("2016-04-12T16:46:56+01:00", $data['data']['published']);
  }

  public function testTweetWithEmoji() {
    list($url, $json) = $this->loadTweet('818943244553699328');

    $data = $this->parse(['url' => $url, 'body' => $json]);

    $this->assertEquals('entry', $data['data']['type']);
    $this->assertEquals('Here 🎉 have an emoji', $data['data']['content']['text']);
  }

  public function testHTMLEscaping() {
    list($url, $json) = $this->loadTweet('818928092383166465');

    $data = $this->parse(['url' => $url, 'body' => $json]);

    $this->assertEquals('entry', $data['data']['type']);
    $this->assertEquals('Double escaping &amp; & amp', $data['data']['content']['text']);
  }

  public function testTweetWithPhoto() {
    list($url, $json) = $this->loadTweet('818912506496229376');

    $data = $this->parse(['url' => $url, 'body' => $json]);

    $this->assertEquals('entry', $data['data']['type']);
    $this->assertEquals('Tweet with a photo and a location', $data['data']['content']['text']);
    $this->assertEquals('https://pbs.twimg.com/media/C11cfRJUoAI26h9.jpg', $data['data']['photo'][0]);
  }

  public function testTweetWithTwoPhotos() {
    list($url, $json) = $this->loadTweet('818935308813103104');

    $data = $this->parse(['url' => $url, 'body' => $json]);

    $this->assertEquals('entry', $data['data']['type']);
    $this->assertEquals('Two photos', $data['data']['content']['text']);
    $this->assertContains('https://pbs.twimg.com/media/C11xS1wUcAAeaKF.jpg', $data['data']['photo']);
    $this->assertContains('https://pbs.twimg.com/media/C11wtndUoAE1WfE.jpg', $data['data']['photo']);
  }

  public function testTweetWithVideo() {
    list($url, $json) = $this->loadTweet('818913178260160512');

    $data = $this->parse(['url' => $url, 'body' => $json]);

    $this->assertEquals('entry', $data['data']['type']);
    $this->assertEquals('Tweet with a video', $data['data']['content']['text']);
    $this->assertEquals('https://video.twimg.com/ext_tw_video/818913089248595970/pr/vid/1280x720/qP-sDx-Q0Hs-ckVv.mp4', $data['data']['video'][0]);
  }

  public function testTweetWithLocation() {
    list($url, $json) = $this->loadTweet('818912506496229376');

    $data = $this->parse(['url' => $url, 'body' => $json]);

    $this->assertEquals('entry', $data['data']['type']);
    $this->assertEquals('Tweet with a photo and a location', $data['data']['content']['text']);
    $this->assertEquals('https://api.twitter.com/1.1/geo/id/ac88a4f17a51c7fc.json', $data['data']['location']);
    $location = $data['data']['refs']['https://api.twitter.com/1.1/geo/id/ac88a4f17a51c7fc.json'];
    $this->assertEquals('adr', $location['type']);
    $this->assertEquals('Portland', $location['locality']);
    $this->assertEquals('United States', $location['country-name']);
    $this->assertEquals('Portland, OR', $location['name']);
  }

  public function testRetweet() {
    list($url, $json) = $this->loadTweet('818913351623245824');

    $data = $this->parse(['url' => $url, 'body' => $json]);

    $this->assertEquals('entry', $data['data']['type']);
    $this->assertArrayNotHasKey('content', $data['data']);
    $repostOf = 'https://twitter.com/aaronpk/status/817414679131660288';
    $this->assertEquals($repostOf, $data['data']['repost-of']);
    $tweet = $data['data']['refs'][$repostOf];
    $this->assertEquals('Yeah that\'s me http://xkcd.com/1782/', $tweet['content']['text']);
  }

  public function testRetweetWithPhoto() {
    list($url, $json) = $this->loadTweet('820039442773798912');

    $data = $this->parse(['url' => $url, 'body' => $json]);

    $this->assertEquals('entry', $data['data']['type']);
    $this->assertArrayNotHasKey('content', $data['data']);
    $this->assertArrayNotHasKey('photo', $data['data']);
    $repostOf = 'https://twitter.com/phlaimeaux/status/819943954724556800';
    $this->assertEquals($repostOf, $data['data']['repost-of']);
    $tweet = $data['data']['refs'][$repostOf];
    $this->assertEquals('this headline is such a rollercoaster', $tweet['content']['text']);
  }

  public function testQuotedTweet() {
    list($url, $json) = $this->loadTweet('818913488609251331');

    $data = $this->parse(['url' => $url, 'body' => $json]);

    $this->assertEquals('entry', $data['data']['type']);
    $this->assertEquals('Quoted tweet with a #hashtag https://twitter.com/aaronpk/status/817414679131660288', $data['data']['content']['text']);
    $this->assertEquals('https://twitter.com/aaronpk/status/817414679131660288', $data['data']['quotation-of']);
    $tweet = $data['data']['refs']['https://twitter.com/aaronpk/status/817414679131660288'];
    $this->assertEquals('Yeah that\'s me http://xkcd.com/1782/', $tweet['content']['text']);
  }

}
