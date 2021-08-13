<?php
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class FetchTest extends PHPUnit\Framework\TestCase
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

    public function testRedirectLimit()
    {
        $url = 'http://redirect.example.com/3';
        $response = $this->parse(
            [
            'url' => $url,
            'max_redirects' => 1
            ]
        );

        $body = $response->getContent();
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($body);
        $this->assertObjectHasAttribute('error', $data);
        $this->assertEquals('too_many_redirects', $data->error);

        $url = 'http://redirect.example.com/2';
        $response = $this->parse(
            [
            'url' => $url,
            'max_redirects' => 1
            ]
        );
        $body = $response->getContent();
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($body);
        $this->assertObjectHasAttribute('error', $data);
        $this->assertEquals('too_many_redirects', $data->error);
    }

    public function testRedirectUnderLimit()
    {
        $url = 'http://redirect.example.com/2';
        $response = $this->parse(
            [
            'url' => $url,
            'max_redirects' => 2
            ]
        );

        $body = $response->getContent();
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($body);
        $this->assertObjectNotHasAttribute('error', $data);
        $this->assertEquals(200, $data->code);
        $this->assertEquals('The Final Page', $data->data->name);
        $this->assertEquals('http://redirect.example.com/0', $data->url);
    }

    public function testReturnsHTTPStatusCode()
    {
        $url = 'http://redirect.example.com/code-418';
        $response = $this->parse(
            [
            'url' => $url
            ]
        );

        $body = $response->getContent();
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($body);
        $this->assertObjectNotHasAttribute('error', $data);
        $this->assertEquals($url, $data->url);
        $this->assertEquals(418, $data->code);
    }

    public function testReturnsForbidden()
    {
        $url = 'http://redirect.example.com/code-403';
        $response = $this->parse(
            [
            'url' => $url
            ]
        );

        $body = $response->getContent();
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($body);
        $this->assertObjectHasAttribute('error', $data);
        $this->assertEquals('forbidden', $data->error);
        $this->assertEquals($url, $data->url);
        $this->assertEquals(403, $data->code);
    }

    public function testReturnsUnauthorized()
    {
        $url = 'http://redirect.example.com/code-401';
        $response = $this->parse(
            [
            'url' => $url
            ]
        );

        $body = $response->getContent();
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($body);
        $this->assertObjectHasAttribute('error', $data);
        $this->assertEquals('unauthorized', $data->error);
        $this->assertEquals($url, $data->url);
        $this->assertEquals(401, $data->code);
    }

    public function testDeleted()
    {
        $url = 'http://source.example.com/deleted-gone';
        $response = $this->parse(
            [
            'url' => $url
            ]
        );

        $body = $response->getContent();
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($body);
        $this->assertObjectNotHasAttribute('error', $data);
        $this->assertEquals(410, $data->code);
        $this->assertEquals('This post has been deleted.', $data->data->content->text);
    }

    public function testDeletedEmptyBody()
    {
        $url = 'http://source.example.com/deleted-empty';
        $response = $this->parse(
            [
            'url' => $url
            ]
        );

        $body = $response->getContent();
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($body);
        $this->assertObjectNotHasAttribute('error', $data);
        $this->assertEquals(410, $data->code);
        $this->assertEquals('unknown', $data->data->type);
    }

    public function testDeletedTargetProvided()
    {
        $url = 'http://source.example.com/deleted-gone';
        $response = $this->parse(
            [
            'url' => $url,
            'target' => 'http://example.com/'
            ]
        );

        $body = $response->getContent();
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($body);
        $this->assertObjectHasAttribute('error', $data);
        $this->assertEquals('no_link_found', $data->error);
        $this->assertEquals(410, $data->code);
    }

    public function testMetaEquivDeleted()
    {
        $url = 'http://source.example.com/deleted';
        $response = $this->parse(
            [
            'url' => $url
            ]
        );

        $body = $response->getContent();
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($body);
        $this->assertObjectNotHasAttribute('error', $data);
        $this->assertEquals(410, $data->code);
        $this->assertEquals('This post has been deleted.', $data->data->content->text);
    }

    public function testMetaEquivDeletedCaps()
    {
        $url = 'http://source.example.com/deleted-2';
        $response = $this->parse(
            [
            'url' => $url
            ]
        );

        $body = $response->getContent();
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($body);
        $this->assertObjectNotHasAttribute('error', $data);
        $this->assertEquals(410, $data->code);
        $this->assertEquals('This post has been deleted.', $data->data->content->text);
    }

    public function testMetaEquivDeletedTargetProvided()
    {
        // for example when verifying a webmention but the source was replaced with an html deleted page

        $url = 'http://source.example.com/deleted';
        $response = $this->parse(
            [
            'url' => $url,
            'target' => 'http://example.com/'
            ]
        );

        $body = $response->getContent();
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($body);
        $this->assertObjectHasAttribute('error', $data);
        $this->assertEquals('no_link_found', $data->error);
        $this->assertEquals(410, $data->code);
    }
}
