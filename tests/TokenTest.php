<?php
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class TokenTest extends PHPUnit\Framework\TestCase
{

    private $http;

    public function setUp(): void
    {
        $this->client = new Token();
        $this->client->http = new p3k\HTTP\Test(dirname(__FILE__).'/data/');
    }

    private function token($params)
    {
        $request = new Request($params);
        $response = new Response();
        return $this->client->token($request, $response);
    }

    public function testMissingURL()
    {
        $response = $this->token([]);

        $body = $response->getContent();
        $this->assertEquals(400, $response->getStatusCode());
        $data = json_decode($body);
        $this->assertObjectHasAttribute('error', $data);
        $this->assertEquals('invalid_request', $data->error);
    }

    public function testInvalidURL()
    {
        $url = 'ftp://example.com/foo';
        $response = $this->token(['source' => $url, 'code' => '1234']);

        $body = $response->getContent();
        $this->assertEquals(400, $response->getStatusCode());
        $data = json_decode($body);
        $this->assertObjectHasAttribute('error', $data);
        $this->assertEquals('invalid_url', $data->error);
    }

    public function testMissingCode()
    {
        $response = $this->token(['source' => 'http://example.com/']);

        $body = $response->getContent();
        $this->assertEquals(400, $response->getStatusCode());
        $data = json_decode($body);
        $this->assertObjectHasAttribute('error', $data);
        $this->assertEquals('invalid_request', $data->error);
    }

    public function testNoLinkHeaders()
    {
        $url = 'http://private.example.com/no-link-headers';
        $response = $this->token(['source' => $url, 'code' => '1234']);

        $body = $response->getContent();
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($body);
        $this->assertObjectHasAttribute('error', $data);
        $this->assertEquals('no_token_endpoint', $data->error);
    }

    public function testNoTokenEndpointOneLinkHeader()
    {
        $url = 'http://private.example.com/no-token-endpoint-one-link-header';
        $response = $this->token(['source' => $url, 'code' => '1234']);

        $body = $response->getContent();
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($body);

        $this->assertObjectHasAttribute('error', $data);
        $this->assertEquals('no_token_endpoint', $data->error);
    }

    public function testNoTokenEndpointTwoLinkHeaders()
    {
        $url = 'http://private.example.com/no-token-endpoint-two-link-headers';
        $response = $this->token(['source' => $url, 'code' => '1234']);

        $body = $response->getContent();
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($body);

        $this->assertObjectHasAttribute('error', $data);
        $this->assertEquals('no_token_endpoint', $data->error);
    }

    public function testTokenEndpointInOAuth2Rel()
    {
        $url = 'http://private.example.com/oauth2-token-endpoint';
        $response = $this->token(['source' => $url, 'code' => '1234']);

        $body = $response->getContent();
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($body);

        $this->assertObjectNotHasAttribute('error', $data);
        $this->assertEquals('1234', $data->access_token);
    }

    public function testTokenEndpointInIndieAuthRel()
    {
        $url = 'http://private.example.com/token-endpoint';
        $response = $this->token(['source' => $url, 'code' => '1234']);

        $body = $response->getContent();
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($body);

        $this->assertObjectNotHasAttribute('error', $data);
        $this->assertEquals('1234', $data->access_token);
    }

    public function testTokenEndpointWithMultipleRelLinks()
    {
        $url = 'http://private.example.com/multiple-rels';
        $response = $this->token(['source' => $url, 'code' => '1234']);

        $body = $response->getContent();
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($body);

        $this->assertObjectNotHasAttribute('error', $data);
        $this->assertEquals('1234', $data->access_token);
    }

    public function testBadTokenEndpointResponse()
    {
        $url = 'http://private.example.com/token-endpoint-bad-response';
        $response = $this->token(['source' => $url, 'code' => '1234']);

        $body = $response->getContent();
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($body);

        $this->assertObjectHasAttribute('error', $data);
        $this->assertEquals('this-string-passed-through-from-token-endpoint', $data->error);
    }

    public function testTokenEndpointTimeout()
    {
        $url = 'http://private.example.com/token-endpoint-timeout';
        $response = $this->token(['source' => $url, 'code' => '1234']);

        $body = $response->getContent();
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($body);

        $this->assertObjectHasAttribute('error', $data);
        $this->assertEquals('timeout', $data->error);
    }

    public function testTokenEndpointReturnsNotJSON()
    {
        $url = 'http://private.example.com/token-endpoint-notjson';
        $response = $this->token(['source' => $url, 'code' => '1234']);

        $body = $response->getContent();
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('text/plain', $response->headers->get('content-type'));
        $this->assertEquals('Invalid request', $body);
    }

}
