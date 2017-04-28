<?php
namespace p3k\XRay\Formats;

use DateTime, DateTimeZone;
use Parse;

class Twitter {

  public static function parse($url, $tweet_id, $creds, $json=null) {

    $host = parse_url($url, PHP_URL_HOST);
    if($host == 'twtr.io') {
      $tweet_id = self::b60to10($tweet_id);
    }

    if($json) {
      if(is_string($json))
        $tweet = json_decode($json);
      else
        $tweet = $json;
    } else {
      $twitter = new \Twitter($creds['twitter_api_key'], $creds['twitter_api_secret'], $creds['twitter_access_token'], $creds['twitter_access_token_secret']);
      try { 
        $tweet = $twitter->request('statuses/show/'.$tweet_id, 'GET', ['tweet_mode'=>'extended']);
      } catch(\TwitterException $e) {
        return [false, false];
      }
    }

    if(!$tweet)
      return [false, false];

    $entry = array(
      'type' => 'entry',
      'url' => $url,
      'author' => [
        'type' => 'card',
        'name' => null,
        'nickname' => null,
        'photo' => null,
        'url' => null
      ]
    );
    $refs = [];

    // Only use the "display" segment of the text
    $text = mb_substr($tweet->full_text, 
      $tweet->display_text_range[0], 
      $tweet->display_text_range[1]-$tweet->display_text_range[0],
      'UTF-8');

    if(property_exists($tweet, 'retweeted_status')) {
      // No content for retweets

      $reposted = $tweet->retweeted_status;
      $repostOf = 'https://twitter.com/' . $reposted->user->screen_name . '/status/' . $reposted->id_str;
      $entry['repost-of'] = $repostOf;

      list($repostedEntry) = self::parse($repostOf, $reposted->id_str, null, $reposted);
      if(isset($repostedEntry['refs'])) {
        foreach($repostedEntry['refs'] as $k=>$v) {
          $refs[$k] = $v;
        }
      }

      $refs[$repostOf] = $repostedEntry['data'];

    } else {
      // Twitter escapes & as &amp; in the text
      $text = html_entity_decode($text);

      $text = self::expandTweetURLs($text, $tweet);

      $entry['content'] = ['text' => $text];
    }

    // Published date
    $published = new DateTime($tweet->created_at);
    if(property_exists($tweet->user, 'utc_offset')) {
      $tz = new DateTimeZone(sprintf('%+d', $tweet->user->utc_offset / 3600));
      $published->setTimeZone($tz);
    }
    $entry['published'] = $published->format('c');

    // Hashtags
    if(property_exists($tweet, 'entities') && property_exists($tweet->entities, 'hashtags')) {
      if(count($tweet->entities->hashtags)) {
        $entry['category'] = [];
        foreach($tweet->entities->hashtags as $hashtag) {
          $entry['category'][] = $hashtag->text;
        }
      }
    }

    // Don't include the RT'd photo or video in the main object. 
    // They get included in the reposted object instead.
    if(!property_exists($tweet, 'retweeted_status')) {
      // Photos and Videos
      if(property_exists($tweet, 'extended_entities') && property_exists($tweet->extended_entities, 'media')) {
        foreach($tweet->extended_entities->media as $media) {
          if($media->type == 'photo') {
            if(!array_key_exists('photo', $entry))
              $entry['photo'] = [];

            $entry['photo'][] = $media->media_url_https;

          } elseif($media->type == 'video') {
            if(!array_key_exists('video', $entry))
              $entry['video'] = [];

            // Find the highest bitrate video that is mp4
            $videos = $media->video_info->variants;
            $videos = array_filter($videos, function($v) {
              return property_exists($v, 'bitrate') && $v->content_type == 'video/mp4';
            });
            if(count($videos)) {
              usort($videos, function($a,$b) {
                return $a->bitrate < $b->bitrate;
              });
              $entry['video'][] = $videos[0]->url;
            }
          }
        }
      }

      // Place
      if(property_exists($tweet, 'place') && $tweet->place) {
        $place = $tweet->place;
        if($place->place_type == 'city') {
          $entry['location'] = $place->url;
          $refs[$place->url] = [
            'type' => 'adr',
            'name' => $place->full_name,
            'locality' => $place->name,
            'country-name' => $place->country,
          ];
        }
      }
    }

    // Quoted Status
    if(property_exists($tweet, 'quoted_status')) {
      $quoteOf = 'https://twitter.com/' . $tweet->quoted_status->user->screen_name . '/status/' . $tweet->quoted_status_id_str;
      list($quoted) = self::parse($quoteOf, $tweet->quoted_status_id_str, null, $tweet->quoted_status);
      if(isset($quoted['refs'])) {
        foreach($quoted['refs'] as $k=>$v) {
          $refs[$k] = $v;
        }
      }
      $refs[$quoteOf] = $quoted['data'];
    }

    if($author = self::_buildHCardFromTwitterProfile($tweet->user)) {
      $entry['author'] = $author;
    }

    $response = [
      'data' => $entry
    ];

    if(count($refs)) {
      $response['refs'] = $refs;
    }

    return [$response, $tweet];
  }

  private static function _buildHCardFromTwitterProfile($profile) {
    if(!$profile) return false;

    $author = [
      'type' => 'card'
    ];

    $author['nickname'] = $profile->screen_name;
    $author['location'] = $profile->location;
    $author['bio'] = self::expandTwitterObjectURLs($profile->description, $profile, 'description');

    if($profile->name)
      $author['name'] = $profile->name;
    else
      $author['name'] = $profile->screen_name;

    if($profile->url) {
      if($profile->entities->url->urls[0]->expanded_url)
        $author['url'] = $profile->entities->url->urls[0]->expanded_url;
      else
        $author['url'] = $profile->entities->url->urls[0]->url;
    }
    else {
      $author['url'] = 'https://twitter.com/' . $profile->screen_name;
    }

    $author['photo'] = $profile->profile_image_url_https;

    return $author;
  }

  private static function expandTweetURLs($text, $object) {
    if(property_exists($object, 'entities') && property_exists($object->entities, 'urls')) {
      foreach($object->entities->urls as $url) {
        $text = str_replace($url->url, $url->expanded_url, $text);
      }
    }
    return $text;
  }

  private static function expandTwitterObjectURLs($text, $object, $key) {
    if(property_exists($object, 'entities') 
      && property_exists($object->entities, $key) 
      && property_exists($object->entities->{$key}, 'urls')) {
      foreach($object->entities->{$key}->urls as $url) {
        $text = str_replace($url->url, $url->expanded_url, $text);
      }
    }
    return $text;
  }

  /**
   * Converts base 60 to base 10, with error checking
   * http://tantek.pbworks.com/NewBase60
   * @param string $s
   * @return int
   */
  function b60to10($s)
  {
    $n = 0;
    for($i = 0; $i < strlen($s); $i++) // iterate from first to last char of $s
    {
      $c = ord($s[$i]); //  put current ASCII of char into $c  
      if ($c>=48 && $c<=57) { $c=bcsub($c,48); }
      else if ($c>=65 && $c<=72) { $c=bcsub($c,55); }
      else if ($c==73 || $c==108) { $c=1; } // typo capital I, lowercase l to 1
      else if ($c>=74 && $c<=78) { $c=bcsub($c,56); }
      else if ($c==79) { $c=0; } // error correct typo capital O to 0
      else if ($c>=80 && $c<=90) { $c=bcsub($c,57); }
      else if ($c==95) { $c=34; } // underscore
      else if ($c>=97 && $c<=107) { $c=bcsub($c,62); }
      else if ($c>=109 && $c<=122) { $c=bcsub($c,63); }
      else { $c = 0; } // treat all other noise as 0
      $n = bcadd(bcmul(60, $n), $c);
    }
    return $n;
  }

}
