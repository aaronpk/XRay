<?php
namespace XRay\Formats;

use DateTime, DateTimeZone;
use Parse;
use cebe\markdown\GithubMarkdown;

class GitHub {

  public static function parse($http, $url, $creds, $json=null) {

    if(!$json) {
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
        return [null, null];
      }

      $response = $http->get($apiurl);
      if($response['code'] != 200) {
        return [null, null];
      }

      $data = json_decode($response['body'], true);
    } else {
      $data = json_decode($json, true);
    }

    if(!$data) {
      return [null, null];
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

    if(!empty($data['labels'])) {
      $entry['category'] = $data['labels'];
    }

    $entry['published'] = $data['created_at'];

    #$entry['author']


    $response = [
      'data' => $entry
    ];

    return [$response, $json];
  }

}
