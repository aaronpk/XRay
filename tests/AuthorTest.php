<?php
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthorTest extends PHPUnit_Framework_TestCase {

  private $http;

  public function setUp() {
    $this->client = new Parse();
    $this->client->http = new p3k\HTTPTest(dirname(__FILE__).'/data/');
  }

  private function parse($params) {
    $request = new Request($params);
    $response = new Response();
    return $this->client->parse($request, $response);
  }

  public function testHEntryAuthorIsName() {
    $url = 'http://author.example.com/h-entry-author-is-name';
    $response = $this->parse(['url' => $url]);

    $body = $response->getContent();
    $this->assertEquals(200, $response->getStatusCode());
    $data = json_decode($body);

    $this->assertEmpty($data->data->author->url);
    $this->assertEquals('Author Name', $data->data->author->name);
    $this->assertEmpty($data->data->author->photo);
  }

  public function testHEntryAuthorIsRelLinkToHCardOnPage() {
    $url = 'http://author.example.com/h-entry-author-is-rel-link-to-h-card-on-page';
    $response = $this->parse(['url' => $url]);

    $body = $response->getContent();

    $this->assertEquals(200, $response->getStatusCode());
    $data = json_decode($body);

    $this->assertEquals('http://author.example.com/about', $data->data->author->url);
    $this->assertEquals('Author', $data->data->author->name);
    $this->assertEquals('http://author.example.com/photo.jpg', $data->data->author->photo);
  }

  public function testHEntryAuthorIsRelLinkToHCardWithRelMe() {
    $url = 'http://author.example.com/h-entry-author-is-rel-link-to-h-card-with-rel-me';
    $response = $this->parse(['url' => $url]);

    $body = $response->getContent();
    $this->assertEquals(200, $response->getStatusCode());
    $data = json_decode($body);

    $this->assertEquals('http://author.example.com/about-rel-me', $data->data->author->url);
    $this->assertEquals('Author Full Name', $data->data->author->name);
    $this->assertEquals('http://author.example.com/photo.jpg', $data->data->author->photo);
  }

  public function testHEntryAuthorIsRelLinkToHCardWithUrlUid() {
    $url = 'http://author.example.com/h-entry-author-is-rel-link-to-h-card-with-url-uid';
    $response = $this->parse(['url' => $url]);

    $body = $response->getContent();
    $this->assertEquals(200, $response->getStatusCode());
    $data = json_decode($body);

    $this->assertEquals('http://author.example.com/about-url-uid', $data->data->author->url);
    $this->assertEquals('Author Full Name', $data->data->author->name);
    $this->assertEquals('http://author.example.com/photo.jpg', $data->data->author->photo);
  }

  public function testHEntryAuthorIsUrlToHCardOnPage() {
    $url = 'http://author.example.com/h-entry-author-is-url-to-h-card-on-page';
    $response = $this->parse(['url' => $url]);

    $body = $response->getContent();
    $this->assertEquals(200, $response->getStatusCode());
    $data = json_decode($body);

    $this->assertEquals('http://author.example.com/about', $data->data->author->url);
    $this->assertEquals('Author', $data->data->author->name);
    $this->assertEquals('http://author.example.com/photo.jpg', $data->data->author->photo);
  }

  public function testHEntryAuthorIsUrlToHCardWithMultipleLinks() {
    $url = 'http://author.example.com/h-entry-author-is-url-to-h-card-with-multiple-links';
    $response = $this->parse(['url' => $url]);

    $body = $response->getContent();

    $this->assertEquals(200, $response->getStatusCode());
    $data = json_decode($body);

    $this->assertEquals('http://author.example.com/about-with-multiple-urls', $data->data->author->url);
    $this->assertEquals('Author Full Name', $data->data->author->name);
    $this->assertEquals('http://author.example.com/photo.jpg', $data->data->author->photo);
  }

  public function testHEntryHasHCardAndUrlAuthor() {
    $url = 'http://author.example.com/h-entry-has-h-card-and-url-author';
    $response = $this->parse(['url' => $url]);

    $body = $response->getContent();
    $this->assertEquals(200, $response->getStatusCode());
    $data = json_decode($body);

    $this->assertEquals('http://author.example.com/about', $data->data->author->url);
    $this->assertEquals('Author', $data->data->author->name);
    $this->assertEquals('http://author.example.com/photo.jpg', $data->data->author->photo);
  }

  public function testHEntryHasHCardAuthor() {
    $url = 'http://author.example.com/h-entry-has-h-card-author';
    $response = $this->parse(['url' => $url]);

    $body = $response->getContent();
    $this->assertEquals(200, $response->getStatusCode());
    $data = json_decode($body);

    $this->assertEquals('http://author.example.com/about', $data->data->author->url);
    $this->assertEquals('Author', $data->data->author->name);
    $this->assertEquals('http://author.example.com/photo.jpg', $data->data->author->photo);
  }

  public function testPageIsHCard() {
    $url = 'http://author.example.com/about';
    $response = $this->parse(['url' => $url]);

    $body = $response->getContent();
    $this->assertEquals(200, $response->getStatusCode());
    $data = json_decode($body);

    $this->assertEquals('card', $data->data->type);
    $this->assertEquals('http://author.example.com/about', $data->data->url);
    $this->assertEquals('Author Full Name', $data->data->name);
    $this->assertEquals('http://author.example.com/photo.jpg', $data->data->photo);
  }

  /*
  public function testHFeedHasHCardAuthor() {
    $url = 'http://author.example.com/h-feed-has-h-card-author';
    $response = $this->parse(['url' => $url]);

    $body = $response->getContent();
    print_r($body);
    $this->assertEquals(200, $response->getStatusCode());
    $data = json_decode($body);
    $this->assertEquals('http://author.example.com/about', $data->data->author->url);
    $this->assertEquals('Author', $data->data->author->name);
    $this->assertEquals('http://author.example.com/photo.jpg', $data->data->author->photo);
  }
  */

}
