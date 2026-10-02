<?php
class SafeModeTest extends PHPUnit\Framework\TestCase
{

    public static function unsafeUrls()
    {
        return [
            ['http://127.0.0.1/'], ['http://localhost/'], ['http://10.0.0.1/'], ['http://169.254.169.254/latest/meta-data/'],
            ['http://[::1]/'], ['http://2130706433/'], ['gopher://127.0.0.1:6379/_PING'], ['file:///etc/passwd'],
        ];
    }

    /** @dataProvider unsafeUrls */
    public function testUnsafeUrlsAreNotFetched($url)
    {
        $xray = new p3k\XRay();

        $this->assertTrue($xray->http->safe_mode());
        foreach (['parse', 'rels', 'feeds'] as $method) {
            $result = $xray->{$method}($url);
            // Non-http schemes were already refused as invalid_url.
            $this->assertContains($result['error'] ?? null, ['blocked_url', 'invalid_url'], "$method $url");
        }
    }

    public function testAllowPrivate()
    {
        // Nothing listens on port 9 here; what matters is that a connection
        // was attempted rather than refused by the check.
        $xray = new p3k\XRay(['allow_private' => ['127.0.0.1'], 'timeout' => 2]);

        $result = $xray->parse('http://127.0.0.1:9/');
        $this->assertNotEquals('blocked_url', $result['error'] ?? null);
        $this->assertEquals('blocked_url', $xray->parse('http://127.0.0.2:9/')['error'] ?? null);
    }

    public function testSafeModeCanBeTurnedOff()
    {
        $xray = new p3k\XRay(['safe_mode' => false]);

        $this->assertFalse($xray->http->safe_mode());
    }

    public function testServiceControllersUseSafeMode()
    {
        foreach ([new Parse(), new Rels(), new Feeds()] as $controller) {
            $this->assertTrue($controller->http->safe_mode(), get_class($controller));
        }
    }

}
