<?php
namespace XRay\Formats;

use HTMLPurifier, HTMLPurifier_Config;
use Parse;

class Mf2 {

  public static function parse($mf2, $url, $http) {
    if(count($mf2['items']) == 0)
      return false;

    // Check if the list of items is a bunch of h-entrys and return as a feed
    // Unless this page's URL matches one of the entries, then treat it as a permalink
    $hentrys = 0;
    $lastSeenEntry = false;
    foreach($mf2['items'] as $item) {
      if(in_array('h-entry', $item['type']) || in_array('h-cite', $item['type'])) {
        if(array_key_exists('url', $item['properties'])) {
          $urls = $item['properties']['url'];
          $urls = array_map('\normalize_url', $urls);
          if(in_array($url, $urls)) {
            Parse::debug("mf2.1: Recognized $url as an h-entry because an h-entry on the page matched the URL of the request");
            return self::parseAsHEntry($mf2, $item, $http, $url);
          }
          $lastSeenEntry = $item;
        }
        $hentrys++;
      }
    }

    // If there was more than one h-entry on the page, treat the whole page as a feed
    if($hentrys > 1) {
      Parse::debug("mf2.2: Recognized $url as an h-feed because there are more than one h-entry on the page");
      return self::parseAsHFeed($mf2, $http);
    }

    // If the first item is an h-feed, parse as a feed
    $first = $mf2['items'][0];
    if(in_array('h-feed', $first['type'])) {
      Parse::debug("mf2.3: Recognized $url as an h-feed because the first item is an h-feed");
      return self::parseAsHFeed($mf2, $http);
    }

    // Check each top-level h-card, and if there is one that matches this URL, the page is an h-card
    foreach($mf2['items'] as $item) {
      if(in_array('h-card', $item['type'])
        and array_key_exists('url', $item['properties'])
      ) {
        $urls = $item['properties']['url'];
        $urls = array_map('\normalize_url', $urls);
        if(in_array($url, $urls)) {
          // TODO: check for children h-entrys (like tantek.com), or sibling h-entries (like aaronparecki.com)
          // and return the result as a feed instead
          Parse::debug("mf2.4: Recognized $url as an h-card because an h-card on the page matched the URL of the request");
          return self::parseAsHCard($item, $http, $url);
        }
      }
    }

    // If there was only one h-entry, but the URL for it is not the same as this page, then treat as a feed
    if($hentrys == 1) {
      if($lastSeenEntry) {
        $urls = $lastSeenEntry['properties']['url'];
        $urls = array_map('\normalize_url', $urls);
        if(count($urls) && !in_array($url, $urls)) {
          Parse::debug("mf2.5: Recognized $url as an h-feed no h-entrys on the page matched the URL of the request");
          return self::parseAsHFeed($mf2, $http);
        }
      }
    }

    // Fallback case, but hopefully we have found something before this point
    foreach($mf2['items'] as $item) {
      // Otherwise check for an h-entry
      if(in_array('h-entry', $item['type']) || in_array('h-cite', $item['type'])) {
        Parse::debug("mf2.6: $url is falling back to the first h-entry on the page");
        return self::parseAsHEntry($mf2, $item, $http);
      }
    }

    Parse::debug("mf2.E: No object at $url was recognized");

    return false;
  }

  private static function parseAsHEntry($mf2, $item, $http) {
    $data = [
      'type' => 'entry'
    ];
    $refs = [];

    // Single plaintext values
    $properties = ['url','published','summary','rsvp'];
    foreach($properties as $p) {
      if($v = self::getPlaintext($item, $p))
        $data[$p] = $v;
    }

    // Always arrays
    $properties = ['photo','video','syndication'];
    foreach($properties as $p) {
      if(array_key_exists($p, $item['properties'])) {
        $data[$p] = [];
        foreach($item['properties'][$p] as $v) {
          if(is_string($v))
            $data[$p][] = $v;
          elseif(is_array($v) and array_key_exists('value', $v))
            $data[$p][] = $v['value'];
        }
      }
    }

    // Always returned as arrays, and may also create external references
    $properties = ['in-reply-to','like-of','repost-of','bookmark-of','category','invitee'];
    foreach($properties as $p) {
      if(array_key_exists($p, $item['properties'])) {
        $data[$p] = [];
        foreach($item['properties'][$p] as $v) {
          if(is_string($v))
            $data[$p][] = $v;
          elseif(self::isMicroformat($v) && ($u=self::getPlaintext($v, 'url'))) {
            $data[$p][] = $u;
            // parse the object and put the result in the "refs" object
            $ref = self::parse(['items'=>[$v]], $u, $http);
            if($ref) {
              $refs[$u] = $ref['data'];
            }
          }
        }
      }      
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
          $htmlContent = trim(self::sanitizeHTML($content['html']));
          $textContent = trim(str_replace("&#xD;","\r",strip_tags($htmlContent)));
          $textContent = trim(str_replace("&#xD;","\r",$content['value']));
        } else {
          $textContent = trim($content['value']);
        }
      }

      // Trim ellipses from the name
      $name = preg_replace('/ ?(\.\.\.|…)$/', '', $name);

      // Remove all whitespace when checking equality
      $nameCompare = preg_replace('/\s/','',trim($name));
      $contentCompare = preg_replace('/\s/','',trim($textContent));

      // Check if the name is a prefix of the content
      if($contentCompare && $nameCompare && strpos($contentCompare, $nameCompare) === 0) {
        $name = null;
      }
    }

    if($name) {
      $data['name'] = $name;
    }

    // If there is content, always return the plaintext content, and return HTML content if it's different
    if($content) {
      $data['content'] = [
        'text' => $textContent
      ];
      if($htmlContent && $textContent != $htmlContent) {
        $data['content']['html'] = $htmlContent;
      }
    }

    if($author = self::findAuthor($mf2, $item, $http))
      $data['author'] = $author;

    $response = [
      'data' => $data
    ];

    if(count($refs)) {
      $response['refs'] = $refs;
    }

    return $response;
  }

  private static function parseAsHFeed($mf2, $http) {
    $data = [
      'type' => 'feed',
      'author' => [
        'type' => 'card',
        'name' => null,
        'url' => null,
        'photo' => null
      ],
      'todo' => 'Not yet implemented. Please see https://github.com/aaronpk/XRay/issues/1'
    ];

    return [
      'data' => $data,
      'entries' => []
    ];
  }

  private static function parseAsHCard($item, $http, $authorURL=false) {
    $data = [
      'type' => 'card',
      'name' => null,
      'url' => null,
      'photo' => null
    ];

    $properties = ['url','name','photo'];
    foreach($properties as $p) {
      if($p == 'url' && $authorURL) {
        // If there is a matching author URL, use that one
        $found = false;
        foreach($item['properties']['url'] as $url) {
          $url = \normalize_url($url);
          if($url == $authorURL) {
            $data['url'] = $url;
            $found = true;
          }
        }
        if(!$found) $data['url'] = $item['properties']['url'][0];
      } else if($v = self::getPlaintext($item, $p)) {
        $data[$p] = $v;
      }
    }

    $response = [
      'data' => $data
    ];

    return $response;
  }

  private static function findAuthor($mf2, $item, $http) {
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
          return self::parseAsHCard($a, $http)['data'];
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
              return self::parseAsHCard($i, $http, $authorPage)['data'];
            }

            // 7.3 "else if author-page has 1+ h-card with url property which matches the href of a rel-me link on the author-page"
            $relMeLinks = (isset($authorPageContents['rels']) && isset($authorPageContents['rels']['me'])) ? $authorPageContents['rels']['me'] : [];
            if(count($relMeLinks) > 0
              and array_key_exists('url', $i['properties'])
              and count(array_intersect($i['properties']['url'], $relMeLinks)) > 0
            ) {
              return self::parseAsHCard($i, $http, $authorPage)['data'];
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
            return self::parseAsHCard($i, $http)['data'];
          }

        }
      }

    }

    if(!$author['name'] && !$author['photo'] && !$author['url'])
      return null;

    return $author;
  }

  private static function sanitizeHTML($html) {
    $config = HTMLPurifier_Config::createDefault();
    $config->set('Cache.DefinitionImpl', null);
    $config->set('HTML.AllowedElements', [
      'a',
      'abbr',
      'b',
      'code',
      'del',
      'em',
      'i',
      'img',
      'q',
      'strike',
      'strong',
      'time',
      'blockquote',
      'pre',
      'p',
      'h1',
      'h2',
      'h3',
      'h4',
      'h5',
      'h6',
      'ul',
      'li',
      'ol'
    ]);
    $def = $config->getHTMLDefinition(true);
    $def->addElement(
      'time',
      'Inline',
      'Inline',
      'Common',
      [
        'datetime' => 'Text'
      ]
    );
    // Override the allowed classes to only support Microformats2 classes
    $def->manager->attrTypes->set('Class', new \HTMLPurifier_AttrDef_HTML_Microformats2());
    $purifier = new HTMLPurifier($config);
    return $purifier->purify($html);
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

  private static function getURL($url, $http) {
    if(!$url) return null;
    // TODO: consider adding caching here
    $result = $http->get($url);
    if($result['error'] || !$result['body']) {
      return null;
    }
    return \mf2\Parse($result['body'], $url);
  }

}
