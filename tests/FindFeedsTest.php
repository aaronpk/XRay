<?php
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class FindFeedsTest extends PHPUnit\Framework\TestCase
{

    private $http;

    public function setUp(): void
    {
        $this->client = new Feeds();
        $this->client->http = new p3k\HTTP\Test(dirname(__FILE__).'/data/');
        $this->client->mc = null;
    }

    private function parse($params)
    {
        $request = new Request($params);
        $response = new Response();
        return $this->client->find($request, $response);
    }

    // h-feed with no alternates
    public function testMf2HFeed()
    {
        $url = 'http://feed.example.com/h-feed-with-child-author';
        $response = $this->parse(['url' => $url]);

        $body = $response->getContent();
        $this->assertEquals(200, $response->getStatusCode());
        $feeds = json_decode($body)->feeds;

        $this->assertEquals(1, count($feeds));
        $this->assertEquals('http://feed.example.com/h-feed-with-child-author', $feeds[0]->url);
        $this->assertEquals('microformats', $feeds[0]->type);
    }

    // h-feed that links to Atom alternate
    public function testMf2WithAtomAlternate()
    {
        $url = 'http://feed.example.com/h-feed-with-atom-alternate';
        $response = $this->parse(['url' => $url]);

        $body = $response->getContent();
        $this->assertEquals(200, $response->getStatusCode());
        $feeds = json_decode($body)->feeds;

        $this->assertEquals(2, count($feeds));
        // Should rank h-feed above Atom
        $this->assertEquals('http://feed.example.com/h-feed-with-atom-alternate', $feeds[0]->url);
        $this->assertEquals('microformats', $feeds[0]->type);
        $this->assertEquals('http://feed.example.com/atom', $feeds[1]->url);
        $this->assertEquals('atom', $feeds[1]->type);
    }

    // h-feed that links to RSS alternate
    public function testMf2WithRSSAlternate()
    {
        $url = 'http://feed.example.com/h-feed-with-rss-alternate';
        $response = $this->parse(['url' => $url]);

        $body = $response->getContent();
        $this->assertEquals(200, $response->getStatusCode());
        $feeds = json_decode($body)->feeds;

        $this->assertEquals(2, count($feeds));
        // Should rank JSONFeed above Atom
        $this->assertEquals('http://feed.example.com/h-feed-with-rss-alternate', $feeds[0]->url);
        $this->assertEquals('microformats', $feeds[0]->type);
        $this->assertEquals('http://feed.example.com/podcast.xml', $feeds[1]->url);
        $this->assertEquals('rss', $feeds[1]->type);
    }

    // No mf2 but links to Atom alternate
    public function testNoMf2()
    {
        $url = 'http://feed.example.com/html-with-atom-alternate';
        $response = $this->parse(['url' => $url]);

        $body = $response->getContent();
        $this->assertEquals(200, $response->getStatusCode());
        $feeds = json_decode($body)->feeds;

        $this->assertEquals(1, count($feeds));
        $this->assertEquals('http://feed.example.com/atom', $feeds[0]->url);
        $this->assertEquals('atom', $feeds[0]->type);
    }

    public function testNoMf2WithJSONAndAtom()
    {
        $url = 'http://feed.example.com/html-with-json-and-atom';
        $response = $this->parse(['url' => $url]);

        $body = $response->getContent();
        $this->assertEquals(200, $response->getStatusCode());
        $feeds = json_decode($body)->feeds;

        $this->assertEquals(2, count($feeds));
        // Should rank JSONFeed above Atom
        $this->assertEquals('http://feed.example.com/jsonfeed', $feeds[0]->url);
        $this->assertEquals('jsonfeed', $feeds[0]->type);
        $this->assertEquals('http://feed.example.com/atom', $feeds[1]->url);
        $this->assertEquals('atom', $feeds[1]->type);
    }

    // input URL is an Atom feed
    public function testInputIsAtom()
    {
        $url = 'http://feed.example.com/atom';
        $response = $this->parse(['url' => $url]);

        $body = $response->getContent();
        $this->assertEquals(200, $response->getStatusCode());
        $feeds = json_decode($body)->feeds;

        $this->assertEquals(1, count($feeds));
        $this->assertEquals('http://feed.example.com/atom', $feeds[0]->url);
        $this->assertEquals('atom', $feeds[0]->type);
    }

    // input URL is an RSS feed with xml content type
    public function testInputIsRSSWithXML()
    {
        $url = 'http://feed.example.com/rss-xml-content-type';
        $response = $this->parse(['url' => $url]);

        $body = $response->getContent();
        $this->assertEquals(200, $response->getStatusCode());
        $feeds = json_decode($body)->feeds;

        $this->assertEquals(1, count($feeds));
        $this->assertEquals('http://feed.example.com/rss-xml-content-type', $feeds[0]->url);
        $this->assertEquals('rss', $feeds[0]->type);
    }

    // input URL redirects to an Atom feed
    public function testInputIsRedirectToAtom()
    {
        $url = 'http://feed.example.com/redirect-to-atom';
        $response = $this->parse(['url' => $url]);

        $body = $response->getContent();
        $this->assertEquals(200, $response->getStatusCode());
        $feeds = json_decode($body)->feeds;

        $this->assertEquals(1, count($feeds));
        $this->assertEquals('http://feed.example.com/atom', $feeds[0]->url);
        $this->assertEquals('atom', $feeds[0]->type);
    }

    // input URL is a temporary redirect to another page.
    // report the original input URL
    public function testInputIsTemporaryRedirect()
    {
        $url = 'http://feed.example.com/temporary-redirect';
        $response = $this->parse(['url' => $url]);

        $body = $response->getContent();
        $this->assertEquals(200, $response->getStatusCode());
        $feeds = json_decode($body)->feeds;

        $this->assertEquals(1, count($feeds));
        $this->assertEquals('http://feed.example.com/temporary-redirect', $feeds[0]->url);
        $this->assertEquals('microformats', $feeds[0]->type);
    }

    public function testInputIsPermanentRedirect()
    {
        $url = 'http://feed.example.com/permanent-redirect';
        $response = $this->parse(['url' => $url]);

        $body = $response->getContent();
        $this->assertEquals(200, $response->getStatusCode());
        $feeds = json_decode($body)->feeds;

        $this->assertEquals(1, count($feeds));
        $this->assertEquals('http://feed.example.com/permanent-redirect-target', $feeds[0]->url);
        $this->assertEquals('microformats', $feeds[0]->type);
    }

    // input URL is an RSS feed
    public function testInputIsRSS()
    {
        $url = 'http://feed.example.com/rss';
        $response = $this->parse(['url' => $url]);

        $body = $response->getContent();
        $this->assertEquals(200, $response->getStatusCode());
        $feeds = json_decode($body)->feeds;

        $this->assertEquals(1, count($feeds));
        $this->assertEquals('http://feed.example.com/rss', $feeds[0]->url);
        $this->assertEquals('rss', $feeds[0]->type);
    }

    // input URL is a JSON feed
    public function testInputIsJSONFeed()
    {
        $url = 'http://feed.example.com/jsonfeed';
        $response = $this->parse(['url' => $url]);

        $body = $response->getContent();
        $this->assertEquals(200, $response->getStatusCode());
        $feeds = json_decode($body)->feeds;

        $this->assertEquals(1, count($feeds));
        $this->assertEquals('http://feed.example.com/jsonfeed', $feeds[0]->url);
        $this->assertEquals('jsonfeed', $feeds[0]->type);
    }

    public function testInputIsMicroformats2JSON()
    {
        $url = 'http://feed.example.com/microformats2-json';
        $response = $this->parse(['url' => $url]);

        $body = $response->getContent();
        $this->assertEquals(200, $response->getStatusCode());
        $feeds = json_decode($body)->feeds;

        $this->assertEquals(1, count($feeds));
        $this->assertEquals('http://feed.example.com/microformats2-json', $feeds[0]->url);
        $this->assertEquals('microformats', $feeds[0]->type);
    }

    public function testInputIsMF2JSON()
    {
        $url = 'http://feed.example.com/mf2-json';
        $response = $this->parse(['url' => $url]);

        $body = $response->getContent();
        $this->assertEquals(200, $response->getStatusCode());
        $feeds = json_decode($body)->feeds;

        $this->assertEquals(1, count($feeds));
        $this->assertEquals('http://feed.example.com/mf2-json', $feeds[0]->url);
        $this->assertEquals('microformats', $feeds[0]->type);
    }
}
