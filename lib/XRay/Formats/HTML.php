<?php
namespace p3k\XRay\Formats;

use HTMLPurifier, HTMLPurifier_Config;
use DOMDocument, DOMXPath;
use p3k\XRay\Formats;

class HTML extends Format {

  public static function matches_host($url) { return true; }
  public static function matches($url) { return true; }

  public static function parse($http, $http_response, $opts=[]) {
    $html = $http_response['body'];
    $url = $http_response['url'];

    $result = [
      'data' => [
        'type' => 'unknown',
      ],
      'url' => $url,
      'code' => $http_response['code'],
      'html' => $html,
    ];

    // attempt to parse the page as HTML
    $doc = new DOMDocument();
    if (empty($html)) {
        $html=' '; // ugly hack to make DOMDocument happy
    };
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

    $includeMF1 = true;
    if(isset($opts['include-mf1']) && $opts['include-mf1'] == false)
      $includeMF1 = false;

    $mf2 = \Mf2\parse($html, $url, $includeMF1);

    $canonical = false;

    if(isset($mf2['rels']['canonical'][0]))
      $canonical = $mf2['rels']['canonical'][0];

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
          $jsondata = json_decode($jsonpage['body'], true);
          if($jsondata) {
            $jsonpage['body'] = $jsondata;
            $data = Formats\Mf2::parse($jsonpage, $http, $opts);
            if($data && is_array($data) && isset($data['data']['type'])) {
              $data['url'] = $relurl;
              $data['source-format'] = 'mf2+json';
              return $data;
            }
          }
        }
      }

      $ignoreAS2 = false;
      if(isset($opts['ignore-as2']) && $opts['ignore-as2'] == true)
        $ignoreAS2 = true;

      if(!$ignoreAS2 && count($alternates['as2'])) {
        $relurl = $alternates['as2'][0];
        // Fetch and parse the ActivityStreams JSON link
        $jsonpage = $http->get($relurl, [
          'Accept' => 'application/activity+json,application/json'
        ]);

        // Skip and fall back to parsing the HTML if anything about this request fails
        if(!$jsonpage['error'] && $jsonpage['body']) {
          $jsondata = json_decode($jsonpage['body'],true);
          if($jsondata) {
            $jsonpage['body'] = $jsondata;
            $data = Formats\ActivityStreams::parse($jsonpage, $http, $opts);
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
      $http_response['body'] = $mf2;
      $data = Formats\Mf2::parse($http_response, $http, $opts);
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

    if($canonical) {
      $result['data']['rels'] = [
        'canonical' => $canonical,
      ];
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
