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

    if (\p3k\XRay\allow_iframe_video()) {
      $allowed[] = 'iframe';
      $config->set('HTML.SafeIframe', true);
      $config->set('URI.SafeIframeRegexp', '%^(https?:)?//(www\.youtube(?:-nocookie)?\.com/embed/|player\.vimeo\.com/video/)%');
      $config->set('AutoFormat.RemoveEmpty', true);
      // Removes iframe in case it has no src. This strips the non-allowed domains.
      $config->set('AutoFormat.RemoveEmpty.Predicate', array('iframe' => array(0 => 'src')));
    }

    $config->set('HTML.AllowedElements', $allowed);

    if($baseURL) {
      $config->set('URI.MakeAbsolute', true);
      $config->set('URI.Base', $baseURL);
    }

    $def = $config->getHTMLDefinition(true);

    // add HTML <time> element
    $def->addElement(
      'time',
      'Inline',
      'Inline',
      'Common',
      [
        'datetime' => 'Text'
      ]
    );

    /*
    // This isn't working right now, not sure why
    // http://developers.whatwg.org/the-video-element.html#the-video-element
    $def->addElement(
      'video',
      'Block',
      'Optional: (source, Flow) | (Flow, source) | Flow',
      'Common',
      [
        'src' => 'URI',
        'type' => 'Text',
        'width' => 'Length',
        'height' => 'Length',
        'poster' => 'URI',
        'preload' => 'Enum#auto,metadata,none',
        'controls' => 'Bool',
      ]
    );
    $def->addElement(
      'source',
      'Block',
      'Flow',
      'Common',
      [
        'src' => 'URI',
        'type' => 'Text',
      ]
    );
    */

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

}
