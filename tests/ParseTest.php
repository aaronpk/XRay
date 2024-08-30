<?php
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class ParseTest extends PHPUnit\Framework\TestCase
{

    private $http;

    public function setUp(): void
    {
        $this->client = new Parse();
        $this->client->http = new p3k\HTTP\Test(dirname(__FILE__).'/data/');
        $this->client->mc = null;
    }

    private function parse($params)
    {
        $request = new Request($params);
        $response = new Response();
        return $this->client->parse($request, $response);
    }

    public function testMissingURL()
    {
        $response = $this->parse([]);

        $body = $response->getContent();
        $this->assertEquals(400, $response->getStatusCode());
        $data = json_decode($body);
        $this->assertObjectHasAttribute('error', $data);
        $this->assertEquals('missing_url', $data->error);
    }

    public function testInvalidURL()
    {
        $url = 'ftp://example.com/foo';
        $response = $this->parse(['url' => $url]);

        $body = $response->getContent();
        $this->assertEquals(400, $response->getStatusCode());
        $data = json_decode($body);
        $this->assertObjectHasAttribute('error', $data);
        $this->assertEquals('invalid_url', $data->error);
    }

    public function testTargetNotFound()
    {
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

    public function testTargetFound()
    {
        $url = 'http://source.example.com/basictest';
        $response = $this->parse(['url' => $url, 'target' => 'http://target.example.com']);

        $body = $response->getContent();
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($body);
        $this->assertObjectNotHasAttribute('error', $data);
    }

    public function testTargetNotFoundInXML()
    {
        $url = 'http://feed.example.com/atom';
        $response = $this->parse(['url' => $url, 'target' => 'http://example.net']);

        $body = $response->getContent();
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($body);
        $this->assertObjectHasAttribute('error', $data);
        $this->assertEquals('no_link_found', $data->error);
        $this->assertEquals('200', $data->code);
        $this->assertEquals($url, $data->url);
    }

    public function testHTMLContent()
    {
        $url = 'http://source.example.com/html-content';
        $response = $this->parse(['url' => $url]);

        $body = $response->getContent();
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($body);
        $this->assertObjectNotHasAttribute('name', $data->data);
        $this->assertEquals('This page has a link to target.example.com and some formatted text.', $data->data->content->text);
        $this->assertEquals('This page has a link to <a href="http://target.example.com">target.example.com</a> and some <b>formatted text</b>.', $data->data->content->html);
    }

    public function testContentFromJSONOnlyPlaintext()
    {
        $mf2JSON = json_encode(
            [
            'items' => [[
            'type' => ['h-entry'],
            'properties' => [
            'content' => [
            'plaintext'
            ]
            ]
            ]]
            ]
        );
        $xray = new \p3k\XRay();
        $parsed = $xray->process(false, $mf2JSON);
        $this->assertEquals('mf2+json', $parsed['source-format']);
        $item = $parsed['data'];
        $this->assertEquals('entry', $item['type']);
        $this->assertEquals('note', $item['post-type']);
        $this->assertEquals('plaintext', $item['content']['text']);
        $this->assertArrayNotHasKey('html', $item['content']);
    }

    public function testHTMLContentFromJSONNoPlaintext()
    {
        $mf2JSON = json_encode(
            [
            'items' => [[
            'type' => ['h-entry'],
            'properties' => [
            'content' => [[
            'html' => '<b>bold</b> <i>italic</i> text'
            ]]
            ]
            ]]
            ]
        );
        $xray = new \p3k\XRay();
        $parsed = $xray->process(false, $mf2JSON);
        $this->assertEquals('mf2+json', $parsed['source-format']);
        $item = $parsed['data'];
        $this->assertEquals('entry', $item['type']);
        $this->assertEquals('note', $item['post-type']);
        $this->assertEquals('bold italic text', $item['content']['text']);
        $this->assertEquals('<b>bold</b> <i>italic</i> text', $item['content']['html']);
    }

    public function testHTMLContentFromJSONEmptyTags()
    {
        $mf2JSON = json_encode(
            [
            'items' => [[
            'type' => ['h-entry'],
            'properties' => [
            'content' => [[
            'html' => '<b></b><i></i>'
            ]]
            ]
            ]]
            ]
        );
        $xray = new \p3k\XRay();
        $parsed = $xray->process(false, $mf2JSON);
        $this->assertEquals('mf2+json', $parsed['source-format']);
        $item = $parsed['data'];
        $this->assertEquals('entry', $item['type']);
        $this->assertEquals('note', $item['post-type']);
        $this->assertArrayNotHasKey('content', $item);
    }

    public function testHTMLContentFromJSONContentMismatch()
    {
        $mf2JSON = json_encode(
            [
            'items' => [[
            'type' => ['h-entry'],
            'properties' => [
            'content' => [[
            'value' => 'foo',
            'html' => '<b>bar</b>'
            ]]
            ]
            ]]
            ]
        );
        $xray = new \p3k\XRay();
        $parsed = $xray->process(false, $mf2JSON);
        $this->assertEquals('mf2+json', $parsed['source-format']);
        $item = $parsed['data'];
        $this->assertEquals('entry', $item['type']);
        $this->assertEquals('note', $item['post-type']);
        $this->assertEquals('bar', $item['content']['text']);
        $this->assertEquals('<b>bar</b>', $item['content']['html']);
    }

    public function testHTMLContentFromJSONNoHTML()
    {
        $mf2JSON = json_encode(
            [
            'items' => [[
            'type' => ['h-entry'],
            'properties' => [
            'content' => [[
            'value' => 'foo',
            ]]
            ]
            ]]
            ]
        );
        $xray = new \p3k\XRay();
        $parsed = $xray->process(false, $mf2JSON);
        $this->assertEquals('mf2+json', $parsed['source-format']);
        $item = $parsed['data'];
        $this->assertEquals('entry', $item['type']);
        $this->assertEquals('note', $item['post-type']);
        $this->assertArrayNotHasKey('content', $item);
    }

    public function testFindTargetLinkIsImage()
    {
        $url = 'http://source.example.com/link-is-img';
        $response = $this->parse(['url' => $url, 'target' => 'http://target.example.com/photo.jpg']);

        $body = $response->getContent();
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($body);
        $this->assertEquals('mf2+html', $data->{'source-format'});
        $this->assertEquals('photo', $data->data->{'post-type'});
        $this->assertObjectNotHasAttribute('name', $data->data);
        $this->assertEquals('This page has an img tag with the target URL.', $data->data->content->text);
    }

    public function testFindTargetLinkIsVideo()
    {
        $url = 'http://source.example.com/link-is-video';
        $response = $this->parse(['url' => $url, 'target' => 'http://target.example.com/movie.mp4']);

        $body = $response->getContent();
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($body);
        $this->assertEquals('mf2+html', $data->{'source-format'});
        $this->assertEquals('video', $data->data->{'post-type'});
        $this->assertObjectNotHasAttribute('name', $data->data);
        $this->assertEquals('This page has a video tag with the target URL.', $data->data->content->text);
    }

    public function testFindTargetLinkIsAudio()
    {
        $url = 'http://source.example.com/link-is-audio';
        $response = $this->parse(['url' => $url, 'target' => 'http://target.example.com/media.mp3']);

        $body = $response->getContent();
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($body);
        $this->assertEquals('mf2+html', $data->{'source-format'});
        $this->assertEquals('audio', $data->data->{'post-type'});
        $this->assertObjectNotHasAttribute('name', $data->data);
        $this->assertEquals('This page has an audio tag with the target URL.', $data->data->content->text);
    }

    public function testFindTargetLinkInFeed()
    {
        $url = 'http://feed.example.com/jsonfeed';
        $response = $this->parse(['url' => $url, 'target' => 'http://www.manton.org/2017/11/5993.html']);

        $body = $response->getContent();
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($body);
        $this->assertObjectNotHasAttribute('error', $data);
    }

    public function testFindTargetLinkInHTMLInFeed()
    {
        $url = 'http://feed.example.com/jsonfeed';
        $response = $this->parse(['url' => $url, 'target' => 'http://www.manton.org/2016/11/todays-social-networks-are-broken.html']);

        $body = $response->getContent();
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($body);
        $this->assertObjectNotHasAttribute('error', $data);
    }

    public function testNotFindTargetLinkInHTMLInFeed()
    {
        $url = 'http://feed.example.com/jsonfeed';
        $response = $this->parse(['url' => $url, 'target' => 'http://example.com/']);

        $body = $response->getContent();
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($body);
        $this->assertObjectHasAttribute('error', $data);
        $this->assertEquals('no_link_found', $data->error);
    }

    public function testFindRelativeTargetLink()
    {
        $url = 'http://source.example.com/multiple-urls';
        $response = $this->parse(['url' => $url, 'target' => 'http://source.example.com/photo.jpg']);

        $body = $response->getContent();
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($body);
        $this->assertObjectNotHasAttribute('error', $data);
    }

    public function testTextContent()
    {
        $url = 'http://source.example.com/text-content';
        $response = $this->parse(['url' => $url]);

        $body = $response->getContent();
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($body);
        $this->assertEquals('mf2+html', $data->{'source-format'});
        $this->assertObjectNotHasAttribute('name', $data->data);
        $this->assertEquals('This page has a link to target.example.com and some formatted text but is in a p-content element so is plaintext.', $data->data->content->text);
    }

    public function testNewlinesInTextContent() {
        $url = 'http://source.example.com/text-content-with-p-tags';
        $response = $this->parse(['url' => $url]);

        $body = $response->getContent();
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($body);
        $this->assertEquals('mf2+html', $data->{'source-format'});
        $this->assertObjectNotHasAttribute('name', $data->data);
        $this->assertEquals("Hello\nWorld", $data->data->content->text);
    }

    public function testArticleWithFeaturedImage()
    {
        $url = 'http://source.example.com/article-with-featured-image';
        $response = $this->parse(['url' => $url]);

        $body = $response->getContent();
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($body);
        $this->assertEquals('mf2+html', $data->{'source-format'});
        $this->assertEquals('article', $data->data->{'post-type'});
        $this->assertEquals('Post Title', $data->data->name);
        $this->assertEquals('This is a blog post.', $data->data->content->text);
        $this->assertEquals('http://source.example.com/featured.jpg', $data->data->featured);
    }

    public function testContentWithPrefixedName()
    {
        $url = 'http://source.example.com/content-with-prefixed-name';
        $response = $this->parse(['url' => $url]);

        $body = $response->getContent();
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($body);
        $this->assertEquals('mf2+html', $data->{'source-format'});
        $this->assertObjectNotHasAttribute('name', $data->data);
        $this->assertEquals('note', $data->data->{'post-type'});
        $this->assertEquals('This page has a link to target.example.com and some formatted text.', $data->data->content->text);
        $this->assertEquals('This page has a link to <a href="http://target.example.com">target.example.com</a> and some <b>formatted text</b>.', $data->data->content->html);
    }

    public function testContentWithDistinctName()
    {
        $url = 'http://source.example.com/content-with-distinct-name';
        $response = $this->parse(['url' => $url]);

        $body = $response->getContent();
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($body);
        $this->assertEquals('mf2+html', $data->{'source-format'});
        $this->assertEquals('Hello World', $data->data->name);
        $this->assertEquals('article', $data->data->{'post-type'});
        $this->assertEquals('This page has a link to target.example.com and some formatted text.', $data->data->content->text);
        $this->assertEquals('This page has a link to <a href="http://target.example.com">target.example.com</a> and some <b>formatted text</b>.', $data->data->content->html);
    }

    public function testNameWithNoContent()
    {
        $url = 'http://source.example.com/name-no-content';
        $response = $this->parse(['url' => $url]);

        $body = $response->getContent();
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($body);
        $this->assertEquals('mf2+html', $data->{'source-format'});
        $this->assertEquals('Hello World', $data->data->name);
        $this->assertEquals('article', $data->data->{'post-type'});
        $this->assertObjectNotHasAttribute('content', $data->data);
    }

    public function testEntryWithDuplicateCategories()
    {
        $url = 'http://source.example.com/h-entry-duplicate-categories';
        $response = $this->parse(['url' => $url]);

        $body = $response->getContent();
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($body);
        $this->assertEquals('mf2+html', $data->{'source-format'});
        $this->assertEquals(['indieweb'], $data->data->category);
    }

    public function testEntryStripHashtagWithDuplicateCategories()
    {
        $url = 'http://source.example.com/h-entry-strip-hashtag-from-categories';
        $response = $this->parse(['url' => $url]);

        $body = $response->getContent();
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($body);
        $this->assertEquals('mf2+html', $data->{'source-format'});
        $this->assertContains('indieweb', $data->data->category);
        $this->assertContains('xray', $data->data->category);
        $this->assertEquals(2, count($data->data->category));
    }

    public function testNoHEntryMarkup()
    {
        $url = 'http://source.example.com/no-h-entry';
        $response = $this->parse(['url' => $url]);

        $body = $response->getContent();
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($body);
        $this->assertEquals('unknown', $data->data->type);
        $this->assertObjectNotHasAttribute('html', $data);
    }

    public function testFindTargetInNoParsedResult()
    {
        $url = 'http://source.example.com/no-h-entry';
        $response = $this->parse(['url' => $url, 'target' => 'http://target.example.com']);

        $body = $response->getContent();
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($body);
        $this->assertObjectNotHasAttribute('error', $data);
        $this->assertEquals('unknown', $data->data->type);
    }

    public function testReplyIsURL()
    {
        $url = 'http://source.example.com/reply-is-url';
        $response = $this->parse(['url' => $url]);

        $body = $response->getContent();
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($body, true);
        $this->assertEquals('mf2+html', $data['source-format']);
        $this->assertEquals('entry', $data['data']['type']);
        $this->assertEquals('http://example.com/100', $data['data']['in-reply-to'][0]);
    }

    public function testReplyIsHCite()
    {
        $url = 'http://source.example.com/reply-is-h-cite';
        $response = $this->parse(['url' => $url]);

        $body = $response->getContent();
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($body, true);
        $this->assertEquals('mf2+html', $data['source-format']);
        $this->assertEquals('entry', $data['data']['type']);
        $this->assertEquals('reply', $data['data']['post-type']);
        $this->assertEquals('http://example.com/100', $data['data']['in-reply-to'][0]);
        $this->assertArrayHasKey('http://example.com/100', $data['data']['refs']);
        $this->assertEquals('Example Post', $data['data']['refs']['http://example.com/100']['name']);
        $this->assertEquals('http://example.com/100', $data['data']['refs']['http://example.com/100']['url']);
    }

    public function testPersonTagIsURL()
    {
        $url = 'http://source.example.com/person-tag-is-url';
        $response = $this->parse(['url' => $url]);

        $body = $response->getContent();
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($body, true);
        $this->assertEquals('mf2+html', $data['source-format']);
        $this->assertEquals('entry', $data['data']['type']);
        $this->assertEquals('http://alice.example.com/', $data['data']['category'][0]);
    }

    public function testPersonTagIsHCard()
    {
        $url = 'http://source.example.com/person-tag-is-h-card';
        $response = $this->parse(['url' => $url]);

        $body = $response->getContent();
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($body, true);
        $this->assertEquals('mf2+html', $data['source-format']);
        $this->assertEquals('entry', $data['data']['type']);
        $this->assertEquals('http://alice.example.com/', $data['data']['category'][0]);
        $this->assertArrayHasKey('http://alice.example.com/', $data['data']['refs']);
        $this->assertEquals('card', $data['data']['refs']['http://alice.example.com/']['type']);
        $this->assertEquals('http://alice.example.com/', $data['data']['refs']['http://alice.example.com/']['url']);
        $this->assertEquals('Alice', $data['data']['refs']['http://alice.example.com/']['name']);
    }

    public function testSyndicationIsURL()
    {
        $url = 'http://source.example.com/has-syndication';
        $response = $this->parse(['url' => $url]);

        $body = $response->getContent();
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($body, true);
        $this->assertEquals('mf2+html', $data['source-format']);
        $this->assertEquals('entry', $data['data']['type']);
        $this->assertEquals('http://syndicated.example/', $data['data']['syndication'][0]);
    }

    public function testHEntryNoContent()
    {
        $url = 'http://source.example.com/h-entry-no-content';
        $response = $this->parse(['url' => $url]);

        $body = $response->getContent();
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($body);
        $this->assertEquals('mf2+html', $data->{'source-format'});
        $this->assertObjectNotHasAttribute('content', $data->data);
        $this->assertEquals('This is a Post', $data->data->name);
    }

    public function testHEntryIsNotFirstObject()
    {
        $url = 'http://source.example.com/h-entry-is-not-first';
        $response = $this->parse(['url' => $url]);

        $body = $response->getContent();
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($body, true);
        $this->assertEquals('mf2+html', $data['source-format']);
        $this->assertEquals('entry', $data['data']['type']);
        $this->assertEquals('Hello World', $data['data']['content']['text']);
    }

    public function testHEntryRSVP()
    {
        $url = 'http://source.example.com/h-entry-rsvp';
        $response = $this->parse(['url' => $url]);

        $body = $response->getContent();
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($body, true);
        $this->assertEquals('mf2+html', $data['source-format']);
        $this->assertEquals('entry', $data['data']['type']);
        $this->assertEquals('rsvp', $data['data']['post-type']);
        $this->assertEquals('I\'ll be there!', $data['data']['content']['text']);
        $this->assertEquals('yes', $data['data']['rsvp']);
    }

    public function testMultipleHEntryOnPermalink()
    {
        $url = 'http://source.example.com/multiple-h-entry-on-permalink';
        $response = $this->parse(['url' => $url]);

        $body = $response->getContent();
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($body, true);
        $this->assertEquals('mf2+html', $data['source-format']);
        $this->assertEquals('entry', $data['data']['type']);
        $this->assertEquals('Primary Post', $data['data']['name']);
    }

    public function testHEntryWithHCardBeforeIt()
    {
        $url = 'http://source.example.com/h-entry-with-h-card-before-it';
        $response = $this->parse(['url' => $url]);

        $body = $response->getContent();
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($body, true);
        $this->assertEquals('mf2+html', $data['source-format']);
        $this->assertEquals('entry', $data['data']['type']);
        $this->assertEquals('Hello World', $data['data']['content']['text']);
    }

    public function testHEntryWithHCardSibling()
    {
        $url = 'http://source.example.com/h-entry-with-h-card-sibling';
        $response = $this->parse(['url' => $url]);

        $body = $response->getContent();
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($body, true);
        $this->assertEquals('mf2+html', $data['source-format']);
        $this->assertEquals('entry', $data['data']['type']);
        $this->assertEquals('Hello World', $data['data']['content']['text']);
    }

    public function testHEntryWithTwoHCardsBeforeIt()
    {
        $url = 'http://source.example.com/h-entry-with-two-h-cards-before-it';
        $response = $this->parse(['url' => $url]);

        $body = $response->getContent();
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($body, true);
        $this->assertEquals('mf2+html', $data['source-format']);
        $this->assertEquals('entry', $data['data']['type']);
        $this->assertEquals('Hello World', $data['data']['content']['text']);
    }

    public function testHEntryRedirectWithHCardSibling()
    {
        $url = 'http://source.example.com/h-entry-redirect-with-h-card-sibling';
        $response = $this->parse(['url' => $url]);

        $body = $response->getContent();
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($body, true);
        $this->assertEquals('mf2+html', $data['source-format']);
        $this->assertEquals('entry', $data['data']['type']);
        $this->assertEquals('Hello World', $data['data']['content']['text']);
    }

    public function testSingleHEntryHasNoPermalink()
    {
        $url = 'http://source.example.com/single-h-entry-has-no-permalink';
        $response = $this->parse(['url' => $url]);

        $body = $response->getContent();
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($body, true);
        $this->assertEquals('mf2+html', $data['source-format']);
        $this->assertEquals('entry', $data['data']['type']);
        $this->assertEquals('Hello World', $data['data']['content']['text']);
    }

    public function testBridgyExampleWithNoMatchingURL()
    {
        $url = 'http://source.example.com/bridgy-example';
        $response = $this->parse(['url' => $url]);

        $body = $response->getContent();
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($body, true);
        $this->assertEquals('mf2+html', $data['source-format']);
        $this->assertEquals('entry', $data['data']['type']);
    }

    public function testEventWithHTMLDescription()
    {
        $url = 'http://source.example.com/h-event';
        $response = $this->parse(['url' => $url]);

        $body = $response->getContent();
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($body, true);

        $this->assertEquals('mf2+html', $data['source-format']);
        $this->assertEquals('event', $data['data']['type']);
        $this->assertEquals('event', $data['data']['post-type']);
        $this->assertEquals('Homebrew Website Club', $data['data']['name']);
        $this->assertEquals($url, $data['data']['url']);
        $this->assertEquals('2016-03-09T18:30', $data['data']['start']);
        $this->assertEquals('2016-03-09T19:30', $data['data']['end']);
        $this->assertStringStartsWith("Are you building your own website? Indie reader? Personal publishing web app? Or some other digital magic-cloud proxy? If so, come on by and join a gathering of people with likeminded interests. Bring your friends that want to start a personal web site. Exchange information, swap ideas, talk shop, help work on a project...", $data['data']['content']['text']);
        $this->assertStringEndsWith("See the Homebrew Website Club Newsletter Volume 1 Issue 1 for a description of the first meeting.",  $data['data']['content']['text']);
        $this->assertStringStartsWith("<p>Are you building your own website? Indie reader? Personal publishing web app? Or some other digital magic-cloud proxy? If so, come on by and join a gathering of people with likeminded interests. Bring your friends that want to start a personal web site. Exchange information, swap ideas, talk shop, help work on a project...</p>", $data['data']['content']['html']);
        $this->assertStringEndsWith('<p>See the <a href="http://tantek.com/2013/332/b1/homebrew-website-club-newsletter">Homebrew Website Club Newsletter Volume 1 Issue 1</a> for a description of the first meeting.</p>', $data['data']['content']['html']);
    }

    public function testEventWithTextDescription()
    {
        $url = 'http://source.example.com/h-event-text-description';
        $response = $this->parse(['url' => $url]);

        $body = $response->getContent();
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($body, true);
        $this->assertEquals('mf2+html', $data['source-format']);
        $this->assertEquals('event', $data['data']['type']);
        $this->assertEquals('event', $data['data']['post-type']);
        $this->assertEquals('Homebrew Website Club', $data['data']['name']);
        $this->assertEquals($url, $data['data']['url']);
        $this->assertEquals('2016-03-09T18:30', $data['data']['start']);
        $this->assertEquals('2016-03-09T19:30', $data['data']['end']);
        $this->assertStringStartsWith("Are you building your own website? Indie reader? Personal publishing web app? Or some other digital magic-cloud proxy? If so, come on by and join a gathering of people with likeminded interests. Bring your friends that want to start a personal web site. Exchange information, swap ideas, talk shop, help work on a project...", $data['data']['content']['text']);
        $this->assertStringEndsWith("See the Homebrew Website Club Newsletter Volume 1 Issue 1 for a description of the first meeting.", $data['data']['content']['text']);
        $this->assertArrayNotHasKey('html', $data['data']['content']);
    }

    public function testEventWithTextContent()
    {
        $url = 'http://source.example.com/h-event-text-content';
        $response = $this->parse(['url' => $url]);

        $body = $response->getContent();
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($body, true);

        $this->assertEquals('mf2+html', $data['source-format']);
        $this->assertEquals('event', $data['data']['type']);
        $this->assertEquals('event', $data['data']['post-type']);
        $this->assertEquals('Homebrew Website Club', $data['data']['name']);
        $this->assertEquals($url, $data['data']['url']);
        $this->assertEquals('2016-03-09T18:30', $data['data']['start']);
        $this->assertEquals('2016-03-09T19:30', $data['data']['end']);
        $this->assertStringStartsWith("Are you building your own website? Indie reader? Personal publishing web app? Or some other digital magic-cloud proxy? If so, come on by and join a gathering of people with likeminded interests. Bring your friends that want to start a personal web site. Exchange information, swap ideas, talk shop, help work on a project...", $data['data']['content']['text']);
        $this->assertStringEndsWith("See the Homebrew Website Club Newsletter Volume 1 Issue 1 for a description of the first meeting.", $data['data']['content']['text']);
        $this->assertArrayNotHasKey('html', $data['data']['content']);
        $this->assertEquals('card', $data['data']['author']['type']);
        $this->assertEquals('Event Author', $data['data']['author']['name']);
        $this->assertEquals('http://source.example.com/', $data['data']['author']['url']);
    }

    public function testEventWithHCardLocation()
    {
        $url = 'http://source.example.com/h-event-with-h-card-location';
        $response = $this->parse(['url' => $url]);

        $body = $response->getContent();
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($body, true);
        $this->assertEquals('mf2+html', $data['source-format']);
        $this->assertEquals('event', $data['data']['type']);
        $this->assertEquals('event', $data['data']['post-type']);
        $this->assertEquals('Homebrew Website Club', $data['data']['name']);
        $this->assertEquals($url, $data['data']['url']);
        $this->assertEquals('2016-02-09T18:30', $data['data']['start']);
        $this->assertEquals('2016-02-09T19:30', $data['data']['end']);
        $this->assertEquals('card', $data['data']['location']['type']);
        $this->assertEquals('http://source.example.com/venue', $data['data']['location']['url']);
        $this->assertEquals('Venue', $data['data']['location']['name']);
        $this->assertEquals('45.5', $data['data']['location']['latitude']);
        $this->assertEquals('-122.6', $data['data']['location']['longitude']);
        $this->assertEquals('1234 Main St', $data['data']['location']['street-address']);
        $this->assertEquals('Portland', $data['data']['location']['locality']);
        $this->assertEquals('Oregon', $data['data']['location']['region']);
        $this->assertEquals('USA', $data['data']['location']['country-name']);
    }

    public function testEventWithFeaturedImage()
    {
        $url = 'http://source.example.com/h-event-featured';
        $response = $this->parse(['url' => $url]);

        $body = $response->getContent();
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($body, true);
        $this->assertEquals('mf2+html', $data['source-format']);
        $this->assertEquals('event', $data['data']['type']);
        $this->assertEquals('http://source.example.com/featured.jpg', $data['data']['featured']);
    }

    public function testMf2ReviewOfProduct()
    {
        $url = 'http://source.example.com/h-review-of-product';
        $response = $this->parse(['url' => $url]);

        $body = $response->getContent();
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($body, true);
        $this->assertEquals('mf2+html', $data['source-format']);
        $this->assertEquals('review', $data['data']['type']);
        $this->assertEquals('review', $data['data']['post-type']);
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

    public function testMf2ReviewOfHCard()
    {
        $url = 'http://source.example.com/h-review-of-h-card';
        $response = $this->parse(['url' => $url]);

        $body = $response->getContent();
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($body, true);
        $this->assertEquals('mf2+html', $data['source-format']);
        $this->assertEquals('review', $data['data']['type']);
        $this->assertEquals('review', $data['data']['post-type']);
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

    public function testMf1Review()
    {
        $url = 'http://source.example.com/hReview';
        $response = $this->parse(['url' => $url]);

        $body = $response->getContent();
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($body, true);
        $this->assertEquals('mf2+html', $data['source-format']);
        $this->assertEquals('review', $data['data']['type']);
        $this->assertEquals('review', $data['data']['post-type']);
        $this->assertEquals('Not great', $data['data']['name']);
        $this->assertEquals('3', $data['data']['rating']);
        $this->assertEquals('5', $data['data']['best']);
        $this->assertEquals('This is the full text of the review', $data['data']['content']['text']);
        $this->assertContains('http://product.example.com/', $data['data']['item']);
        $this->assertArrayHasKey('http://product.example.com/', $data['data']['refs']);
        $this->assertEquals('item', $data['data']['refs']['http://product.example.com/']['type']);
        $this->assertEquals('The Reviewed Product', $data['data']['refs']['http://product.example.com/']['name']);
        $this->assertEquals('http://product.example.com/', $data['data']['refs']['http://product.example.com/']['url']);
    }

    public function testMf2Recipe()
    {
        $url = 'http://source.example.com/h-recipe';
        $response = $this->parse(['url' => $url]);

        $body = $response->getContent();
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($body, true);

        $this->assertEquals('mf2+html', $data['source-format']);
        $this->assertEquals('recipe', $data['data']['type']);
        $this->assertEquals('recipe', $data['data']['post-type']);
        $this->assertEquals('Cookie Recipe', $data['data']['name']);
        $this->assertEquals('12 Cookies', $data['data']['yield']);
        $this->assertEquals('PT30M', $data['data']['duration']);
        $this->assertEquals('The best chocolate chip cookie recipe', $data['data']['summary']);
        $this->assertContains('3 cups flour', $data['data']['ingredient']);
        $this->assertContains('chocolate chips', $data['data']['ingredient']);
    }

    public function testEntryIsAnInvitee()
    {
        $url = 'http://source.example.com/bridgy-invitee';
        $response = $this->parse(['url' => $url]);

        $body = $response->getContent();
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($body, true);

        $this->assertEquals('mf2+html', $data['source-format']);
        $this->assertEquals('entry', $data['data']['type']);
        $this->assertEquals('https://www.facebook.com/555707837940351#tantek', $data['data']['url']);
        $this->assertContains('https://www.facebook.com/tantek.celik', $data['data']['invitee']);
        $this->assertArrayHasKey('https://www.facebook.com/tantek.celik', $data['data']['refs']);
        $this->assertEquals('Tantek Çelik', $data['data']['refs']['https://www.facebook.com/tantek.celik']['name']);
    }

    public function testEntryAtFragmentID()
    {
        $url = 'http://source.example.com/fragment-id#comment-1000';
        $response = $this->parse(['url' => $url]);

        $body = $response->getContent();
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($body, true);
        $this->assertEquals('mf2+html', $data['source-format']);
        $this->assertEquals('entry', $data['data']['type']);
        $this->assertEquals('note', $data['data']['post-type']);
        $this->assertEquals('Comment text', $data['data']['content']['text']);
        $this->assertEquals('http://source.example.com/fragment-id#comment-1000', $data['data']['url']);
        $this->assertTrue($data['info']['found_fragment']);
    }

    public function testEntryAtNonExistentFragmentID()
    {
        $url = 'http://source.example.com/fragment-id#comment-404';
        $response = $this->parse(['url' => $url]);

        $body = $response->getContent();
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($body, true);
        $this->assertEquals('mf2+html', $data['source-format']);
        $this->assertEquals('entry', $data['data']['type']);
        $this->assertEquals('http://source.example.com/fragment-id', $data['data']['url']);
        $this->assertFalse($data['info']['found_fragment']);
    }

    public function testCheckin()
    {
        $url = 'http://source.example.com/checkin';
        $response = $this->parse(['url' => $url]);

        $body = $response->getContent();
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($body, true);

        $this->assertEquals('mf2+html', $data['source-format']);
        $this->assertEquals('entry', $data['data']['type']);
        $venue = $data['data']['checkin'];
        $this->assertEquals('checkin', $data['data']['post-type']);
        $this->assertEquals('https://foursquare.com/v/57104d2e498ece022e169dca', $venue['url']);
        $this->assertEquals('DreamHost', $venue['name']);
        $this->assertEquals('45.518716', $venue['latitude']);
        $this->assertEquals('Homebrew Website Club!', $data['data']['content']['text']);
        $this->assertEquals('https://aaronparecki.com/2017/06/07/12/photo.jpg', $data['data']['photo'][0]);
        $this->assertEquals('2017-06-07T17:14:40-07:00', $data['data']['published']);
        $this->assertArrayNotHasKey('name', $data['data']);
    }

    public function testCheckinURLOnly()
    {
        $url = 'http://source.example.com/checkin-url';
        $response = $this->parse(['url' => $url]);

        $body = $response->getContent();
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($body, true);

        $this->assertEquals('mf2+html', $data['source-format']);
        $this->assertEquals('entry', $data['data']['type']);
        $this->assertEquals('checkin', $data['data']['post-type']);
        $venue = $data['data']['checkin'];
        $this->assertEquals('https://foursquare.com/v/57104d2e498ece022e169dca', $venue['url']);
        $this->assertEquals('Homebrew Website Club!', $data['data']['content']['text']);
        $this->assertEquals('https://aaronparecki.com/2017/06/07/12/photo.jpg', $data['data']['photo'][0]);
        $this->assertEquals('2017-06-07T17:14:40-07:00', $data['data']['published']);
        $this->assertArrayNotHasKey('name', $data['data']);
    }

    public function testXKCD()
    {
        $url = 'http://xkcd.com/1810/';
        $response = $this->parse(['url' => $url]);

        $body = $response->getContent();
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($body, true);
        $this->assertEquals(200, $data['code']);
        $this->assertEquals('xkcd', $data['source-format']);
        $this->assertEquals('entry', $data['data']['type']);
        $this->assertEquals('photo', $data['data']['post-type']);
        $this->assertEquals('http://xkcd.com/1810/', $data['data']['url']);
        $this->assertEquals('Chat Systems', $data['data']['name']);
        $this->assertContains('http://imgs.xkcd.com/comics/chat_systems_2x.png', $data['data']['photo']);
    }

    public function testEntryHasMultipleURLs()
    {
        $url = 'http://source.example.com/multiple-urls';
        $response = $this->parse(['url' => $url]);

        $body = $response->getContent();
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($body, true);

        // Should prioritize the URL on the same domain
        $this->assertEquals($url, $data['data']['url']);
    }

    public function testEntryHasMultipleURLsOffDomain()
    {
        $url = 'http://source.example.com/multiple-urls-off-domain';
        $response = $this->parse(['url' => $url]);

        $body = $response->getContent();
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($body, true);

        // Neither URL is on the same domain, so should use the first
        $this->assertEquals('http://one.example.com/test', $data['data']['url']);
    }

    public function testInputIsJSON()
    {
        $url = 'http://example.com/entry';

        $mf2json = ['items' => [
        [
        'type' => ['h-entry'],
        'properties' => [
          'content' => [['html' => 'Hello World']]
        ]
        ]
        ]];

        $response = $this->parse(
            [
            'body' => $mf2json,
            'url' => $url,
            ]
        );

        $body = $response->getContent();
        $data = json_decode($body, true);

        $this->assertEquals('mf2+json', $data['source-format']);
        $this->assertEquals('Hello World', $data['data']['content']['text']);
    }

    public function testInputIsParsedMf2()
    {
        $html = '<div class="h-entry"><p class="p-content p-name">Hello World</p><img src="/photo.jpg"></p></div>';
        $mf2 = Mf2\parse($html, 'http://example.com/entry');

        $url = 'http://example.com/entry';
        $response = $this->parse(
            [
            'url' => $url,
            'body' => json_encode($mf2)
            ]
        );

        $body = $response->getContent();
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($body, true);
        $this->assertEquals('mf2+json', $data['source-format']);

        $this->assertEquals('Hello World', $data['data']['content']['text']);
        $this->assertEquals('http://example.com/photo.jpg', $data['data']['photo'][0]);
    }

    public function testInputIsParsedMf2WithHTML()
    {
        $html = '<div class="h-entry"><p class="e-content p-name"><b>Hello</b> <i>World</i></p><img src="/photo.jpg"></p></div>';
        $mf2 = Mf2\parse($html, 'http://example.com/entry');

        $url = 'http://example.com/entry';
        $response = $this->parse(
            [
            'url' => $url,
            'body' => json_encode($mf2)
            ]
        );

        $body = $response->getContent();
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($body, true);

        $this->assertEquals('mf2+json', $data['source-format']);
        $this->assertEquals('Hello World', $data['data']['content']['text']);
        $this->assertEquals('<b>Hello</b> <i>World</i>', $data['data']['content']['html']);
        $this->assertEquals('http://example.com/photo.jpg', $data['data']['photo'][0]);
    }

    public function testHApp()
    {
        $url = 'http://source.example.com/h-app';
        $response = $this->parse(['url' => $url]);

        $body = $response->getContent();
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($body, true);
        $this->assertEquals('mf2+html', $data['source-format']);
        $this->assertEquals('app', $data['data']['type']);
        $this->assertEquals('http://source.example.com/images/quill.png', $data['data']['logo']);
        $this->assertEquals('Quill', $data['data']['name']);
        $this->assertEquals($url, $data['data']['url']);
        $this->assertEquals(['http://source.example.com/redirect1','http://source.example.com/redirect2'], $data['data']['redirect-uri']);
        $this->assertArrayNotHasKey('photo', $data['data']);
    }

    public function testHXApp()
    {
        $url = 'http://source.example.com/h-x-app';
        $response = $this->parse(['url' => $url]);

        $body = $response->getContent();
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($body);
        $this->assertEquals('mf2+html', $data->{'source-format'});
        $this->assertEquals('app', $data->data->type);
        $this->assertEquals('http://source.example.com/images/quill.png', $data->data->logo);
        $this->assertEquals('Quill', $data->data->name);
        $this->assertEquals($url, $data->data->url);
        $this->assertObjectNotHasAttribute('photo', $data->data);
    }

    public function testDuplicateReplyURLValues()
    {
        $url = 'http://source.example.com/duplicate-in-reply-to-urls';
        $response = $this->parse(['url' => $url]);

        $body = $response->getContent();
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($body, true);

        $this->assertEquals('http://example.com/100', $data['data']['in-reply-to'][0]);
        $this->assertEquals(1, count($data['data']['in-reply-to']));
    }

    public function testDuplicateLikeOfURLValues()
    {
        $url = 'http://source.example.com/duplicate-like-of-urls';
        $response = $this->parse(['url' => $url]);

        $body = $response->getContent();
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($body, true);

        $this->assertEquals('http://example.com/100', $data['data']['like-of'][0]);
        $this->assertEquals(1, count($data['data']['like-of']));
    }

    public function testQuotationOf()
    {
        $url = 'http://source.example.com/quotation-of';
        $response = $this->parse(['url' => $url]);

        $body = $response->getContent();
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($body, true);

        $this->assertEquals('I’m so making this into a t-shirt', $data['data']['content']['text']);
        $this->assertEquals('https://twitter.com/gitlost/status/1015005409726357504', $data['data']['quotation-of']);
        $this->assertArrayHasKey('https://twitter.com/gitlost/status/1015005409726357504', $data['data']['refs']);
        $q = $data['data']['refs']['https://twitter.com/gitlost/status/1015005409726357504'];
        $this->assertEquals("Still can't git fer shit", $q['content']['text']);
        $this->assertEquals('2018-07-05T22:52:02+00:00', $q['published']);
    }

    public function testHTML5Markup()
    {
        $url = 'http://source.example.com/html5-tags';
        $response = $this->parse(['url' => $url]);

        $body = $response->getContent();
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($body, true);

        $this->assertEquals('Hello World', $data['data']['name']);
        $this->assertEquals('The content of the blog post', $data['data']['content']['text']);
    }

    public function testRelAlternateToMf2JSON()
    {
        $url = 'http://source.example.com/rel-alternate-mf2-json';
        $response = $this->parse(['url' => $url]);

        $body = $response->getContent();
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($body, true);

        $this->assertEquals('mf2+json', $data['source-format']);
        $this->assertEquals('http://source.example.com/rel-alternate-mf2-json.json', $data['parsed-url']);
        $this->assertEquals('Pretty great to see a new self-hosted IndieAuth server! Congrats @nilshauk, and great project name! https://twitter.com/nilshauk/status/1017485223716630528', $data['data']['content']['text']);
    }

    public function testRelAlternateToNotFoundURL()
    {
        $url = 'http://source.example.com/rel-alternate-not-found';
        $response = $this->parse(['url' => $url]);

        $body = $response->getContent();
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($body, true);

        $this->assertEquals('mf2+html', $data['source-format']);
        $this->assertArrayNotHasKey('parsed-url', $data);
        $this->assertEquals('Test content with a rel alternate link to a 404 page', $data['data']['content']['text']);
    }

    public function testRelAlternatePrioritizesJSON()
    {
        $url = 'http://source.example.com/rel-alternate-priority';
        $response = $this->parse(['url' => $url]);

        $body = $response->getContent();
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($body, true);

        $this->assertEquals('mf2+json', $data['source-format']);
        $this->assertEquals('http://source.example.com/rel-alternate-priority.json', $data['parsed-url']);
        $this->assertEquals('This is the content in the MF2 JSON file', $data['data']['content']['text']);
    }

    public function testRelAlternatePrioritizesMf2OverAS2()
    {
        $url = 'http://source.example.com/rel-alternate-priority-mf2-as2';
        $response = $this->parse(['url' => $url]);

        $body = $response->getContent();
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($body, true);

        $this->assertEquals('mf2+json', $data['source-format']);
        $this->assertEquals('http://source.example.com/rel-alternate-priority.json', $data['parsed-url']);
        $this->assertEquals('This is the content in the MF2 JSON file', $data['data']['content']['text']);
    }

    public function testRelAlternateIgnoreAS2AlternateOption()
    {
        $url = 'http://source.example.com/rel-alternate-as2';
        $response = $this->parse(['url' => $url, 'ignore-as2' => true]);

        $body = $response->getContent();
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($body, true);

        $this->assertEquals('mf2+html', $data['source-format']);
        $this->assertArrayNotHasKey('parsed-url', $data);
        $this->assertEquals('This is the content in the HTML instead of the AS2 JSON', $data['data']['content']['text']);
    }

    public function testRelAlternateFallsBackOnInvalidJSON()
    {
        $url = 'http://source.example.com/rel-alternate-fallback';
        $response = $this->parse(['url' => $url]);

        $body = $response->getContent();
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($body, true);

        $this->assertEquals('mf2+html', $data['source-format']);
        $this->assertArrayNotHasKey('parsed-url', $data);
        $this->assertEquals('XRay should use this content since the JSON in the rel-alternate is invalid', $data['data']['content']['text']);
    }

    public function testMultipleContentTypeHeaders()
    {
        $url = 'http://source.example.com/multiple-content-type';
        $response = $this->parse(['url' => $url]);

        $body = $response->getContent();
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($body, true);

        $this->assertEquals('mf2+html', $data['source-format']);
    }

    public function testFollowOf()
    {
        $url = 'http://source.example.com/bridgy-follow';
        $response = $this->parse(['url' => $url]);

        $body = $response->getContent();
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($body, true);

        $this->assertEquals('https://realize.be/', $data['data']['follow-of']);
        $this->assertEquals('follow', $data['data']['post-type']);
    }

    public function testFollowOfHCard()
    {
        $url = 'http://source.example.com/follow-of-h-card';
        $response = $this->parse(['url' => $url]);

        $body = $response->getContent();
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($body, true);

        $this->assertEquals('https://realize.be/', $data['data']['follow-of']);
        $this->assertEquals('follow', $data['data']['post-type']);
    }

    public function testRelCanonical()
    {
        $url = 'http://source.example.com/rel-canonical';
        $response = $this->parse(['url' => $url]);

        $body = $response->getContent();
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($body, true);

        $this->assertEquals('https://aaronparecki.com/2019/12/01/10/homeautomation', $data['data']['url']);
        $this->assertEquals('https://aaronparecki.com/2019/12/01/10/homeautomation', $data['data']['rels']['canonical']);
    }

    public function testTargetLinkOutsideHEntry()
    {
        $url = 'http://source.example.com/target-test-link-outside-h-entry';
        $response = $this->parse(['url' => $url, 'target' => 'https://target.example.com/']);

        $body = $response->getContent();
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($body, true);

        $this->assertEquals('no_link_found', $data['error']);
    }

    public function testTargetLinkWithBadMf1()
    {
        $url = 'http://source.example.com/target-test-only-bad-mf1';
        $response = $this->parse(['url' => $url, 'target' => 'https://target.example.com/']);

        $body = $response->getContent();
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($body, true);

        $this->assertEquals('unknown', $data['data']['type']);
    }

    public function testTargetLinkWithValidMf1()
    {
        $url = 'http://source.example.com/target-test-only-good-mf1';
        $response = $this->parse(['url' => $url, 'target' => 'https://target.example.com/']);

        $body = $response->getContent();
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($body, true);

        $this->assertEquals('entry', $data['data']['type']);
        $this->assertEquals('<a href="https://target.example.com/">target</a>', $data['data']['content']['html']);
    }

    public function testTargetLinkOutsideValidMf1()
    {
        $url = 'http://source.example.com/target-test-link-outside-valid-mf1';
        $response = $this->parse(['url' => $url, 'target' => 'https://target.example.com/']);

        $body = $response->getContent();
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($body, true);

        // Since the link was found in the HTML, but not in the parsed tree, it shouldn't return the parsed document
        $this->assertEquals('unknown', $data['data']['type']);
    }

    public function testDisableMf1Parsing()
    {
        $url = 'http://source.example.com/target-test-only-good-mf1';
        $response = $this->parse(['url' => $url, 'include-mf1' => 'false']);

        $body = $response->getContent();
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($body, true);

        $this->assertEquals('unknown', $data['data']['type']);
    }

    public function testEnableMf1Parsing()
    {
        $url = 'http://source.example.com/target-test-only-good-mf1';
        $response = $this->parse(['url' => $url, 'include-mf1' => 'true']);

        $body = $response->getContent();
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($body, true);

        $this->assertEquals('entry', $data['data']['type']);
    }

    public function testMissingContent() {
        $url = 'http://source.example.com/bookmark-missing-content';
        $response = $this->parse(['url' => $url]);
        $body = $response->getContent();
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($body, true);

        $this->assertArrayNotHasKey('content', $data['data']);
    }

}
