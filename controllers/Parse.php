<?php
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

use XRay\Formats;

class Parse {

  public $http;
  public $mc;
  private $_cacheTime = 120;
  private $_pretty = false;

  public static function useragent() {
    return 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_12_1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/57.0.2987.133 Safari/537.36 XRay/1.0.0 ('.\Config::$base.')';
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
      $this->http->set_timeout($request->get('timeout') / 2);
    }

    if($request->get('max_redirects') !== null) {
      $this->http->set_max_redirects((int)$request->get('max_redirects'));
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
      $result['url'] = $url;
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

      // Check if this is a Twitter URL and if they've provided API credentials, use the API
      if(preg_match('/https?:\/\/(?:mobile\.twitter\.com|twitter\.com|twtr\.io)\/(?:[a-z0-9_\/!#]+statuse?s?\/([0-9]+)|([a-zA-Z0-9_]+))/i', $url, $match)) {
        return $this->parseTwitterURL($request, $response, $url, $match);
      }

      if($host == 'github.com') {
        return $this->parseGitHubURL($request, $response, $url);
      }

      if(!should_follow_redirects($url))
        $this->http->set_transport(new p3k\HTTP\Stream());
      else
        $this->http->set_transport(new p3k\HTTP\Curl());

      // Now fetch the URL and check for any curl errors
      // Don't cache the response if a token is used to fetch it
      if($this->mc && !$request->get('token')) {
        $cacheKey = 'xray-'.md5($url);
        if($cached=$this->mc->get($cacheKey)) {
          $result = json_decode($cached, true);
          self::debug('using HTML from cache', 'X-Cache-Debug');
        } else {
          $result = $this->http->get($url);
          $cacheData = json_encode($result);
          // App Engine limits the size of cached items, so don't cache ones larger than that
          if(strlen($cacheData) < 1000000) 
            $this->mc->set($cacheKey, $cacheData, MEMCACHE_COMPRESSED, $this->_cacheTime);
        }
      } else {
        $headers = [];
        if($request->get('token')) {
          $headers[] = 'Authorization: Bearer ' . $request->get('token');
        }

        $result = $this->http->get($url, $headers);
      }

      if($result['error']) {
        return $this->respond($response, 200, [
          'error' => $result['error'],
          'error_description' => $result['error_description'],
          'url' => $result['url'],
          'code' => $result['code']
        ]);
      }

      if(trim($result['body']) == '') {
        if($result['code'] == 410) {
          // 410 Gone responses are valid and should not return an error
          return $this->respond($response, 200, [
            'data' => [
              'type' => 'unknown'
            ],
            'url' => $result['url'],
            'code' => $result['code']
          ]);
        }

        return $this->respond($response, 200, [
          'error' => 'no_content',
          'error_description' => 'We did not get a response body when fetching the URL',
          'url' => $result['url'],
          'code' => $result['code']
        ]);
      }

      // Check for HTTP 401/403
      if($result['code'] == 401) {
        return $this->respond($response, 200, [
          'error' => 'unauthorized',
          'error_description' => 'The URL returned "HTTP 401 Unauthorized"',
          'url' => $result['url'],
          'code' => 401
        ]);
      }
      if($result['code'] == 403) {
        return $this->respond($response, 200, [
          'error' => 'forbidden',
          'error_description' => 'The URL returned "HTTP 403 Forbidden"',
          'url' => $result['url'],
          'code' => 403
        ]);
      }

    }

    // Check for known services
    $host = parse_url($result['url'], PHP_URL_HOST);

    if(in_array($host, ['www.instagram.com','instagram.com'])) {
      list($data, $parsed) = Formats\Instagram::parse($result['body'], $result['url'], $this->http);
      if($request->get('include_original'))
        $data['original'] = $parsed;
      $data['url'] = $result['url'];
      $data['code'] = $result['code'];
      return $this->respond($response, 200, $data);
    }

    if($host == 'xkcd.com' && parse_url($url, PHP_URL_PATH) != '/') {
      $data = Formats\XKCD::parse($result['body'], $url);
      $data['url'] = $result['url'];
      $data['code'] = $result['code'];
      return $this->respond($response, 200, $data);
    }

    // attempt to parse the page as HTML
    $doc = new DOMDocument();
    @$doc->loadHTML(self::toHtmlEntities($result['body']));

    if(!$doc) {
      return $this->respond($response, 200, [
        'error' => 'invalid_content',
        'error_description' => 'The document could not be parsed as HTML'
      ]);
    }

    $xpath = new DOMXPath($doc);

    // Check for meta http equiv and replace the status code if present
    foreach($xpath->query('//meta[translate(@http-equiv,\'STATUS\',\'status\')=\'status\']') as $el) {
      $equivStatus = ''.$el->getAttribute('content');
      if($equivStatus && is_string($equivStatus)) {
        if(preg_match('/^(\d+)/', $equivStatus, $match)) {
          $result['code'] = (int)$match[1];
        }
      }
    }

    // If a target parameter was provided, make sure a link to it exists on the page
    if($target=$request->get('target')) {
      $found = [];
      if($target) {
        self::xPathFindNodeWithAttribute($xpath, 'a', 'href', function($u) use($target, &$found){
          if($u == $target) {
            $found[$u] = null;
          }
        });
        self::xPathFindNodeWithAttribute($xpath, 'img', 'src', function($u) use($target, &$found){
          if($u == $target) {
            $found[$u] = null;
          }
        });
        self::xPathFindNodeWithAttribute($xpath, 'video', 'src', function($u) use($target, &$found){
          if($u == $target) {
            $found[$u] = null;
          }
        });
        self::xPathFindNodeWithAttribute($xpath, 'audio', 'src', function($u) use($target, &$found){
          if($u == $target) {
            $found[$u] = null;
          }
        });
      }

      if(!$found) {
        return $this->respond($response, 200, [
          'error' => 'no_link_found',
          'error_description' => 'The source document does not have a link to the target URL',
          'url' => $result['url'],
          'code' => $result['code'],
        ]);
      }
    }

    // If the URL has a fragment ID, find the DOM starting at that node and parse it instead
    $html = $result['body'];

    $fragment = parse_url($url, PHP_URL_FRAGMENT);
    if($fragment) {
      $fragElement = self::xPathGetElementById($xpath, $fragment);
      if($fragElement) {
        $html = $doc->saveHTML($fragElement);
        $foundFragment = true;
      } else {
        $foundFragment = false;
      }
    }

    // Now start pulling in the data from the page. Start by looking for microformats2
    $mf2 = mf2\Parse($html, $result['url']);

    if($mf2 && count($mf2['items']) > 0) {
      $data = Formats\Mf2::parse($mf2, $result['url'], $this->http);
      if($data) {
        if($fragment) {
          $data['info'] = [
            'found_fragment' => $foundFragment
          ];
        }
        if($request->get('include_original'))
          $data['original'] = $html;
        $data['url'] = $result['url']; // this will be the effective URL after following redirects
        $data['code'] = $result['code'];
        return $this->respond($response, 200, $data);
      }
    }

    // TODO: look for other content like OEmbed or other known services later

    return $this->respond($response, 200, [
      'data' => [
        'type' => 'unknown',
      ],
      'url' => $result['url'],
      'code' => $result['code']
    ]);
  }

  private static function xPathFindNodeWithAttribute($xpath, $node, $attr, $callback) {
    foreach($xpath->query('//'.$node.'[@'.$attr.']') as $el) {
      $v = $el->getAttribute($attr);
      $callback($v);
    }
  }

  private static function xPathGetElementById($xpath, $id) {
    $element = null;
    foreach($xpath->query("//*[@id='$id']") as $el) {
      $element = $el;
    }
    return $element;
  }

  private function parseTwitterURL(&$request, &$response, $url, $match) {
    $fields = ['twitter_api_key','twitter_api_secret','twitter_access_token','twitter_access_token_secret'];
    $creds = [];
    foreach($fields as $f) {
      if($v=$request->get($f))
        $creds[$f] = $v;
    }
    $data = false;
    if(count($creds) == 4) {
      list($data, $parsed) = Formats\Twitter::parse($url, $match[1], $creds);
    } elseif(count($creds) > 0) {
      // If only some Twitter credentials were present, return an error  
      return $this->respond($response, 400, [
        'error' => 'missing_parameters',
        'error_description' => 'All 4 Twitter credentials must be included in the request'
      ]);
    } else {
      // Accept Tweet JSON and parse that if provided
      $json = $request->get('json');
      if($json) {
        list($data, $parsed) = Formats\Twitter::parse($url, $match[1], null, $json);
      }
      // Skip parsing from the Twitter API if they didn't include credentials
    }

    if($data) {
      if($request->get('include_original'))
        $data['original'] = $parsed;
      $data['url'] = $url;
      $data['code'] = 200;
      return $this->respond($response, 200, $data);
    } else {
      return $this->respond($response, 200, [
        'data' => [
          'type' => 'unknown'
        ],
        'url' => $url,
        'code' => 0
      ]);
    }
  }

  private function parseGitHubURL(&$request, &$response, $url) {
    $fields = ['github_access_token'];
    $creds = [];
    foreach($fields as $f) {
      if($v=$request->get($f))
        $creds[$f] = $v;
    }
    $data = false;
    $json = $request->get('json');
    if($json) {
      // Accept GitHub JSON and parse that if provided
      list($data, $json, $code) = Formats\GitHub::parse($this->http, $url, null, $json);
    } else {
      // Otherwise fetch the post unauthenticated or with the provided access token
      list($data, $json, $code) = Formats\GitHub::parse($this->http, $url, $creds);
    }

    if($data) {
      if($request->get('include_original'))
        $data['original'] = $json;
      $data['url'] = $url;
      $data['code'] = $code;
      return $this->respond($response, 200, $data);
    } else {
      return $this->respond($response, 200, [
        'data' => [
          'type' => 'unknown'
        ],
        'url' => $url,
        'code' => $code
      ]);
    }
  }


}
