<?php
class HelpersTest extends PHPUnit\Framework\TestCase
{

    public function testLowercaseHostname()
    {
        $url = 'http://Example.com/';
        $result = p3k\XRay\normalize_url($url);
        $this->assertEquals('http://example.com/', $result);
    }

    public function testAddsSlashToBareDomain()
    {
        $url = 'http://example.com';
        $result = p3k\XRay\normalize_url($url);
        $this->assertEquals('http://example.com/', $result);
    }

    public function testDoesNotModify()
    {
        $url = 'https://example.com/';
        $result = p3k\XRay\normalize_url($url);
        $this->assertEquals('https://example.com/', $result);
    }

    public function testURLEquality()
    {
        $url1 = 'https://example.com/';
        $url2 = 'https://example.com';
        $result = p3k\XRay\urls_are_equal($url1, $url2);
        $this->assertEquals(true, $result);
    }

    public function testFindMicroformatsByType()
    {
        $html = <<<EOF
      <div class="h-feed">
        <div class="u-author h-card">
          <a href="/1" class="u-url p-name">Author</a>
        </div>
        <div class="h-entry">
          <div class="u-author h-card">
            <a href="/2" class="u-url p-name">Author</a>
          </div>
        </div>
        <div class="h-card">
          <a href="/3" class="u-url p-name">Author</a>
        </div>
      </div>
      <div class="h-card">
        <a href="/4" class="u-url p-name">Author</a>
      </div>
EOF;

        $mf2 = \Mf2\parse($html);
        $hcards = \p3k\XRay\Formats\Mf2::findAllMicroformatsByType($mf2, 'h-card');
        $this->assertEquals('/1', $hcards[0]['properties']['url'][0]);
        $this->assertEquals('/2', $hcards[1]['properties']['url'][0]);
        $this->assertEquals('/3', $hcards[2]['properties']['url'][0]);
        $this->assertEquals('/4', $hcards[3]['properties']['url'][0]);
    }

}
