<?php
namespace XRay\Formats;

class Mf2 {

  public static function parse($mf2, $url, $http) {

    if($item = $mf2['items'][0]) {
      // If the first item is a feed, the page is a feed
      if(in_array('h-feed', $item['type'])) {
        return self::parseHFeed($mf2, $http);
      }

      // Check each top-level h-card, and if there is one that matches this URL, the page is an h-card
      foreach($mf2['items'] as $i) {
        if(in_array('h-card', $i['type'])
          and array_key_exists('url', $i['properties'])
          and in_array($url, $i['properties']['url'])
        ) {
          // TODO: check for children h-entrys (like tantek.com), or sibling h-entries (like aaronparecki.com)
          // and return the result as a feed instead
          return self::parseHCard($i, $http);
        }
      }

      // Otherwise check for an h-entry
      if(in_array('h-entry', $item['type'])) {
        return self::parseHEntry($mf2, $http);
      }
    }

    return false;
  }

  private static function parseHEntry($mf2, \p3k\HTTP $http) {
    $data = [
      'type' => 'entry',
      'author' => [
        'type' => 'card',
        'name' => null,
        'url' => null,
        'photo' => null
      ]
    ];

    $item = $mf2['items'][0];

    // Single plaintext values
    $properties = ['url','published','summary','rsvp'];
    foreach($properties as $p) {
      if($v = self::getPlaintext($item, $p))
        $data[$p] = $v;
    }

    // Always arrays
    $properties = ['photo','video','syndication','in-reply-to','like-of','repost-of'];
    foreach($properties as $p) {
      if(array_key_exists($p, $item['properties']))
        $data[$p] = $item['properties'][$p];
    }

    // Determine if the name is distinct from the content
    $name = self::getPlaintext($item, 'name');
    $content = null;
    $textContent = null;
    $htmlContent = null;
    if(array_key_exists('content', $item['properties'])) {
      $content = $item['properties']['content'][0];
      if(is_string($content)) {
        $textContent = $content;
      } elseif(!is_string($content) && is_array($content) && array_key_exists('value', $content)) {
        if(array_key_exists('html', $content)) {
          $textContent = strip_tags($content['html']);
          $htmlContent = $content['html'];
        } else {
          $textContent = $content['value'];
        }
      }

      // Trim ellipses from the name
      $name = preg_replace('/ ?(\.\.\.|…)$/', '', $name);

      // Check if the name is a prefix of the content
      if(strpos($textContent, $name) === 0) {
        $name = null;
      }
    }

    if($name) {
      $data['name'] = $name;
    }
    if($content) {
      $data['content'] = [
        'text' => $textContent
      ];
      if($textContent != $htmlContent) {
        $data['content']['html'] = $htmlContent;
      }
    }

    $data['author'] = self::findAuthor($mf2, $item, $http);

    return $data;    
  }

  private static function parseHFeed($mf2, \p3k\HTTP $http) {
    $data = [
      'type' => 'feed',
      'author' => [
        'type' => 'card',
        'name' => null,
        'url' => null,
        'photo' => null
      ],
      'items' => []
    ];

    return $data;
  }

  private static function parseHCard($item, \p3k\HTTP $http) {
    $data = [
      'type' => 'card',
      'name' => null,
      'url' => null,
      'photo' => null
    ];

    $properties = ['url','name','photo'];
    foreach($properties as $p) {
      if($v = self::getPlaintext($item, $p))
        $data[$p] = $v;
    }

    return $data;
  }

  private static function findAuthor($mf2, $item, \p3k\HTTP $http) {
    $author = [
      'type' => 'card',
      'name' => null,
      'url' => null,
      'photo' => null
    ];

    // Author Discovery
    // http://indiewebcamp.com/authorship

    $authorPage = false;
    if(array_key_exists('author', $item['properties'])) {

      // Check if any of the values of the author property are an h-card
      foreach($item['properties']['author'] as $a) {
        if(self::isHCard($a)) {
          // 5.1 "if it has an h-card, use it, exit."
          return self::parseHCard($a, $http);
        } elseif(is_string($a)) {
          if(self::isURL($a)) {
            // 5.2 "otherwise if author property is an http(s) URL, let the author-page have that URL"
            $authorPage = $a;
          } else {
            // 5.3 "otherwise use the author property as the author name, exit"
            // We can only set the name, no h-card or URL was found
            $author['name'] = self::getPlaintext($item, 'author');
            return $author;
          }
        } else {
          // This case is only hit when the author property is an mf2 object that is not an h-card
          $author['name'] = self::getPlaintext($item, 'author');
          return $author;
        }
      }

    }

    // 6. "if no author page was found" ... check for rel-author link
    if(!$authorPage) {
      if(isset($mf2['rels']) && isset($mf2['rels']['author']))
        $authorPage = $mf2['rels']['author'][0];
    }

    // 7. "if there is an author-page URL" ...
    if($authorPage) {

      // 7.1 "get the author-page from that URL and parse it for microformats2"
      $authorPageContents = self::getURL($authorPage, $http);

      if($authorPageContents) {
        foreach($authorPageContents['items'] as $i) {
          if(self::isHCard($i)) {

            // 7.2 "if author-page has 1+ h-card with url == uid == author-page's URL, then use first such h-card, exit."
            if(array_key_exists('url', $i['properties'])
              and in_array($authorPage, $i['properties']['url'])
              and array_key_exists('uid', $i['properties'])
              and in_array($authorPage, $i['properties']['uid'])
            ) { 
              return self::parseHCard($i, $http);
            }

            // 7.3 "else if author-page has 1+ h-card with url property which matches the href of a rel-me link on the author-page"
            $relMeLinks = (isset($authorPageContents['rels']) && isset($authorPageContents['rels']['me'])) ? $authorPageContents['rels']['me'] : [];
            if(count($relMeLinks) > 0
              and array_key_exists('url', $i['properties'])
              and count(array_intersect($i['properties']['url'], $relMeLinks)) > 0
            ) {
              return self::parseHCard($i, $http);
            }

          }
        }
      }

      // 7.4 "if the h-entry's page has 1+ h-card with url == author-page URL, use first such h-card, exit."
      foreach($mf2['items'] as $i) {
        if(self::isHCard($i)) {

          if(array_key_exists('url', $i['properties'])
            and in_array($authorPage, $i['properties']['url'])
          ) {
            return self::parseHCard($i, $http);
          }

        }
      }

    }

    return $author;
  }

  private static function responseDisplayText($name, $summary, $content) {

    // Build a fake h-entry to pass to the comments parser
    $input = [
      'type' => ['h-entry'],
      'properties' => [
        'name' => [trim($name)],
        'summary' => [trim($summary)],
        'content' => [trim($content)]
      ]
    ];

    if(!trim($name))
      unset($input['properties']['name']);

    if(!trim($summary))
      unset($input['properties']['summary']);

    $result = \IndieWeb\comments\parse($input, false, 1024, 4);

    return [
      'name' => trim($result['name']),
      'content' => $result['text']
    ];
  }  

  private static function hasNumericKeys(array $arr) {
    foreach($arr as $key=>$val) 
      if (is_numeric($key)) 
        return true;
    return false;
  }

  private static function isMicroformat($mf) {
    return is_array($mf) 
      and !self::hasNumericKeys($mf) 
      and !empty($mf['type']) 
      and isset($mf['properties']);
  }

  private static function isHCard($mf) {
    return is_array($mf)
      and !empty($mf['type'])
      and is_array($mf['type'])
      and in_array('h-card', $mf['type']);
  }

  private static function isURL($string) {
    return preg_match('/^https?:\/\/.+\..+$/', $string);
  }

  // Given an array of microformats properties and a key name, return the plaintext value
  // at that property
  // e.g.
  // {"properties":{"published":["foo"]}} results in "foo"
  private static function getPlaintext($mf2, $k, $fallback=null) {
    if(!empty($mf2['properties'][$k]) and is_array($mf2['properties'][$k])) {
      // $mf2['properties'][$v] will always be an array since the input was from the mf2 parser
      $value = $mf2['properties'][$k][0];
      if(is_string($value)) {
        return $value;
      } elseif(self::isMicroformat($value) && array_key_exists('value', $value)) {
        return $value['value'];
      }
    }
    return $fallback;
  }

  private static function getURL($url, \p3k\HTTP $http) {
    if(!$url) return null;
    // TODO: consider adding caching here
    $result = $http->get($url);
    if($result['error'] || !$result['body']) {
      return null;
    }
    return \mf2\Parse($result['body'], $url);
  }

}
