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
    $url = 'http://source.example.com/basictest';
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
    $this->assertArrayHasKey('http://example.com/100', $data['refs']);
    $this->assertEquals('Example Post', $data['refs']['http://example.com/100']['name']);
    $this->assertEquals('http://example.com/100', $data['refs']['http://example.com/100']['url']);
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
    $this->assertArrayHasKey('http://alice.example.com/', $data['refs']);
    $this->assertEquals('card', $data['refs']['http://alice.example.com/']['type']);
    $this->assertEquals('http://alice.example.com/', $data['refs']['http://alice.example.com/']['url']);
    $this->assertEquals('Alice', $data['refs']['http://alice.example.com/']['name']);
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
    $this->assertArrayHasKey('http://source.example.com/venue', $data['refs']);
    $this->assertEquals('card', $data['refs']['http://source.example.com/venue']['type']);
    $this->assertEquals('http://source.example.com/venue', $data['refs']['http://source.example.com/venue']['url']);
    $this->assertEquals('Venue', $data['refs']['http://source.example.com/venue']['name']);
  }

}
