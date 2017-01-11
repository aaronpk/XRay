<?php
const TESTING = true;
require __DIR__ . '/../vendor/autoload.php';

// Load config file if present, otherwise use default
if(file_exists(dirname(__FILE__).'/../config.php')) {
  require dirname(__FILE__).'/../config.php';
} else {
  class Config {
    public static $cache = false;
  }
}

