<?php
namespace p3k\XRay\Formats;

use DateTime, DateTimeZone;
use Config;

class XKCD extends Format {

  public static function matches_host($url) {
    $host = parse_url($url, PHP_URL_HOST);
    return $host == 'xkcd.com';
  }

  public static function matches($url) {
    return self::matches_host($url) && preg_match('/^\/\d+\/$/', ''.parse_url($url, PHP_URL_PATH));
  }

  public static function parse($http_response) {
    $html = $http_response['body'];
    $url = $http_response['url'];

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

    $entry['post-type'] = \p3k\XRay\PostType::discover($entry);

    $response = [
      'data' => $entry,
      'source-format' => 'xkcd',
    ];

    return $response;
  }

}
