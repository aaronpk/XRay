<?php
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class FetchTest extends PHPUnit\Framework\TestCase
{

    private $http;

    public function setUp(): void
    {
        $this->http = new p3k\HTTP();
    }

    public function testTimeout()
    {
        $url = 'https://nghttp2.org/httpbin/delay/2';
        $this->http->timeout = 1;
        $response = $this->http->get($url);
        $this->assertEquals('timeout', $response['error']);
    }

    public function testRedirectLimit()
    {
        $url = 'https://nghttp2.org/httpbin/redirect/3';
        $this->http->max_redirects = 1;
        $response = $this->http->get($url);
        $this->assertEquals('too_many_redirects', $response['error']);
    }

    public function testNoError()
    {
        $url = 'https://nghttp2.org/httpbin/ip';
        $response = $this->http->get($url);
        $this->assertEquals('', $response['error']);    
    }

}