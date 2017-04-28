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
    $opts = [];

    if($request->get('timeout')) {
      // We might make 2 HTTP requests, so each request gets half the desired timeout
      $opts['timeout'] = $request->get('timeout') / 2;
    }

    if($request->get('max_redirects')) {
      $opts['max_redirects'] = (int)$request->get('max_redirects');
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

    $xray = new p3k\XRay();
    $xray->http = $this->http;
    $res = $xray->rels($url, $opts);

    return $this->respond($response, !empty($res['error']) ? 400 : 200, $res);
  }

}
