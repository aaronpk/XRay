<?php
namespace XRay\Formats;

use DOMDocument, DOMXPath;
use DateTime, DateTimeZone;
use Parse;

class Instagram {

  public static function parse($html, $url, $http) {

    $photoData = self::_extractPhotoDataFromPhotoPage($html);

    if(!$photoData)
      return false;

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
    if(isset($photoData['caption'])) {
      if(preg_match_all('/#([a-z0-9_-]+)/i', $photoData['caption'], $matches)) {
        $entry['category'] = [];
        foreach($matches[1] as $match) {
          $entry['category'][] = $match;
        }
      }

      $entry['content'] = [
        'text' => $photoData['caption']
      ];
    }

    // Include the photo/video media URLs
    // (Always return arrays)
    $entry['photo'] = [$photoData['display_src']];

    if(array_key_exists('is_video', $photoData) && $photoData['is_video']) {
      $entry['video'] = [$photoData['video_url']];
    }

    $refs = [];

    // Find person tags and fetch user profiles
    if(array_key_exists('usertags', $photoData) && $photoData['usertags']['nodes']) {
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

    // Published date
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

    $response = [
      'data' => $entry
    ];

    if(count($refs)) {
      $response['refs'] = $refs;
    }

    return [$response, [
      'photo' => $photoData,
      'profiles' => $profiles,
      'locations' => $locations
    ]];
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
      $author['url'] = 'https://www.instagram.com/' . $username;

    if(isset($profile['profile_pic_url_hd']))
      $author['photo'] = $profile['profile_pic_url_hd'];
    else
      $author['photo'] = $profile['profile_pic_url'];

    return $author;
  }

  private static function _getInstagramProfile($username, $http) {
    $response = $http->get('https://www.instagram.com/'.$username.'/?__a=1');

    if(!$response['error']) {
      $profile = @json_decode($response['body'], true);
      if($profile && array_key_exists('user', $profile)) {
        $user = $profile['user'];
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
        if(is_array($post) && array_key_exists(0, $post) && array_key_exists('media', $post[0])) {
          $media = $post[0]['media'];

          return $media;
        }
      }
    }

    return null;
  }

  private static function _extractVenueDataFromVenuePage($html) {
    $data = self::_extractIGData($html);

    if($data && is_array($data) && array_key_exists('entry_data', $data)) {
      if(is_array($data['entry_data']) && array_key_exists('LocationsPage', $data['entry_data'])) {
        $data = $data['entry_data']['LocationsPage'];
        if(is_array($data) && array_key_exists(0, $data) && array_key_exists('location', $data[0])) {
          $location = $data[0]['location'];

          # we don't need these and they're huge, so drop them now
          unset($location['media']);
          unset($location['top_posts']);
          
          return $location;
        }
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
