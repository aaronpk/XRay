<?php
namespace p3k\XRay\Formats;

class Mf2 extends Format {

  use Mf2Feed;

  public static function matches_host($url) {
    return true;
  }

  public static function matches($url) {
    return true;
  }

  public static function parse($http_response, $http, $opts=[]) {
    $mf2 = $http_response['body'];
    $url = $http_response['url'];

    if(!isset($mf2['items']) || count($mf2['items']) == 0)
      return false;

    // If they are expecting a feed, always return a feed or an error
    if(isset($opts['expect']) && $opts['expect'] == 'feed') {
      return self::parseAsHFeed($mf2, $http, $url);
    }

    // Remove h-breadcrumb since we never use it and it causes problems determining
    // whether a page is a feed or permalink
    $mf2['items'] = array_values(array_filter($mf2['items'], function($item){
      return !in_array('h-breadcrumb', $item['type']);
    }));

    $items = $mf2['items'];

    // If there is more than one item on the page, it may be a feed.
    // Remove an h-card if there is one that doesn't match the page URL, then try again.
    // (Don't modify the actual tree, but compare on the modified tree)
    if(count($items) > 1) {
      $tmpmf2 = array_filter($items, function($item) use($url){
        return !(in_array('h-card', $item['type']) && isset($item['properties']['url'][0]) && $item['properties']['url'][0] != $url);
      });
      $items = array_values($tmpmf2);
    }

    // If there is only one item left on the page, it's a permalink, and just use that
    if(count($items) == 1) {
      $item = $items[0];
      if(in_array('h-entry', $item['type']) || in_array('h-cite', $item['type'])) {
        #Parse::debug("mf2:0: Recognized $url as an h-entry it is the only item on the page");
        return self::parseAsHEntry($mf2, $item, $http, $url);
      }
      if(in_array('h-event', $item['type'])) {
        #Parse::debug("mf2:0: Recognized $url as an h-event it is the only item on the page");
        return self::parseAsHEvent($mf2, $item, $http, $url);
      }
      if(in_array('h-review', $item['type'])) {
        #Parse::debug("mf2:0: Recognized $url as an h-review it is the only item on the page");
        return self::parseAsHReview($mf2, $item, $http, $url);
      }
      if(in_array('h-recipe', $item['type'])) {
        #Parse::debug("mf2:0: Recognized $url as an h-recipe it is the only item on the page");
        return self::parseAsHRecipe($mf2, $item, $http, $url);
      }
      if(in_array('h-product', $item['type'])) {
        #Parse::debug("mf2:0: Recognized $url as an h-product it is the only item on the page");
        return self::parseAsHProduct($mf2, $item, $http, $url);
      }
      if(in_array('h-item', $item['type'])) {
        #Parse::debug("mf2:0: Recognized $url as an h-product it is the only item on the page");
        return self::parseAsHItem($mf2, $item, $http, $url);
      }
      if(in_array('h-card', $item['type'])) {
        #Parse::debug("mf2:0: Recognized $url as an h-card it is the only item on the page");
        return self::parseAsHCard($item, $http, $url, $url);
      }
      if(in_array('h-app', $item['type']) || in_array('h-x-app', $item['type'])) {
        #Parse::debug("mf2:0: Recognized $url as an h-feed because it is the only item on the page");
        return self::parseAsHApp($mf2, $item, $http, $url);
      }
      if(in_array('h-feed', $item['type'])) {
        #Parse::debug("mf2:0: Recognized $url as an h-feed because it is the only item on the page");
        return self::parseAsHFeed($mf2, $http, $url);
      }
    }

    // Check the list of items on the page to see if one matches the URL of the page,
    // and treat as a permalink for that object if so.
    foreach($mf2['items'] as $item) {
      if(array_key_exists('url', $item['properties'])) {
        $urls = $item['properties']['url'];
        $urls = array_map('\p3k\XRay\normalize_url', $urls);
        if(in_array($url, $urls)) {
          #Parse::debug("mf2:1: Recognized $url as a permalink because an object on the page matched the URL of the request");
          if(in_array('h-card', $item['type'])) {
            return self::parseAsHCard($item, $http, $url, $url);
          } elseif(in_array('h-entry', $item['type']) || in_array('h-cite', $item['type'])) {
            return self::parseAsHEntry($mf2, $item, $http, $url);
          } elseif(in_array('h-event', $item['type'])) {
            return self::parseAsHEvent($mf2, $item, $http, $url);
          } elseif(in_array('h-review', $item['type'])) {
            return self::parseAsHReview($mf2, $item, $http, $url);
          } elseif(in_array('h-recipe', $item['type'])) {
            return self::parseAsHRecipe($mf2, $item, $http, $url);
          } elseif(in_array('h-product', $item['type'])) {
            return self::parseAsHProduct($mf2, $item, $http, $url);
          } elseif(in_array('h-item', $item['type'])) {
            return self::parseAsHItem($mf2, $item, $http, $url);
          } elseif(in_array('h-app', $item['type']) || in_array('h-x-app', $item['type'])) {
            return self::parseAsHApp($mf2, $item, $http, $url);
          } elseif(in_array('h-feed', $item['type'])) {
            return self::parseAsHFeed($mf2, $http, $url);
          } else {
            #Parse::debug('This object was not a recognized type.');
            return false;
          }
        }
      }
    }

    // Check for an h-card matching rel=author or the author URL of any h-* on the page,
    // and return the h-* object if so
    if(isset($mf2['rels']['author'])) {
      foreach($mf2['items'] as $card) {
        if(in_array('h-card', $card['type']) && array_key_exists('url', $card['properties'])) {
          $urls = \p3k\XRay\normalize_urls($card['properties']['url']);
          if(count(array_intersect($urls, \p3k\XRay\normalize_urls($mf2['rels']['author']))) > 0) {
            // There is an author h-card on this page
            // Now look for the first h-* object other than an h-card and use that as the object
            foreach($mf2['items'] as $item) {
              if(!in_array('h-card', $item['type'])) {
                if(in_array('h-entry', $item['type']) || in_array('h-cite', $item['type'])) {
                  return self::parseAsHEntry($mf2, $item, $http, $url);
                } elseif(in_array('h-event', $item['type'])) {
                  return self::parseAsHEvent($mf2, $item, $http, $url);
                } elseif(in_array('h-review', $item['type'])) {
                  return self::parseAsHReview($mf2, $item, $http, $url);
                } elseif(in_array('h-recipe', $item['type'])) {
                  return self::parseAsHRecipe($mf2, $item, $http, $url);
                } elseif(in_array('h-product', $item['type'])) {
                  return self::parseAsHProduct($mf2, $item, $http, $url);
                } elseif(in_array('h-item', $item['type'])) {
                  return self::parseAsHItem($mf2, $item, $http, $url);
                } elseif(in_array('h-app', $item['type']) || in_array('h-x-app', $item['type'])) {
                  return self::parseAsHApp($mf2, $item, $http, $url);
                }
              }
            }
          }
        }
      }
    }

    // At this point, if there are any h-entrys left on the page, it's probably a feed.
    if(count($items) > 0) {
      if(count(array_filter($items, function($item){
        return in_array('h-entry', $item['type']);
      })) > 0) {
        #Parse::debug("mf2:2: Recognized $url as an h-feed because there are more than one object on the page");
        return self::parseAsHFeed($mf2, $http, $url);
      }
    }

    // If the first item is an h-feed, parse as a feed
    $first = $items[0];
    if(in_array('h-feed', $first['type'])) {
      #Parse::debug("mf2:3: Recognized $url as an h-feed because the first item is an h-feed");
      return self::parseAsHFeed($mf2, $http, $url);
    }

    // Fallback case, but hopefully we have found something before this point
    foreach($mf2['items'] as $item) {
      // Otherwise check for a recognized h-* object
      if(in_array('h-entry', $item['type']) || in_array('h-cite', $item['type'])) {
        #Parse::debug("mf2:6: $url is falling back to the first h-entry on the page");
        return self::parseAsHEntry($mf2, $item, $http, $url);
      } elseif(in_array('h-event', $item['type'])) {
        #Parse::debug("mf2:6: $url is falling back to the first h-event on the page");
        return self::parseAsHEvent($mf2, $item, $http, $url);
      } elseif(in_array('h-review', $item['type'])) {
        #Parse::debug("mf2:6: $url is falling back to the first h-review on the page");
        return self::parseAsHReview($mf2, $item, $http, $url);
      } elseif(in_array('h-recipe', $item['type'])) {
        #Parse::debug("mf2:6: $url is falling back to the first h-recipe on the page");
        return self::parseAsHRecipe($mf2, $item, $http, $url);
      } elseif(in_array('h-product', $item['type'])) {
        #Parse::debug("mf2:6: $url is falling back to the first h-product on the page");
        return self::parseAsHProduct($mf2, $item, $http, $url);
      } elseif(in_array('h-item', $item['type'])) {
        #Parse::debug("mf2:6: $url is falling back to the first h-item on the page");
        return self::parseAsHItem($mf2, $item, $http, $url);
      } elseif(in_array('h-app', $item['type']) || in_array('h-x-app', $item['type'])) {
        #Parse::debug("mf2:6: $url is falling back to the first h-item on the page");
        return self::parseAsHApp($mf2, $item, $http, $url);
      }
    }

    #Parse::debug("mf2:E: No object at $url was recognized");

    return false;
  }

  private static function collectSingleValues($properties, $urlProperties, $item, $url, &$data) {
    foreach($properties as $p) {
      if(($v = self::getPlaintext($item, $p)) !== null) {
        $data[$p] = $v;
      }
    }
    foreach($urlProperties as $p) {
      if($p == 'url') {
        // Special handling for the 'url' property to prioritize finding the URL on the same domain
        if($values = self::getPlaintextValues($item, 'url')) {
          if(count($values) == 1) {
            if(self::isURL($values[0]))
              $data['url'] = $values[0];
          }
          else {
            $set = false;
            foreach($values as $v) {
              if(self::isURL($v) && parse_url($v, PHP_URL_HOST) == parse_url($url, PHP_URL_HOST)) {
                $set = true;
                $data['url'] = $v;
              }
            }
            if(!$set) {
              // Fall back to the first URL if there isn't one on the domain
              if(self::isURL($values[0]))
                $data['url'] = $values[0];
            }
          }
        }
      } else {
        if(($v = self::getPlaintext($item, $p)) !== null) {
          if(self::isURL($v))
            $data[$p] = $v;
        }
      }
    }
  }

  private static function parseHTMLValue($property, $item) {
    if(!array_key_exists($property, $item['properties']))
      return null;

    $textContent = false;
    $htmlContent = false;

    $content = $item['properties'][$property][0];
    if(is_string($content)) {
      $textContent = $content;
    } elseif(!is_string($content) && is_array($content) && array_key_exists('html', $content)) {
      if(array_key_exists('html', $content)) {
        // Only allow images in the content if there is no photo property set
        if(isset($item['properties']['photo']))
          $allowImg = false;
        else
          $allowImg = true;

        $htmlContent = trim(self::sanitizeHTML($content['html'], $allowImg));
        #$textContent = trim(str_replace("&#xD;","\r",$content['value']));
        $textContent = trim(self::stripHTML($htmlContent));
      } else {
        if(isset($content['value']))
          $textContent = trim($content['value']);
      }
    }

    if($textContent || $htmlContent) {
      $data = [
        'text' => $textContent
      ];
      // Only add HTML content if there is actual content.
      // If the text content ends up empty, then the HTML should be too
      // e.g. <div class="e-content"><a href=""><img src="" class="u-photo"></a></div>
      // should not return content of <a href=""></a>
      // TODO: still need to remove empty <a> tags when there is other text in the content
      if($htmlContent && $textContent && $textContent != $htmlContent) {
        $data['html'] = $htmlContent;
      }

      // Also add HTML content if it contains images, video or audio
      // TODO: allow video and audio tags in content, then uncomment this
      if(strpos($htmlContent, '<img') !== false
        /* || strpos($htmlContent, '<video') !== false */
        /* || strpos($htmlContent, '<audio') !== false */) {
        $data['html'] = $htmlContent;
      }

      if(!$data['text'] && !isset($data['html']))
        return null;

      return $data;
    } else {
      return null;
    }
  }

  // Always return arrays, and may contain plaintext content
  // Nested objects are added to refs and the URL is used as the value if present
  private static function collectArrayValues($properties, $item, &$data, &$refs, &$http) {
    foreach($properties as $p) {
      if(array_key_exists($p, $item['properties'])) {
        foreach($item['properties'][$p] as $v) {
          if(is_string($v)) {
            if(!array_key_exists($p, $data)) $data[$p] = [];
            if(!in_array($v, $data[$p]))
              $data[$p][] = $v;
          } elseif(self::isMicroformat($v)) {
            if(($u=self::getPlaintext($v, 'url')) && self::isURL($u)) {
              if(!array_key_exists($p, $data)) $data[$p] = [];
              if(!in_array($u, $data[$p]))
                $data[$p][] = $u;
              $ref = self::parse([
                'body' => ['items'=>[$v]],
                'url' => $u,
                'code' => null,
              ], $http);
              if($ref) {
                $refs[$u] = $ref['data'];
              }
            } else {
              if(!array_key_exists($p, $data)) $data[$p] = [];
              if(!in_array($v['value'], $data[$p]))
                $data[$p][] = $v['value'];
            }
          }
        }
      }
    }
  }

  private static function parseEmbeddedHCard($property, $item, &$http) {
    if(array_key_exists($property, $item['properties'])) {
      $mf2 = $item['properties'][$property][0];
      if(is_string($mf2) && self::isURL($mf2)) {
        $hcard = [
          'type' => 'card',
          'url' => $mf2
        ];
        return $hcard;
      } if(self::isMicroformat($mf2) && in_array('h-card', $mf2['type'])) {
        $hcard = [
          'type' => 'card',
        ];
        $properties = ['name','latitude','longitude','street-address','locality','region','country-name','url'];
        foreach($properties as $p) {
          if($v=self::getPlaintext($mf2, $p)) {
            $hcard[$p] = $v;
          }
        }
        return $hcard;
      }
    }
    return false;
  }

  private static function collectArrayURLValues($properties, $item, &$data, &$refs, &$http) {
    $keys = [];
    foreach($properties as $p) {
      if(array_key_exists($p, $item['properties'])) {
        foreach($item['properties'][$p] as $v) {
          if(is_string($v) && self::isURL($v)) {
            if(!array_key_exists($p, $data)) $data[$p] = [];
            $data[$p][] = $v;
            $keys[] = $p;
          }
          elseif(self::isImgAlt($v)) {
            // For the moment, disregard the alt value and output a string for compatibility with current consuming code.
            $imgURL = $v['value'];
            if (is_string($imgURL) and self::isURL($imgURL)) {
              if(!array_key_exists($p, $data)) $data[$p] = [];
              $data[$p][] = $imgURL;
              $keys[] = $p;
            }
          }
          elseif(self::isMicroformat($v) && ($u=self::getPlaintext($v, 'url')) && self::isURL($u)) {
            if(!array_key_exists($p, $data)) $data[$p] = [];
            $data[$p][] = $u;
            $keys[] = $p;
            // parse the object and put the result in the "refs" object
            $ref = self::parse([
              'body' => ['items'=>[$v]],
              'url' => $u,
              'code' => null,
            ], $http);
            if($ref) {
              $refs[$u] = $ref['data'];
            }
          }
        }
      }
    }

    // Remove duplicate values
    foreach(array_unique($keys) as $key) {
      $data[$key] = array_unique($data[$key]);
    }
  }

  private static function determineNameAndContent($item, &$data) {
    // Determine if the name is distinct from the content
    $name = self::getPlaintext($item, 'name');

    $textContent = null;
    $htmlContent = null;

    $content = self::getHTMLValue($item, 'content');

    if(is_string($content)) {
      $textContent = $content;
    } elseif($content) {
      $htmlContent = array_key_exists('html', $content) ? $content['html'] : null;
      $textContent = array_key_exists('value', $content) ? $content['value'] : null;
    }

    $checkedname = $name;
    if($content) {
      // Trim ellipses from the name
      $name = preg_replace('/ ?(\.\.\.|…)$/', '', $name ?: '');

      // Remove all whitespace when checking equality
      $nameCompare = preg_replace('/\s/','',trim($name) ?: '');
      $contentCompare = preg_replace('/\s/','',trim($textContent) ?: '');

      // Check if the name is a prefix of the content
      if($contentCompare && $nameCompare && strpos($contentCompare, $nameCompare) === 0) {
        $checkedname = null;
      }
    }

    if($checkedname) {
      $data['name'] = $checkedname;
    }

    // If there is content, always return the plaintext content, and return HTML content if it's different
    if($content) {
      $content = self::parseHTMLValue('content', $item);
      if($content && ($content['text'] || $content['html'])) {
        $data['content']['text'] = $content['text'];
        if(isset($content['html']))
          $data['content']['html'] = $content['html'];
      } else {
        // If the content text was blank because the img was removed and that was the only content,
        // then put the name back as the name if it was previously set.
        // See https://github.com/aaronpk/XRay/issues/57
        if($name) {
          $data['name'] = $name;
        }
      }
    }
  }

  private static function parseAsHEntry($mf2, $item, $http, $url) {
    $data = [
      'type' => 'entry'
    ];
    $refs = [];

    // Single plaintext and URL values
    self::collectSingleValues(['published','summary','rsvp','swarm-coins'], ['url','featured','follow-of'], $item, $url, $data);

    if(isset($data['rsvp']))
      $data['rsvp'] = strtolower($data['rsvp']);

    // These properties are always returned as arrays and may contain plaintext content
    // First strip leading hashtags from category values if present
    if(array_key_exists('category', $item['properties'])) {
      foreach($item['properties']['category'] as $i=>$c) {
        if(is_string($c))
          $item['properties']['category'][$i] = ltrim($c, '#');
      }
    }
    self::collectArrayValues(['category','invitee'], $item, $data, $refs, $http);

    // These properties are always returned as arrays and always URLs
    // If the value is an h-* object with a URL, the URL is used and a "ref" is added as well
    self::collectArrayURLValues(['photo','video','audio','syndication','in-reply-to','like-of','repost-of','bookmark-of','quotation-of'], $item, $data, $refs, $http);

    // Hack to make quotation-of a single value
    if(isset($data['quotation-of']))
      $data['quotation-of'] = $data['quotation-of'][0];

    self::determineNameAndContent($item, $data);

    if($author = self::findAuthor($mf2, $item, $http, $url))
      $data['author'] = $author;

    if($checkin = self::parseEmbeddedHCard('checkin', $item, $http))
      $data['checkin'] = $checkin;

    $data['post-type'] = \p3k\XRay\PostType::discover($data);

    $response = [
      'data' => $data,
    ];

    if(count($refs)) {
      $response['data']['refs'] = $refs;
    }

    return $response;
  }

  private static function parseAsHReview($mf2, $item, $http, $url) {
    $data = [
      'type' => 'review'
    ];
    $refs = [];

    self::collectSingleValues(['summary','published','rating','best','worst'], ['url'], $item, $url, $data);

    // Fallback for Mf1 "description" as content. The PHP parser does not properly map this to "content"
    $description = self::parseHTMLValue('description', $item);
    if($description) {
      $data['content'] = $description;
    }

    self::collectArrayValues(['category'], $item, $data, $refs, $http);

    self::collectArrayURLValues(['item'], $item, $data, $refs, $http);

    self::determineNameAndContent($item, $data);

    if($author = self::findAuthor($mf2, $item, $http, $url))
      $data['author'] = $author;

    $data['post-type'] = \p3k\XRay\PostType::discover($data);

    $response = [
      'data' => $data
    ];

    if(count($refs)) {
      $response['data']['refs'] = $refs;
    }

    return $response;
  }

  private static function parseAsHRecipe($mf2, $item, $http, $url) {
    $data = [
      'type' => 'recipe',
    ];
    $refs = [];

    self::collectSingleValues(['name','summary','published','duration','yield','nutrition'], ['url'], $item, $url, $data);

    $instructions = self::parseHTMLValue('instructions', $item);
    if($instructions) {
      $data['instructions'] = $instructions;
    }

    self::collectArrayValues(['category','ingredient'], $item, $data, $refs, $http);

    self::collectArrayURLValues(['photo'], $item, $data, $refs, $http);

    if($author = self::findAuthor($mf2, $item, $http, $url))
      $data['author'] = $author;

    $data['post-type'] = \p3k\XRay\PostType::discover($data);

    $response = [
      'data' => $data
    ];

    if(count($refs)) {
      $response['data']['refs'] = $refs;
    }

    return $response;
  }

  private static function parseAsHProduct($mf2, $item, $http, $url) {
    $data = [
      'type' => 'product'
    ];
    $refs = [];

    self::collectSingleValues(['name','identifier','price'], ['url'], $item, $url, $data);

    $description = self::parseHTMLValue('description', $item);
    if($description) {
      $data['description'] = $description;
    }

    self::collectArrayValues(['category','brand'], $item, $data, $refs, $http);

    self::collectArrayURLValues(['photo','video','audio'], $item, $data, $refs, $http);

    $response = [
      'data' => $data
    ];

    if(count($refs)) {
      $response['data']['refs'] = $refs;
    }

    return $response;
  }

  private static function parseAsHItem($mf2, $item, $http, $url) {
    $data = [
      'type' => 'item'
    ];
    $refs = [];

    self::collectSingleValues(['name'], ['url'], $item, $url, $data);

    self::collectArrayURLValues(['photo','video','audio'], $item, $data, $refs, $http);

    $response = [
      'data' => $data
    ];

    if(count($refs)) {
      $response['data']['refs'] = $refs;
    }

    return $response;
  }

  private static function parseAsHApp($mf2, $item, $http, $url) {
    $data = [
      'type' => 'app'
    ];

    self::collectSingleValues(['name'], ['url','logo'], $item, $url, $data);
    self::collectArrayURLValues(['redirect-uri'], $item, $data, $refs, $http);

    if(!isset($data['url']))
      $data['url'] = $url;

    if(isset($mf2['rels']['redirect_uri'])) {
      if(!isset($data['redirect-uri'])) $data['redirect-uri'] = [];
      $data['redirect-uri'] = array_merge($data['redirect-uri'], $mf2['rels']['redirect_uri']);
    }

    if(isset($data['redirect-uri'])) {
      $data['redirect-uri'] = array_values(array_unique($data['redirect-uri']));
    }

    $response = [
      'data' => $data
    ];

    return $response;
  }

  private static function parseAsHEvent($mf2, $item, $http, $url) {
    $data = [
      'type' => 'event'
    ];
    $refs = [];

    // Single plaintext and URL values
    self::collectSingleValues(['name','summary','published','start','end','duration'], ['url','featured'], $item, $url, $data);

    // These properties are always returned as arrays and may contain plaintext content
    self::collectArrayValues(['category','attendee'], $item, $data, $refs, $http);

    if($location = self::parseEmbeddedHCard('location', $item, $http))
      $data['location'] = $location;

    // These properties are always returned as arrays and always URLs
    // If the value is an h-* object with a URL, the URL is used and a "ref" is added as well
    self::collectArrayURLValues(['photo','video','audio','syndication'], $item, $data, $refs, $http);

    // If there is a description, always return the plaintext content, and return HTML content if it's different
    $content = self::parseHTMLValue('content', $item);
    if($content) {
      $data['content'] = $content;
    } else {
      // Fall back to looking for "description"
      $content = self::parseHTMLValue('description', $item);
      if($content)
        $data['content'] = $content;
    }

    if($author = self::findAuthor($mf2, $item, $http, $url))
      $data['author'] = $author;

    $data['post-type'] = \p3k\XRay\PostType::discover($data);

    $response = [
      'data' => $data
    ];

    if(count($refs)) {
      $response['data']['refs'] = $refs;
    }

    return $response;
  }

  private static function parseAsHCard($item, $http, $url, $authorURL=false) {
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
        if (array_key_exists('url', $item['properties']) and is_array($item['properties']['url'])) {
          foreach($item['properties']['url'] as $url) {
            if(self::isURL($url)) {
              $url = \p3k\XRay\normalize_url($url);
              if($url == \p3k\XRay\normalize_url($authorURL)) {
                $data['url'] = $url;
                $found = true;
              }
            }
          }

          if(!$found && self::isURL($item['properties']['url'][0])) {
            $data['url'] = $item['properties']['url'][0];
          }
        }
        
      } else if(($v = self::getPlaintext($item, $p)) !== null) {
        // Make sure the URL property is actually a URL
        if($p == 'url' || $p == 'photo') {
          if(self::isURL($v))
            $data[$p] = $v;
        } else {
          $data[$p] = $v;
        }
      }
    }

    // If no URL property was found, use the $authorURL provided
    if(!$data['url'])
      $data['url'] = $authorURL;

    $response = [
      'data' => $data
    ];

    return $response;
  }

  private static function findAuthor($mf2, $item, $http, $url) {
    $author = [
      'type' => 'card',
      'name' => null,
      'url' => null,
      'photo' => null
    ];

    // Start by setting the URL of the author to the author URL if one is present in the item.
    // It will be upgraded to a full h-card if additional data can be found.
    if(isset($item['properties']['author'][0]) && self::isURL($item['properties']['author'][0])) {
      $author['url'] = $item['properties']['author'][0];
    }

    // Author Discovery
    // http://indiewebcamp.com/authorship

    $authorPage = false;
    if(array_key_exists('author', $item['properties'])) {

      // Check if any of the values of the author property are an h-card
      foreach($item['properties']['author'] as $a) {
        if(self::isHCard($a)) {
          // 5.1 "if it has an h-card, use it, exit."
          return self::parseAsHCard($a, $http, $url)['data'];
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
        $allHCards = self::findAllMicroformatsByType($authorPageContents, 'h-card');
        $numHCards = count($allHCards);

        foreach($allHCards as $i) {
          if(self::isHCard($i)) {
            // 7.2 "if author-page has 1+ h-card with url == uid == author-page's URL, then use first such h-card, exit."
            if(array_key_exists('url', $i['properties'])
              and in_array(\p3k\XRay\normalize_url($authorPage), \p3k\XRay\normalize_urls($i['properties']['url']))
              and array_key_exists('uid', $i['properties'])
              and in_array(\p3k\XRay\normalize_url($authorPage), \p3k\XRay\normalize_urls($i['properties']['uid']))
            ) {
              return self::parseAsHCard($i, $http, $url, $authorPage)['data'];
            }

            // 7.3 "else if author-page has 1+ h-card with url property which matches the href of a rel-me link on the author-page"
            $relMeLinks = (isset($authorPageContents['rels']) && isset($authorPageContents['rels']['me'])) ? $authorPageContents['rels']['me'] : [];
            if(count($relMeLinks) > 0
              and array_key_exists('url', $i['properties'])
              and count(array_intersect(\p3k\XRay\normalize_urls($i['properties']['url']), \p3k\XRay\normalize_urls($relMeLinks))) > 0
            ) {
              return self::parseAsHCard($i, $http, $url, $authorPage)['data'];
            }

          }
        }
      }

      // 7.4 "if the h-entry's page has 1+ h-card with url == author-page URL, use first such h-card, exit."
      foreach($mf2['items'] as $i) {
        if(self::isHCard($i)) {

          if(array_key_exists('url', $i['properties'])
            and in_array(\p3k\XRay\normalize_url($authorPage), \p3k\XRay\normalize_urls($i['properties']['url']))
          ) {
            return self::parseAsHCard($i, $http, $url)['data'];
          }

        }
        // Also check the "author" property
        // (for finding the author of an h-feed's children when the author is the p-author property of the h-feed)
        if(isset($i['properties']['author'])) {
          foreach($i['properties']['author'] as $ic) {
            if(self::isHCard($ic)) {
              if(array_key_exists('url', $ic['properties'])
                and in_array(\p3k\XRay\normalize_url($authorPage), \p3k\XRay\normalize_urls($ic['properties']['url']))
              ) {
                return self::parseAsHCard($ic, $http, $url)['data'];
              }

            }
          }
        }
      }

    }

    // The below is not yet in the authorship algorithm.

    // If the top object is an h-feed, check for an author property there
    if(isset($mf2['items'][0]['type'][0]) && in_array('h-feed', $mf2['items'][0]['type'])) {
      if(isset($mf2['items'][0]['properties']['author'][0])) {
        $potentialAuthor = $mf2['items'][0]['properties']['author'][0];
        if(self::isHCard($potentialAuthor)) {
          return self::parseAsHCard($potentialAuthor, $http, $url)['data'];
        }
      }
    }

    // If still no author is found, and this page is a feed (list of h-*),
    // then use the first h-card in the list of items.
    $items = array_filter($mf2['items'], function($item){
      return !in_array('h-card', $item['type']);
    });
    if(count($items) > 1) {
      $card = self::_findFirstOfType($mf2, 'h-card');
      if($card) {
        return self::parseAsHCard($card, $http, $url)['data'];
      }
    }

    if(!$author['name'] && !$author['photo'] && !$author['url'])
      return null;

    return $author;
  }

  private static function hasNumericKeys(array $arr) {
    foreach($arr as $key=>$val)
      if (is_numeric($key))
        return true;
    return false;
  }

  private static function isImgAlt($mf) {
    return is_array($mf)
      and !self::hasNumericKeys($mf)
      and array_key_exists('value', $mf)
      and array_key_exists('alt', $mf);
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
    return is_string($string) && preg_match('/^https?:\/\/.+\..+$/', $string);
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
      }
      elseif(self::isImgAlt($value)) {
        // For back-compatibility, assume that the consuming code wants the URL value.
        if (is_string($value['value'])) {
          return $value['value'];
        }
      } elseif(self::isMicroformat($value) && array_key_exists('value', $value)) {
        return $value['value'];
      }
    }
    return $fallback;
  }

  private static function getHTMLValue($mf2, $k, $fallback=null) {
    // Return an array with html and value if the value is html, otherwise return a string
    if(!empty($mf2['properties'][$k]) and is_array($mf2['properties'][$k])) {
      // $mf2['properties'][$v] will always be an array since the input was from the mf2 parser
      $value = $mf2['properties'][$k][0];
      if(is_string($value)) {
        return $value;
      } elseif(isset($value['html'])) {
        return $value;
      }
    }
    return $fallback;
  }

  private static function getPlaintextValues($mf2, $k, $values=[]) {
    if(!empty($mf2['properties'][$k]) and is_array($mf2['properties'][$k])) {
      foreach($mf2['properties'][$k] as $value) {
        if(is_string($value)) {
          $values[] = $value;
        } elseif(self::isMicroformat($value) && array_key_exists('value', $value)) {
          $values[] = $value['value'];
        }
      }
    }
    return $values;
  }

  private static function getURL($url, $http) {
    if(!$url || !$http) return null;
    // TODO: consider adding caching here
    $result = $http->get($url);
    if($result['error'] || !$result['body']) {
      return null;
    }
    return \mf2\Parse($result['body'], $url);
  }

  public static function findAllMicroformatsByType($mf2, $type='h-card') {
    $objects = [];

    foreach($mf2['items'] as $item) {
      if(in_array($type, $item['type'])) {
        $objects[] = $item;
      } else {
        if(isset($item['properties']) && is_array($item['properties'])) {
          foreach($item['properties'] as $property=>$values) {
            foreach($values as $value) {
              if(is_array($value) && isset($value['type']) && is_array($value['type'])) {
                if(in_array($type, $value['type'])) {
                  $objects[] = $value;
                }
              }
            }
          }
        }
        if(isset($item['children']) && is_array($item['children'])) {
          $items = $item['children'];
          $objects = array_merge($objects, self::findAllMicroformatsByType(['items'=>$items], $type));
        }
      }
    }

    return $objects;
  }
}
