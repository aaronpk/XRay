<?php
class MediaTypeTest extends PHPUnit\Framework\TestCase
{

    public function testParseTextHtml()
    {
        $type = new p3k\XRay\MediaType('text/html');
        $this->assertEquals('text', $type->type);
        $this->assertEquals('html', $type->subtype);
        $this->assertEquals('html', $type->format);
        $this->assertEquals(null, $type->charset);
    }

    public function testParseTextHtmlUtf8()
    {
        $type = new p3k\XRay\MediaType('text/html; charset=UTF-8');
        $this->assertEquals('text', $type->type);
        $this->assertEquals('html', $type->subtype);
        $this->assertEquals('html', $type->format);
        $this->assertEquals('UTF-8', $type->charset);
    }

    public function testParseTextHtmlUtf8Extra()
    {
        $type = new p3k\XRay\MediaType('text/html; hello=world; charset=UTF-8');
        $this->assertEquals('text', $type->type);
        $this->assertEquals('html', $type->subtype);
        $this->assertEquals('html', $type->format);
        $this->assertEquals('UTF-8', $type->charset);
    }

    public function testParseApplicationJson()
    {
        $type = new p3k\XRay\MediaType('application/json');
        $this->assertEquals('application', $type->type);
        $this->assertEquals('json', $type->subtype);
        $this->assertEquals('json', $type->format);
        $this->assertEquals(null, $type->charset);
    }

    public function testParseApplicationJsonFeed()
    {
        $type = new p3k\XRay\MediaType('application/feed+json');
        $this->assertEquals('application', $type->type);
        $this->assertEquals('feed+json', $type->subtype);
        $this->assertEquals('json', $type->format);
        $this->assertEquals(null, $type->charset);
    }

}
