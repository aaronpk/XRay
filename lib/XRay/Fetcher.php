<?php
namespace p3k\XRay;

use DateTime;


class Fetcher {
  private $http;
  private $httpsig;
  
  use HTTPSig;

  public function __construct($http) {
    $this->http = $http;
  }
  
  public function httpsig($key) {
    $this->httpsig = $key;
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
    
    if($this->httpsig) {
      // If we're making a signed GET, include the default headers that mastodon requires as part of the signature
      $date = new DateTime('UTC');
      $date = $date->format('D, d M Y H:i:s \G\M\T');
      $headers = $this->_headersToSign($url, $date);
    }

    $accept = 'application/mf2+json, application/activity+json, text/html, application/json, application/xml, text/xml';
    if(isset($opts['accept'])) {
      if($opts['accept'] == 'html')
        $accept = 'text/html';
      if($opts['accept'] == 'json')
        $accept = 'application/mf2+json, application/activity+json, application/json';
      if($opts['accept'] == 'activitypub')
        $accept = 'application/activity+json';
      if($opts['accept'] == 'xml')
        $accept = 'application/xml, text/xml';
    }

    // Override with the accept header here
    $headers['Accept'] = $accept;

    if(isset($opts['token']))
      $headers['Authorization'] = 'Bearer ' . $opts['token'];
    
    if(isset($opts['httpsig'])) {
      $this->_httpSign($headers, $this->httpsig);
    }

    $result = $this->http->get($url, $this->_headersToCurlArray($headers));

    if($result['error']) {
      return [
        'error' => $result['error'],
        'error_description' => $result['error_description'],
        'url' => $result['url'],
        'code' => $result['code'],
      ];
    }

    // Show an error if the content type returned is not a recognized type
    $format = null;
    if(isset($result['headers']['Content-Type'])) {
      $contentType = null;
      if(is_array($result['headers']['Content-Type'])) {
        $contentType = $result['headers']['Content-Type'][0];
      } elseif(is_string($result['headers']['Content-Type'])) {
        $contentType = $result['headers']['Content-Type'];
      }
      if($contentType) {
        $type = new MediaType($contentType);
        $format = $type->format;
      }
    }

    if(!$format ||
      !in_array($format, ['html', 'json', 'xml'])) {
        return [
          'error' => 'invalid_content',
          'error_description' => 'The server did not return a recognized content type',
          'content_type' => isset($result['headers']['Content-Type']) ? $result['headers']['Content-Type'] : null,
          'url' => $result['url'],
          'code' => $result['code']
        ];
    }

    if(trim($result['body']) == '') {
      if($result['code'] == 410) {
        // 410 Gone responses are valid and should not return an error
        return $result;
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
  
  public function signed_get($url, $headers) {
    $date = new DateTime('UTC');
    $date = $date->format('D, d M Y H:i:s \G\M\T');
    $headers = array_merge($headers, $this->_headersToSign($url, $date));
    $this->_httpSign($headers, $this->httpsig);
    return $this->http->get($url, $this->_headersToCurlArray($headers));
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
