<?php
class LibraryTest extends PHPUnit\Framework\TestCase
{

    public function testInputIsParsedMf2Array()
    {
        $html = '<div class="h-entry"><p class="p-content p-name">Hello World</p><img src="/photo.jpg"></p></div>';
        $mf2 = Mf2\parse($html, 'http://example.com/entry');

        $xray = new p3k\XRay();
        $data = $xray->process('http://example.com/entry', $mf2);

        $this->assertEquals('Hello World', $data['data']['content']['text']);
        $this->assertEquals('http://example.com/photo.jpg', $data['data']['photo'][0]);
    }

    public function testInputIsParsedMf2JSON()
    {
        $html = '<div class="h-entry"><p class="p-content p-name">Hello World</p><img src="/photo.jpg"></p></div>';
        $mf2 = Mf2\parse($html, 'http://example.com/entry');

        $xray = new p3k\XRay();
        $data = $xray->process('http://example.com/entry', json_encode($mf2));

        $this->assertEquals('Hello World', $data['data']['content']['text']);
        $this->assertEquals('http://example.com/photo.jpg', $data['data']['photo'][0]);
    }

    public function testInputIsParsedMf2HCard()
    {
        $url = 'https://waterpigs.co.uk/';
        $html = '<a class="h-card" href="https://waterpigs.co.uk/">Barnaby Walters</a>';
        $mf2 = Mf2\parse($html, $url);

        $xray = new p3k\XRay();
        $data = $xray->process($url, $mf2);
        $this->assertEquals('card', $data['data']['type']);
        $this->assertEquals('Barnaby Walters', $data['data']['name']);
    }

    public function testNoHEntryMarkupMF2JSON()
    {
        $url = 'http://example.com/';
        $html = '<p><a href="http://target.example.com/">Target</a></p>';
        $mf2 = Mf2\parse($html, $url);

        $xray = new p3k\XRay();
        $data = $xray->process($url, $mf2);
        $this->assertEquals('unknown', $data['data']['type']);
    }

    public function testNoHEntryMarkup()
    {
        $url = 'http://example.com/';
        $html = '<p><a href="http://target.example.com/">Target</a></p>';

        $xray = new p3k\XRay();
        $data = $xray->parse($url, $html);
        $this->assertEquals('unknown', $data['data']['type']);
    }

    public function testNoHEntryMarkupWithTarget()
    {
        $url = 'http://example.com/';
        $html = '<p><a href="http://target.example.com/">Target</a></p>';

        $xray = new p3k\XRay();
        $data = $xray->parse($url, $html, ['target' => 'http://target.example.com/']);
        $this->assertEquals('unknown', $data['data']['type']);
        $this->assertArrayNotHasKey('error', $data);
        $this->assertArrayNotHasKey('html', $data);
    }

    public function testHandlesHCardWithoutURLProperty()
    {
        $url = 'http://example.com/';
        $html = '<p class="h-card">The Mythical URLless Person</p>';
        $xray = new p3k\XRay();
        $data = $xray->parse($url, $html);
        $this->assertEquals('card', $data['data']['type']);
        // On pages where the h-card is the main data but lacks a URL property, it will be filled with the page URL.
        $this->assertEquals($url, $data['data']['url']);
    }

    public function testDefaultOptionsAreUsed()
    {
        $url = 'http://example.com/';
        $html = '<p class="h-card">A Person</p>';

        $defaultOptionsXRay = new p3k\XRay(['include_original' => true]);
        $normalXRay = new p3k\XRay();

        // Make sure that the options we’re testing with actually result in different values first.
        $this->assertNotEquals(
            $defaultOptionsXRay->parse($url, $html),
            $normalXRay->parse($url, $html)
        );
        
        // Make sure that the options are applied in the same way as they would have been if passed to parse()
        $this->assertEquals(
            $defaultOptionsXRay->parse($url, $html),
            $normalXRay->parse($url, $html, ['include_original' => true])
        );

        // Make sure that the options can be overridden (this doesn’t test on a property-by-property basis but should be good enough.)
        $this->assertEquals(
            $defaultOptionsXRay->parse($url, $html, ['include_original' => false]),
            $normalXRay->parse($url, $html)
        );
    }

    public function testDefaultOptionsAreUsedForFetching()
    {
        // LibraryTest::testDefaultOptionsAreUsed can only test that default options are merged and passed 
        // to the relevant format handler. To test that they’re additionally passed to the fetcher currently
        // requires a network request to the twitter API for an auth error.
        // A potential future improvement for this would be to make a new mock HTTP client object which 
        // accepts a callback, which gets passed the request it would send. We can then check that the
        // request has the parameters we want without having to actually hit the network.
        $url = 'https://twitter.com/BarnabyWalters/status/990659593561952256';

        // Confirm the expected behaviour.
        $xray = new p3k\XRay();
        $result = $xray->parse($url);
        $this->assertEquals('missing_parameters', $result['error']);

        $xray = new p3k\XRay([
            'twitter_api_key' => 'extremely real API credentials',
            'twitter_api_secret' => 'extremely real API credentials',
            'twitter_access_token' => 'extremely real API credentials',
            'twitter_access_token_secret' => 'extremely real API credentials'
        ]);
        $result = $xray->parse($url);
        $this->assertEquals('twitter_error', $result['error']);
    }

}
