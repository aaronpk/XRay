<?php
namespace p3k\XRay\Formats;

use HTMLPurifier, HTMLPurifier_Config;
use DOMDocument, DOMXPath;
use p3k\XRay\Formats;

class JSONFeed extends Format {

  public static function matches_host($url) { return true; }
  public static function matches($url) { return true; }

  public static function parse($json, $url) {
    $result = [
      'data' => [
        'type' => 'unknown',
      ],
      'url' => $url,
    ];

    $feed = json_decode($json, true);

    if($feed) {
      $result['data']['type'] = 'feed';

      foreach($feed['items'] as $item) {
        $result['data']['items'][] = self::_hEntryFromFeedItem($item, $feed);
      }
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

    if(isset($item['author']['name'])) {
      $entry['author']['name'] = $item['author']['name'];
    }
    if(isset($item['author']['url'])) {
      $entry['author']['url'] = $item['author']['url'];
    } elseif(isset($feed['home_page_url'])) {
      $entry['author']['url'] = $feed['home_page_url'];
    }
    if(isset($item['author']['avatar'])) {
      $entry['author']['photo'] = $item['author']['avatar'];
    }

    if(isset($item['url'])) {
      $entry['url'] = $item['url'];
    }

    if(isset($item['id'])) {
      $entry['uid'] = $item['id'];
    }

    if(isset($item['title']) && trim($item['title'])) {
      $entry['name'] = trim($item['title']);
    }

    if(isset($item['content_html']) && isset($item['content_text'])) {
      $entry['content'] = [
        'html' => self::sanitizeHTML($item['content_html']),
        'text' => trim($item['content_text'])
      ];
    } elseif(isset($item['content_html'])) {
      $entry['content'] = [
        'html' => self::sanitizeHTML($item['content_html']),
        'text' => self::stripHTML($item['content_html'])
      ];
    } elseif(isset($item['content_text'])) {
      $entry['content'] = [
        'text' => trim($item['content_text'])
      ];
    }

    if(isset($item['summary'])) {
      $entry['summary'] = $item['summary'];
    }

    if(isset($item['date_published'])) {
      $entry['published'] = $item['date_published'];
    }

    if(isset($item['date_modified'])) {
      $entry['updated'] = $item['date_modified'];
    }

    if(isset($item['image'])) {
      $entry['photo'] = $item['image'];
    }

    if(isset($item['tags'])) {
      $entry['category'] = $item['tags'];
    }

    return $entry;
  }
}
