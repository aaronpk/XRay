<?php
namespace p3k\XRay\Formats;

use DateTime, DateTimeZone;
use Config;
use cebe\markdown\GithubMarkdown;

class Hackernews extends Format {

  public static function matches_host($url) {
    $host = parse_url($url, PHP_URL_HOST);
    return $host == 'news.ycombinator.com';
  }

  public static function matches($url) {
    if(preg_match('~https?://news\.ycombinator\.com/item\?id=(\d+)$~', $url, $match))
      return $match;
    else
      return false;
  }

  public static function fetch($http, $url, $opts) {
    $match = self::matches($url);

    $response = $http->get('https://hacker-news.firebaseio.com/v0/item/'.$match[1].'.json');
    if($response['code'] != 200) {
      return [
        'error' => 'hackernews_error',
        'error_description' => $response['body'],
        'code' => $response['code'],
      ];
    }

    return [
      'url' => $url,
      'body' => $response['body'],
      'code' => $response['code'],
    ];
  }

  public static function parse($http_response) {
    $json = $http_response['body'];
    $url = $http_response['url'];

    $data = @json_decode($json, true);

    if(!$data)
      return self::_unknown();

    $match = self::matches($url);

    $date = DateTime::createFromFormat('U', $data['time']);

    // Start building the h-entry
    $entry = array(
      'type' => 'entry',
      'url' => $url,
      'author' => [
        'type' => 'card',
        'name' => $data['by'],
        'photo' => null,
        'url' => 'https://news.ycombinator.com/user?id='.$data['by']
      ],
      'published' => $date->format('c'),
    );

    if(isset($data['url'])) {
      $entry['bookmark-of'] = [$data['url']];
    }

    if(isset($data['title'])) {
      $entry['name'] = $data['title'];
    }

    if(isset($data['text'])) {
      $htmlContent = trim(self::sanitizeHTML($data['text']));
      $textContent = str_replace('<p>', "\n<p>", $htmlContent);
      $textContent = strip_tags($textContent);
      $entry['content'] = [
        'html' => $htmlContent,
        'text' => $textContent
      ];
    }

    if(isset($data['parent'])) {
      $entry['in-reply-to'] = ['https://news.ycombinator.com/item?id='.$data['parent']];
    }

    $entry['post-type'] = \p3k\XRay\PostType::discover($entry);

    return [
      'data' => $entry,
      'original' => $json,
      'source-format' => 'hackernews',
    ];
  }

}
