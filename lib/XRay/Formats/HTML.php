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
        $found = self::findLinksInDocument($xpath, $target);
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

    $mf2 = \mf2\Parse($html, $url);

    // Check for a rel=alternate link to a Microformats JSON representation, and use that instead
    if(isset($mf2['rel-urls'])) {
      $alternates = [
        'mf2' => [],
        'as2' => [],
      ];

      foreach($mf2['rel-urls'] as $relurl => $reltype) {

        if(isset($reltype['type'])) {
          if(in_array('alternate', $reltype['rels']) && $reltype['type'] == 'application/mf2+json') {
            $alternates['mf2'][] = $relurl;
          }

          if(in_array('alternate', $reltype['rels']) && $reltype['type'] == 'application/activity+json') {
            $alternates['as2'][] = $relurl;
          }
        }

      }

      if(count($alternates['mf2'])) {
        // Fetch and parse the MF2 JSON link
        $relurl = $alternates['mf2'][0];
        $jsonpage = $http->get($relurl, [
          'Accept' => 'application/mf2+json,application/json'
        ]);
        // Skip and fall back to parsing the HTML if anything about this request fails
        if(!$jsonpage['error'] && $jsonpage['body']) {
          $jsondata = json_decode($jsonpage['body'],true);
          if($jsondata) {
            $data = Formats\Mf2::parse($jsondata, $url, $http, $opts);
            if($data && is_array($data) && isset($data['data']['type'])) {
              $data['url'] = $relurl;
              $data['source-format'] = 'mf2+json';
              return $data;
            }
          }
        }
      }

      if(count($alternates['as2'])) {
        $relurl = $alternates['as2'][0];
        // Fetch and parse the ActivityStreams JSON link
        $jsonpage = $http->get($relurl, [
          'Accept' => 'application/activity+json,application/json'
        ]);
        // Skip and fall back to parsing the HTML if anything about this request fails
        if(!$jsonpage['error'] && $jsonpage['body']) {
          $jsondata = json_decode($jsonpage['body'],true);
          if($jsondata) {
            $data = Formats\ActivityStreams::parse($jsondata, $url, $http, $opts);
            if($data && is_array($data) && isset($data['data']['type'])) {
              $data['url'] = $relurl;
              $data['source-format'] = 'activity+json';
              return $data;
            }
          }
        }
      }

    }

    // Now start pulling in the data from the page. Start by looking for microformats2
    if($mf2 && count($mf2['items']) > 0) {
      $data = Formats\Mf2::parse($mf2, $url, $http, $opts);
      if($data) {
        $result = array_merge($result, $data);
        if($fragment) {
          $result['info'] = [
            'found_fragment' => $foundFragment
          ];
        }
        $result['original'] = $html;
        $result['url'] = $url; // this will be the effective URL after following redirects
        $result['source-format'] = 'mf2+html';
      }
    }
    return $result;
  }

  private static function toHtmlEntities($input) {
    return mb_convert_encoding($input, 'HTML-ENTITIES', mb_detect_encoding($input));
  }

  private static function xPathGetElementById($xpath, $id) {
    $element = null;
    foreach($xpath->query("//*[@id='$id']") as $el) {
      $element = $el;
    }
    return $element;
  }

}
