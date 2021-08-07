<?php
namespace p3k\XRay\Formats;

use DOMDocument, DOMXPath;
use DateTime, DateTimeZone;

class Facebook extends Format {

  public static function matches_host($url) {
    $host = parse_url($url, PHP_URL_HOST);
    // TODO: match hosts like 'm.facebook.com' and 'mbasic.facebook.com'
    return in_array($host, ['www.facebook.com','facebook.com']);
  }

  public static function matches($url) {
    return self::matches_host($url);
  }

  public static function parse($http_response) {
    $fbObject = $http_response['body'];
    $url = $http_response['url'];

    if(is_string($fbObject)) $fbObject = json_decode($fbObject, true);

    $parts = self::extract_url_parts($url);

    if($parts['type'] == 'event') {

      $event = array(
        'type' => 'event',
        'url' => $url,
        'name' => $fbObject['name'],
        'start' => $fbObject['start_time']
      );

      if(isset($fbObject['end_time'])) $event['end'] = $fbObject['end_time'];
      if(isset($fbObject['description'])) $event['summary'] = $fbObject['description'];

      // Is the event linked to a Page?
      if(isset($fbObject['place']['id'])) {

        $card = array(
          'type' => 'card',
          'url' => 'https://facebook.com/'.$fbObject['place']['id'],
          'name' => $fbObject['place']['name']
        );

        if(isset($fbObject['place']['location'])) {

          $location = $fbObject['place']['location'];

          if(isset($location['zip']))       $card['postal-code'] = $location['zip'];
          if(isset($location['city']))      $card['locality'] = $location['city'];
          if(isset($location['state']))     $card['region'] = $location['state'];
          if(isset($location['street']))    $card['street-address'] = $location['street'];
          if(isset($location['country']))   $card['country'] =  $location['country'];
          if(isset($location['latitude']))  $card['latitude'] =  (string)$location['latitude'];
          if(isset($location['longitude'])) $card['longitude'] = (string)$location['longitude'];

        }

        $event['location'] = $card['url'];
        $event['refs'] = array($card);

      // If we only have a name, use that
      } elseif(isset($fbObject['place']['name'])) {
        $event['location'] = $fbObject['place']['name'];
      }

      $event['post-type'] = \p3k\XRay\PostType::discover($event);

      return [
        'data' => $event,
        'original' => $fbObject,
        'source-format' => 'facebook',
      ];
    }
  }

  public static function fetch($url, $creds) {

    //Disabled Function for now
    //TODO: Search all references to this class and remove it.

    return [
      'code' => 200,
      'body' => '',
      'url' => $url
    ];
  }

  private static function extract_url_parts($url) {
    $response = false;

    if(preg_match('~https://(.*?).?facebook.com/([^/]+)/posts/(\d+)/?$~', $url, $match)) {
      // TODO: how do we get these?
      // $response['type'] = 'entry';
      // $response['api_uri'] = false;

    } elseif(preg_match('~https://(.*?).?facebook.com/events/(\d+)/?$~', $url, $match)) {
      $response['type'] = 'event';
      $response['api_uri'] = '/'.$match[2];
    }

    return $response;
  }
}
