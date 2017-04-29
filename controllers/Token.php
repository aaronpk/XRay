<?php
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class Token {

  public $http;
  private $_pretty = false;

  public function __construct() {
    $this->http = new p3k\HTTP();
  }

  public function token(Request $request, Response $response) {

    if($request->get('pretty')) {
      $this->_pretty = true;
    }

    $source = $request->get('source');
    $code = $request->get('code');

    if(!$source) {
      return $this->respond($response, 400, [
        'error' => 'invalid_request',
        'error_description' => 'Provide a source URL'
      ]);
    }

    if(!$code) {
      return $this->respond($response, 400, [
        'error' => 'invalid_request',
        'error_description' => 'Provide an authorization code'
      ]);
    }

    $scheme = parse_url($source, PHP_URL_SCHEME);
    if(!in_array($scheme, ['http','https'])) {
      return $this->respond($response, 400, [
        'error' => 'invalid_url',
        'error_description' => 'Only http and https URLs are supported'
      ]);
    }

    // First try to discover the token endpoint
    $head = $this->http->head($source);

    if(!array_key_exists('Link', $head['headers'])) {
      return $this->respond($response, 200, [
        'error' => 'no_token_endpoint',
        'error_description' => 'No Link headers were returned'
      ]);
    }

    if(is_string($head['headers']['Link']))
      $head['headers']['Link'] = [$head['headers']['Link']];

    $rels = $head['rels'];

    $endpoint = false;
    if(array_key_exists('token_endpoint', $rels)) {
      $endpoint = $rels['token_endpoint'][0];
    } elseif(array_key_exists('oauth2-token', $rels)) {
      $endpoint = $rels['oauth2-token'][0];
    }

    if(!$endpoint) {
      return $this->respond($response, 200, [
        'error' => 'no_token_endpoint',
        'error_description' => 'No token endpoint was found in the headers'
      ]);
    }

    // Resolve the endpoint URL relative to the source URL
    $endpoint = \mf2\resolveUrl($source, $endpoint);

    // Now exchange the code for a token
    $token = $this->http->post($endpoint, [
      'grant_type' => 'authorization_code',
      'code' => $code
    ]);

    // Catch HTTP errors here such as timeouts
    if($token['error']) {
      return $this->respond($response, 200, [
        'error' => $token['error'],
        'error_description' => $token['error_description'] ?: 'An unknown error occurred trying to fetch the token'
      ]);
    }

    // Otherwise pass through the response from the token endpoint
    $body = @json_decode($token['body']);

    // Pass through the content type if we were not able to decode the response as JSON
    $headers = [];
    if(!$body && isset($token['headers']['Content-Type'])) {
      $headers['Content-Type'] = $token['headers']['Content-Type'];
    }

    return $this->respond($response, 200, $body ?: $token['body'], $headers);
  }

  private function respond(Response $response, $code, $params, $headers=[]) {
    $response->setStatusCode($code);
    foreach($headers as $k=>$v) {
      $response->headers->set($k, $v);
    }
    if(is_array($params) || is_object($params)) {
      $response->headers->set('Content-Type', 'application/json');
      $opts = JSON_UNESCAPED_SLASHES;
      if($this->_pretty) $opts += JSON_PRETTY_PRINT;
      $response->setContent(json_encode($params, $opts)."\n");
    } else {
      $response->setContent($params);
    }
    return $response;
  }

}
