<?php
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class TwitterTest extends PHPUnit\Framework\TestCase
{

    public function setUp(): void
    {
        $this->client = new Parse();
        $this->client->mc = null;
    }

    private function parse($params)
    {
        $request = new Request($params);
        $response = new Response();
        $result = $this->client->parse($request, $response);
        $body = $result->getContent();
        $this->assertEquals(200, $result->getStatusCode());
        return json_decode($body, true);
    }

    private function loadTweet($id)
    {
        $url = 'https://twitter.com/_/status/'.$id;
        $json = file_get_contents(dirname(__FILE__).'/data/api.twitter.com/'.$id.'.json');
        $parsed = json_decode($json);
        $url = 'https://twitter.com/'.$parsed->user->screen_name.'/status/'.$id;
        return [$url, $json];
    }

    public function testBasicProfileInfo()
    {
        list($url, $json) = $this->loadTweet('818912506496229376');

        $data = $this->parse(['url' => $url, 'body' => $json]);

        $this->assertEquals('twitter', $data['source-format']);

        $this->assertEquals('entry', $data['data']['type']);
        $this->assertEquals('aaronpk dev', $data['data']['author']['name']);
        $this->assertEquals('pkdev', $data['data']['author']['nickname']);
        $this->assertEquals('https://twitter.com/pkdev', $data['data']['author']['url']);
        $this->assertEquals('Portland, OR', $data['data']['author']['location']);
        $this->assertEquals('Dev account for testing Twitter things. Follow me here: https://twitter.com/aaronpk', $data['data']['author']['bio']);
        $this->assertEquals('https://pbs.twimg.com/profile_images/638125135904436224/qd_d94Qn_normal.jpg', $data['data']['author']['photo']);
    }

    public function testProfileWithNonExpandedURL()
    {
        list($url, $json) = $this->loadTweet('791704641046052864');

        $data = $this->parse(['url' => $url, 'body' => $json]);

        $this->assertEquals('https://twitter.com/agiletortoise', $data['data']['author']['url']);
    }

    public function testBasicTestStuff()
    {
        list($url, $json) = $this->loadTweet('818913630569664512');

        $data = $this->parse(['url' => $url, 'body' => $json]);

        $this->assertEquals(null, $data['code']); // no code is expected if we pass in the body
        $this->assertEquals('twitter', $data['source-format']);

        $this->assertEquals('https://twitter.com/pkdev/status/818913630569664512', $data['url']);
        $this->assertEquals('entry', $data['data']['type']);
        $this->assertEquals('note', $data['data']['post-type']);
        $this->assertEquals('A tweet with a URL https://indieweb.org/ #and #some #hashtags', $data['data']['content']['text']);
        $this->assertContains('and', $data['data']['category']);
        $this->assertContains('some', $data['data']['category']);
        $this->assertContains('hashtags', $data['data']['category']);
        // Published date should be set to the timezone of the user
        $this->assertEquals('2017-01-10T12:13:18-08:00', $data['data']['published']);
    }

    public function testPositiveTimezone()
    {
        list($url, $json) = $this->loadTweet('719914707566649344');

        $data = $this->parse(['url' => $url, 'body' => $json]);
        $this->assertEquals("2016-04-12T16:46:56+01:00", $data['data']['published']);
    }

    public function testTweetWithEmoji()
    {
        list($url, $json) = $this->loadTweet('818943244553699328');

        $data = $this->parse(['url' => $url, 'body' => $json]);

        $this->assertEquals('twitter', $data['source-format']);
        $this->assertEquals('entry', $data['data']['type']);
        $this->assertEquals('Here 🎉 have an emoji', $data['data']['content']['text']);
    }

    public function testHTMLEscaping()
    {
        list($url, $json) = $this->loadTweet('818928092383166465');

        $data = $this->parse(['url' => $url, 'body' => $json]);

        $this->assertEquals('entry', $data['data']['type']);
        $this->assertEquals('Double escaping &amp; & amp', $data['data']['content']['text']);
    }

    public function testTweetWithPhoto()
    {
        list($url, $json) = $this->loadTweet('818912506496229376');

        $data = $this->parse(['url' => $url, 'body' => $json]);

        $this->assertEquals('entry', $data['data']['type']);
        $this->assertEquals('photo', $data['data']['post-type']);
        $this->assertEquals('Tweet with a photo and a location', $data['data']['content']['text']);
        $this->assertEquals('https://pbs.twimg.com/media/C11cfRJUoAI26h9.jpg', $data['data']['photo'][0]);
    }

    public function testTweetWithTwoPhotos()
    {
        list($url, $json) = $this->loadTweet('818935308813103104');

        $data = $this->parse(['url' => $url, 'body' => $json]);

        $this->assertEquals('entry', $data['data']['type']);
        $this->assertEquals('photo', $data['data']['post-type']);
        $this->assertEquals('Two photos', $data['data']['content']['text']);
        $this->assertContains('https://pbs.twimg.com/media/C11xS1wUcAAeaKF.jpg', $data['data']['photo']);
        $this->assertContains('https://pbs.twimg.com/media/C11wtndUoAE1WfE.jpg', $data['data']['photo']);
    }

    public function testTweetWithVideo()
    {
        list($url, $json) = $this->loadTweet('818913178260160512');

        $data = $this->parse(['url' => $url, 'body' => $json]);

        $this->assertEquals('entry', $data['data']['type']);
        $this->assertEquals('video', $data['data']['post-type']);
        $this->assertEquals('Tweet with a video', $data['data']['content']['text']);
        $this->assertEquals('https://pbs.twimg.com/ext_tw_video_thumb/818913089248595970/pr/img/qVoEjF03Y41SKpNt.jpg', $data['data']['photo'][0]);
        $this->assertEquals('https://video.twimg.com/ext_tw_video/818913089248595970/pr/vid/1280x720/qP-sDx-Q0Hs-ckVv.mp4', $data['data']['video'][0]);
    }

    public function testTweetWithGif()
    {
        list($url, $json) = $this->loadTweet('tweet-with-gif');

        $data = $this->parse(['url' => $url, 'body' => $json]);

        $this->assertEquals('entry', $data['data']['type']);
        $this->assertEquals('reply', $data['data']['post-type']);
        $this->assertEquals('https://twitter.com/SwiftOnSecurity/status/1018178408398966784', $data['data']['in-reply-to'][0]);
        $this->assertEquals('Look! A distraction 🐁', $data['data']['content']['text']);
        $this->assertEquals('https://video.twimg.com/tweet_video/DiFOUuYV4AAUsgL.mp4', $data['data']['video'][0]);
        $this->assertEquals('https://pbs.twimg.com/tweet_video_thumb/DiFOUuYV4AAUsgL.jpg', $data['data']['photo'][0]);
    }

    public function testTweetWithLocation()
    {
        list($url, $json) = $this->loadTweet('818912506496229376');

        $data = $this->parse(['url' => $url, 'body' => $json]);

        $this->assertEquals('entry', $data['data']['type']);
        $this->assertEquals('Tweet with a photo and a location', $data['data']['content']['text']);
        $this->assertEquals('https://api.twitter.com/1.1/geo/id/ac88a4f17a51c7fc.json', $data['data']['location']);
        $location = $data['data']['refs']['https://api.twitter.com/1.1/geo/id/ac88a4f17a51c7fc.json'];
        $this->assertEquals('adr', $location['type']);
        $this->assertEquals('Portland', $location['locality']);
        $this->assertEquals('United States', $location['country-name']);
        $this->assertEquals('Portland, OR', $location['name']);
    }

    public function testRetweet()
    {
        list($url, $json) = $this->loadTweet('818913351623245824');

        $data = $this->parse(['url' => $url, 'body' => $json]);

        $this->assertEquals('twitter', $data['source-format']);
        $this->assertEquals('entry', $data['data']['type']);
        $this->assertEquals('repost', $data['data']['post-type']);
        $this->assertArrayNotHasKey('content', $data['data']);
        $repostOf = 'https://twitter.com/aaronpk/status/817414679131660288';
        $this->assertEquals($repostOf, $data['data']['repost-of']);
        $tweet = $data['data']['refs'][$repostOf];
        $this->assertEquals('Yeah that\'s me http://xkcd.com/1782/', $tweet['content']['text']);
    }

    public function testRetweetWithPhoto()
    {
        list($url, $json) = $this->loadTweet('820039442773798912');

        $data = $this->parse(['url' => $url, 'body' => $json]);

        $this->assertEquals('entry', $data['data']['type']);
        $this->assertEquals('repost', $data['data']['post-type']);
        $this->assertArrayNotHasKey('content', $data['data']);
        $this->assertArrayNotHasKey('photo', $data['data']);
        $repostOf = 'https://twitter.com/phlaimeaux/status/819943954724556800';
        $this->assertEquals($repostOf, $data['data']['repost-of']);
        $tweet = $data['data']['refs'][$repostOf];
        $this->assertEquals('this headline is such a rollercoaster', $tweet['content']['text']);
    }

    public function testQuotedTweet()
    {
        list($url, $json) = $this->loadTweet('818913488609251331');

        $data = $this->parse(['url' => $url, 'body' => $json]);

        $this->assertEquals('entry', $data['data']['type']);
        $this->assertEquals('note', $data['data']['post-type']);
        $this->assertEquals('Quoted tweet with a #hashtag https://twitter.com/aaronpk/status/817414679131660288', $data['data']['content']['text']);
        $this->assertEquals('https://twitter.com/aaronpk/status/817414679131660288', $data['data']['quotation-of']);
        $tweet = $data['data']['refs']['https://twitter.com/aaronpk/status/817414679131660288'];
        $this->assertEquals('Yeah that\'s me http://xkcd.com/1782/', $tweet['content']['text']);
    }

    public function testTruncatedQuotedTweet()
    {
        list($url, $json) = $this->loadTweet('tweet-with-truncated-quoted-tweet');

        $data = $this->parse(['url' => $url, 'body' => $json]);

        $this->assertEquals('entry', $data['data']['type']);
        $this->assertEquals('.@stream_pdx is a real treasure of our city.', $data['data']['content']['text']);
        $this->assertEquals('https://twitter.com/PDXStephenG/status/964598574322339841', $data['data']['quotation-of']);
        $tweet = $data['data']['refs']['https://twitter.com/PDXStephenG/status/964598574322339841'];
        $this->assertEquals('Hey @OregonGovBrown @tedwheeler day 16 of #BHM is for @stream_pdx. An amazing podcast trailer run by @tyeshasnow helping to democratize story telling in #PDX. Folks can get training in the production of podcasts. @siliconflorist #SupportBlackBusiness', $tweet['content']['text']);
        $this->assertEquals("Hey <a href=\"https://twitter.com/OregonGovBrown\">@OregonGovBrown</a> <a href=\"https://twitter.com/tedwheeler\">@tedwheeler</a> day 16 of #BHM is for <a href=\"https://twitter.com/stream_pdx\">@stream_pdx</a>. An amazing podcast trailer run by <a href=\"https://twitter.com/tyeshasnow\">@tyeshasnow</a> helping to democratize story telling in #PDX. Folks can get training in the production of podcasts. <a href=\"https://twitter.com/siliconflorist\">@siliconflorist</a> #SupportBlackBusiness", $tweet['content']['html']);
    }

    public function testTweetWithHTML()
    {
        list($url, $json) = $this->loadTweet('tweet-with-html');

        $data = $this->parse(['url' => $url, 'body' => $json]);

        $this->assertStringContainsString('<script>', $data['data']['content']['text']);
        $this->assertStringContainsString('&lt;script&gt;', $data['data']['content']['html']);
    }

    public function testStreamingTweetWithLink()
    {
        list($url, $json) = $this->loadTweet('streaming-tweet-with-link');
        $data = $this->parse(['url' => $url, 'body' => $json]);

        $this->assertEquals('twitter', $data['source-format']);
        $this->assertEquals('what happens if i include a link like https://kmikeym.com', $data['data']['content']['text']);
        $this->assertEquals('what happens if i include a link like <a href="https://kmikeym.com">https://kmikeym.com</a>', $data['data']['content']['html']);
    }

    public function testStreamingTweetWithMentions()
    {
        list($url, $json) = $this->loadTweet('streaming-tweet-with-mentions');
        $data = $this->parse(['url' => $url, 'body' => $json]);

        $this->assertEquals('Offer accepted! @aaronpk bought 1 shares from @coledrobison at $6.73 https://kmikeym.com/trades', $data['data']['content']['text']);
        $this->assertEquals('Offer accepted! <a href="https://twitter.com/aaronpk">@aaronpk</a> bought 1 shares from <a href="https://twitter.com/coledrobison">@coledrobison</a> at $6.73 <a href="https://kmikeym.com/trades">https://kmikeym.com/trades</a>', $data['data']['content']['html']);
    }

    public function testStreamingTweetTruncated()
    {
        list($url, $json) = $this->loadTweet('streaming-tweet-truncated');
        $data = $this->parse(['url' => $url, 'body' => $json]);

        $this->assertEquals("#indieweb community. Really would like to see a Micropub client for Gratitude logging and also a Mastodon poster similar to the twitter one.\nFeel like I could (maybe) rewrite previous open code to do some of this :)", $data['data']['content']['text']);
        $this->assertEquals(
            '#indieweb community. Really would like to see a Micropub client for Gratitude logging and also a Mastodon poster similar to the twitter one.<br>
Feel like I could (maybe) rewrite previous open code to do some of this :)', $data['data']['content']['html']
        );
    }

    public function testStreamingTweetTruncatedWithPhoto()
    {
        list($url, $json) = $this->loadTweet('streaming-tweet-truncated-with-photo');
        $data = $this->parse(['url' => $url, 'body' => $json]);

        $this->assertEquals("#MicrosoftFlow ninja-tip.\nI'm getting better at custom-connector and auth.  Thanks @skillriver \nThis is OAuth2 with MSA/Live (not AzureAD) which I need to do MVP timesheets.\nStill dislike Swagger so I don't know why I bother with this. I'm just that lazy doing this manually", $data['data']['content']['text']);
        $this->assertEquals(4, count($data['data']['photo']));
        $this->assertEquals('https://pbs.twimg.com/media/DWZ-5UPVAAAQOWY.jpg', $data['data']['photo'][0]);
        $this->assertEquals('https://pbs.twimg.com/media/DWaAhZ2UQAAIEoS.jpg', $data['data']['photo'][3]);
    }

    public function testStreamingTweetTruncatedWithVideo()
    {
        list($url, $json) = $this->loadTweet('streaming-tweet-truncated-with-video');
        $data = $this->parse(['url' => $url, 'body' => $json]);

        $this->assertEquals("hi @aaronpk Ends was a great job I was just talking to her about the house I think she is just talking to you about that stuff like that you don't have any idea of how to make to your job so you don't want me going back on your own to make it happen", $data['data']['content']['text']);
        $this->assertEquals(1, count($data['data']['video']));
        $this->assertEquals('https://video.twimg.com/ext_tw_video/965608338917548032/pu/vid/720x720/kreAfCMf-B1dLqBH.mp4', $data['data']['video'][0]);
    }

    public function testTweetWithNewlines()
    {
        list($url, $json) = $this->loadTweet('tweet-with-newlines');
        $data = $this->parse(['url' => $url, 'body' => $json]);

        $this->assertEquals(4, substr_count($data['data']['content']['text'], "\n"));
        $this->assertEquals(4, substr_count($data['data']['content']['html'], "<br>\n"));
        $this->assertEquals(
            "🌈🌈 I’ve watched the sun rise at Corona Heights countless times, but never before have I seen a #rainbow at #sunrise.

#CoronaHeights #SanFrancisco #SF #wakeupthesun #fromwhereirun #nofilter

Woke up this morning feeling compelled to run to Corona… http://tantek.com/2018/049/t3/rainbow-at-sunrise", $data['data']['content']['text']
        );
    }

    public function testStreamingTweetReply()
    {
        list($url, $json) = $this->loadTweet('streaming-tweet-reply');
        $data = $this->parse(['url' => $url, 'body' => $json]);

        $this->assertEquals('twitter', $data['source-format']);
        $this->assertEquals('https://twitter.com/anomalily/status/967024586423386112', $data['data']['in-reply-to'][0]);
    }

    public function testTweetReply()
    {
        list($url, $json) = $this->loadTweet('967046438822674432');
        $data = $this->parse(['url' => $url, 'body' => $json]);

        $this->assertEquals('twitter', $data['source-format']);
        $this->assertEquals('https://twitter.com/anomalily/status/967024586423386112', $data['data']['in-reply-to'][0]);
    }

}
