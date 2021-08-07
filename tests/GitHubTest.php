<?php
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class GitHubTest extends PHPUnit\Framework\TestCase
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

    public function testGitHubPull()
    {
        // Original URL: https://github.com/idno/Known/pull/1690
        $url = 'https://github.com/idno/Known/pull/1690';
        $response = $this->parse(['url' => $url]);

        $body = $response->getContent();
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($body, true);

        $this->assertEquals(200, $data['code']);
        $this->assertEquals('github', $data['source-format']);

        $this->assertEquals('entry', $data['data']['type']);
        $this->assertEquals('reply', $data['data']['post-type']);
        $this->assertEquals('https://github.com/idno/Known', $data['data']['in-reply-to'][0]);
        $this->assertEquals('2017-04-10T17:44:57Z', $data['data']['published']);
        $this->assertEquals('aaronpk', $data['data']['author']['name']);
        $this->assertEquals('https://github.com/aaronpk', $data['data']['author']['url']);
        $this->assertEquals('https://avatars2.githubusercontent.com/u/113001?v=3', $data['data']['author']['photo']);
        $this->assertEquals('#1690 fixes bookmark Microformats markup', $data['data']['name']);
        $this->assertStringContainsString('<h2>Here\'s what I fixed or added:</h2>', $data['data']['content']['html']);
        $this->assertStringContainsString('## Here\'s what I fixed or added:', $data['data']['content']['text']);
    }

    public function testGitHubIssue()
    {
        $url = 'https://github.com/aaronpk/XRay/issues/25';
        $response = $this->parse(['url' => $url]);

        $body = $response->getContent();
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($body, true);

        $this->assertEquals(200, $data['code']);
        $this->assertEquals('github', $data['source-format']);

        $this->assertEquals('entry', $data['data']['type']);
        $this->assertEquals('reply', $data['data']['post-type']);
        $this->assertEquals('https://github.com/aaronpk/XRay/issues', $data['data']['in-reply-to'][0]);
        $this->assertEquals('2017-01-26T14:13:42Z', $data['data']['published']);
        $this->assertEquals('sebsel', $data['data']['author']['name']);
        $this->assertEquals('https://github.com/sebsel', $data['data']['author']['url']);
        $this->assertEquals('https://avatars3.githubusercontent.com/u/16517999?v=3', $data['data']['author']['photo']);
        $this->assertEquals('#25 Post type discovery', $data['data']['name']);
        $this->assertStringContainsString('<blockquote>', $data['data']['content']['html']);
        $this->assertStringContainsString('<a href="https://www.w3.org/TR/post-type-discovery/">', $data['data']['content']['html']);
        $this->assertStringContainsString('> sebsel', $data['data']['content']['text']);
    }

    public function testGitHubIssueWithCategory()
    {
        $url = 'https://github.com/aaronpk/XRay/issues/20';
        $response = $this->parse(['url' => $url]);

        $body = $response->getContent();
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($body, true);

        $this->assertEquals(200, $data['code']);
        $this->assertEquals('github', $data['source-format']);

        $this->assertEquals('entry', $data['data']['type']);
        $this->assertContains('silo', $data['data']['category']);
    }

    public function testGitHubRepo()
    {
        $url = 'https://github.com/aaronpk/XRay';
        $response = $this->parse(['url' => $url]);

        $body = $response->getContent();
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($body, true);

        $this->assertEquals(200, $data['code']);
        $this->assertEquals('github', $data['source-format']);

        $this->assertEquals('repo', $data['data']['type']);
        $this->assertArrayNotHasKey('post-type', $data['data']);
        $this->assertEquals('2016-02-19T16:53:20Z', $data['data']['published']);
        $this->assertEquals('aaronpk', $data['data']['author']['name']);
        $this->assertEquals('https://github.com/aaronpk', $data['data']['author']['url']);
        $this->assertEquals('https://avatars2.githubusercontent.com/u/113001?v=3', $data['data']['author']['photo']);
        $this->assertEquals('XRay', $data['data']['name']);
        $this->assertEquals('X-Ray returns structured data from any URL', $data['data']['summary']);
    }

    public function testGitHubIssueComment()
    {
        $url = 'https://github.com/aaronpk/XRay/issues/25#issuecomment-275433926';
        $response = $this->parse(['url' => $url]);

        $body = $response->getContent();
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($body, true);

        $this->assertEquals(200, $data['code']);
        $this->assertEquals('github', $data['source-format']);

        $this->assertEquals('entry', $data['data']['type']);
        $this->assertEquals('reply', $data['data']['post-type']);
        $this->assertEquals('https://github.com/aaronpk/XRay/issues/25', $data['data']['in-reply-to'][0]);
        $this->assertEquals('2017-01-26T16:24:37Z', $data['data']['published']);
        $this->assertEquals('sebsel', $data['data']['author']['name']);
        $this->assertEquals('https://avatars3.githubusercontent.com/u/16517999?v=3', $data['data']['author']['photo']);
        $this->assertEquals('https://github.com/sebsel', $data['data']['author']['url']);
        $this->assertStringContainsString('<p>Well it\'s just that php-comments does more than XRay does currently. But that\'s no good reason.</p>', $data['data']['content']['html']);
        $this->assertStringContainsString('<code class="language-php">', $data['data']['content']['html']);
        $this->assertStringContainsString('```php', $data['data']['content']['text']);
        $this->assertNotContains('name', $data['data']);
        $this->assertContains('https://github.com/aaronpk/XRay/issues/25', $data['data']['in-reply-to']);
    }


}
