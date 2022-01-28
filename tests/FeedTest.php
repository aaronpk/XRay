<?php
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class FeedTest extends PHPUnit\Framework\TestCase
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

    public function testListOfHEntrys()
    {
        $url = 'http://feed.example.com/list-of-hentrys';
        $response = $this->parse(['url' => $url, 'expect' => 'feed']);

        $body = $response->getContent();
        $this->assertEquals(200, $response->getStatusCode());
        $result = json_decode($body);
        $this->assertEquals('mf2+html', $result->{'source-format'});
        $data = $result->data;
        $this->assertEquals('feed', $data->type);
        $this->assertEquals(4, count($data->items));
        $this->assertEquals('One', $data->items[0]->name);
        $this->assertEquals('article', $data->items[0]->{'post-type'});
        $this->assertEquals('Two', $data->items[1]->name);
        $this->assertEquals('article', $data->items[1]->{'post-type'});
        $this->assertEquals('Three', $data->items[2]->name);
        $this->assertEquals('Four', $data->items[3]->name);
    }

    public function testListOfHEntrysWithHCard()
    {
        $url = 'http://feed.example.com/list-of-hentrys-with-h-card';
        $response = $this->parse(['url' => $url, 'expect' => 'feed']);

        $body = $response->getContent();
        $this->assertEquals(200, $response->getStatusCode());
        $result = json_decode($body);
        $this->assertEquals('mf2+html', $result->{'source-format'});
        $data = $result->data;
        $this->assertEquals('feed', $data->type);
        $this->assertEquals(4, count($data->items));
        $this->assertEquals('One', $data->items[0]->name);
        $this->assertEquals('article', $data->items[0]->{'post-type'});
        $this->assertEquals('Two', $data->items[1]->name);
        $this->assertEquals('Three', $data->items[2]->name);
        $this->assertEquals('Four', $data->items[3]->name);

        // Check that the author h-card was matched up with each h-entry
        $this->assertEquals('Author Name', $data->items[0]->author->name);
        $this->assertEquals('Author Name', $data->items[1]->author->name);
        $this->assertEquals('Author Name', $data->items[2]->author->name);
        $this->assertEquals('Author Name', $data->items[3]->author->name);
    }

    public function testListOfHEntrysWithHCardNoExpect()
    {
        $url = 'http://feed.example.com/list-of-hentrys-with-h-card';
        $response = $this->parse(['url' => $url]);

        $body = $response->getContent();
        $this->assertEquals(200, $response->getStatusCode());
        $result = json_decode($body);
        $this->assertEquals('mf2+html', $result->{'source-format'});
        $data = $result->data;

        $this->assertEquals('feed', $data->type);
        $this->assertEquals(4, count($data->items));
        $this->assertEquals('One', $data->items[0]->name);
        $this->assertEquals('article', $data->items[0]->{'post-type'});
        $this->assertEquals('Two', $data->items[1]->name);
        $this->assertEquals('Three', $data->items[2]->name);
        $this->assertEquals('Four', $data->items[3]->name);

        // Check that the author h-card was matched up with each h-entry
        $this->assertEquals('Author Name', $data->items[0]->author->name);
        $this->assertEquals('Author Name', $data->items[1]->author->name);
        $this->assertEquals('Author Name', $data->items[2]->author->name);
        $this->assertEquals('Author Name', $data->items[3]->author->name);
    }

    public function testShortListOfHEntrysWithHCardNoExpect()
    {
        $url = 'http://feed.example.com/short-list-of-hentrys-with-h-card';
        $response = $this->parse(['url' => $url]);

        $body = $response->getContent();
        $this->assertEquals(200, $response->getStatusCode());
        $result = json_decode($body);
        $this->assertEquals('mf2+html', $result->{'source-format'});
        $data = $result->data;

        // In this case, this looks like a page permalink
        $this->assertEquals('entry', $data->type);
        // This test should find the h-entry rather than the h-card, because the h-card does not contain the page URL
        $this->assertEquals('http://feed.example.com/1', $data->url);
        $this->assertEquals('Author', $data->author->name);
    }

    public function testShortListOfHEntrysWithHCard()
    {
        $url = 'http://feed.example.com/short-list-of-hentrys-with-h-card';
        $response = $this->parse(['url' => $url, 'expect' => 'feed']);

        $body = $response->getContent();
        $this->assertEquals(200, $response->getStatusCode());
        $result = json_decode($body);
        $this->assertEquals('mf2+html', $result->{'source-format'});
        $data = $result->data;
        $this->assertEquals('feed', $data->type);
        // This test should find the h-entry rather than the h-card, because expect=feed
        $this->assertEquals('entry', $data->items[0]->type);
        $this->assertEquals('http://feed.example.com/1', $data->items[0]->url);
        $this->assertEquals('Author', $data->items[0]->author->name);
    }

    public function testTopLevelHFeed()
    {
        $url = 'http://feed.example.com/top-level-h-feed';
        $response = $this->parse(['url' => $url, 'expect' => 'feed']);

        $body = $response->getContent();
        $this->assertEquals(200, $response->getStatusCode());
        $result = json_decode($body);
        $this->assertEquals('mf2+html', $result->{'source-format'});
        $data = $result->data;
        $this->assertEquals('feed', $data->type);
        $this->assertEquals(4, count($data->items));
        $this->assertEquals('One', $data->items[0]->name);
        $this->assertEquals('Two', $data->items[1]->name);
        $this->assertEquals('Three', $data->items[2]->name);
        $this->assertEquals('Four', $data->items[3]->name);
    }

    public function testTopLevelHFeedWithChildAuthor()
    {
        $url = 'http://feed.example.com/h-feed-with-child-author';
        $response = $this->parse(['url' => $url, 'expect' => 'feed']);

        $body = $response->getContent();
        $this->assertEquals(200, $response->getStatusCode());
        $result = json_decode($body);
        $this->assertEquals('mf2+html', $result->{'source-format'});
        $data = $result->data;
        $this->assertEquals('feed', $data->type);
        $this->assertEquals(4, count($data->items));
        $this->assertEquals('One', $data->items[0]->name);
        $this->assertEquals('Two', $data->items[1]->name);
        $this->assertEquals('Three', $data->items[2]->name);
        $this->assertEquals('Four', $data->items[3]->name);
        $this->assertEquals('Author Name', $data->items[0]->author->name);
        $this->assertEquals('Author Name', $data->items[1]->author->name);
        $this->assertEquals('Author Name', $data->items[2]->author->name);
        $this->assertEquals('Author Name', $data->items[3]->author->name);
    }

    public function testHCardWithChildHEntrys()
    {
        $url = 'http://feed.example.com/h-card-with-child-h-entrys';
        $response = $this->parse(['url' => $url, 'expect' => 'feed']);

        $body = $response->getContent();
        $this->assertEquals(200, $response->getStatusCode());
        $result = json_decode($body);
        $this->assertEquals('mf2+html', $result->{'source-format'});
        $data = $result->data;
        $this->assertEquals('feed', $data->type);
        $this->assertEquals(4, count($data->items));
        $this->assertEquals('One', $data->items[0]->name);
        $this->assertEquals('Two', $data->items[1]->name);
        $this->assertEquals('Three', $data->items[2]->name);
        $this->assertEquals('Four', $data->items[3]->name);
    }

    public function testHCardWithSiblingHEntrys()
    {
        $url = 'http://feed.example.com/h-card-with-sibling-h-entrys';
        $response = $this->parse(['url' => $url, 'expect' => 'feed']);

        $body = $response->getContent();
        $this->assertEquals(200, $response->getStatusCode());
        $result = json_decode($body);
        $this->assertEquals('mf2+html', $result->{'source-format'});
        $data = $result->data;
        $this->assertEquals('feed', $data->type);
        $this->assertEquals(4, count($data->items));
        $this->assertEquals('One', $data->items[0]->name);
        $this->assertEquals('Two', $data->items[1]->name);
        $this->assertEquals('Three', $data->items[2]->name);
        $this->assertEquals('Four', $data->items[3]->name);
        // Check that the author h-card was matched up with each h-entry
        $this->assertEquals('Author Name', $data->items[0]->author->name);
        $this->assertEquals('Author Name', $data->items[1]->author->name);
        $this->assertEquals('Author Name', $data->items[2]->author->name);
        $this->assertEquals('Author Name', $data->items[3]->author->name);
    }

    public function testHCardWithChildHFeed()
    {
        $url = 'http://feed.example.com/h-card-with-child-h-feed';
        $response = $this->parse(['url' => $url, 'expect' => 'feed']);

        $body = $response->getContent();
        $this->assertEquals(200, $response->getStatusCode());
        $result = json_decode($body);
        $this->assertEquals('mf2+html', $result->{'source-format'});
        $data = $result->data;
        $this->assertEquals('feed', $data->type);
        $this->assertEquals(4, count($data->items));
        $this->assertEquals('One', $data->items[0]->name);
        $this->assertEquals('Two', $data->items[1]->name);
        $this->assertEquals('Three', $data->items[2]->name);
        $this->assertEquals('Four', $data->items[3]->name);
        // Check that the author h-card was matched up with each h-entry
        $this->assertEquals('Author Name', $data->items[0]->author->name);
        $this->assertEquals('Author Name', $data->items[1]->author->name);
        $this->assertEquals('Author Name', $data->items[2]->author->name);
        $this->assertEquals('Author Name', $data->items[3]->author->name);
    }

    public function testHCardWithChildHFeedNoExpect()
    {
        $url = 'http://feed.example.com/h-card-with-child-h-feed';
        $response = $this->parse(['url' => $url]);

        $body = $response->getContent();
        $this->assertEquals(200, $response->getStatusCode());
        $result = json_decode($body);
        $this->assertEquals('mf2+html', $result->{'source-format'});
        $data = $result->data;
        $this->assertEquals('card', $data->type);
        $this->assertEquals('Author Name', $data->name);
    }

    public function testJSONFeed()
    {
        $url = 'http://feed.example.com/jsonfeed';
        $response = $this->parse(['url' => $url, 'expect' => 'feed']);

        $body = $response->getContent();
        $this->assertEquals(200, $response->getStatusCode());
        $result = json_decode($body);
        $this->assertEquals('feed+json', $result->{'source-format'});
        $data = $result->data;

        $this->assertEquals(11, count($data->items));
        for($i=0; $i<8; $i++) {
            $this->assertEquals('entry', $data->items[$i]->type);
            $this->assertEquals('manton', $data->items[$i]->author->name);
            $this->assertEquals('http://www.manton.org', $data->items[$i]->author->url);
            $this->assertNotEmpty($data->items[$i]->url);
            $this->assertNotEmpty($data->items[$i]->uid);
            $this->assertNotEmpty($data->items[$i]->published);
            $this->assertNotEmpty($data->items[$i]->content->html);
            $this->assertNotEmpty($data->items[$i]->content->text);
        }
        $this->assertEquals('note', $data->items[0]->{'post-type'});
        $this->assertEquals('article', $data->items[4]->{'post-type'});

        $this->assertEquals('<p>Coming up on a year since I wrote about how <a href="http://www.manton.org/2016/11/todays-social-networks-are-broken.html">today’s social networks are broken</a>. Still what I believe.</p>', $data->items[7]->content->html);
        $this->assertEquals('Coming up on a year since I wrote about how today’s social networks are broken. Still what I believe.', $data->items[7]->content->text);
        $this->assertEquals('http://www.manton.org/2017/11/5979.html', $data->items[7]->url);
        $this->assertEquals('http://www.manton.org/2017/11/5979.html', $data->items[7]->uid);
        $this->assertEquals('2017-11-07T21:00:42+00:00', $data->items[7]->published);

        $this->assertEquals('feed', $data->type);
    }

    public function testJSONFeedFallbackAuthor()
    {
        $url = 'http://feed.example.com/jsonfeed-author';
        $response = $this->parse(['url' => $url, 'expect' => 'feed']);

        $body = $response->getContent();
        $this->assertEquals(200, $response->getStatusCode());
        $result = json_decode($body);
        $this->assertEquals('feed+json', $result->{'source-format'});
        $data = $result->data;

        $this->assertEquals(11, count($data->items));
        for($i=0; $i<8; $i++) {
            $this->assertEquals('entry', $data->items[$i]->type);
            $this->assertEquals('Manton Reece', $data->items[$i]->author->name);
            $this->assertEquals('https://www.manton.org/', $data->items[$i]->author->url);
            $this->assertEquals('https://micro.blog/manton/avatar.jpg', $data->items[$i]->author->photo);
        }
    }

    public function testJSONFeed1Point1()
    {
        $url = 'http://feed.example.com/jsonfeed-1.1';
        $response = $this->parse(['url' => $url, 'expect' => 'feed']);

        $body = $response->getContent();
        $this->assertEquals(200, $response->getStatusCode());
        $result = json_decode($body);
        $this->assertEquals('feed+json', $result->{'source-format'});
        $data = $result->data;

        $this->assertEquals(48, count($data->items));
        for($i=0; $i<8; $i++) {
            $this->assertEquals('entry', $data->items[$i]->type);
            $this->assertEquals('John Gruber', $data->items[$i]->author->name);
            $this->assertEquals('https://twitter.com/gruber', $data->items[$i]->author->url);
            $this->assertNotEmpty($data->items[$i]->url);
            $this->assertNotEmpty($data->items[$i]->uid);
            $this->assertNotEmpty($data->items[$i]->published);
            $this->assertNotEmpty($data->items[$i]->content->html);
            $this->assertNotEmpty($data->items[$i]->content->text);
        }
        $this->assertEquals('article', $data->items[0]->{'post-type'});
        $this->assertEquals('article', $data->items[4]->{'post-type'});

        $this->assertEquals('The Talk Show: “Blofeld-69-420”', $data->items[7]->name);
        $this->assertEquals('https://daringfireball.net/linked/2022/01/26/the-talk-show-335', $data->items[7]->url);
        $this->assertEquals('https://daringfireball.net/linked/2022/01/26/the-talk-show-335', $data->items[7]->uid);
        $this->assertEquals('2022-01-27T01:58:12Z', $data->items[7]->published);

        $this->assertEquals('feed', $data->type);
    }

    public function testJSONFeedTopLevelAuthor()
    {
        $url = 'http://feed.example.com/jsonfeed-top-level-author';
        $response = $this->parse(['url' => $url, 'expect' => 'feed']);

        $body = $response->getContent();
        $this->assertEquals(200, $response->getStatusCode());
        $result = json_decode($body, true);

        $this->assertEquals('feed+json', $result['source-format']);
        $data = $result['data'];

        $item = $data['items'][0];

        $this->assertEquals('Author Name', $item['author']['name']);
        $this->assertEquals('https://author.example.com', $item['author']['url']);
    }

    public function testJSONFeedRelativeImages()
    {
        $url = 'http://feed.example.com/jsonfeed';
        $response = $this->parse(['url' => $url, 'expect' => 'feed']);

        $body = $response->getContent();
        $this->assertEquals(200, $response->getStatusCode());
        $result = json_decode($body);
        $this->assertEquals('feed+json', $result->{'source-format'});
        $data = $result->data;

        // Relative image on an item that has a url
        $this->assertEquals('http://www.manton.org/2017/11/image.jpg', $data->items[9]->photo[0]);

        // Relative image on an item that has no URL, fall back to feed URL
        $this->assertEquals('http://feed.example.com/image.jpg', $data->items[10]->photo[0]);

        // Relative image inside the content html
        $this->assertStringContainsString('http://www.manton.org/2017/11/img.jpg', $data->items[9]->content->html);
    }

    public function testAtomFeed()
    {
        $url = 'http://feed.example.com/atom';
        $response = $this->parse(['url' => $url, 'expect' => 'feed']);

        $body = $response->getContent();
        $this->assertEquals(200, $response->getStatusCode());
        $result = json_decode($body);
        $this->assertEquals('xml', $result->{'source-format'});
        $data = $result->data;

        $this->assertEquals(8, count($data->items));
        for($i=0; $i<8; $i++) {
            $this->assertEquals('entry', $data->items[$i]->type);
            $this->assertEquals('note', $data->items[$i]->{'post-type'});
            $this->assertEquals('Tantek', $data->items[$i]->author->name);
            $this->assertEquals('http://tantek.com/', $data->items[$i]->author->url);
            $this->assertNotEmpty($data->items[$i]->url);
            $this->assertNotEmpty($data->items[$i]->published);
            $this->assertNotEmpty($data->items[$i]->content->html);
            $this->assertNotEmpty($data->items[$i]->content->text);
        }

        $this->assertEquals('2017-11-08T23:53:00-08:00', $data->items[0]->published);
        $this->assertEquals('http://tantek.com/2017/312/t3/tam-trail-run-first-trail-half', $data->items[0]->url);

        $this->assertEquals('went to MORE Pancakes! this morning @RunJanji pop-up on California st after #NPSF. Picked up a new running shirt.', $data->items[1]->content->text);
        $this->assertEquals('went to MORE Pancakes! this morning <a href="https://twitter.com/RunJanji">@RunJanji</a> pop-up on California st after #NPSF. Picked up a new running shirt.', $data->items[1]->content->html);

        $this->assertEquals('feed', $data->type);
    }

    public function testRSSFeed()
    {
        $url = 'http://feed.example.com/rss';
        $response = $this->parse(['url' => $url, 'expect' => 'feed']);

        $body = $response->getContent();
        $this->assertEquals(200, $response->getStatusCode());
        $result = json_decode($body);
        $this->assertEquals('xml', $result->{'source-format'});
        $data = $result->data;

        $this->assertEquals(10, count($data->items));
        for($i=0; $i<10; $i++) {
            $this->assertEquals('entry', $data->items[$i]->type);
            $this->assertEquals(($i == 4 ? 'article' : 'note'), $data->items[$i]->{'post-type'});
            $this->assertEquals('Ryan Barrett', $data->items[$i]->author->name);
            $this->assertEquals('https://snarfed.org/', $data->items[$i]->author->url);
            $this->assertNotEmpty($data->items[$i]->url);
            $this->assertNotEmpty($data->items[$i]->published);
            $this->assertNotEmpty($data->items[$i]->content->html);
            if($i > 1) {
                $this->assertNotEmpty($data->items[$i]->content->text);
            }
        }

        $this->assertEquals('2017-09-12T20:09:12+00:00', $data->items[9]->published);
        $this->assertEquals('https://snarfed.org/2017-09-12_25492', $data->items[9]->url);
        $this->assertEquals(
            '<p>new business cards <img src="https://s.w.org/images/core/emoji/2.3/72x72/1f602.png" alt="😂" /></p>
<p><img src="https://i0.wp.com/snarfed.org/w/wp-content/uploads/2017/09/IMG_20170912_131414_767.jpg?w=696&amp;ssl=1" alt="IMG_20170912_131414_767.jpg?w=696&amp;ssl=1" /></p>', $data->items[9]->content->html
        );
        $this->assertEquals('new business cards', $data->items[9]->content->text);

        $this->assertEquals('feed', $data->type);
    }

    public function testPodcastFeed()
    {
        $url = 'http://feed.example.com/podcast-rss';
        $response = $this->parse(['url' => $url, 'expect' => 'feed']);

        $body = $response->getContent();
        $this->assertEquals(200, $response->getStatusCode());
        $result = json_decode($body);
        $this->assertEquals('xml', $result->{'source-format'});
        $data = $result->data;

        $this->assertEquals(12, count($data->items));
        for($i=0; $i<12; $i++) {
            $this->assertEquals('entry', $data->items[$i]->type);
            $this->assertEquals('audio', $data->items[$i]->{'post-type'});
            $this->assertEquals('Aaron Parecki', $data->items[$i]->author->name);
            $this->assertEquals('https://percolator.today/', $data->items[$i]->author->url);
            $this->assertNotEmpty($data->items[$i]->url);
            $this->assertNotEmpty($data->items[$i]->published);
            $this->assertNotEmpty($data->items[$i]->name);
            $this->assertNotEmpty($data->items[$i]->content->html);
            $this->assertNotEmpty($data->items[$i]->content->text);
            $this->assertNotEmpty($data->items[$i]->audio);
        }

        $this->assertEquals('Episode 1: Welcome', $data->items[11]->name);
        $this->assertEquals('https://percolator.today/episode/1', $data->items[11]->url);
        $this->assertEquals('2017-09-20T07:00:00+00:00', $data->items[11]->published);
        $this->assertEquals('https://percolator.today/redirect.php?url=https%3A%2F%2Fpercolator.today%2Fmedia%2FPercolator_Episode_1.mp3', $data->items[11]->audio[0]);
        $this->assertStringContainsString('What is Percolator? Some thoughts about multi-photos in Instagram.', $data->items[11]->content->text);
        $this->assertStringContainsString('What is Percolator? Some thoughts about multi-photos in Instagram.', $data->items[11]->content->html);
        $this->assertStringContainsString('<li><a href="https://indieweb.org/multi-photo_vs_collection">multi-photo vs collection</a></li>', $data->items[11]->content->html);

        $this->assertEquals('feed', $data->type);
    }

    public function testInstagramAtomFeed()
    {
        $url = 'http://feed.example.com/instagram-atom';
        $response = $this->parse(['url' => $url, 'expect' => 'feed']);

        $body = $response->getContent();
        $this->assertEquals(200, $response->getStatusCode());
        $result = json_decode($body);
        $this->assertEquals('xml', $result->{'source-format'});
        $data = $result->data;
        $this->assertEquals(12, count($data->items));

        $this->assertEquals('Marshall Kirkpatrick', $data->items[11]->author->name);
        $this->assertEquals('https://www.instagram.com/marshallk/', $data->items[11]->author->url);
        $this->assertEquals('https://www.instagram.com/p/BcFjw9SHYql/', $data->items[11]->url);
        $this->assertEquals('2017-11-29T17:04:00+00:00', $data->items[11]->published);
        // Should remove the "name" since it's a prefix of the content
        $this->assertObjectNotHasAttribute('name', $data->items[11]);
        $this->assertEquals('Sometimes my job requires me to listen to 55 minutes of an hour long phone call while I go for a long walk on a sunny morning and wait for my turn to give an update. Pretty nice!', $data->items[11]->content->text);
    }

    public function testAscraeus()
    {
        $url = 'http://source.example.com/ascraeus';
        $response = $this->parse(['url' => $url, 'expect' => 'feed']);

        $body = $response->getContent();
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($body)->data;

        $this->assertEquals('feed', $data->type);
        $this->assertEquals(20, count($data->items));
    }

    public function testAdactioLinks()
    {
        $url = 'http://feed.example.com/adactio-links';
        $response = $this->parse(['url' => $url, 'expect' => 'feed']);

        $body = $response->getContent();
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($body)->data;

        $this->assertEquals('feed', $data->type);
        // 20 h-entrys followed by one h-card, which should have been removed and used as the author instead
        $this->assertEquals(20, count($data->items));
        $this->assertEquals('http://feed.example.com/links/14501', $data->items[0]->url);
        $this->assertEquals('http://feed.example.com/links/14445', $data->items[19]->url);
        $item = $data->items[0];
        $this->assertEquals('Jeremy Keith', $item->author->name);
        $this->assertEquals('https://adactio.com/', $item->author->url);
    }

    public function testWaterpigsFeed()
    {
        $url = 'http://feed.example.com/waterpigs';
        $response = $this->parse(['url' => $url, 'expect' => 'feed']);

        $body = $response->getContent();
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($body)->data;

        $this->assertEquals('feed', $data->type);
        $this->assertEquals(21, count($data->items));
        $item = $data->items[16];
        $this->assertEquals('Barnaby Walters', $item->author->name);
        $this->assertEquals('https://waterpigs.co.uk', $item->author->url);
    }

    public function testRSSWithNoXMLTag()
    {
        $url = 'http://feed.example.com/rss-no-xml-tag';
        $response = $this->parse(['url' => $url, 'expect' => 'feed']);
        $body = $response->getContent();
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($body)->data;

        $this->assertEquals('feed', $data->type);
    }

    public function testAuthorFeedOnHomePage()
    {
        $url = 'http://feed.example.com/h-feed-author-is-feed';
        $response = $this->parse(['url' => $url, 'expect' => 'feed']);
        $body = $response->getContent();
        $this->assertEquals(200, $response->getStatusCode());
        $parsed = json_decode($body, true);
        $data = $parsed['data'];

        $this->assertEquals('feed', $data['type']);
        $this->assertEquals('http://author.example.com/h-feed-author', $data['items'][0]['author']['url']);
        $this->assertEquals('Author', $data['items'][0]['author']['name']);
        $this->assertEquals('http://author.example.com/h-feed-author', $data['items'][1]['author']['url']);
        $this->assertEquals('Author', $data['items'][1]['author']['name']);
    }

    public function testAuthorFeedOnHomePageInvalid()
    {
        $url = 'http://feed.example.com/h-feed-author-is-bad-feed';
        $response = $this->parse(['url' => $url, 'expect' => 'feed']);
        $body = $response->getContent();
        $this->assertEquals(200, $response->getStatusCode());
        $parsed = json_decode($body, true);
        $data = $parsed['data'];

        $this->assertEquals('feed', $data['type']);
        $this->assertEquals('http://author.example.com/h-feed-author-bad', $data['items'][0]['author']['url']);
        $this->assertEquals('http://author.example.com/h-feed-author-bad', $data['items'][1]['author']['url']);
    }
}
