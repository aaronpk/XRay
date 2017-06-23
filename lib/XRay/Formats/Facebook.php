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

  public static function parse($fbObject, $url) {

    $parts = self::extract_url_parts($url);

    if($parts['type'] == 'event') {

      $event = array(
        'type' => 'event',
        'url' => $url,
        'name' => $fbObject['name'],
        'start' => $fbObject['start_time'],
        'end' => $fbObject['end_time']
      );

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
          if(isset($location['street']))    $card['adr'] = $location['street'];
          if(isset($location['country']))   $card['country-name'] =  $location['country'];
          if(isset($location['latitude']))  $card['latitude'] =  $location['latitude'];
          if(isset($location['longitude'])) $card['longitude'] = $location['longitude'];

        }

        $event['location'] = $card['url'];
        $event['refs'] = array($card);

      // If we only have a name, use that
      } elseif(isset($fbObject['place']['name'])) {
        $event['location'] = $fbObject['place']['name'];
      }

      return [
        'data' => $event,
        'original' => $fbObject
      ];
    }
  }

  public static function fetch($url, $creds) {

    $parts = self::extract_url_parts($url);

    if(!$parts or $parts['api_uri'] == false) {
      return [
        'error' => 'unsupported_url',
        'error_description' => 'This Facebook URL is not supported',
        'error_code' => 400,
      ];
    }

    $fb = new \Facebook\Facebook([
      'app_id' => $creds['facebook_app_id'],
      'app_secret' => $creds['facebook_app_secret'],
      'default_graph_version' => 'v2.9',
      ]);

    $fbApp = new \Facebook\FacebookApp($creds['facebook_app_id'], $creds['facebook_app_secret']);
    $token = $fbApp->getAccessToken();

    $request = new \Facebook\FacebookRequest($fbApp, $token, 'GET', $parts['api_uri']);

    try {
      $response = $fb->getClient()->sendRequest($request);
    } catch(\Facebook\Exceptions\FacebookResponseException $e) {
      return [
        'error' => 'facebook_graph_error',
        'error_description' => 'Graph returned an error: ' . $e->getMessage(),
        'error_code' => 400,
      ];
    } catch(\Facebook\Exceptions\FacebookSDKException $e) {
      return [
        'error' => 'facebook_sdk_error',
        'error_description' => 'Facebook SDK returned an error: ' . $e->getMessage(),
        'error_code' => 400,
      ];
    }

    return [
      'code' => 200,
      'body' => $response->getDecodedBody(),
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
