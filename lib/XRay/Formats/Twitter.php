<?php
namespace p3k\XRay\Formats;

use DateTime, DateTimeZone;

class Twitter extends Format {

  public static function matches_host($url) {
    $host = parse_url($url, PHP_URL_HOST);
    return in_array($host, ['mobile.twitter.com','twitter.com','www.twitter.com','twtr.io']);
  }

  public static function matches($url) {
    if(preg_match('/https?:\/\/(?:mobile\.twitter\.com|twitter\.com|twtr\.io)\/(?:[a-z0-9_\/!#]+statuse?s?\/([0-9]+)|([a-zA-Z0-9_]+))/i', $url, $match))
      return $match;
    else
      return false;
  }

  public static function fetch($url, $creds) {
    if(!($match = self::matches($url))) {
      return false;
    }

    $tweet_id = $match[1];

    $host = parse_url($url, PHP_URL_HOST);
    if($host == 'twtr.io') {
      $tweet_id = self::b60to10($tweet_id);
    }

    $twitter = new \Twitter($creds['twitter_api_key'], $creds['twitter_api_secret'], $creds['twitter_access_token'], $creds['twitter_access_token_secret']);
    try {
      $tweet = $twitter->request('statuses/show/'.$tweet_id, 'GET', ['tweet_mode'=>'extended']);
    } catch(\TwitterException $e) {
      return [
        'error' => 'twitter_error',
        'error_description' => $e->getMessage()
      ];
    }

    return [
      'url' => $url,
      'body' => $tweet,
      'code' => 200,
    ];
  }

  public static function parse($http_response) {
    $json = is_array($http_response) ? $http_response['body'] : $http_response->body;
    $url = is_array($http_response) ? $http_response['url'] : $http_response->url;

    if(is_string($json))
      $tweet = json_decode($json);
    else
      $tweet = $json;

    if(!$tweet) {
      return self::_unknown();
    }

    $entry = array(
      'type' => 'entry',
      'url' => $url,
      'author' => [
        'type' => 'card',
        'name' => null,
        'nickname' => null,
        'photo' => null,
        'url' => null,
      ]
    );
    $refs = [];

    if(property_exists($tweet, 'retweeted_status')) {
      // No content for retweets

      $reposted = $tweet->retweeted_status;
      $repostOf = 'https://twitter.com/' . $reposted->user->screen_name . '/status/' . $reposted->id_str;
      $entry['repost-of'] = $repostOf;

      $repostedEntry = self::parse(['body' => $reposted, 'url' => $repostOf]);
      if(isset($repostedEntry['data']['refs'])) {
        foreach($repostedEntry['data']['refs'] as $k=>$v) {
          $refs[$k] = $v;
        }
      }

      $refs[$repostOf] = $repostedEntry['data'];

    } else {
      $entry['content'] = self::expandTweetContent($tweet);
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

    // In-Reply-To
    if(property_exists($tweet, 'in_reply_to_status_id_str') && $tweet->in_reply_to_status_id_str) {
      $entry['in-reply-to'] = [
        'https://twitter.com/'.$tweet->in_reply_to_screen_name.'/status/'.$tweet->in_reply_to_status_id_str
      ];
    }

    // Don't include the RT'd photo or video in the main object.
    // They get included in the reposted object instead.
    if(!property_exists($tweet, 'retweeted_status')) {
      // Photos and Videos
      if(property_exists($tweet, 'extended_entities') && property_exists($tweet->extended_entities, 'media')) {
        foreach($tweet->extended_entities->media as $media) {
          self::extractMedia($media, $entry);
        }
      }

      // Photos from Streaming API Tweets
      if(property_exists($tweet, 'extended_tweet')) {
        if(property_exists($tweet->extended_tweet, 'entities') && property_exists($tweet->extended_tweet->entities, 'media')) {
          foreach($tweet->extended_tweet->entities->media as $media) {
            self::extractMedia($media, $entry);
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
      $quotedEntry = self::parse(['body' => $tweet->quoted_status, 'url' => $quoteOf]);
      if(isset($quotedEntry['data']['refs'])) {
        foreach($quotedEntry['data']['refs'] as $k=>$v) {
          $refs[$k] = $v;
        }
      }
      $refs[$quoteOf] = $quotedEntry['data'];
      $entry['quotation-of'] = $quoteOf;
    }

    if($author = self::_buildHCardFromTwitterProfile($tweet->user)) {
      $entry['author'] = $author;
    }

    if(count($refs)) {
      $entry['refs'] = $refs;
    }

    $entry['post-type'] = \p3k\XRay\PostType::discover($entry);

    return [
      'data' => $entry,
      'original' => $tweet,
      'source-format' => 'twitter',
    ];
  }

  private static function extractMedia($media, &$entry) {
    if($media->type == 'photo') {
      if(!array_key_exists('photo', $entry))
        $entry['photo'] = [];

      $entry['photo'][] = $media->media_url_https;

    } elseif($media->type == 'video' || $media->type == 'animated_gif') {
      if(!array_key_exists('photo', $entry))
        $entry['photo'] = [];

      if(!array_key_exists('video', $entry))
        $entry['video'] = [];

      // Include the thumbnail
      $entry['photo'][] = $media->media_url_https;

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

    $author['url'] = 'https://twitter.com/' . $profile->screen_name;

    $author['photo'] = $profile->profile_image_url_https;

    return $author;
  }

  private static function expandTweetContent($tweet) {
    $entities = new \StdClass;

    if(property_exists($tweet, 'truncated') && $tweet->truncated) {
      if(property_exists($tweet, 'extended_tweet')) {
        $text = $tweet->extended_tweet->full_text;

        $text = mb_substr($text,
          $tweet->extended_tweet->display_text_range[0],
          $tweet->extended_tweet->display_text_range[1]-$tweet->extended_tweet->display_text_range[0],
          'UTF-8');

        if(property_exists($tweet->extended_tweet, 'entities')) {
          $entities = $tweet->extended_tweet->entities;
        }
      } else {
        $text = $tweet->text;

        if(property_exists($tweet, 'entities')) {
          $entities = $tweet->entities;
        }
      }
    } else {
      // Only use the "display" segment of the text
      if(property_exists($tweet, 'full_text')) {
        // Only use the "display" segment of the text
        $text = mb_substr($tweet->full_text,
          $tweet->display_text_range[0],
          $tweet->display_text_range[1]-$tweet->display_text_range[0],
          'UTF-8');
      } else {
        $text = $tweet->text;
      }

      if(property_exists($tweet, 'entities')) {
        $entities = $tweet->entities;
      }
    }

    // Twitter escapes & as &amp; in the text
    $text = html_entity_decode($text);

    $html = htmlspecialchars($text);
    $html = str_replace("\n", "<br>\n", $html);

    if(property_exists($entities, 'user_mentions')) {
      foreach($entities->user_mentions as $user) {
        $html = str_replace('@'.$user->screen_name, '<a href="https://twitter.com/'.$user->screen_name.'">@'.$user->screen_name.'</a>', $html);
      }
    }

    if(property_exists($entities, 'urls')) {
      foreach($entities->urls as $url) {
        $text = str_replace($url->url, $url->expanded_url, $text);
        $html = str_replace($url->url, '<a href="'.$url->expanded_url.'">'.$url->expanded_url.'</a>', $html);
      }
    }

    $content = [
      'text' => $text,
    ];

    if($html != $text)
      $content['html'] = $html;

    return $content;
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
