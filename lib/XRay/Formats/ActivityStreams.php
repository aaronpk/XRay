<?php
namespace p3k\XRay\Formats;

use DateTime;
use \p3k\XRay\PostType;

class ActivityStreams extends Format {
  
  private static $fetcher;

  public static function is_as2_json($document) {
    if(is_array($document) && isset($document['@context'])) {
      if(is_string($document['@context']) && $document['@context'] == 'https://www.w3.org/ns/activitystreams')
        return true;
      if(is_array($document['@context']) && in_array('https://www.w3.org/ns/activitystreams', $document['@context']))
        return true;
    }

    return false;
  }

  public static function matches_host($url) {
    return true;
  }

  public static function matches($url) {
    return true;
  }

  public static function parse($http_response, $http, $opts=[]) {
    $as2 = $http_response['body'];
    $url = $http_response['url'];
    
    self::$fetcher = $opts['fetcher'] ?? null;

    if(!isset($as2['type']))
      return false;

    if($as2['type'] == 'Create' && is_array($as2['object'])) {
      // Extract the object and parse that instead
      $as2 = $as2['object'];
    }

    switch($as2['type']) {
      case 'Person':
        return self::parseAsHCard($as2, $url, $http, $opts);
      case 'Article':
      case 'Note':
      case 'Announce': // repost
      case 'Like': // like
        return self::parseAsHEntry($as2, $url, $http, $opts);
    }

    $result = [
      'data' => [
        'type' => 'unknown',
      ],
      'url' => $url,
      'code' => $http_response['code'],
    ];
    return $result;
  }

  private static function parseAsHEntry($as2, $url, $http, $opts) {
    $data = [
      'type' => 'entry'
    ];
    $refs = [];

    if(isset($as2['url']))
      $data['url'] = $as2['url'];
    elseif(isset($as2['id']))
      $data['url'] = $as2['id'];

    if(isset($as2['published'])) {
      try {
        $date = new DateTime($as2['published']);
        $data['published'] = $date->format('c');
      } catch(\Exception $e){}
    } elseif(isset($as2['signature']['created'])) {
      // Pull date from the signature if there isn't one in the activity
      try {
        $date = new DateTime($as2['signature']['created']);
        $data['published'] = $date->format('c');
      } catch(\Exception $e){}
    }

    if(isset($as2['name'])) {
      $data['name'] = $as2['name'];
    }

    if(isset($as2['summary'])) {
      $data['summary'] = $as2['summary'];
    }

    if(isset($as2['content'])) {
      $html = trim(self::sanitizeHTML($as2['content']));
      $text = trim(self::stripHTML($html));

      $data['content'] = [
        'text' => $text
      ];

      if($html && $text && $text != $html) {
        $data['content']['html'] = $html;
      }
    }

    if(isset($as2['tag']) && is_array($as2['tag'])) {
      $emoji = [];
      $category = [];
      foreach($as2['tag'] as $tag) {
        if(is_array($tag) && isset($tag['name']) && isset($tag['type']) && $tag['type'] == 'Hashtag')
          $category[] = trim($tag['name'], '#');
        if(is_array($tag) && isset($tag['type']) && $tag['type'] == 'Emoji' && isset($tag['icon']['url'])) {
          $emoji[$tag['name']] = $tag['icon']['url'];
        }
      }

      if(count($category))
        $data['category'] = $category;

      if(count($emoji) && isset($data['content']['html'])) {
        foreach($emoji as $code=>$img) {
          $data['content']['html'] = str_replace($code, '<img src="'.$img.'" alt="'.$code.'" title="'.$code.'" width="24" class="xray-emoji">', $data['content']['html']);
        }
      }
    }

    if(isset($as2['inReplyTo'])) {
      $data['in-reply-to'] = [$as2['inReplyTo']];
    }

    // Photos and Videos
    if(isset($as2['attachment'])) {
      $photos = [];
      $videos = [];
      foreach($as2['attachment'] as $attachment) {
        if(!isset($attachment['mediaType'])) {
          continue;
        }
        if(strpos($attachment['mediaType'], 'image/') !== false) {
          $photos[] = $attachment['url'];
        }
        if(strpos($attachment['mediaType'], 'video/') !== false) {
          $videos[] = $attachment['url'];
        }
      }
      if(count($photos))
        $data['photo'] = $photos;
      if(count($videos))
        $data['video'] = $videos;
    }

    // Fetch the author info, which requires an HTTP request
    $authorURL = false;
    if(isset($as2['attributedTo']) && is_string($as2['attributedTo'])) {
      $authorURL = $as2['attributedTo'];
    } elseif(isset($as2['actor']) && is_string($as2['actor'])) {
      $authorURL = $as2['actor'];
    }
    if($authorURL) {
      if(self::$fetcher)
        $authorResponse = self::$fetcher->signed_get($authorURL, ['Accept' => 'application/activity+json,application/json']);
      else 
        $authorResponse = $http->get($authorURL, ['Accept: application/activity+json,application/json']);
        
      if($authorResponse && !empty($authorResponse['body'])) {
        $authorProfile = json_decode($authorResponse['body'], true);
        $author = self::parseAsHCard($authorProfile, $authorURL, $http, $opts);
        if($author && !empty($author['data']))
          $data['author'] = $author['data'];
      }
    }

    // If this is a repost, fetch the reposted content
    if($as2['type'] == 'Announce' && isset($as2['object']) &&  is_string($as2['object'])) {
      $data['repost-of'] = [$as2['object']];

      if(self::$fetcher)
        $reposted = self::$fetcher->signed_get($as2['object'], ['Accept: application/activity+json,application/json']);
      else
        $reposted = $http->get($as2['object'], ['Accept: application/activity+json,application/json']);

      if($reposted && !empty($reposted['body'])) {
        $repostedData = json_decode($reposted['body'], true);
        if($repostedData) {
          $reposted['body'] = $repostedData;
          $repost = self::parse($reposted, $http, $opts);
          if($repost && isset($repost['data']) && $repost['data']['type'] != 'unknown') {
            $refs[$as2['object']] = $repost['data'];
          }
        }
      }
    }

    // If this is a like, fetch the liked post
    if($as2['type'] == 'Like' && isset($as2['object']) &&  is_string($as2['object'])) {
      $data['like-of'] = [$as2['object']];

      if(self::$fetcher)
        $liked = self::$fetcher->signed_get($as2['object'], ['Accept: application/activity+json,application/json']);
      else
        $liked = $http->get($as2['object'], ['Accept: application/activity+json,application/json']);

      if($liked && !empty($liked['body'])) {
        $likedData = json_decode($liked['body'], true);
        if($likedData) {
          $liked['body'] = $likedData;
          $like = self::parse($liked, $http, $opts);
          if($like && isset($like['data']['type']) && $like['data']['type'] != 'unknown') {
            $refs[$as2['object']] = $like['data'];
          }
        }
      }
    }

    $data['post-type'] = PostType::discover($data);

    $response = [
      'data' => $data,
    ];

    if(count($refs)) {
      $response['data']['refs'] = $refs;
    }

    return $response;
  }

  private static function parseAsHCard($as2, $url, $http, $opts) {
    $data = [
      'type' => 'card',
      'name' => null,
      'url' => null,
      'photo' => null
    ];

    if(!empty($as2['name']))
      $data['name'] = $as2['name'];
    elseif(isset($as2['preferredUsername']))
      $data['name'] = $as2['preferredUsername'];

    if(isset($as2['preferredUsername']))
      $data['nickname'] = $as2['preferredUsername'];

    if(isset($as2['url']))
      $data['url'] = $as2['url'];

    if(isset($as2['icon']) && isset($as2['icon']['url']))
      $data['photo'] = $as2['icon']['url'];

    // TODO: featured image for h-cards?
    // if(isset($as2['image']) && isset($as2['image']['url']))
    //   $data['featured'] = $as2['image']['url'];

    $response = [
      'data' => $data
    ];

    return $response;
  }

}
