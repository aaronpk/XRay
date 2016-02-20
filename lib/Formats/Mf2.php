<?php
namespace Percolator\Formats;

class Mf2 {

  public static function parse($mf2) {
    $data = [
      'type' => 'entry',
      'author' => [
        'type' => 'card',
        'name' => null,
        'url' => null,
        'photo' => null
      ]
    ];

    if($item = $mf2['items'][0]) {
      if(in_array('h-entry', $item['type'])) {

        // Single plaintext values
        $properties = ['url','published','summary','rsvp'];
        foreach($properties as $p) {
          if($v = self::getPlaintext($item, $p))
            $data[$p] = $v;
        }

        // Always arrays
        $properties = ['photo','video','syndication','in-reply-to','like-of','repost-of'];
        foreach($properties as $p) {
          if(array_key_exists($p, $item['properties']))
            $data[$p] = $item['properties'][$p];
        }

        // Determine if the name is distinct from the content
        $name = self::getPlaintext($item, 'name');
        $content = null;
        $textContent = null;
        $htmlContent = null;
        if(array_key_exists('content', $item['properties'])) {
          $content = $item['properties']['content'][0];
          if(is_string($content)) {
            $textContent = $content;
          } elseif(!is_string($content) && is_array($content) && array_key_exists('value', $content)) {
            if(array_key_exists('html', $content)) {
              $textContent = strip_tags($content['html']);
              $htmlContent = $content['html'];
            } else {
              $textContent = $content['value'];
            }
          }

          // Trim ellipses from the name
          $name = preg_replace('/ ?(\.\.\.|…)$/', '', $name);

          // Check if the name is a prefix of the content
          if(strpos($textContent, $name) === 0) {
            $name = null;
          }

        }

        if($name) {
          $data['name'] = $name;
        }
        if($content) {
          $data['content'] = [
            'text' => $textContent
          ];
          if($textContent != $htmlContent) {
            $data['content']['html'] = $htmlContent;
          }
        }

        return $data;
      }
    }

    return false;
  }

  private static function responseDisplayText($name, $summary, $content) {

    // Build a fake h-entry to pass to the comments parser
    $input = [
      'type' => ['h-entry'],
      'properties' => [
        'name' => [trim($name)],
        'summary' => [trim($summary)],
        'content' => [trim($content)]
      ]
    ];

    if(!trim($name))
      unset($input['properties']['name']);

    if(!trim($summary))
      unset($input['properties']['summary']);

    $result = \IndieWeb\comments\parse($input, false, 1024, 4);

    return [
      'name' => trim($result['name']),
      'content' => $result['text']
    ];
  }  

  private static function hasNumericKeys(array $arr) {
    foreach($arr as $key=>$val) 
      if (is_numeric($key)) 
        return true;
    return false;
  }

  private static function isMicroformat($mf) {
    return is_array($mf) 
      and !self::hasNumericKeys($mf) 
      and !empty($mf['type']) 
      and isset($mf['properties']);
  }

  // Given an array of microformats properties and a key name, return the plaintext value
  // at that property
  // e.g.
  // {"properties":{"published":["foo"]}} results in "foo"
  private static function getPlaintext($mf2, $k, $fallback=null) {
    if(!empty($mf2['properties'][$k]) and is_array($mf2['properties'][$k])) {
      // $mf2['properties'][$v] will always be an array since the input was from the mf2 parser
      $value = $mf2['properties'][$k][0];
      if(is_string($value)) {
        return $value;
      } elseif(self::isMicroformat($value) && array_key_exists('value', $value)) {
        return $value['value'];
      }
    }
    return $fallback;
  }

}
