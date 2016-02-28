<?php
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

use XRay\Formats;

class Parse {

  public $http;

  public function __construct() {
    $this->http = new p3k\HTTP();
  }

  private function respond(Response $response, $code, $params, $headers=[]) {
    $response->setStatusCode($code);
    foreach($headers as $k=>$v) {
      $response->headers->set($k, $v);
    }
    $response->headers->set('Content-Type', 'application/json');
    $response->setContent(json_encode($params));
    return $response;
  }

  private static function toHtmlEntities($input) {
    return mb_convert_encoding($input, 'HTML-ENTITIES', mb_detect_encoding($input));
  }

  public function parse(Request $request, Response $response) {

    if($request->get('timeout')) {
      // We might make 2 HTTP requests, so each request gets half the desired timeout
      $this->http->timeout = $request->get('timeout') / 2;
    }

    if($request->get('max_redirects')) {
      $this->http->max_redirects = (int)$request->get('max_redirects');
    }

    $url = $request->get('url');

    if(!$url) {
      return $this->respond($response, 400, [
        'error' => 'missing_url',
        'error_description' => 'Provide a URL to fetch'
      ]);
    }

    // Attempt some basic URL validation
    $scheme = parse_url($url, PHP_URL_SCHEME);
    if(!in_array($scheme, ['http','https'])) {
      return $this->respond($response, 400, [
        'error' => 'invalid_url',
        'error_description' => 'Only http and https URLs are supported'
      ]);
    }

    $host = parse_url($url, PHP_URL_HOST);
    if(!$host) {
      return $this->respond($response, 400, [
        'error' => 'invalid_url',
        'error_description' => 'The URL provided was not valid'
      ]);
    }

    $url = \normalize_url($url);

    // Now fetch the URL and check for any curl errors
    $result = $this->http->get($url);

    if($result['error']) {
      return $this->respond($response, 400, [
        'error' => $result['error'],
        'error_description' => $result['error_description']
      ]);
    }

    // attempt to parse the page as HTML
    $doc = new DOMDocument();
    @$doc->loadHTML(self::toHtmlEntities($result['body']));

    if(!$doc) {
      return $this->respond($response, 400, [
        'error' => 'invalid_content',
        'error_description' => 'The document could not be parsed as HTML'
      ]);
    }

    // If a target parameter was provided, make sure a link to it exists on the page
    if($target=$request->get('target')) {
      $xpath = new DOMXPath($doc);

      $found = [];
      foreach($xpath->query('//a[@href]') as $href) {
        $url = $href->getAttribute('href');

        if($target) {
          # target parameter was provided
          if($url == $target) {
            $found[$url] = null;
          }
        }
      }

      if(!$found) {
        return $this->respond($response, 400, [
          'error' => 'no_link_found',
          'error_description' => 'The source document does not have a link to the target URL'
        ]);
      }
    }

    // Now start pulling in the data from the page. Start by looking for microformats2
    $mf2 = mf2\Parse($result['body'], $url);

    if($mf2 && count($mf2['items']) > 0) {
      $data = Formats\Mf2::parse($mf2, $url, $this->http);
      if($data) {
        return $this->respond($response, 200, $data);
      }
    }

    // TODO: look for other content like OEmbed or other known services later


    return $this->respond($response, 200, [
      'data' => [
        'type' => 'unknown',
      ]
    ]);
  }

}
