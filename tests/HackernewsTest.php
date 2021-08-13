<?php
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class HackernewsTest extends PHPUnit\Framework\TestCase
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

    public function testSubmission()
    {
        $url = 'https://news.ycombinator.com/item?id=14516538';
        $response = $this->parse(['url' => $url]);

        $body = $response->getContent();
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($body, true);

        $this->assertEquals(200, $data['code']);
        $this->assertEquals('hackernews', $data['source-format']);

        $this->assertEquals('entry', $data['data']['type']);
        $this->assertEquals('article', $data['data']['post-type']);
        $this->assertEquals('2017-06-08T19:32:12+00:00', $data['data']['published']);
        $this->assertEquals('vkb', $data['data']['author']['name']);
        $this->assertEquals('https://news.ycombinator.com/user?id=vkb', $data['data']['author']['url']);
        $this->assertEquals('What are we doing about Facebook, Google, and the closed internet?', $data['data']['name']);
        $this->assertEquals(
            'There have been many, many posts about how toxic advertising and Facebook are (I\'ve written many myself[1][2][3]) for our internet ecosystem today.<p>What projects or companies are you working on to combat filter bubbles, walled gardens, emotional manipulation, and the like, and how can the HN community help you in your goals?</p><p>[1]http://veekaybee.github.io/facebook-is-collecting-this/
[2]http://veekaybee.github.io/content-is-dead/
[3] http://veekaybee.github.io/who-is-doing-this-to-my-internet/</p>', $data['data']['content']['html']
        );
        $this->assertEquals(
            'There have been many, many posts about how toxic advertising and Facebook are (I\'ve written many myself[1][2][3]) for our internet ecosystem today.
What projects or companies are you working on to combat filter bubbles, walled gardens, emotional manipulation, and the like, and how can the HN community help you in your goals?
[1]http://veekaybee.github.io/facebook-is-collecting-this/
[2]http://veekaybee.github.io/content-is-dead/
[3] http://veekaybee.github.io/who-is-doing-this-to-my-internet/', $data['data']['content']['text']
        );
    }

    public function testComment()
    {
        $url = 'https://news.ycombinator.com/item?id=14516923';
        $response = $this->parse(['url' => $url]);

        $body = $response->getContent();
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($body, true);

        $this->assertEquals(200, $data['code']);
        $this->assertEquals('hackernews', $data['source-format']);

        $this->assertEquals('entry', $data['data']['type']);
        $this->assertEquals('reply', $data['data']['post-type']);
        $this->assertEquals('2017-06-08T20:23:20+00:00', $data['data']['published']);
        $this->assertEquals('aaronpk', $data['data']['author']['name']);
        $this->assertEquals('https://news.ycombinator.com/user?id=aaronpk', $data['data']['author']['url']);
        $this->assertEquals('https://news.ycombinator.com/item?id=14516538', $data['data']['in-reply-to'][0]);
        $this->assertArrayNotHasKey('name', $data['data']);
        $this->assertEquals('I am a member of the W3C Social Web Working Group (<a href="https://www.w3.org/wiki/Socialwg">https://www.w3.org/wiki/Socialwg</a>), and have been organizing IndieWebCamp (<a href="https://indieweb.org/">https://indieweb.org/</a>) conferences in this space for the last 7 years. We\'ve been making a lot of progress:<p>* <a href="https://www.w3.org/TR/webmention/">https://www.w3.org/TR/webmention/</a> - cross-site commenting</p><p>* <a href="https://www.w3.org/TR/micropub/">https://www.w3.org/TR/micropub/</a> - API for apps to create posts on various servers</p><p>* <a href="https://www.w3.org/TR/websub/">https://www.w3.org/TR/websub/</a> - realtime subscriptions to feeds</p><p>* More: <a href="https://indieweb.org/specs">https://indieweb.org/specs</a></p><p>We focus on making sure there are a plurality of implementations and approaches rather than trying to build a single software solution to solve everything.</p><p>Try commenting on my copy of this post on my website by sending me a webmention! <a href="https://aaronparecki.com/2017/06/08/9/indieweb">https://aaronparecki.com/2017/06/08/9/indieweb</a></p>', $data['data']['content']['html']);
        $this->assertEquals(
            'I am a member of the W3C Social Web Working Group (https://www.w3.org/wiki/Socialwg), and have been organizing IndieWebCamp (https://indieweb.org/) conferences in this space for the last 7 years. We\'ve been making a lot of progress:
* https://www.w3.org/TR/webmention/ - cross-site commenting
* https://www.w3.org/TR/micropub/ - API for apps to create posts on various servers
* https://www.w3.org/TR/websub/ - realtime subscriptions to feeds
* More: https://indieweb.org/specs
We focus on making sure there are a plurality of implementations and approaches rather than trying to build a single software solution to solve everything.
Try commenting on my copy of this post on my website by sending me a webmention! https://aaronparecki.com/2017/06/08/9/indieweb', $data['data']['content']['text']
        );
    }


}

