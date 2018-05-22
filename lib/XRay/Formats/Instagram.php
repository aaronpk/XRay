<?php
namespace p3k\XRay\Formats;

const BASE_URL = 'https://www.instagram.com/';
const QUERY_MEDIA = BASE_URL.'graphql/query/?query_hash=42323d64886122307be10013ad2dcc44&variables=%s';
const QUERY_MEDIA_VARS = '{"id":"%s","first":20,"after":"%s"}';

use DOMDocument, DOMXPath;
use DateTime, DateTimeZone;

class Instagram extends Format {

  private static $gis;

  public static function matches_host($url) {
    $host = parse_url($url, PHP_URL_HOST);
    return in_array($host, ['www.instagram.com','instagram.com']);
  }

  public static function matches($url) {
    return self::matches_host($url);
  }

  public static function parse($http, $html, $url, $opts=[]) {
    if(preg_match('#instagram.com/([^/]+)/$#', $url)) {
      if(isset($opts['expect']) && $opts['expect'] == 'feed')
        return self::parseFeed($http, $html, $url);
      else
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

  private static function _getIntstagramGIS($params) {
    $data = self::$gis.":".$params;
    return md5($data);
  }

  private static function _getMorePhotos($http,$html,$url,$profileData) {
    $params = sprintf(QUERY_MEDIA_VARS, $profileData['id'], $profileData['edge_owner_to_timeline_media']['page_info']['end_cursor']);
    $url = sprintf(QUERY_MEDIA,$params);
    $headers = [];
    $headers[] = 'x-instagram-gis: ' . self::_getIntstagramGIS($params);
    $headers[] = 'x-requested-with: XMLHttpRequest';

    $resp = $http->get($url,$headers);

    if(!$resp['error'])
      $data = json_decode($resp['body'],true);
      $photos = $data['data']['user']['edge_owner_to_timeline_media']['edges'];
      return $photos;

    return null;
  }

  private static function parseFeed($http, $html, $url) {
    $profileData = self::_parseProfileFromHTML($html);
    if(!$profileData)
      return self::_unknown();

    $photos = $profileData['edge_owner_to_timeline_media']['edges'];
    $items = [];

    $morePhotos = self::_getMorePhotos($http,$html,$url,$profileData);

    $photos = array_merge($photos,$morePhotos);

    foreach($photos as $photoData) {
      $item = self::parsePhotoFromData($http, $photoData['node'],
        BASE_URL.'p/'.$photoData['node']['shortcode'].'/', $profileData);
      // Note: Not all the photo info is available in the initial JSON.
      // Things like video mp4 URLs and person tags and locations are missing.
      // Consumers of the feed will need to fetch the photo permalink in order to get all missing information.
      // if($photoData['is_video'])
      //   $item['data']['video'] = true;
      $items[] = $item['data'];
    }

    return [
      'data' => [
        'type' => 'feed',
        'items' => $items,
      ]
    ];
  }

  private static function parsePhoto($http, $html, $url, $profile=false) {
    $photoData = self::_extractPhotoDataFromPhotoPage($html);
    return self::parsePhotoFromData($http, $photoData, $url, $profile);
  }

  private static function parsePhotoFromData($http, $photoData, $url, $profile=false) {

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

    if(!$profile) {
      // Fetch profile info for this user
      $username = $photoData['owner']['username'];
      $profile = self::_getInstagramProfile($username, $http);
      if($profile) {
        $entry['author'] = self::_buildHCardFromInstagramProfile($profile);
        $profiles[] = $profile;
      }
    } else {
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

      if(isset($photoData['is_video']) && $photoData['is_video'] && isset($photoData['video_url'])) {
        $entry['video'] = [$photoData['video_url']];
      }
    }

    // Find person tags and fetch user profiles
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
    if(isset($photoData['taken_at_timestamp']))
      $published = DateTime::createFromFormat('U', $photoData['taken_at_timestamp']);
    elseif(isset($photoData['date']))
      $published = DateTime::createFromFormat('U', $photoData['date']);

    // Include venue data
    $locations = [];
    if(isset($photoData['location'])) {
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
      $author['url'] = BASE_URL . $profile['username'];

    if(isset($profile['profile_pic_url_hd']))
      $author['photo'] = $profile['profile_pic_url_hd'];
    else
      $author['photo'] = $profile['profile_pic_url'];

    if(isset($profile['biography']))
      $author['note'] = $profile['biography'];

    return $author;
  }

  private static function _getInstagramProfile($username, $http) {
    $response = $http->get(BASE_URL.$username.'/');

    if(!$response['error'])
      return self::_parseProfileFromHTML($response['body']);

    return null;
  }

  private static function _parseProfileFromHTML($html) {
    $data = self::_extractIGData($html);
    if(isset($data['rhx_gis'])) {
      self::$gis = $data['rhx_gis'];
    }
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
    $igURL = BASE_URL.'explore/locations/'.$id.'/';
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
