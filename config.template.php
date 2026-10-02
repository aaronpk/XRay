<?php
class Config {
  public static $base = 'http://xray.dev';
  public static $cache = false;
  public static $admins = [
    'https://you.example.com/'
  ];
  // Fetching only reaches public addresses. Hosts, addresses or CIDR ranges
  // listed here may be reached anyway, e.g. ['dev.example', '10.0.0.0/8'].
  public static $allow_private = [];
}
