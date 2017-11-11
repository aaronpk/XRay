<?php
namespace p3k\XRay\Formats;

use HTMLPurifier, HTMLPurifier_Config;
use DOMDocument, DOMXPath;
use p3k\XRay\Formats;

class HTML extends Format {

  public static function matches_host($url) { return true; }
  public static function matches($url) { return true; }

  public static function parse($http, $html, $url, $opts=[]) {
    $result = [
      'data' => [
        'type' => 'unknown',
      ],
      'url' => $url,
    ];

    // attempt to parse the page as HTML
    $doc = new DOMDocument();
    @$doc->loadHTML(self::toHtmlEntities($html));

    if(!$doc) {
      return [
        'error' => 'invalid_content',
        'error_description' => 'The document could not be parsed as HTML'
      ];
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
    if(isset($opts['target'])) {
      $target = $opts['target'];

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
        return [
          'error' => 'no_link_found',
          'error_description' => 'The source document does not have a link to the target URL',
          'code' => isset($result['code']) ? $result['code'] : 200,
          'url' => $url
        ];
      }
    }

    // If the URL has a fragment ID, find the DOM starting at that node and parse it instead
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
    $mf2 = \mf2\Parse($html, $url);

    if($mf2 && count($mf2['items']) > 0) {
      $data = Formats\Mf2::parse($mf2, $url, $http, $opts);
      $result = array_merge($result, $data);
      if($data) {
        if($fragment) {
          $result['info'] = [
            'found_fragment' => $foundFragment
          ];
        }
        $result['original'] = $html;
        $result['url'] = $url; // this will be the effective URL after following redirects
      }
    }
    return $result;
  }

  private static function toHtmlEntities($input) {
    return mb_convert_encoding($input, 'HTML-ENTITIES', mb_detect_encoding($input));
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

}
