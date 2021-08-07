<?php
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class ActivityStreamsTest extends PHPUnit\Framework\TestCase
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

    public function testAuthorProfile()
    {
        $url = 'http://activitystreams.example/aaronpk';
        $response = $this->parse(['url' => $url]);

        $body = $response->getContent();
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($body, true);

        $this->assertEquals('activity+json', $data['source-format']);
        $this->assertEquals('card', $data['data']['type']);
        $this->assertEquals('aaronpk', $data['data']['name']);
        $this->assertEquals('https://aaronparecki.com/images/profile.jpg', $data['data']['photo']);
        $this->assertEquals('https://aaronparecki.com/', $data['data']['url']);
    }

    public function testNoteWithTags()
    {
        $url = 'http://activitystreams.example/note.json';
        $response = $this->parse(['url' => $url]);

        $body = $response->getContent();
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($body, true);

        $this->assertEquals('activity+json', $data['source-format']);
        $this->assertEquals('note', $data['data']['post-type']);
        $this->assertEquals($url, $data['data']['url']);
        $this->assertEquals('2018-07-12T13:02:04-07:00', $data['data']['published']);
        $this->assertEquals('This is the text content of an ActivityStreams note', $data['data']['content']['text']);
        $this->assertArrayNotHasKey('html', $data['data']['content']);
        $this->assertSame(['activitystreams'], $data['data']['category']);
        $this->assertEquals('aaronpk', $data['data']['author']['name']);
        $this->assertEquals('https://aaronparecki.com/images/profile.jpg', $data['data']['author']['photo']);
        $this->assertEquals('https://aaronparecki.com/', $data['data']['author']['url']);
    }

    public function testArticle()
    {
        $url = 'http://activitystreams.example/article.json';
        $response = $this->parse(['url' => $url]);

        $body = $response->getContent();
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($body, true);

        $this->assertEquals('activity+json', $data['source-format']);
        $this->assertEquals('article', $data['data']['post-type']);
        $this->assertEquals($url, $data['data']['url']);
        $this->assertEquals('An Article', $data['data']['name']);
        $this->assertEquals('This is the content of an ActivityStreams article', $data['data']['content']['text']);
        $this->assertEquals('<p>This is the content of an <b>ActivityStreams</b> article</p>', $data['data']['content']['html']);
        $this->assertEquals('aaronpk', $data['data']['author']['name']);
        $this->assertEquals('https://aaronparecki.com/images/profile.jpg', $data['data']['author']['photo']);
        $this->assertEquals('https://aaronparecki.com/', $data['data']['author']['url']);
    }

    public function testPhoto()
    {
        $url = 'http://activitystreams.example/photo.json';
        $response = $this->parse(['url' => $url]);

        $body = $response->getContent();
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($body, true);

        $this->assertEquals('activity+json', $data['source-format']);
        $this->assertEquals($url, $data['data']['url']);
        $this->assertEquals('photo', $data['data']['post-type']);
        $this->assertEquals('2018-07-12T13:02:04-07:00', $data['data']['published']);
        $this->assertEquals('This is the text content of an ActivityStreams photo', $data['data']['content']['text']);
        $this->assertArrayNotHasKey('html', $data['data']['content']);
        $this->assertSame(['activitystreams'], $data['data']['category']);
        $this->assertSame(['https://aaronparecki.com/2018/06/28/26/photo.jpg'], $data['data']['photo']);
    }

    public function testVideo()
    {
        $url = 'http://activitystreams.example/video.json';
        $response = $this->parse(['url' => $url]);

        $body = $response->getContent();
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($body, true);

        $this->assertEquals('activity+json', $data['source-format']);
        $this->assertEquals('video', $data['data']['post-type']);
        $this->assertEquals('2018-07-12T13:02:04-07:00', $data['data']['published']);
        $this->assertSame(['https://aaronparecki.com/2018/07/21/19/video.mp4'], $data['data']['video']);
    }

    public function testReply()
    {
        $url = 'http://activitystreams.example/reply.json';
        $response = $this->parse(['url' => $url]);

        $body = $response->getContent();
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($body, true);

        $this->assertEquals('activity+json', $data['source-format']);
        $this->assertEquals('reply', $data['data']['post-type']);
        $this->assertEquals('2018-07-12T13:02:04-07:00', $data['data']['published']);
        $this->assertArrayNotHasKey('category', $data['data']); // should not include the person-tag
        // For now, don't fetch the reply context
        $this->assertEquals(['http://activitystreams.example/note.json'], $data['data']['in-reply-to']);
    }

    public function testCustomEmoji()
    {
        $url = 'http://activitystreams.example/custom-emoji.json';
        $response = $this->parse(['url' => $url]);

        $body = $response->getContent();
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($body, true);

        $this->assertEquals('activity+json', $data['source-format']);
        $this->assertEquals('note', $data['data']['post-type']);
        $this->assertEquals("https://mastodon.social/@Gargron/100465999501820229", $data['data']['url']);
        $this->assertEquals('2018-07-30T22:24:54+00:00', $data['data']['published']);
        $this->assertEquals(':yikes:', $data['data']['content']['text']);
        $this->assertEquals('<p><img src="https://files.mastodon.social/custom_emojis/images/000/031/275/original/yikes.png" alt=":yikes:" title=":yikes:" height="24" class="xray-emoji"></p>', $data['data']['content']['html']);
        $this->assertEquals('Eugen', $data['data']['author']['name']);
        $this->assertEquals('Gargron', $data['data']['author']['nickname']);
        $this->assertEquals('https://files.mastodon.social/accounts/avatars/000/000/001/original/eb9e00274b135808.png', $data['data']['author']['photo']);
        $this->assertEquals('https://mastodon.social/@Gargron', $data['data']['author']['url']);
    }

    public function testRelAlternatePriority()
    {
        $url = 'http://source.example.com/rel-alternate-as2';
        $response = $this->parse(['url' => $url]);

        $body = $response->getContent();
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($body, true);

        $this->assertEquals('activity+json', $data['source-format']);
        $this->assertEquals('http://activitystreams.example/note.json', $data['parsed-url']);
        $this->assertEquals('http://source.example.com/rel-alternate-as2', $data['url']);
        $this->assertEquals('note', $data['data']['post-type']);
        $this->assertEquals('2018-07-12T13:02:04-07:00', $data['data']['published']);
        $this->assertEquals('This is the text content of an ActivityStreams note', $data['data']['content']['text']);
        $this->assertArrayNotHasKey('html', $data['data']['content']);
        $this->assertSame(['activitystreams'], $data['data']['category']);
    }

    public function testSensitiveContent()
    {
        $url = 'http://activitystreams.example/sensitive.json';
        $response = $this->parse(['url' => $url]);

        $body = $response->getContent();
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($body, true);

        $this->assertEquals('activity+json', $data['source-format']);
        $this->assertEquals('note', $data['data']['post-type']);
        $this->assertEquals('sensitive topic', $data['data']['summary']);
        $this->assertEquals('This is the text content of a sensitive ActivityStreams note', $data['data']['content']['text']);
        $this->assertArrayNotHasKey('name', $data['data']);
    }

    public function testRepost()
    {
        $url = 'http://activitystreams.example/repost.json';
        $response = $this->parse(['url' => $url]);

        $body = $response->getContent();
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($body, true);

        $this->assertEquals('activity+json', $data['source-format']);
        $this->assertEquals('repost', $data['data']['post-type']);
        $this->assertArrayNotHasKey('content', $data['data']);
        $this->assertArrayNotHasKey('name', $data['data']);
        $this->assertEquals('Gargron', $data['data']['author']['nickname']);
        $this->assertEquals(['http://activitystreams.example/note.json'], $data['data']['repost-of']);
        $this->assertArrayHasKey('http://activitystreams.example/note.json', $data['data']['refs']);
        $this->assertEquals('This is the text content of an ActivityStreams note', $data['data']['refs']['http://activitystreams.example/note.json']['content']['text']);
    }

    public function testLike()
    {
        $url = 'http://activitystreams.example/like.json';
        $response = $this->parse(['url' => $url]);

        $body = $response->getContent();
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($body, true);

        $this->assertEquals('activity+json', $data['source-format']);
        $this->assertEquals('like', $data['data']['post-type']);
        $this->assertArrayNotHasKey('content', $data['data']);
        $this->assertArrayNotHasKey('name', $data['data']);
        $this->assertEquals('Gargron', $data['data']['author']['nickname']);
        $this->assertEquals(['http://activitystreams.example/note.json'], $data['data']['like-of']);
        $this->assertArrayHasKey('http://activitystreams.example/note.json', $data['data']['refs']);
        $this->assertEquals('This is the text content of an ActivityStreams note', $data['data']['refs']['http://activitystreams.example/note.json']['content']['text']);
    }

    public function testNoteWrappedInCreate()
    {
        $url = 'http://activitystreams.example/create.json';
        $response = $this->parse(['url' => $url]);

        $body = $response->getContent();
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($body, true);

        $this->assertEquals('activity+json', $data['source-format']);
        $this->assertEquals('reply', $data['data']['post-type']);
        $this->assertEquals('https://toot.cat/@jamey/100471682482196371', $data['data']['url']);
        $this->assertEquals('2018-07-31T22:30:09+00:00', $data['data']['published']);
        $this->assertEquals('@darius Huh, I just have never encountered anyone using the phrase generically like that.But you might consider writing IndieWeb.org-style bots (Atom+WebSub, and optionally WebMention if you want them to be interactive), and then using https://fed.brid.gy/ as an alternative to implementing ActivityPub yourself...', $data['data']['content']['text']);
        $this->assertEquals('https://social.tinysubversions.com/users/darius/statuses/100471614681787834', $data['data']['in-reply-to'][0]);
        $this->assertEquals('Jamey Sharp', $data['data']['author']['name']);
        $this->assertEquals('https://s3-us-west-2.amazonaws.com/tootcatapril2017/accounts/avatars/000/013/259/original/c904452a8411e4f5.jpg', $data['data']['author']['photo']);
        $this->assertEquals('https://toot.cat/@jamey', $data['data']['author']['url']);
    }

}
