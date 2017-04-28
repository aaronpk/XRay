<?php
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class Rels {

  public $http;
  private $_pretty = false;

  public function __construct() {
    $this->http = new p3k\HTTP();
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

  public function fetch(Request $request, Response $response) {
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

    $url = p3k\XRay\normalize_url($url);

    $result = $this->http->get($url);

    $html = $result['body'];
    $mf2 = mf2\Parse($html, $result['url']);

    $rels = $result['rels'];
    if(isset($mf2['rels'])) {
      $rels = array_merge($rels, $mf2['rels']);
    }

    // Resolve all relative URLs
    foreach($rels as $rel=>$values) {
      foreach($values as $i=>$value) {
        $value = \mf2\resolveUrl($result['url'], $value);
        $rels[$rel][$i] = $value;
      }
    }

    if(count($rels) == 0)
      $rels = new StdClass;

    return $this->respond($response, 200, [
      'url' => $result['url'],
      'code' => $result['code'],
      'rels' => $rels
    ]);
  }

}
