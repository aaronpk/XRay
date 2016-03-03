<?php
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

use XRay\Formats;

class Parse {

  public $http;
  public $mc;
  private $_cacheTime = 300;
  private $_pretty = false;

  public function __construct() {
    $this->http = new p3k\HTTP();
    $this->mc = new Memcache();
    $this->mc->addServer('127.0.0.1');
  }

  public static function debug($msg, $header='X-Parse-Debug') {
    syslog(LOG_INFO, $msg);
    if(array_key_exists('REMOTE_ADDR', $_SERVER))
      header($header . ": " . $msg);
  }

  private function respond(Response $response, $code, $params, $headers=[]) {
    $response->setStatusCode($code);
    foreach($headers as $k=>$v) {
      $response->headers->set($k, $v);
    }
    $response->headers->set('Content-Type', 'application/json');
    $opts = JSON_UNESCAPED_SLASHES;
    if($this->_pretty) $opts += JSON_PRETTY_PRINT;
    $response->setContent(json_encode($params, $opts)."\n");
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

    if($request->get('pretty')) {
      $this->_pretty = true;
    }

    $url = $request->get('url');
    $html = $request->get('html');

    if(!$url && !$html) {
      return $this->respond($response, 400, [
        'error' => 'missing_url',
        'error_description' => 'Provide a URL or HTML to fetch'
      ]);
    }

    if($html) {
      // If HTML is provided in the request, parse that, and use the URL provided as the base URL for mf2 resolving
      $result['body'] = $html;
    } else {
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
      if($cached=$this->mc->get('xray-'.md5($url))) {
        $result = json_decode($cached, true);
        self::debug('using HTML from cache', 'X-Cache-Debug');
      } else {
        $result = $this->http->get($url);
        $cacheData = json_encode($result);
        if(strlen($cacheData) < 1000000) // App Engine limits the size of cached items, so don't cache ones larger than that
          $this->mc->set('xray-'.md5($url), $cacheData, MEMCACHE_COMPRESSED, $this->_cacheTime);
      }

      if($result['error']) {
        return $this->respond($response, 400, [
          'error' => $result['error'],
          'error_description' => $result['error_description']
        ]);
      }

      if(trim($result['body']) == '') {
        return $this->respond($response, 200, [
          'error' => 'no_content',
          'error_description' => 'We did not get a response body when fetching the URL'
        ]);
      }
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
        $u = $href->getAttribute('href');

        if($target) {
          # target parameter was provided
          if($u == $target) {
            $found[$u] = null;
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
    $mf2 = mf2\Parse($result['body'], $result['url']);

    if($mf2 && count($mf2['items']) > 0) {
      $data = Formats\Mf2::parse($mf2, $result['url'], $this->http);
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
