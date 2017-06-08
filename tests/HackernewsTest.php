<?php
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class HackernewsTest extends PHPUnit_Framework_TestCase {

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

  public function testSubmission() {
    $url = 'https://news.ycombinator.com/item?id=14516538';
    $response = $this->parse(['url' => $url]);

    $body = $response->getContent();
    $this->assertEquals(200, $response->getStatusCode());
    $data = json_decode($body, true);

    $this->assertEquals('entry', $data['data']['type']);
    $this->assertEquals('2017-06-08T19:32:12+00:00', $data['data']['published']);
    $this->assertEquals('vkb', $data['data']['author']['name']);
    $this->assertEquals('https://news.ycombinator.com/user?id=vkb', $data['data']['author']['url']);
    $this->assertEquals('What are we doing about Facebook, Google, and the closed internet?', $data['data']['name']);
    $this->assertEquals('There have been many, many posts about how toxic advertising and Facebook are (I\'ve written many myself[1][2][3]) for our internet ecosystem today.<p>What projects or companies are you working on to combat filter bubbles, walled gardens, emotional manipulation, and the like, and how can the HN community help you in your goals?</p><p>[1]http://veekaybee.github.io/facebook-is-collecting-this/
[2]http://veekaybee.github.io/content-is-dead/
[3] http://veekaybee.github.io/who-is-doing-this-to-my-internet/</p>', $data['data']['content']['html']);
  }



}

