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

}
