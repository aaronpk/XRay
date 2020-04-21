<?php
namespace p3k\XRay\Formats;

trait Mf2Feed {

  private static function parseAsHFeed($mf2, $http, $url) {
    $data = [
      'type' => 'feed',
      'items' => [],
    ];

    // Given an mf2 data structure from a web page, assume it is a feed of entries
    // and return the XRay data structure for the feed.
    // Look for the first (BFS) h-feed if present, otherwise use the list of items.
    // Normalize this into a simpler mf2 structure, (h-feed -> h-* children)
    $feed = self::_findFirstOfType($mf2, 'h-feed');
    if(!$feed) {
      // There was no h-feed.
      // Check for a top-level h-card with children
      if(isset($mf2['items'][0]) && in_array('h-card', $mf2['items'][0]['type'])) {
        $feed = $mf2['items'][0];
        // If the h-card has children, use them, otherwise look for siblings
        if(!isset($feed['children'])) {
          $items = self::_findAllObjectsExcept($mf2, ['h-card']);
          $feed['children'] = $items;
        }
      } else {
        $children = self::_findAllObjectsExcept($mf2, ['h-card','h-feed']);
        $feed = [
          'type' => ['h-feed'],
          'properties' => [],
          'children' => $children
        ];
      }
    }
    if(!isset($feed['children']))
      $feed['children'] = [];

    // Now that the feed has been normalized so all the items are under "children", we
    // can transform each entry into the XRay format, including finding the author, etc
    foreach($feed['children'] as $item) {
      $parsed = false;
      if(in_array('h-entry', $item['type']) || in_array('h-cite', $item['type'])) {
        $parsed = self::parseAsHEntry($mf2, $item, $http, $url);
      }
      elseif(in_array('h-event', $item['type'])) {
        $parsed = self::parseAsHEvent($mf2, $item, $http, $url);
      }
      elseif(in_array('h-review', $item['type'])) {
        $parsed = self::parseAsHReview($mf2, $item, $http, $url);
      }
      elseif(in_array('h-recipe', $item['type'])) {
        $parsed = self::parseAsHRecipe($mf2, $item, $http, $url);
      }
      elseif(in_array('h-product', $item['type'])) {
        $parsed = self::parseAsHProduct($mf2, $item, $http, $url);
      }
      elseif(in_array('h-item', $item['type'])) {
        $parsed = self::parseAsHItem($mf2, $item, $http, $url);
      }
      elseif(in_array('h-card', $item['type'])) {
        $parsed = self::parseAsHCard($item, $http, $url);
      }
      if($parsed) {
        $data['items'][] = $parsed['data'];
      }
    }

    return [
      'data' => $data,
      'source-format' => 'mf2+html',
    ];
  }

  private static function _findFirstOfType($mf2, $type) {
    foreach($mf2['items'] as $item) {
      if(in_array($type, $item['type'])) {
        return $item;
      } else {
        if(isset($item['children'])) {
          $items = $item['children'];
          return self::_findFirstOfType(['items'=>$items], $type);
        }
      }
    }
  }

  private static function _findAllObjectsExcept($mf2, $types) {
    $items = [];
    foreach($mf2['items'] as $item) {
      if(count(array_intersect($item['type'], $types)) == 0) {
        $items[] = $item;
      }
    }
    return $items;
  }

}
