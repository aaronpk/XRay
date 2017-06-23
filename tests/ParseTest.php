<?php
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class ParseTest extends PHPUnit_Framework_TestCase {

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
    $url = 'http://source.example.com/basictest';
    $response = $this->parse(['url' => $url, 'target' => 'http://example.net']);

    $body = $response->getContent();
    $this->assertEquals(200, $response->getStatusCode());
    $data = json_decode($body);
    $this->assertObjectHasAttribute('error', $data);
    $this->assertEquals('no_link_found', $data->error);
    $this->assertEquals('200', $data->code);
    $this->assertEquals($url, $data->url);
  }

  public function testTargetFound() {
    $url = 'http://source.example.com/basictest';
    $response = $this->parse(['url' => $url, 'target' => 'http://target.example.com']);

    $body = $response->getContent();
    $this->assertEquals(200, $response->getStatusCode());
    $data = json_decode($body);
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

  public function testFindTargetLinkIsImage() {
    $url = 'http://source.example.com/link-is-img';
    $response = $this->parse(['url' => $url, 'target' => 'http://target.example.com/photo.jpg']);

    $body = $response->getContent();
    $this->assertEquals(200, $response->getStatusCode());
    $data = json_decode($body);
    $this->assertObjectNotHasAttribute('name', $data->data);
    $this->assertEquals('This page has an img tag with the target URL.', $data->data->content->text);
  }

  public function testFindTargetLinkIsVideo() {
    $url = 'http://source.example.com/link-is-video';
    $response = $this->parse(['url' => $url, 'target' => 'http://target.example.com/movie.mp4']);

    $body = $response->getContent();
    $this->assertEquals(200, $response->getStatusCode());
    $data = json_decode($body);
    $this->assertObjectNotHasAttribute('name', $data->data);
    $this->assertEquals('This page has a video tag with the target URL.', $data->data->content->text);
  }

  public function testFindTargetLinkIsAudio() {
    $url = 'http://source.example.com/link-is-audio';
    $response = $this->parse(['url' => $url, 'target' => 'http://target.example.com/media.mp3']);

    $body = $response->getContent();
    $this->assertEquals(200, $response->getStatusCode());
    $data = json_decode($body);
    $this->assertObjectNotHasAttribute('name', $data->data);
    $this->assertEquals('This page has an audio tag with the target URL.', $data->data->content->text);
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

  public function testEntryWithDuplicateCategories() {
    $url = 'http://source.example.com/h-entry-duplicate-categories';
    $response = $this->parse(['url' => $url]);

    $body = $response->getContent();
    $this->assertEquals(200, $response->getStatusCode());
    $data = json_decode($body);
    $this->assertEquals(['indieweb'], $data->data->category);
  }

  public function testEntryStripHashtagWithDuplicateCategories() {
    $url = 'http://source.example.com/h-entry-strip-hashtag-from-categories';
    $response = $this->parse(['url' => $url]);

    $body = $response->getContent();
    $this->assertEquals(200, $response->getStatusCode());
    $data = json_decode($body);
    $this->assertContains('indieweb', $data->data->category);
    $this->assertContains('xray', $data->data->category);
    $this->assertEquals(2, count($data->data->category));
  }

  public function testNoHEntryMarkup() {
    $url = 'http://source.example.com/no-h-entry';
    $response = $this->parse(['url' => $url]);

    $body = $response->getContent();
    $this->assertEquals(200, $response->getStatusCode());
    $data = json_decode($body);
    $this->assertEquals('unknown', $data->data->type);
  }

  public function testReplyIsURL() {
    $url = 'http://source.example.com/reply-is-url';
    $response = $this->parse(['url' => $url]);

    $body = $response->getContent();
    $this->assertEquals(200, $response->getStatusCode());
    $data = json_decode($body, true);
    $this->assertEquals('entry', $data['data']['type']);
    $this->assertEquals('http://example.com/100', $data['data']['in-reply-to'][0]);
  }

  public function testReplyIsHCite() {
    $url = 'http://source.example.com/reply-is-h-cite';
    $response = $this->parse(['url' => $url]);

    $body = $response->getContent();
    $this->assertEquals(200, $response->getStatusCode());
    $data = json_decode($body, true);
    $this->assertEquals('entry', $data['data']['type']);    
    $this->assertEquals('http://example.com/100', $data['data']['in-reply-to'][0]);
    $this->assertArrayHasKey('http://example.com/100', $data['data']['refs']);
    $this->assertEquals('Example Post', $data['data']['refs']['http://example.com/100']['name']);
    $this->assertEquals('http://example.com/100', $data['data']['refs']['http://example.com/100']['url']);
  }

  public function testPersonTagIsURL() {
    $url = 'http://source.example.com/person-tag-is-url';
    $response = $this->parse(['url' => $url]);

    $body = $response->getContent();
    $this->assertEquals(200, $response->getStatusCode());
    $data = json_decode($body, true);
    $this->assertEquals('entry', $data['data']['type']);
    $this->assertEquals('http://alice.example.com/', $data['data']['category'][0]);
  }

  public function testPersonTagIsHCard() {
    $url = 'http://source.example.com/person-tag-is-h-card';
    $response = $this->parse(['url' => $url]);

    $body = $response->getContent();
    $this->assertEquals(200, $response->getStatusCode());
    $data = json_decode($body, true);
    $this->assertEquals('entry', $data['data']['type']);
    $this->assertEquals('http://alice.example.com/', $data['data']['category'][0]);
    $this->assertArrayHasKey('http://alice.example.com/', $data['data']['refs']);
    $this->assertEquals('card', $data['data']['refs']['http://alice.example.com/']['type']);
    $this->assertEquals('http://alice.example.com/', $data['data']['refs']['http://alice.example.com/']['url']);
    $this->assertEquals('Alice', $data['data']['refs']['http://alice.example.com/']['name']);
  }

  public function testSyndicationIsURL() {
    $url = 'http://source.example.com/has-syndication';
    $response = $this->parse(['url' => $url]);

    $body = $response->getContent();
    $this->assertEquals(200, $response->getStatusCode());
    $data = json_decode($body, true);
    $this->assertEquals('entry', $data['data']['type']);
    $this->assertEquals('http://syndicated.example/', $data['data']['syndication'][0]);
  }

  public function testHEntryIsNotFirstObject() {
    $url = 'http://source.example.com/h-entry-is-not-first';
    $response = $this->parse(['url' => $url]);

    $body = $response->getContent();
    $this->assertEquals(200, $response->getStatusCode());
    $data = json_decode($body, true);
    $this->assertEquals('entry', $data['data']['type']);
    $this->assertEquals('Hello World', $data['data']['content']['text']);
  }

  public function testHEntryRSVP() {
    $url = 'http://source.example.com/h-entry-rsvp';
    $response = $this->parse(['url' => $url]);

    $body = $response->getContent();
    $this->assertEquals(200, $response->getStatusCode());
    $data = json_decode($body, true);
    $this->assertEquals('entry', $data['data']['type']);
    $this->assertEquals('I\'ll be there!', $data['data']['name']);
    $this->assertEquals('yes', $data['data']['rsvp']);
  }

  public function testMultipleHEntryOnPermalink() {
    $url = 'http://source.example.com/multiple-h-entry-on-permalink';
    $response = $this->parse(['url' => $url]);

    $body = $response->getContent();
    $this->assertEquals(200, $response->getStatusCode());
    $data = json_decode($body, true);
    $this->assertEquals('entry', $data['data']['type']);
    $this->assertEquals('Primary Post', $data['data']['name']);
  }

  public function testHEntryWithHCardSibling() {
    $url = 'http://source.example.com/h-entry-with-h-card-sibling';
    $response = $this->parse(['url' => $url]);

    $body = $response->getContent();
    $this->assertEquals(200, $response->getStatusCode());
    $data = json_decode($body, true);
    $this->assertEquals('entry', $data['data']['type']);
    $this->assertEquals('Hello World', $data['data']['content']['text']);
  }

  public function testHEntryRedirectWithHCardSibling() {
    $url = 'http://source.example.com/h-entry-redirect-with-h-card-sibling';
    $response = $this->parse(['url' => $url]);

    $body = $response->getContent();
    $this->assertEquals(200, $response->getStatusCode());
    $data = json_decode($body, true);
    $this->assertEquals('entry', $data['data']['type']);
    $this->assertEquals('Hello World', $data['data']['content']['text']);
  }

  public function testSingleHEntryHasNoPermalink() {
    $url = 'http://source.example.com/single-h-entry-has-no-permalink';
    $response = $this->parse(['url' => $url]);

    $body = $response->getContent();
    $this->assertEquals(200, $response->getStatusCode());
    $data = json_decode($body, true);
    $this->assertEquals('entry', $data['data']['type']);
    $this->assertEquals('Hello World', $data['data']['content']['text']);
  }

  public function testBridgyExampleWithNoMatchingURL() {
    $url = 'http://source.example.com/bridgy-example';
    $response = $this->parse(['url' => $url]);

    $body = $response->getContent();
    $this->assertEquals(200, $response->getStatusCode());
    $data = json_decode($body, true);
    $this->assertEquals('entry', $data['data']['type']);
  }

  public function testEventWithHTMLDescription() {
    $url = 'http://source.example.com/h-event';
    $response = $this->parse(['url' => $url]);

    $body = $response->getContent();
    $this->assertEquals(200, $response->getStatusCode());
    $data = json_decode($body, true);
    $this->assertEquals('event', $data['data']['type']);
    $this->assertEquals('Homebrew Website Club', $data['data']['name']);
    $this->assertEquals($url, $data['data']['url']);
    $this->assertEquals('2016-03-09T18:30', $data['data']['start']);
    $this->assertEquals('2016-03-09T19:30', $data['data']['end']);
    $this->assertStringStartsWith("Are you building your own website? Indie reader? Personal publishing web app? Or some other digital magic-cloud proxy? If so, come on by and join a gathering of people with likeminded interests. Bring your friends that want to start a personal web site. Exchange information, swap ideas, talk shop, help work on a project...", $data['data']['description']['text']);
    $this->assertStringEndsWith("See the Homebrew Website Club Newsletter Volume 1 Issue 1 for a description of the first meeting.",  $data['data']['description']['text']);
    $this->assertStringStartsWith("<p>Are you building your own website? Indie reader? Personal publishing web app? Or some other digital magic-cloud proxy? If so, come on by and join a gathering of people with likeminded interests. Bring your friends that want to start a personal web site. Exchange information, swap ideas, talk shop, help work on a project...</p>", $data['data']['description']['html']);
    $this->assertStringEndsWith('<p>See the <a href="http://tantek.com/2013/332/b1/homebrew-website-club-newsletter">Homebrew Website Club Newsletter Volume 1 Issue 1</a> for a description of the first meeting.</p>', $data['data']['description']['html']);
  }

  public function testEventWithTextDescription() {
    $url = 'http://source.example.com/h-event-text-description';
    $response = $this->parse(['url' => $url]);

    $body = $response->getContent();
    $this->assertEquals(200, $response->getStatusCode());
    $data = json_decode($body, true);
    $this->assertEquals('event', $data['data']['type']);
    $this->assertEquals('Homebrew Website Club', $data['data']['name']);
    $this->assertEquals($url, $data['data']['url']);
    $this->assertEquals('2016-03-09T18:30', $data['data']['start']);
    $this->assertEquals('2016-03-09T19:30', $data['data']['end']);
    $this->assertStringStartsWith("Are you building your own website? Indie reader? Personal publishing web app? Or some other digital magic-cloud proxy? If so, come on by and join a gathering of people with likeminded interests. Bring your friends that want to start a personal web site. Exchange information, swap ideas, talk shop, help work on a project...", $data['data']['description']['text']);
    $this->assertStringEndsWith("See the Homebrew Website Club Newsletter Volume 1 Issue 1 for a description of the first meeting.", $data['data']['description']['text']);
    $this->assertArrayNotHasKey('html', $data['data']['description']);
  }

  public function testEventWithHCardLocation() {
    $url = 'http://source.example.com/h-event-with-h-card-location';
    $response = $this->parse(['url' => $url]);

    $body = $response->getContent();
    $this->assertEquals(200, $response->getStatusCode());
    $data = json_decode($body, true);

    $this->assertEquals('event', $data['data']['type']);
    $this->assertEquals('Homebrew Website Club', $data['data']['name']);
    $this->assertEquals($url, $data['data']['url']);
    $this->assertEquals('2016-02-09T18:30', $data['data']['start']);
    $this->assertEquals('2016-02-09T19:30', $data['data']['end']);
    $this->assertArrayHasKey('http://source.example.com/venue', $data['data']['refs']);
    $this->assertEquals('card', $data['data']['refs']['http://source.example.com/venue']['type']);
    $this->assertEquals('http://source.example.com/venue', $data['data']['refs']['http://source.example.com/venue']['url']);
    $this->assertEquals('Venue', $data['data']['refs']['http://source.example.com/venue']['name']);
  }

  public function testMf2ReviewOfProduct() {
    $url = 'http://source.example.com/h-review-of-product';
    $response = $this->parse(['url' => $url]);

    $body = $response->getContent();
    $this->assertEquals(200, $response->getStatusCode());
    $data = json_decode($body, true);

    $this->assertEquals('review', $data['data']['type']);
    $this->assertEquals('Review', $data['data']['name']);
    $this->assertEquals('Not great', $data['data']['summary']);
    $this->assertEquals('3', $data['data']['rating']);
    $this->assertEquals('5', $data['data']['best']);
    $this->assertEquals('This is the full text of the review', $data['data']['content']['text']);
    $this->assertContains('red', $data['data']['category']);
    $this->assertContains('blue', $data['data']['category']);
    $this->assertContains('http://product.example.com/', $data['data']['item']);
    $this->assertArrayHasKey('http://product.example.com/', $data['data']['refs']);
    $this->assertEquals('product', $data['data']['refs']['http://product.example.com/']['type']);
    $this->assertEquals('The Reviewed Product', $data['data']['refs']['http://product.example.com/']['name']);
    $this->assertEquals('http://product.example.com/', $data['data']['refs']['http://product.example.com/']['url']);
  }

  public function testMf2ReviewOfHCard() {
    $url = 'http://source.example.com/h-review-of-h-card';
    $response = $this->parse(['url' => $url]);

    $body = $response->getContent();
    $this->assertEquals(200, $response->getStatusCode());
    $data = json_decode($body, true);

    $this->assertEquals('review', $data['data']['type']);
    $this->assertEquals('Review', $data['data']['name']);
    $this->assertEquals('Not great', $data['data']['summary']);
    $this->assertEquals('3', $data['data']['rating']);
    $this->assertEquals('5', $data['data']['best']);
    $this->assertEquals('This is the full text of the review', $data['data']['content']['text']);
    $this->assertContains('http://business.example.com/', $data['data']['item']);
    $this->assertArrayHasKey('http://business.example.com/', $data['data']['refs']);
    $this->assertEquals('card', $data['data']['refs']['http://business.example.com/']['type']);
    $this->assertEquals('The Reviewed Business', $data['data']['refs']['http://business.example.com/']['name']);
    $this->assertEquals('http://business.example.com/', $data['data']['refs']['http://business.example.com/']['url']);
  }

  public function testMf1Review() {
    $url = 'http://source.example.com/hReview';
    $response = $this->parse(['url' => $url]);

    $body = $response->getContent();
    $this->assertEquals(200, $response->getStatusCode());
    $data = json_decode($body, true);

    $this->assertEquals('review', $data['data']['type']);
    $this->assertEquals('Not great', $data['data']['summary']);
    $this->assertEquals('3', $data['data']['rating']);
    $this->assertEquals('5', $data['data']['best']);
    $this->assertEquals('This is the full text of the review', $data['data']['content']['text']);
    $this->assertContains('http://product.example.com/', $data['data']['item']);
    $this->assertArrayHasKey('http://product.example.com/', $data['data']['refs']);
    $this->assertEquals('item', $data['data']['refs']['http://product.example.com/']['type']);
    $this->assertEquals('The Reviewed Product', $data['data']['refs']['http://product.example.com/']['name']);
    $this->assertEquals('http://product.example.com/', $data['data']['refs']['http://product.example.com/']['url']);
  }

  public function testMf2Recipe() {
    $url = 'http://source.example.com/h-recipe';
    $response = $this->parse(['url' => $url]);

    $body = $response->getContent();
    $this->assertEquals(200, $response->getStatusCode());
    $data = json_decode($body, true);

    $this->assertEquals('recipe', $data['data']['type']);
    $this->assertEquals('Cookie Recipe', $data['data']['name']);
    $this->assertEquals('12 Cookies', $data['data']['yield']);
    $this->assertEquals('PT30M', $data['data']['duration']);
    $this->assertEquals('The best chocolate chip cookie recipe', $data['data']['summary']);
    $this->assertContains('3 cups flour', $data['data']['ingredient']);
    $this->assertContains('chocolate chips', $data['data']['ingredient']);
  }

  public function testEntryIsAnInvitee() {
    $url = 'http://source.example.com/bridgy-invitee';
    $response = $this->parse(['url' => $url]);

    $body = $response->getContent();
    $this->assertEquals(200, $response->getStatusCode());
    $data = json_decode($body, true);

    $this->assertEquals('entry', $data['data']['type']);
    $this->assertEquals('https://www.facebook.com/555707837940351#tantek', $data['data']['url']);
    $this->assertContains('https://www.facebook.com/tantek.celik', $data['data']['invitee']);
    $this->assertArrayHasKey('https://www.facebook.com/tantek.celik', $data['data']['refs']);
    $this->assertEquals('Tantek Çelik', $data['data']['refs']['https://www.facebook.com/tantek.celik']['name']);
  }

  public function testEntryAtFragmentID() {
    $url = 'http://source.example.com/fragment-id#comment-1000';
    $response = $this->parse(['url' => $url]);

    $body = $response->getContent();
    $this->assertEquals(200, $response->getStatusCode());
    $data = json_decode($body, true);
    $this->assertEquals('entry', $data['data']['type']);
    $this->assertEquals('Comment text', $data['data']['content']['text']);
    $this->assertEquals('http://source.example.com/fragment-id#comment-1000', $data['data']['url']);
    $this->assertTrue($data['info']['found_fragment']);
  }

  public function testEntryAtNonExistentFragmentID() {
    $url = 'http://source.example.com/fragment-id#comment-404';
    $response = $this->parse(['url' => $url]);

    $body = $response->getContent();
    $this->assertEquals(200, $response->getStatusCode());
    $data = json_decode($body, true);
    $this->assertEquals('entry', $data['data']['type']);
    $this->assertEquals('http://source.example.com/fragment-id', $data['data']['url']);
    $this->assertFalse($data['info']['found_fragment']);
  }

  public function testCheckin() {
    $url = 'http://source.example.com/checkin';
    $response = $this->parse(['url' => $url]);

    $body = $response->getContent();
    $this->assertEquals(200, $response->getStatusCode());
    $data = json_decode($body, true);

    $this->assertEquals('entry', $data['data']['type']);
    $venue = $data['data']['checkin'];
    $this->assertEquals('https://foursquare.com/v/57104d2e498ece022e169dca', $venue['url']);
    $this->assertEquals('DreamHost', $venue['name']);
    $this->assertEquals('45.518716', $venue['latitude']);
    $this->assertEquals('Homebrew Website Club!', $data['data']['content']['text']);
    $this->assertEquals('https://aaronparecki.com/2017/06/07/12/photo.jpg', $data['data']['photo'][0]);
    $this->assertEquals('2017-06-07T17:14:40-07:00', $data['data']['published']);
    $this->assertArrayNotHasKey('name', $data['data']);
  }

  public function testCheckinURLOnly() {
    $url = 'http://source.example.com/checkin-url';
    $response = $this->parse(['url' => $url]);

    $body = $response->getContent();
    $this->assertEquals(200, $response->getStatusCode());
    $data = json_decode($body, true);

    $this->assertEquals('entry', $data['data']['type']);
    $venue = $data['data']['checkin'];
    $this->assertEquals('https://foursquare.com/v/57104d2e498ece022e169dca', $venue['url']);
    $this->assertEquals('Homebrew Website Club!', $data['data']['content']['text']);
    $this->assertEquals('https://aaronparecki.com/2017/06/07/12/photo.jpg', $data['data']['photo'][0]);
    $this->assertEquals('2017-06-07T17:14:40-07:00', $data['data']['published']);
    $this->assertArrayNotHasKey('name', $data['data']);
  }

  public function testXKCD() {
    $url = 'http://xkcd.com/1810/';
    $response = $this->parse(['url' => $url]);

    $body = $response->getContent();
    $this->assertEquals(200, $response->getStatusCode());
    $data = json_decode($body, true);
    $this->assertEquals('entry', $data['data']['type']);
    $this->assertEquals('http://xkcd.com/1810/', $data['data']['url']);
    $this->assertEquals('Chat Systems', $data['data']['name']);
    $this->assertContains('http://imgs.xkcd.com/comics/chat_systems_2x.png', $data['data']['photo']);
  }

}
