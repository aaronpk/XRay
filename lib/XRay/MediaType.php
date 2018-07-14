<?php
namespace p3k\XRay;

class MediaType {

  public $type;
  public $subtype;
  public $format;
  public $charset;

  // Parse a media type into component parts: type, subtype, format, charset
  // e.g. "application/json" => type: application, subtype: json, format: json
  // "application/ld+json" => type: application, subtype: "ld+json", format: json
  public function __construct($string) {
    if(strstr($string, ';')) {
      list($type, $parameters) = explode(';', $string, 2);

      $parameters = explode(';', $parameters);
      foreach($parameters as $p) {
        list($k, $v) = explode('=', trim($p));
        if($k == 'charset')
          $this->charset = $v;
      }
    } else {
      $type = $string;
    }

    list($type, $subtype) = explode('/', $type);

    $this->type = $type;
    $this->subtype = $subtype;
    $this->format = $subtype;

    if(strstr($subtype, '+')) {
      list($a, $b) = explode('+', $subtype, 2);
      $this->format = $b;
    }
  }

}
