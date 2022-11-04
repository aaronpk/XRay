<?php
const TESTING = true;
require __DIR__ . '/../vendor/autoload.php';

// TODO: fix the many things causing deprecation warnings!
// For the moment, report all errors except for deprecation warnings during testing.
error_reporting(E_ALL ^ E_DEPRECATED);

// Load config file if present, otherwise use default
if(file_exists(dirname(__FILE__).'/../config.php')) {
    include dirname(__FILE__).'/../config.php';
} else {
    class Config
    {
        public static $cache = false;
        public static $base = '';
        public static $admins = [];

    }
}

