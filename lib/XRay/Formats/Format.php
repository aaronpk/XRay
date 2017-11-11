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

  protected static function sanitizeHTML($html) {
    $config = HTMLPurifier_Config::createDefault();
    $config->set('Cache.DefinitionImpl', null);
    $config->set('HTML.AllowedElements', [
      'a',
      'abbr',
      'b',
      'code',
      'del',
      'em',
      'i',
      'img',
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
      'ol'
    ]);
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

  protected static function stripHTML($html) {
    return trim(strip_tags($html));
  }


}
