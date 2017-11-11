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
    $response = $this->parse(['url' => $url, 'expect' => 'feed']);

    $body = $response->getContent();
    $this->assertEquals(200, $response->getStatusCode());
    $data = json_decode($body)->data;

    $this->assertEquals('feed', $data->type);
  }

  public function testListOfHEntrysWithHCard() {
    $url = 'http://feed.example.com/list-of-hentrys-with-h-card';
    $response = $this->parse(['url' => $url, 'expect' => 'feed']);

    $body = $response->getContent();
    $this->assertEquals(200, $response->getStatusCode());
    $data = json_decode($body)->data;

    $this->assertEquals('feed', $data->type);
  }

  public function testShortListOfHEntrysWithHCard() {
    $url = 'http://feed.example.com/short-list-of-hentrys-with-h-card';
    $response = $this->parse(['url' => $url, 'expect' => 'feed']);

    $body = $response->getContent();
    $this->assertEquals(200, $response->getStatusCode());
    $data = json_decode($body)->data;

    $this->assertEquals('feed', $data->type);
  }

  public function testTopLevelHFeed() {
    $url = 'http://feed.example.com/top-level-h-feed';
    $response = $this->parse(['url' => $url, 'expect' => 'feed']);

    $body = $response->getContent();
    $this->assertEquals(200, $response->getStatusCode());
    $data = json_decode($body)->data;

    $this->assertEquals('feed', $data->type);
  }

  public function testHCardWithChildHEntrys() {
    $url = 'http://feed.example.com/h-card-with-child-h-entrys';
    $response = $this->parse(['url' => $url, 'expect' => 'feed']);

    $body = $response->getContent();
    $this->assertEquals(200, $response->getStatusCode());
    $data = json_decode($body)->data;

    $this->assertEquals('feed', $data->type);
  }

  public function testHCardWithChildHFeed() {
    $url = 'http://feed.example.com/h-card-with-child-h-feed';
    $response = $this->parse(['url' => $url, 'expect' => 'feed']);

    $body = $response->getContent();
    $this->assertEquals(200, $response->getStatusCode());
    $data = json_decode($body)->data;

    $this->assertEquals('feed', $data->type);
  }

  public function testAtomFeed() {
    $url = 'http://feed.example.com/atom';
    $response = $this->parse(['url' => $url, 'expect' => 'feed']);

    $body = $response->getContent();
    $this->assertEquals(200, $response->getStatusCode());
    $data = json_decode($body)->data;

    $this->assertEquals(8, count($data->items));
    for($i=0; $i<8; $i++) {
      $this->assertEquals('entry', $data->items[$i]->type);
      $this->assertEquals('Tantek', $data->items[$i]->author->name);
      $this->assertEquals('http://tantek.com/', $data->items[$i]->author->url);
      $this->assertNotEmpty($data->items[$i]->url);
      $this->assertNotEmpty($data->items[$i]->published);
      $this->assertNotEmpty($data->items[$i]->content->html);
      $this->assertNotEmpty($data->items[$i]->content->text);
    }

    $this->assertEquals('2017-11-08T23:53:00-08:00', $data->items[0]->published);
    $this->assertEquals('http://tantek.com/2017/312/t3/tam-trail-run-first-trail-half', $data->items[0]->url);

    $this->assertEquals('went to MORE Pancakes! this morning @RunJanji pop-up on California st after #NPSF. Picked up a new running shirt.', $data->items[1]->content->text);
    $this->assertEquals('went to MORE Pancakes! this morning <a href="https://twitter.com/RunJanji">@RunJanji</a> pop-up on California st after #NPSF. Picked up a new running shirt.', $data->items[1]->content->html);

    $this->assertEquals('feed', $data->type);
  }

  public function testRSSFeed() {
    $url = 'http://feed.example.com/rss';
    $response = $this->parse(['url' => $url, 'expect' => 'feed']);

    $body = $response->getContent();
    $this->assertEquals(200, $response->getStatusCode());
    $data = json_decode($body)->data;

    $this->assertEquals(10, count($data->items));
    for($i=0; $i<10; $i++) {
      $this->assertEquals('entry', $data->items[$i]->type);
      $this->assertEquals('Ryan Barrett', $data->items[$i]->author->name);
      $this->assertEquals('https://snarfed.org/', $data->items[$i]->author->url);
      $this->assertNotEmpty($data->items[$i]->url);
      $this->assertNotEmpty($data->items[$i]->published);
      $this->assertNotEmpty($data->items[$i]->content->html);
      if($i > 1)
        $this->assertNotEmpty($data->items[$i]->content->text);
    }

    $this->assertEquals('2017-09-12T20:09:12+00:00', $data->items[9]->published);
    $this->assertEquals('https://snarfed.org/2017-09-12_25492', $data->items[9]->url);
    $this->assertEquals('<p>new business cards <img src="https://s.w.org/images/core/emoji/2.3/72x72/1f602.png" alt="😂" /></p>
<p><img src="https://i0.wp.com/snarfed.org/w/wp-content/uploads/2017/09/IMG_20170912_131414_767.jpg?w=696&amp;ssl=1" alt="IMG_20170912_131414_767.jpg?w=696&amp;ssl=1" /></p>', $data->items[9]->content->html);
    $this->assertEquals('new business cards', $data->items[9]->content->text);

    $this->assertEquals('feed', $data->type);
  }

  public function testPodcastFeed() {
    $url = 'http://feed.example.com/podcast-rss';
    $response = $this->parse(['url' => $url, 'expect' => 'feed']);

    $body = $response->getContent();
    $this->assertEquals(200, $response->getStatusCode());
    $data = json_decode($body)->data;

    $this->assertEquals(12, count($data->items));
    for($i=0; $i<12; $i++) {
      $this->assertEquals('entry', $data->items[$i]->type);
      $this->assertEquals('Aaron Parecki', $data->items[$i]->author->name);
      $this->assertEquals('https://percolator.today/', $data->items[$i]->author->url);
      $this->assertNotEmpty($data->items[$i]->url);
      $this->assertNotEmpty($data->items[$i]->published);
      $this->assertNotEmpty($data->items[$i]->name);
      $this->assertNotEmpty($data->items[$i]->content->html);
      $this->assertNotEmpty($data->items[$i]->content->text);
      $this->assertNotEmpty($data->items[$i]->audio);
    }

    $this->assertEquals('Episode 1: Welcome', $data->items[11]->name);
    $this->assertEquals('https://percolator.today/episode/1', $data->items[11]->url);
    $this->assertEquals('2017-09-20T07:00:00+00:00', $data->items[11]->published);
    $this->assertEquals('https://percolator.today/redirect.php?url=https%3A%2F%2Fpercolator.today%2Fmedia%2FPercolator_Episode_1.mp3', $data->items[11]->audio);
    $this->assertContains('What is Percolator? Some thoughts about multi-photos in Instagram.', $data->items[11]->content->text);
    $this->assertContains('What is Percolator? Some thoughts about multi-photos in Instagram.', $data->items[11]->content->html);
    $this->assertContains('<li><a href="https://indieweb.org/multi-photo_vs_collection">multi-photo vs collection</a></li>', $data->items[11]->content->html);

    $this->assertEquals('feed', $data->type);
  }

}