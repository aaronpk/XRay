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

  public static function fetch($http, $url, $creds) {
    // Transform the GitHub URL to an API request
    if(preg_match('~https://github.com/([^/]+)/([^/]+)/pull/(\d+)$~', $url, $match)) {
      $type = 'pull';
      $org = $match[1];
      $repo = $match[2];
      $pull = $match[3];
      $apiurl = 'https://api.github.com/repos/'.$org.'/'.$repo.'/pulls/'.$pull;

    } elseif(preg_match('~https://github.com/([^/]+)/([^/]+)/issues/(\d+)$~', $url, $match)) {
      $type = 'issue';
      $org = $match[1];
      $repo = $match[2];
      $issue = $match[3];
      $apiurl = 'https://api.github.com/repos/'.$org.'/'.$repo.'/issues/'.$issue;

    } elseif(preg_match('~https://github.com/([^/]+)/([^/]+)$~', $url, $match)) {
      $type = 'repo';
      $org = $match[1];
      $repo = $match[2];
      $apiurl = 'https://api.github.com/repos/'.$org.'/'.$repo;

    } elseif(preg_match('~https://github.com/([^/]+)/([^/]+)/issues/(\d+)#issuecomment-(\d+)~', $url, $match)) {
      $type = 'comment';
      $org = $match[1];
      $repo = $match[2];
      $issue = $match[3];
      $comment = $match[4];
      $apiurl = 'https://api.github.com/repos/'.$org.'/'.$repo.'/issues/comments/'.$comment;

    } else {
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

    $response = $http->get($apiurl, $headers);
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

  public static function parse($http, $url, $creds, $json=null) {

    if(false) {
    } else {
      $data = json_decode($json, true);
    }

    if(!$data) {
      return [null, null, 0];
    }

    // Start building the h-entry
    $entry = array(
      'type' => ($type == 'repo' ? 'repo' : 'entry'),
      'url' => $url,
      'author' => [
        'type' => 'card',
        'name' => null,
        'photo' => null,
        'url' => null
      ]
    );

    if($type == 'repo')
      $authorkey = 'owner';
    else
      $authorkey = 'user';

    $entry['author']['name'] = $data[$authorkey]['login'];
    $entry['author']['photo'] = $data[$authorkey]['avatar_url'];
    $entry['author']['url'] = $data[$authorkey]['html_url'];

    if($type == 'pull') {
      $entry['name'] = '#' . $pull . ' ' . $data['title'];
    } elseif($type == 'issue') {
      $entry['name'] = '#' . $issue . ' ' . $data['title'];
    } elseif($type == 'repo') {
      $entry['name'] = $data['name'];
    }

    if($type == 'repo') {
      if(!empty($data['description']))
        $entry['summary'] = $data['description'];
    }

    if($type != 'repo' && !empty($data['body'])) {
      $parser = new GithubMarkdown();

      $entry['content'] = [
        'text' => $data['body'],
        'html' => $parser->parse($data['body'])
      ];
    }

    if($type == 'comment') {
      $entry['in-reply-to'] = ['https://github.com/'.$org.'/'.$repo.'/issues/'.$issue];
    }

    if(!empty($data['labels'])) {
      $entry['category'] = array_map(function($l){
        return $l['name'];
      }, $data['labels']);
    }

    $entry['published'] = $data['created_at'];

    $r = [
      'data' => $entry
    ];

    return [$r, $json, $response['code']];
  }

}
