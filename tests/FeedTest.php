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
    $this->assertEquals(4, count($data->items));
    $this->assertEquals('One', $data->items[0]->name);
    $this->assertEquals('Two', $data->items[1]->name);
    $this->assertEquals('Three', $data->items[2]->name);
    $this->assertEquals('Four', $data->items[3]->name);
  }

  public function testListOfHEntrysWithHCard() {
    $url = 'http://feed.example.com/list-of-hentrys-with-h-card';
    $response = $this->parse(['url' => $url, 'expect' => 'feed']);

    $body = $response->getContent();
    $this->assertEquals(200, $response->getStatusCode());
    $data = json_decode($body)->data;

    $this->assertEquals('feed', $data->type);
    $this->assertEquals(4, count($data->items));
    $this->assertEquals('One', $data->items[0]->name);
    $this->assertEquals('Two', $data->items[1]->name);
    $this->assertEquals('Three', $data->items[2]->name);
    $this->assertEquals('Four', $data->items[3]->name);

    // Check that the author h-card was matched up with each h-entry
    $this->assertEquals('Author Name', $data->items[0]->author->name);
    $this->assertEquals('Author Name', $data->items[1]->author->name);
    $this->assertEquals('Author Name', $data->items[2]->author->name);
    $this->assertEquals('Author Name', $data->items[3]->author->name);
  }

  public function testShortListOfHEntrysWithHCard() {
    $url = 'http://feed.example.com/short-list-of-hentrys-with-h-card';
    $response = $this->parse(['url' => $url, 'expect' => 'feed']);

    $body = $response->getContent();
    $this->assertEquals(200, $response->getStatusCode());
    $data = json_decode($body)->data;

    $this->assertEquals('feed', $data->type);
    // This test should find the h-entry rather than the h-card, because expect=feed
    $this->assertEquals('entry', $data->items[0]->type);
    $this->assertEquals('http://feed.example.com/1', $data->items[0]->url);
    $this->assertEquals('Author', $data->items[0]->author->name);
  }

  public function testTopLevelHFeed() {
    $url = 'http://feed.example.com/top-level-h-feed';
    $response = $this->parse(['url' => $url, 'expect' => 'feed']);

    $body = $response->getContent();
    $this->assertEquals(200, $response->getStatusCode());
    $data = json_decode($body)->data;

    $this->assertEquals('feed', $data->type);
    $this->assertEquals(4, count($data->items));
    $this->assertEquals('One', $data->items[0]->name);
    $this->assertEquals('Two', $data->items[1]->name);
    $this->assertEquals('Three', $data->items[2]->name);
    $this->assertEquals('Four', $data->items[3]->name);
  }

  public function testTopLevelHFeedWithChildAuthor() {
    $url = 'http://feed.example.com/h-feed-with-child-author';
    $response = $this->parse(['url' => $url, 'expect' => 'feed']);

    $body = $response->getContent();
    $this->assertEquals(200, $response->getStatusCode());
    $data = json_decode($body)->data;

    $this->assertEquals('feed', $data->type);
    $this->assertEquals(4, count($data->items));
    $this->assertEquals('One', $data->items[0]->name);
    $this->assertEquals('Two', $data->items[1]->name);
    $this->assertEquals('Three', $data->items[2]->name);
    $this->assertEquals('Four', $data->items[3]->name);
    $this->assertEquals('Author Name', $data->items[0]->author->name);
    $this->assertEquals('Author Name', $data->items[1]->author->name);
    $this->assertEquals('Author Name', $data->items[2]->author->name);
    $this->assertEquals('Author Name', $data->items[3]->author->name);
  }

  public function testHCardWithChildHEntrys() {
    $url = 'http://feed.example.com/h-card-with-child-h-entrys';
    $response = $this->parse(['url' => $url, 'expect' => 'feed']);

    $body = $response->getContent();
    $this->assertEquals(200, $response->getStatusCode());
    $data = json_decode($body)->data;

    $this->assertEquals('feed', $data->type);
    $this->assertEquals(4, count($data->items));
    $this->assertEquals('One', $data->items[0]->name);
    $this->assertEquals('Two', $data->items[1]->name);
    $this->assertEquals('Three', $data->items[2]->name);
    $this->assertEquals('Four', $data->items[3]->name);
  }

  public function testHCardWithSiblingHEntrys() {
    $url = 'http://feed.example.com/h-card-with-sibling-h-entrys';
    $response = $this->parse(['url' => $url, 'expect' => 'feed']);

    $body = $response->getContent();
    $this->assertEquals(200, $response->getStatusCode());
    $data = json_decode($body)->data;

    $this->assertEquals('feed', $data->type);
    $this->assertEquals(4, count($data->items));
    $this->assertEquals('One', $data->items[0]->name);
    $this->assertEquals('Two', $data->items[1]->name);
    $this->assertEquals('Three', $data->items[2]->name);
    $this->assertEquals('Four', $data->items[3]->name);
    // Check that the author h-card was matched up with each h-entry
    $this->assertEquals('Author Name', $data->items[0]->author->name);
    $this->assertEquals('Author Name', $data->items[1]->author->name);
    $this->assertEquals('Author Name', $data->items[2]->author->name);
    $this->assertEquals('Author Name', $data->items[3]->author->name);
  }

  public function testHCardWithChildHFeed() {
    $url = 'http://feed.example.com/h-card-with-child-h-feed';
    $response = $this->parse(['url' => $url, 'expect' => 'feed']);

    $body = $response->getContent();
    $this->assertEquals(200, $response->getStatusCode());
    $data = json_decode($body)->data;

    $this->assertEquals('feed', $data->type);
    $this->assertEquals(4, count($data->items));
    $this->assertEquals('One', $data->items[0]->name);
    $this->assertEquals('Two', $data->items[1]->name);
    $this->assertEquals('Three', $data->items[2]->name);
    $this->assertEquals('Four', $data->items[3]->name);
    // Check that the author h-card was matched up with each h-entry
    $this->assertEquals('Author Name', $data->items[0]->author->name);
    $this->assertEquals('Author Name', $data->items[1]->author->name);
    $this->assertEquals('Author Name', $data->items[2]->author->name);
    $this->assertEquals('Author Name', $data->items[3]->author->name);
  }

  public function testHCardWithChildHFeedNoExpect() {
    $url = 'http://feed.example.com/h-card-with-child-h-feed';
    $response = $this->parse(['url' => $url]);

    $body = $response->getContent();
    $this->assertEquals(200, $response->getStatusCode());
    $data = json_decode($body)->data;

    $this->assertEquals('card', $data->type);
    $this->assertEquals('Author Name', $data->name);
  }

  public function testJSONFeed() {
    $url = 'http://feed.example.com/jsonfeed';
    $response = $this->parse(['url' => $url, 'expect' => 'feed']);

    $body = $response->getContent();
    $this->assertEquals(200, $response->getStatusCode());
    $data = json_decode($body)->data;

    $this->assertEquals(10, count($data->items));
    for($i=0; $i<8; $i++) {
      $this->assertEquals('entry', $data->items[$i]->type);
      $this->assertEquals('manton', $data->items[$i]->author->name);
      $this->assertEquals('http://www.manton.org', $data->items[$i]->author->url);
      $this->assertNotEmpty($data->items[$i]->url);
      $this->assertNotEmpty($data->items[$i]->uid);
      $this->assertNotEmpty($data->items[$i]->published);
      $this->assertNotEmpty($data->items[$i]->content->html);
      $this->assertNotEmpty($data->items[$i]->content->text);
    }

    $this->assertEquals('<p>Lots of good feedback on <a href="http://help.micro.blog/2017/wordpress-import/">the WordPress import</a>. Made a couple improvements this morning. Overall, pretty good.</p>', $data->items[9]->content->html);
    $this->assertEquals('Lots of good feedback on the WordPress import. Made a couple improvements this morning. Overall, pretty good.', $data->items[9]->content->text);
    $this->assertEquals('http://www.manton.org/2017/11/5975.html', $data->items[9]->url);
    $this->assertEquals('http://www.manton.org/2017/11/5975.html', $data->items[9]->uid);
    $this->assertEquals('2017-11-07T15:04:01+00:00', $data->items[9]->published);

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
    $this->assertEquals('https://percolator.today/redirect.php?url=https%3A%2F%2Fpercolator.today%2Fmedia%2FPercolator_Episode_1.mp3', $data->items[11]->audio[0]);
    $this->assertContains('What is Percolator? Some thoughts about multi-photos in Instagram.', $data->items[11]->content->text);
    $this->assertContains('What is Percolator? Some thoughts about multi-photos in Instagram.', $data->items[11]->content->html);
    $this->assertContains('<li><a href="https://indieweb.org/multi-photo_vs_collection">multi-photo vs collection</a></li>', $data->items[11]->content->html);

    $this->assertEquals('feed', $data->type);
  }

  public function testInstagramAtomFeed() {
    $url = 'http://feed.example.com/instagram-atom';
    $response = $this->parse(['url' => $url, 'expect' => 'feed']);

    $body = $response->getContent();
    $this->assertEquals(200, $response->getStatusCode());
    $data = json_decode($body)->data;

    $this->assertEquals(12, count($data->items));

    $this->assertEquals('Marshall Kirkpatrick', $data->items[11]->author->name);
    $this->assertEquals('https://www.instagram.com/marshallk/', $data->items[11]->author->url);
    $this->assertEquals('https://www.instagram.com/p/BcFjw9SHYql/', $data->items[11]->url);
    $this->assertEquals('2017-11-29T17:04:00+00:00', $data->items[11]->published);
    // Should remove the "name" since it's a prefix of the content
    $this->assertObjectNotHasAttribute('name', $data->items[11]);
    $this->assertEquals('Sometimes my job requires me to listen to 55 minutes of an hour long phone call while I go for a long walk on a sunny morning and wait for my turn to give an update. Pretty nice!', $data->items[11]->content->text);
  }

  public function testAscraeus() {
    $url = 'http://source.example.com/ascraeus';
    $response = $this->parse(['url' => $url, 'expect' => 'feed']);

    $body = $response->getContent();
    $this->assertEquals(200, $response->getStatusCode());
    $data = json_decode($body)->data;

    $this->assertEquals('feed', $data->type);
    $this->assertEquals(20, count($data->items));
  }

}
