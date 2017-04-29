<?php
namespace p3k\XRay\Formats;

use DOMDocument, DOMXPath;

interface iFormat {

  public static function matches_host($url);
  public static function matches($url);

}

abstract class Format implements iFormat {

  protected static function _unknown() {
    return [
      'data' => [
        'type' => 'unknown'
      ]
    ];
  }

  protected static function _loadHTML($html) {
    $doc = new DOMDocument();
    @$doc->loadHTML($html);

    if(!$doc) {
      return [null, null];
    }

    $xpath = new DOMXPath($doc);

    return [$doc, $xpath];
  }

}
