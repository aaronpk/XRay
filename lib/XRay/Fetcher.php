<?php
namespace p3k\XRay;

class Fetcher {
  private $http;

  public function __construct($http) {
    $this->http = $http;
  }

  public function fetch($url, $opts=[]) {
    if($opts == false) $opts = [];

    if(isset($opts['timeout']))
      $this->http->set_timeout($opts['timeout']);
    if(isset($opts['max_redirects']))
      $this->http->set_max_redirects($opts['max_redirects']);

    // Attempt some basic URL validation
    $scheme = parse_url($url, PHP_URL_SCHEME);
    if(!in_array($scheme, ['http','https'])) {
      return [
        'error_code' => 400,
        'error' => 'invalid_url',
        'error_description' => 'Only http and https URLs are supported'
      ];
    }

    $host = parse_url($url, PHP_URL_HOST);
    if(!$host) {
      return [
        'error_code' => 400,
        'error' => 'invalid_url',
        'error_description' => 'The URL provided was not valid'
      ];
    }

    $url = normalize_url($url);
    $host = parse_url($url, PHP_URL_HOST);

    // Check if this is a Twitter URL and use the API
    if(Formats\Twitter::matches_host($url)) {
      return $this->_fetch_tweet($url, $opts);
    }

    // Transform the HTML GitHub URL into an GitHub API request and fetch the API response
    if(Formats\GitHub::matches_host($url)) {
      return $this->_fetch_github($url, $opts);
    }

    // Check if this is a Hackernews URL and use the API
    if(Formats\Hackernews::matches($url)) {
      return Formats\Hackernews::fetch($this->http, $url, $opts);
    }

    // All other URLs are fetched normally

    // Special-case appspot.com URLs to not follow redirects.
    // https://cloud.google.com/appengine/docs/php/urlfetch/
    if(!should_follow_redirects($url)) {
      $this->http->set_max_redirects(0);
      $this->http->set_transport(new \p3k\HTTP\Stream());
    } else {
      $this->http->set_transport(new \p3k\HTTP\Curl());
    }

    $headers = [];
    if(isset($opts['token']))
      $headers[] = 'Authorization: Bearer ' . $opts['token'];

    $result = $this->http->get($url, $headers);

    if($result['error']) {
      return [
        'error' => $result['error'],
        'error_description' => $result['error_description'],
        'url' => $result['url'],
        'code' => $result['code'],
      ];
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

      return [
        'error' => 'no_content',
        'error_description' => 'We did not get a response body when fetching the URL',
        'url' => $result['url'],
        'code' => $result['code']
      ];
    }

    // Check for HTTP 401/403
    if($result['code'] == 401) {
      return [
        'error' => 'unauthorized',
        'error_description' => 'The URL returned "HTTP 401 Unauthorized"',
        'url' => $result['url'],
        'code' => $result['code']
      ];
    }
    if($result['code'] == 403) {
      return [
        'error' => 'forbidden',
        'error_description' => 'The URL returned "HTTP 403 Forbidden"',
        'url' => $result['url'],
        'code' => $result['code']
      ];
    }

    // If the original URL had a fragment, include it in the final URL
    if(($fragment=parse_url($url, PHP_URL_FRAGMENT)) && !parse_url($result['url'], PHP_URL_FRAGMENT)) {
      $result['url'] .= '#'.$fragment;
    }

    return [
      'url' => $result['url'],
      'body' => $result['body'],
      'code' => $result['code'],
    ];
  }

  private function _fetch_tweet($url, $opts) {
    $fields = ['twitter_api_key','twitter_api_secret','twitter_access_token','twitter_access_token_secret'];
    $creds = [];
    foreach($fields as $f) {
      if(isset($opts[$f]))
        $creds[$f] = $opts[$f];
    }

    if(count($creds) < 4) {
      return [
        'error_code' => 400,
        'error' => 'missing_parameters',
        'error_description' => 'All 4 Twitter credentials must be included in the request'
      ];
    }

    $tweet = Formats\Twitter::fetch($url, $creds);
    if(!$tweet) {
      return [
        'error' => 'twitter_error',
        'error_description' => $e->getMessage()
      ];
    }

    return [
      'url' => $url,
      'body' => $tweet,
      'code' => 200,
    ];
  }

  private function _fetch_github($url, $opts) {
    $fields = ['github_access_token'];
    $creds = [];
    foreach($fields as $f) {
      if(isset($opts[$f]))
        $creds[$f] = $opts[$f];
    }

    return Formats\GitHub::fetch($this->http, $url, $creds);
  }

}
