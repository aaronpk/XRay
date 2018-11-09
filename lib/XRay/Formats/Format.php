<?php
namespace p3k\XRay\Formats;

use DOMDocument, DOMXPath;
use HTMLPurifier, HTMLPurifier_Config;

interface iFormat {

  public static function matches_host($url);
  public static function matches($url);

}

abstract class Format implements iFormat {

  protected static function _unknown() {
    return [
      'data' => [
        'type' => 'unknown'
      ]
    ];
  }

  protected static function _loadHTML($html) {
    $doc = new DOMDocument();
    @$doc->loadHTML($html);

    if(!$doc) {
      return [null, null];
    }

    $xpath = new DOMXPath($doc);

    return [$doc, $xpath];
  }

  protected static function sanitizeHTML($html, $allowImg=true, $baseURL=false) {
    $allowed = [
      'a',
      'abbr',
      'b',
      'br',
      'code',
      'del',
      'em',
      'i',
      'q',
      'strike',
      'strong',
      'time',
      'blockquote',
      'pre',
      'p',
      'h1',
      'h2',
      'h3',
      'h4',
      'h5',
      'h6',
      'ul',
      'li',
      'ol',
      'span',
    ];
    if($allowImg)
      $allowed[] = 'img';

    $config = HTMLPurifier_Config::createDefault();
    $config->set('Cache.DefinitionImpl', null);
    $config->set('HTML.AllowedElements', $allowed);

    if($baseURL) {
      $config->set('URI.MakeAbsolute', true);
      $config->set('URI.Base', $baseURL);
    }

    $def = $config->getHTMLDefinition(true);
    $def->addElement(
      'time',
      'Inline',
      'Inline',
      'Common',
      [
        'datetime' => 'Text'
      ]
    );
    // Override the allowed classes to only support Microformats2 classes
    $def->manager->attrTypes->set('Class', new HTMLPurifier_AttrDef_HTML_Microformats2());
    $purifier = new HTMLPurifier($config);
    $sanitized = $purifier->purify($html);
    $sanitized = str_replace("&#xD;","\r",$sanitized);
    return trim($sanitized);
  }

  // Return a plaintext version of the input HTML
  protected static function stripHTML($html) {
    $config = HTMLPurifier_Config::createDefault();
    $config->set('Cache.DefinitionImpl', null);
    $config->set('HTML.AllowedElements', ['br']);
    $purifier = new HTMLPurifier($config);
    $sanitized = $purifier->purify($html);
    $sanitized = str_replace("&#xD;","\r",$sanitized);
    $sanitized = html_entity_decode($sanitized);
    return trim(str_replace(['<br>','<br />'],"\n", $sanitized));
  }

  protected static function findLinksInDocument(&$xpath, $target) {
    $found = [];
    self::xPathFindNodeWithAttribute($xpath, 'a', 'href', function($u) use($target, &$found){
      if($u == $target) {
        $found[$u] = null;
      }
    });
    self::xPathFindNodeWithAttribute($xpath, 'img', 'src', function($u) use($target, &$found){
      if($u == $target) {
        $found[$u] = null;
      }
    });
    self::xPathFindNodeWithAttribute($xpath, 'video', 'src', function($u) use($target, &$found){
      if($u == $target) {
        $found[$u] = null;
      }
    });
    self::xPathFindNodeWithAttribute($xpath, 'audio', 'src', function($u) use($target, &$found){
      if($u == $target) {
        $found[$u] = null;
      }
    });
    return $found;
  }

  public static function xPathFindNodeWithAttribute($xpath, $node, $attr, $callback) {
    foreach($xpath->query('//'.$node.'[@'.$attr.']') as $el) {
      $v = $el->getAttribute($attr);
      $callback($v);
    }
  }

}
