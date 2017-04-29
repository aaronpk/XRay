<?php
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class SanitizeTest extends PHPUnit_Framework_TestCase {

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

  public function testAllowsWhitelistedTags() {
    $url = 'http://sanitize.example/entry-with-valid-tags';
    $response = $this->parse(['url' => $url]);

    $body = $response->getContent();
    $this->assertEquals(200, $response->getStatusCode());
    $data = json_decode($body, true);
    $html = $data['data']['content']['html'];

    $this->assertEquals('entry', $data['data']['type']);
    $this->assertContains('This content has only valid tags.', $html); 
    $this->assertContains('<a href="http://sanitize.example/example">links</a>,', $html, '<a> missing'); 
    $this->assertContains('<abbr>abbreviations</abbr>,', $html, '<abbr> missing'); 
    $this->assertContains('<b>bold</b>,', $html, '<b> missing'); 
    $this->assertContains('<code>inline code</code>,', $html, '<code> missing'); 
    $this->assertContains('<del>delete</del>,', $html, '<del> missing'); 
    $this->assertContains('<em>emphasis</em>,', $html, '<em> missing'); 
    $this->assertContains('<i>italics</i>,', $html, '<i> missing'); 
    $this->assertContains('<img alt="images are allowed" src="http://sanitize.example/example.jpg" />', $html, '<img> missing'); 
    $this->assertContains('<q>inline quote</q>,', $html, '<q> missing');
    $this->assertContains('<strike>strikethrough</strike>,', $html, '<strike> missing');
    $this->assertContains('<strong>strong text</strong>,', $html, '<strong> missing');
    $this->assertContains('<time datetime="2016-01-01">time elements</time>', $html, '<time> missing');
    $this->assertContains('<blockquote>Blockquote tags are okay</blockquote>', $html);
    $this->assertContains('<pre>preformatted text is okay too', $html, '<pre> missing');
    $this->assertContains('for code examples and such</pre>', $html, '<pre> missing');
    $this->assertContains('<p>Paragraph tags are allowed</p>', $html, '<p> missing');
    $this->assertContains('<h1>One</h1>', $html, '<h1> missing');
    $this->assertContains('<h2>Two</h2>', $html, '<h2> missing');
    $this->assertContains('<h3>Three</h3>', $html, '<h3> missing');
    $this->assertContains('<h4>Four</h4>', $html, '<h4> missing');
    $this->assertContains('<h5>Five</h5>', $html, '<h5> missing');
    $this->assertContains('<h6>Six</h6>', $html, '<h6> missing');
    $this->assertContains('<ul>', $html, '<ul> missing');
    $this->assertContains('<li>One</li>', $html, '<li> missing');
  }

  public function testRemovesUnsafeTags() {
    $url = 'http://sanitize.example/entry-with-unsafe-tags';
    $response = $this->parse(['url' => $url]);

    $body = $response->getContent();
    $this->assertEquals(200, $response->getStatusCode());
    $data = json_decode($body, true);
    $html = $data['data']['content']['html'];
    $text = $data['data']['content']['text'];

    $this->assertEquals('entry', $data['data']['type']);
    $this->assertNotContains('<script>', $html);
    $this->assertNotContains('<style>', $html);
    $this->assertNotContains('visiblity', $html); // from the CSS
    $this->assertNotContains('alert', $html); // from the JS
    $this->assertNotContains('visiblity', $text);
    $this->assertNotContains('alert', $text);
  }

  public function testAllowsMF2Classes() {
    $url = 'http://sanitize.example/entry-with-mf2-classes';
    $response = $this->parse(['url' => $url]);

    $body = $response->getContent();
    $this->assertEquals(200, $response->getStatusCode());
    $data = json_decode($body, true);
    $html = $data['data']['content']['html'];

    $this->assertEquals('entry', $data['data']['type']);
    $this->assertContains('<h2 class="p-name">Hello World</h2>', $html);
    $this->assertContains('<h3>Utility Class</h3>', $html);
  }

  public function testEscapingHTMLTagsInText() {
    $url = 'http://sanitize.example/html-escaping-in-text';
    $response = $this->parse(['url' => $url]);

    $body = $response->getContent();
    $this->assertEquals(200, $response->getStatusCode());
    $data = json_decode($body, true);

    $this->assertEquals('entry', $data['data']['type']);
    $this->assertEquals('This content has some HTML escaped entities such as & ampersand, " quote, escaped <code> HTML tags, an ümlaut, an @at sign.', $data['data']['content']['text']);
  }

  public function testEscapingHTMLTagsInHTML() {
    $url = 'http://sanitize.example/html-escaping-in-html';
    $response = $this->parse(['url' => $url]);

    $body = $response->getContent();
    $this->assertEquals(200, $response->getStatusCode());
    $data = json_decode($body, true);

    $this->assertEquals('entry', $data['data']['type']);
    $this->assertArrayNotHasKey('name', $data['data']);
    $this->assertEquals('This content has some HTML escaped entities such as & ampersand, " quote, escaped <code> HTML tags, an ümlaut, an @at sign.', $data['data']['content']['text']);
    $this->assertEquals('This content has some <i>HTML escaped</i> entities such as &amp; ampersand, " quote, escaped &lt;code&gt; HTML tags, an ümlaut, an @at sign.', $data['data']['content']['html']);
  }

  public function testSanitizeJavascriptURLs() {
    $url = 'http://sanitize.example/h-entry-with-javascript-urls';
    $response = $this->parse(['url' => $url]);

    $body = $response->getContent();
    $this->assertEquals(200, $response->getStatusCode());
    $data = json_decode($body, true);

    $this->assertEquals('entry', $data['data']['type']);
    $this->assertEquals('', $data['data']['author']['url']);
    $this->assertArrayNotHasKey('url', $data['data']);
    $this->assertArrayNotHasKey('photo', $data['data']);
    $this->assertArrayNotHasKey('audio', $data['data']);
    $this->assertArrayNotHasKey('video', $data['data']);
    $this->assertArrayNotHasKey('syndication', $data['data']);
    $this->assertArrayNotHasKey('in-reply-to', $data['data']);
    $this->assertArrayNotHasKey('like-of', $data['data']);
    $this->assertArrayNotHasKey('repost-of', $data['data']);
    $this->assertArrayNotHasKey('bookmark-of', $data['data']);
    $this->assertEquals('Author', $data['data']['author']['name']);
    $this->assertEquals('', $data['data']['author']['photo']);
  }

  public function testSanitizeEmailAuthorURL() {
    $url = 'http://sanitize.example/h-entry-with-email-author';
    $response = $this->parse(['url' => $url]);

    $body = $response->getContent();
    $this->assertEquals(200, $response->getStatusCode());
    $data = json_decode($body);

    $this->assertEquals('entry', $data->data->type);
    $this->assertEquals('', $data->data->author->url);
    $this->assertEquals('Author', $data->data->author->name);
    $this->assertEquals('http://sanitize.example/photo.jpg', $data->data->author->photo);
  }

}
