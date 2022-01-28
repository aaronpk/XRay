<?php
namespace p3k\XRay;

use p3k\XRay\Formats;
use DOMDocument, DOMXPath;

class Parser {
  private $http;

  public function __construct($http) {
    $this->http = $http;
  }

  public function parse($http_response, $opts=[]) {
    $allowIframeVideo = isset($opts['allowIframeVideo']) ? $opts['allowIframeVideo'] : false;
    allow_iframe_video($allowIframeVideo);
    $document = $this->parse_document($http_response, $opts);

    // If a target parameter was provided, make sure a link to it exists in the parsed document
    if(!isset($document['error']) && !empty($opts['target'])) {

      if(isset($document['data']['type']) && $document['data']['type'] == 'unknown') {
        if(isset($document['html'])) {
          // Couldn't parse the page, check for the link manually assuming HTML content
          $found = $this->_findLinkInHTML($opts['target'], $document['html']);
        } else {
          // Ignore this check for any non-HTML documents since this will be uncommon anyway
          $found = false;
        }
        $error_description = 'The source document does not have a link to the target URL';
      } else {
        $found = $this->_findLinkInTree($opts['target'], $document['data']);
        $error_description = 'The Microformats at the source URL do not contain a link to the target URL. Check the source URL in a Microformats parser such as php.microformats.io';

        if(!$found && isset($document['html'])) {
          // If no link was found in the parsed mf2 tree, check for a link in the HTML
          $found = $this->_findLinkInHTML($opts['target'], $document['html']);
          // If there is a link, and if the HTML document has no mf2, then downgrade to a regular mention
          if($found) {
            $mf2Data = Formats\HTML::parse($this->http, $http_response, ['include-mf1'=>false]);
            if(isset($mf2Data['data']['type']) && $mf2Data['data']['type'] == 'unknown') {
              // Since the link was found in the HTML, but not in the parsed tree, it shouldn't return the parsed document
              $document['data'] = [
                'type' => 'unknown'
              ];
            } else {
              // Otherwise, the document did have mf2, but the link wasn't in it (checked earlier), so set found=false
              $found = false;
            }
          }
        }

      }

      if(!$found) {
        return [
          'error' => 'no_link_found',
          'error_description' => $error_description,
          'code' => isset($document['code']) ? $document['code'] : 200,
          'url' => $document['url'],
          'debug' => $document['data']
        ];
      }
    }

    // If the HTML parser couldn't parse the page it returns the full HTML for checking the target above,
    // but we don't want to return that in the output so remove it here
    unset($document['html']);

    return $document;
  }

  public function parse_document($http_response, $opts=[]) {
    if(isset($opts['timeout']))
      $this->http->set_timeout($opts['timeout']);
    if(isset($opts['max_redirects']))
      $this->http->set_max_redirects($opts['max_redirects']);

    // Check if the URL matches a special parser
    $url = $http_response['url'];

    if(Formats\GitHub::matches($url)) {
      return Formats\GitHub::parse($http_response);
    }

    if(Formats\Twitter::matches($url)) {
      return Formats\Twitter::parse($http_response);
    }

    if(Formats\XKCD::matches($url)) {
      return Formats\XKCD::parse($http_response);
    }

    if(Formats\Hackernews::matches($url)) {
      return Formats\Hackernews::parse($http_response);
    }

    $body = $http_response['body'];

    // Check if an mf2 JSON object was passed in
    if(is_array($body) && isset($body['items'])) {
      $data = Formats\Mf2::parse($http_response, $this->http, $opts);
      if($data == false) {
        $data = [
          'data' => [
            'type' => 'unknown',
          ]
        ];
      }
      $data['source-format'] = 'mf2+json';
      return $data;
    }

    // Check if an ActivityStreams JSON object was passed in
    if(Formats\ActivityStreams::is_as2_json($body)) {
      $data = Formats\ActivityStreams::parse($http_response, $this->http, $opts);
      $data['source-format'] = 'activity+json';
      return $data;
    }

    if(is_string($body) && substr($body, 0, 5) == '<?xml') {
      return Formats\XML::parse($http_response);
    }

    if(is_string($body)) {
      // Some feeds don't start with <?xml
      $begin = trim(substr($body, 0, 40));
      if(substr($begin, 0, 4) == '<rss') {
        return Formats\XML::parse($http_response);
      }
    }

    if(is_string($body) && substr($body, 0, 1) == '{') {
      $parsed = json_decode($body, true);
      if($parsed && isset($parsed['version']) && in_array($parsed['version'], ['https://jsonfeed.org/version/1','https://jsonfeed.org/version/1.1'])) {
        $http_response['body'] = $parsed;
        return Formats\JSONFeed::parse($http_response);
      } elseif($parsed && isset($parsed['items'][0]['type']) && isset($parsed['items'][0]['properties'])) {
        // Check if an mf2 JSON string was passed in
        $http_response['body'] = $parsed;
        $data = Formats\Mf2::parse($http_response, $this->http, $opts);
        $data['source-format'] = 'mf2+json';
        return $data;
      } elseif($parsed && Formats\ActivityStreams::is_as2_json($parsed)) {
        // Check if an ActivityStreams JSON string was passed in
        $http_response['body'] = $parsed;
        $data = Formats\ActivityStreams::parse($http_response, $this->http, $opts);
        $data['source-format'] = 'activity+json';
        return $data;
      }
    }

    // No special parsers matched, parse for Microformats now
    $data = Formats\HTML::parse($this->http, $http_response, $opts);
    if(!isset($data['source-format']) && isset($data['type']) && $data['type'] != 'unknown')
      $data['source-format'] = 'mf2+html';
    return $data;
  }

  private function _findLinkInTree($link, $document) {
    if(!$document)
      return false;

    if(is_string($document) || is_numeric($document)) {
      return $document == $link;
    }

    if(is_array($document)) {
      foreach($document as $key=>$value) {
        if($key === 'html') {
          $found = $this->_findLinkInHTML($link, $value);
          if($found) {
            return true;
          }
        } else {
          $found = $this->_findLinkInTree($link, $value);
          if($found) {
            return true;
          }
        }
      }
      return false;
    }

    throw new Exception('Unexpected value in tree');
  }

  private function _findLinkInHTML($link, $html) {
    $doc = new DOMDocument();
    @$doc->loadHTML(self::_toHtmlEntities($html));

    if(!$doc)
      return false;

    $xpath = new DOMXPath($doc);

    return self::_findLinksInDOMDocument($xpath, $link);
  }

  private static function _findLinksInDOMDocument(&$xpath, $target) {
    $found = [];
    self::_xPathFindNodeWithAttribute($xpath, 'a', 'href', function($u) use($target, &$found){
      if($u == $target) {
        $found[$u] = null;
      }
    });
    self::_xPathFindNodeWithAttribute($xpath, 'img', 'src', function($u) use($target, &$found){
      if($u == $target) {
        $found[$u] = null;
      }
    });
    self::_xPathFindNodeWithAttribute($xpath, 'video', 'src', function($u) use($target, &$found){
      if($u == $target) {
        $found[$u] = null;
      }
    });
    self::_xPathFindNodeWithAttribute($xpath, 'audio', 'src', function($u) use($target, &$found){
      if($u == $target) {
        $found[$u] = null;
      }
    });
    return $found;
  }

  private static function _xPathFindNodeWithAttribute($xpath, $node, $attr, $callback) {
    foreach($xpath->query('//'.$node.'[@'.$attr.']') as $el) {
      $v = $el->getAttribute($attr);
      $callback($v);
    }
  }

  private static function _toHtmlEntities($input) {
    return mb_convert_encoding($input, 'HTML-ENTITIES', mb_detect_encoding($input));
  }

}
