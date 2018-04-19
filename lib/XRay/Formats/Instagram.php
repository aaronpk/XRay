<?php
namespace p3k\XRay\Formats;

use DOMDocument, DOMXPath;
use DateTime, DateTimeZone;

class Instagram extends Format {

  public static function matches_host($url) {
    $host = parse_url($url, PHP_URL_HOST);
    return in_array($host, ['www.instagram.com','instagram.com']);
  }

  public static function matches($url) {
    return self::matches_host($url);
  }

  public static function parse($http, $html, $url) {
    if(preg_match('#instagram.com/([^/]+)/$#', $url)) {
      return self::parseProfile($http, $html, $url);
    } else {
      return self::parsePhoto($http, $html, $url);
    }
  }

  private static function parseProfile($http, $html, $url) {
    $profileData = self::_parseProfileFromHTML($html);
    if(!$profileData)
      return self::_unknown();

    $card = self::_buildHCardFromInstagramProfile($profileData);

    return [
      'data' => $card
    ];
  }

  private static function parsePhoto($http, $html, $url) {

    $photoData = self::_extractPhotoDataFromPhotoPage($html);

    if(!$photoData)
      return self::_unknown();

    // Start building the h-entry
    $entry = array(
      'type' => 'entry',
      'url' => $url,
      'author' => [
        'type' => 'card',
        'name' => null,
        'photo' => null,
        'url' => null
      ]
    );

    $profiles = [];

    // Fetch profile info for this user
    $username = $photoData['owner']['username'];
    $profile = self::_getInstagramProfile($username, $http);
    if($profile) {
      $entry['author'] = self::_buildHCardFromInstagramProfile($profile);
      $profiles[] = $profile;
    }

    // Content and hashtags
    $caption = false;


    if(isset($photoData['caption'])) {
      $caption = $photoData['caption'];
    } elseif(isset($photoData['edge_media_to_caption']['edges'][0]['node']['text'])) {
      $caption = $photoData['edge_media_to_caption']['edges'][0]['node']['text'];
    }

    if($caption) {
      if(preg_match_all('/#([a-z0-9_-]+)/i', $caption, $matches)) {
        $entry['category'] = [];
        foreach($matches[1] as $match) {
          $entry['category'][] = $match;
        }
      }

      $entry['content'] = [
        'text' => $caption
      ];
    }

    $refs = [];

    // Include the photo/video media URLs
    // (Always return arrays, even for single images)
    if(array_key_exists('edge_sidecar_to_children', $photoData)) {
      // Multi-post
      // For now, we will only pull photos from multi-posts, and skip videos.

      $entry['photo'] = [];
      foreach($photoData['edge_sidecar_to_children']['edges'] as $edge) {
        $entry['photo'][] = $edge['node']['display_url'];
        // Don't need to pull person-tags from here because the main parent object already has them.
      }

    } else {
      // Single photo or video

      if(array_key_exists('display_src', $photoData))
        $entry['photo'] = [$photoData['display_src']];
      elseif(array_key_exists('display_url', $photoData))
        $entry['photo'] = [$photoData['display_url']];

      if(array_key_exists('is_video', $photoData) && $photoData['is_video']) {
        $entry['video'] = [$photoData['video_url']];
      }
    }

    // Find person tags and fetch user profiles

    // old instagram json
    if(isset($photoData['usertags']['nodes'])) {
      if(!isset($entry['category'])) $entry['category'] = [];

      foreach($photoData['usertags']['nodes'] as $tag) {
        $profile = self::_getInstagramProfile($tag['user']['username'], $http);
        if($profile) {
          $card = self::_buildHCardFromInstagramProfile($profile);
          $entry['category'][] = $card['url'];
          $refs[$card['url']] = $card;
          $profiles[] = $profile;
        }
      }
    }

    // new instagram json as of approximately 2017-04-19
    if(isset($photoData['edge_media_to_tagged_user']['edges'])) {
      if(!isset($entry['category'])) $entry['category'] = [];

      foreach($photoData['edge_media_to_tagged_user']['edges'] as $edge) {
        $profile = self::_getInstagramProfile($edge['node']['user']['username'], $http);
        if($profile) {
          $card = self::_buildHCardFromInstagramProfile($profile);
          $entry['category'][] = $card['url'];
          $refs[$card['url']] = $card;
          $profiles[] = $profile;
        }
      }
    }

    // Published date
    if(array_key_exists('taken_at_timestamp', $photoData))
      $published = DateTime::createFromFormat('U', $photoData['taken_at_timestamp']);
    elseif(array_key_exists('date', $photoData))
      $published = DateTime::createFromFormat('U', $photoData['date']);

    // Include venue data
    $locations = [];
    if($photoData['location']) {
      $location = self::_getInstagramLocation($photoData['location']['id'], $http);
      if($location) {
        $entry['location'] = [$location['url']];
        $refs[$location['url']] = $location;
        $locations[] = $location;

        // Look up timezone
        if($location['latitude']) {
          $tz = \p3k\Timezone::timezone_for_location($location['latitude'], $location['longitude']);
          if($tz) {
            $published->setTimeZone(new DateTimeZone($tz));
          }
        }
      }
    }

    $entry['published'] = $published->format('c');

    if(count($refs)) {
      $entry['refs'] = $refs;
    }

    return [
      'data' => $entry,
      'original' => json_encode([
        'photo' => $photoData,
        'profiles' => $profiles,
        'locations' => $locations
      ])
    ];
  }

  private static function _buildHCardFromInstagramProfile($profile) {
    if(!$profile) return false;

    $author = [
      'type' => 'card'
    ];

    if($profile['full_name'])
      $author['name'] = $profile['full_name'];
    else
      $author['name'] = $profile['username'];

    if(isset($profile['external_url']) && $profile['external_url'])
      $author['url'] = $profile['external_url'];
    else
      $author['url'] = 'https://www.instagram.com/' . $profile['username'];

    if(isset($profile['profile_pic_url_hd']))
      $author['photo'] = $profile['profile_pic_url_hd'];
    else
      $author['photo'] = $profile['profile_pic_url'];

    return $author;
  }

  private static function _getInstagramProfile($username, $http) {
    $response = $http->get('https://www.instagram.com/'.$username.'/');

    if(!$response['error'])
      return self::_parseProfileFromHTML($response['body']);

    return null;
  }

  private static function _parseProfileFromHTML($html) {
    $data = self::_extractIGData($html);
    if(isset($data['entry_data']['ProfilePage'][0])) {
      $profile = $data['entry_data']['ProfilePage'][0];
      if($profile && isset($profile['graphql']['user'])) {
        $user = $profile['graphql']['user'];
        return $user;
      }
    }
    return null;
  }

  private static function _getInstagramLocation($id, $http) {
    $igURL = 'https://www.instagram.com/explore/locations/'.$id.'/';
    $response = $http->get($igURL);
    if($response['body']) {
      $data = self::_extractVenueDataFromVenuePage($response['body']);
      if($data) {
        return [
          'type' => 'card',
          'name' => $data['name'],
          'url' => $igURL,
          'latitude' => $data['lat'],
          'longitude' => $data['lng'],
        ];
      }
    }
    return null;
  }

  private static function _extractPhotoDataFromPhotoPage($html) {
    $data = self::_extractIGData($html);

    if($data && is_array($data) && array_key_exists('entry_data', $data)) {
      if(is_array($data['entry_data']) && array_key_exists('PostPage', $data['entry_data'])) {
        $post = $data['entry_data']['PostPage'];
        if(isset($post[0]['graphql']['shortcode_media'])) {
          return $post[0]['graphql']['shortcode_media'];
        } elseif(isset($post[0]['graphql']['media'])) {
          return $post[0]['graphql']['media'];
        } elseif(isset($post[0]['media'])) {
          return $post[0]['media'];
        }
      }
    }

    return null;
  }

  private static function _extractVenueDataFromVenuePage($html) {
    $data = self::_extractIGData($html);

    if($data && isset($data['entry_data']['LocationsPage'])) {
      $data = $data['entry_data']['LocationsPage'];
      if(isset($data[0]['graphql']['location'])) {
        $location = $data[0]['graphql']['location'];

        # we don't need these and they're huge, so drop them now
        unset($location['media']);
        unset($location['top_posts']);

        return $location;
      }
    }

    return null;
  }

  private static function _extractIGData($html) {
    $doc = new DOMDocument();
    @$doc->loadHTML($html);

    if(!$doc) {
      return null;
    }

    $xpath = new DOMXPath($doc);

    $data = null;

    foreach($xpath->query('//script') as $script) {
      if(preg_match('/window\._sharedData = ({.+});/', $script->textContent, $match)) {
        $data = json_decode($match[1], true);
      }
    }

    return $data;
  }

}
