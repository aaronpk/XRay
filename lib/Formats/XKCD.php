<?php
namespace XRay\Formats;

use DOMDocument, DOMXPath;
use DateTime, DateTimeZone;
use Parse, Config;

class XKCD {

  public static function parse($html, $url) {
    list($doc, $xpath) = self::_loadHTML($html);

    if(!$doc)
      return self::_unknown();

    $entry = [
      'type' => 'entry',
      'url' => $url,
      'author' => [
        'type' => 'card',
        'name' => 'XKCD',
        'photo' => Config::$base.'/images/xkcd.png',
        'url' => 'https://xkcd.com/'
      ]
    ];

    $name = $doc->getElementById('ctitle');

    if(!$name)
      return self::_unknown();
    
    $entry['name'] = $name->nodeValue;

    $photo = $xpath->query("//div[@id='comic']/img");
    if($photo->length != 1)
      return self::_unknown();

    $photo = $photo->item(0);
    $img1 = $photo->getAttribute('src');
    $img2 = $photo->getAttribute('srcset');
    if($img2) {
      $img2 = explode(',', $img2)[0];
      if(preg_match('/([^ ]+)/', $img2, $match)) {
        $img2 = $match[1];
      }
    }

    $src = \Mf2\resolveUrl($url, $img2 ?: $img1);

    $entry['photo'] = [$src];

    $response = [
      'data' => $entry
    ];

    return $response;
  }

  private static function _unknown() {
    return [
      'data' => [
        'type' => 'unknown'
      ]
    ];
  }

  private static function _loadHTML($html) {
    $doc = new DOMDocument();
    @$doc->loadHTML($html);

    if(!$doc) {
      return [null, null];
    }

    $xpath = new DOMXPath($doc);

    return [$doc, $xpath];
  }

}
