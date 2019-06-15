<?php
namespace p3k\XRay\Formats;

use DateTime, DateTimeZone;
use Config;
use cebe\markdown\GithubMarkdown;

class GitHub extends Format {

  public static function matches_host($url) {
    $host = parse_url($url, PHP_URL_HOST);
    return $host == 'github.com';
  }

  public static function matches($url) {
    return preg_match('~https://github.com/([^/]+)/([^/]+)/pull/(\d+)$~', $url, $match)
      || preg_match('~https://github.com/([^/]+)/([^/]+)/issues/(\d+)$~', $url, $match)
      || preg_match('~https://github.com/([^/]+)/([^/]+)$~', $url, $match)
      || preg_match('~https://github.com/([^/]+)/([^/]+)/issues/(\d+)#issuecomment-(\d+)~', $url, $match);
  }

  private static function extract_url_parts($url) {
    $response = false;

    if(preg_match('~https://github.com/([^/]+)/([^/]+)/pull/(\d+)$~', $url, $match)) {
      $response = [];
      $response['type'] = 'pull';
      $response['org'] = $match[1];
      $response['repo'] = $match[2];
      $response['pull'] = $match[3];
      $response['apiurl'] = 'https://api.github.com/repos/'.$response['org'].'/'.$response['repo'].'/pulls/'.$response['pull'];

    } elseif(preg_match('~https://github.com/([^/]+)/([^/]+)/issues/(\d+)$~', $url, $match)) {
      $response = [];
      $response['type'] = 'issue';
      $response['org'] = $match[1];
      $response['repo'] = $match[2];
      $response['issue'] = $match[3];
      $response['apiurl'] = 'https://api.github.com/repos/'.$response['org'].'/'.$response['repo'].'/issues/'.$response['issue'];

    } elseif(preg_match('~https://github.com/([^/]+)/([^/]+)$~', $url, $match)) {
      $response = [];
      $response['type'] = 'repo';
      $response['org'] = $match[1];
      $response['repo'] = $match[2];
      $response['apiurl'] = 'https://api.github.com/repos/'.$response['org'].'/'.$response['repo'];

    } elseif(preg_match('~https://github.com/([^/]+)/([^/]+)/issues/(\d+)#issuecomment-(\d+)~', $url, $match)) {
      $response = [];
      $response['type'] = 'comment';
      $response['org'] = $match[1];
      $response['repo'] = $match[2];
      $response['issue'] = $match[3];
      $response['comment'] = $match[4];
      $response['apiurl'] = 'https://api.github.com/repos/'.$response['org'].'/'.$response['repo'].'/issues/comments/'.$response['comment'];

    }

    return $response;
  }

  public static function fetch($http, $url, $creds) {
    $parts = self::extract_url_parts($url);

    if(!$parts) {
      return [
        'error' => 'unsupported_url',
        'error_description' => 'This GitHub URL is not supported',
        'error_code' => 400,
      ];
    }

    $headers = [];
    if(isset($creds['github_access_token'])) {
      $headers[] = 'Authorization: Bearer ' . $creds['github_access_token'];
    }

    $response = $http->get($parts['apiurl'], $headers);
    if($response['code'] != 200) {
      return [
        'error' => 'github_error',
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

    $parts = self::extract_url_parts($url);

    if(!$parts)
      return self::_unknown();

    // Start building the h-entry
    $entry = array(
      'type' => ($parts['type'] == 'repo' ? 'repo' : 'entry'),
      'url' => $url,
      'author' => [
        'type' => 'card',
        'name' => null,
        'photo' => null,
        'url' => null
      ]
    );

    if($parts['type'] == 'repo')
      $authorkey = 'owner';
    else
      $authorkey = 'user';

    $entry['author']['name'] = $data[$authorkey]['login'];
    $entry['author']['photo'] = $data[$authorkey]['avatar_url'];
    $entry['author']['url'] = $data[$authorkey]['html_url'];

    if($parts['type'] == 'pull') {
      $entry['name'] = '#' . $parts['pull'] . ' ' . $data['title'];
    } elseif($parts['type'] == 'issue') {
      $entry['name'] = '#' . $parts['issue'] . ' ' . $data['title'];
    } elseif($parts['type'] == 'repo') {
      $entry['name'] = $data['name'];
    }

    if($parts['type'] == 'repo') {
      if(!empty($data['description']))
        $entry['summary'] = $data['description'];
    }

    if($parts['type'] != 'repo' && !empty($data['body'])) {
      $parser = new GithubMarkdown();

      $entry['content'] = [
        'text' => $data['body'],
        'html' => $parser->parse($data['body'])
      ];
    }

    if($parts['type'] == 'comment') {
      $entry['in-reply-to'] = ['https://github.com/'.$parts['org'].'/'.$parts['repo'].'/issues/'.$parts['issue']];
    } elseif($parts['type'] == 'pull') {
      $entry['in-reply-to'] = ['https://github.com/'.$parts['org'].'/'.$parts['repo']];
    } elseif($parts['type'] == 'issue') {
      $entry['in-reply-to'] = ['https://github.com/'.$parts['org'].'/'.$parts['repo'].'/issues'];
    }

    if(!empty($data['labels'])) {
      $entry['category'] = array_map(function($l){
        return $l['name'];
      }, $data['labels']);
    }

    $entry['published'] = $data['created_at'];

    if($entry['type'] != 'repo')
      $entry['post-type'] = \p3k\XRay\PostType::discover($entry);

    return [
      'data' => $entry,
      'original' => $json,
      'source-format' => 'github',
    ];
  }

}
