<?php
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

use p3k\XRay\Formats;

class Parse {

  public $http;
  public $mc;
  private $_cacheTime = 120;
  private $_pretty = false;
  private static $_version = '1.4.25';

  public static function useragent() {
    return 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_12_1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/57.0.2987.133 Safari/537.36 XRay/'.self::$_version.' ('.\Config::$base.')';
  }

  public function __construct() {
    $this->http = new p3k\HTTP(self::useragent());
    if(Config::$cache && class_exists('Memcache')) {
      $this->mc = new Memcache();
      $this->mc->addServer('127.0.0.1');
    }
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
    $response->headers->set('Version', 'XRay/'.self::$_version.' php-mf2/'.p3k\XRay\phpmf2_version());
    $opts = JSON_UNESCAPED_SLASHES;
    if($this->_pretty) $opts += JSON_PRETTY_PRINT;
    $response->setContent(json_encode($params, $opts)."\n");
    return $response;
  }

  public function parse(Request $request, Response $response) {
    $opts = [];

    if($request->get('timeout')) {
      // We might make 2 HTTP requests, so each request gets half the desired timeout
      $opts['timeout'] = $request->get('timeout') / 2;
    }

    if($request->get('max_redirects') !== null) {
      $opts['max_redirects'] = (int)$request->get('max_redirects');
    }

    if($request->get('target')) {
      $opts['target'] = $request->get('target');
    }

    if($request->get('expect')) {
      $opts['expect'] = $request->get('expect');
    }

    if($request->get('pretty')) {
      $this->_pretty = true;
    }

    if($request->get('include-mf1')) {
      $opts['include-mf1'] = $request->get('include-mf1') == 'false' ? false : true;
    }

    if($request->get('allow-iframe-video')) {
      $opts['allowIframeVideo'] = $request->get('allow-iframe-video') == 'true';
    }

    if($request->get('ignore-as2')) {
      $opts['ignore-as2'] = $request->get('ignore-as2') == 'true';
    }

    $url = $request->get('url');
    $html = $request->get('html') ?: $request->get('body');

    if(!$url && !$html) {
      return $this->respond($response, 400, [
        'error' => 'missing_url',
        'error_description' => 'Provide a URL or HTML to fetch',
      ]);
    }

    if($html) {
      // If HTML is provided in the request, parse that, and use the URL provided as the base URL for mf2 resolving
      $result['body'] = $html;
      $result['url'] = $url;
      $result['code'] = null;
    } else {
      $fetcher = new p3k\XRay\Fetcher($this->http);

      $fields = [
        'github_access_token',
        'token',
        'accept',
      ];
      foreach($fields as $f) {
        if($v=$request->get($f))
          $opts[$f] = $v;
      }

      $result = $fetcher->fetch($url, $opts);

      if(!empty($result['error'])) {
        $error_code = isset($result['error_code']) ? $result['error_code'] : 200;
        unset($result['error_code']);
        return $this->respond($response, $error_code, $result);
      }
    }

    $parser = new p3k\XRay\Parser($this->http);
    $parsed = $parser->parse($result, $opts);

    // Allow the parser to override the HTTP response code, e.g. a meta-equiv tag
    if(isset($parsed['code']))
      $result['code'] = $parsed['code'];

    if(!empty($parsed['error'])) {
      $error_code = isset($parsed['error_code']) ? $parsed['error_code'] : 200;
      unset($parsed['error_code']);
      return $this->respond($response, $error_code, $parsed);
    } else {
      $data = [
        'data' => $parsed['data'],
        'url' => $result['url'],
        'code' => $result['code'],
      ];
      if(isset($parsed['info']))
        $data['info'] = $parsed['info'];
      if($request->get('include_original') && isset($parsed['original']))
        $data['original'] = $parsed['original'];
      if(isset($parsed['source-format']))
        $data['source-format'] = $parsed['source-format'];
      if(isset($parsed['url']) && $parsed['url'] != $result['url'])
        $data['parsed-url'] = $parsed['url'];

      return $this->respond($response, 200, $data);
    }
  }

}
