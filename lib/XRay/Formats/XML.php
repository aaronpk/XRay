<?php
namespace p3k\XRay\Formats;

use HTMLPurifier, HTMLPurifier_Config;
use DOMDocument, DOMXPath;
use p3k\XRay\Formats;
use PicoFeed\Reader\Reader;
use PicoFeed\PicoFeedException;

class XML extends Format {

  public static function matches_host($url) { return true; }
  public static function matches($url) { return true; }

  public static function parse($http_response) {
    $xml = $http_response['body'];
    $url = $http_response['url'];

    $result = [
      'data' => [
        'type' => 'unknown',
      ],
      'url' => $url,
      'source-format' => 'xml',
      'code' => $http_response['code'],
    ];

    try {
      $reader = new Reader();
      $parser = $reader->getParser($url, $xml, '');
      $feed = $parser->execute();

      $result['data']['type'] = 'feed';
      $result['data']['items'] = [];

      foreach($feed->getItems() as $item) {
        $result['data']['items'][] = self::_hEntryFromFeedItem($item, $feed);
      }

    } catch(PicoFeedException $e) {

    }

    return $result;
  }

  private static function _hEntryFromFeedItem($item, $feed) {
    $entry = [
      'type' => 'entry',
      'author' => [
        'name' => null,
        'url' => null,
        'photo' => null
      ]
    ];

    if(is_array($guid=$item->getTag('guid')) && count($guid))
      $entry['uid'] = $guid[0];
    elseif(is_array($guid=$item->getTag('id')) && count($guid))
      $entry['uid'] = $guid[0];

    if($item->getUrl())
      $entry['url'] = $item->getUrl();

    if($item->getPublishedDate())
      $entry['published'] = $item->getPublishedDate()->format('c');

    if($item->getContent())
      $entry['content'] = [
        'html' => self::sanitizeHTML($item->getContent()),
        'text' => self::stripHTML($item->getContent())
      ];

    if($item->getTitle() && $item->getTitle() != $item->getUrl()) {
      $title = $item->getTitle();
      $entry['name'] = $title;

      // Check if the title is a prefix of the content and drop if so
      if(isset($entry['content'])) {
        if(substr($title, -3) == '...' || substr($title, -1) == '…') {
          if(substr($title, -3) == '...') {
            $trimmedTitle = substr($title, 0, -3);
          } else {
            $trimmedTitle = substr($title, 0, -1);
          }
          if(substr($entry['content']['text'], 0, strlen($trimmedTitle)) == $trimmedTitle) {
            unset($entry['name']);
          }
        }
      }
    }

    if($item->getAuthor()) {
      $entry['author']['name'] = $item->getAuthor();
    }

    if($item->getAuthorUrl()) {
      $entry['author']['url'] = $item->getAuthorUrl();
    } else if($feed->siteUrl) {
      $entry['author']['url'] = $feed->siteUrl;
    }

    if($item->getEnclosureType()) {
      $prop = false;
      switch($item->getEnclosureType()) {
        case 'audio/mpeg':
          $prop = 'audio'; break;
        case 'image/jpeg':
        case 'image/png':
        case 'image/gif':
          $prop = 'photo'; break;
      }
      if($prop)
        $entry[$prop] = [$item->getEnclosureUrl()];
    }

    $entry['post-type'] = \p3k\XRay\PostType::discover($entry);

    return $entry;
  }

}
