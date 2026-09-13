<?php
chdir(dirname(__FILE__).'/..');
include('vendor/autoload.php');

register_shutdown_function('shutdown');

// Load config file if present, otherwise use default
if(file_exists(dirname(__FILE__).'/../config.php')) {
  require dirname(__FILE__).'/../config.php';
} else {
  class Config {
    public static $cache = false;
    public static $admins = [];
    public static $base = '';
  }
}

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

$templates = new League\Plates\Engine(dirname(__FILE__).'/../views');

$routes = [
  ['GET',  '/', 'Main::index'],
  ['GET',  '/parse', 'Parse::parse'],
  ['POST', '/parse', 'Parse::parse'],
  ['POST', '/token', 'Token::token'],

  ['GET',  '/feeds', 'Feeds::find'],
  ['POST', '/feeds', 'Feeds::find'],

  ['GET',  '/rels', 'Rels::fetch'],
  ['POST', '/rels', 'Rels::fetch'],

  ['GET',  '/cert', 'Certbot::index'],
  ['GET',  '/cert/auth', 'Certbot::start_auth'],
  ['GET',  '/cert/logout', 'Certbot::logout'],
  ['GET',  '/cert/redirect', 'Certbot::redirect'],
  ['POST', '/cert/save-challenge', 'Certbot::save_challenge'],
  ['GET',  '/.well-known/acme-challenge/{token}', 'Certbot::challenge'],
];

$request = Request::createFromGlobals();
$response = new Response;

$method = $request->getMethod();
$path = $request->getPathInfo();

$handler = null;
$args = [];
$pathMatched = false;

foreach($routes as list($routeMethod, $pattern, $routeHandler)) {
  // Convert {name} placeholders into named regex groups
  $regex = '#^'.preg_replace('/\{([a-zA-Z_]+)\}/', '(?P<$1>[^/]+)', $pattern).'$#';
  if(!preg_match($regex, $path, $match))
    continue;
  $pathMatched = true;
  if($routeMethod != $method)
    continue;
  $handler = $routeHandler;
  $args = array_filter($match, 'is_string', ARRAY_FILTER_USE_KEY);
  break;
}

if($handler) {
  list($class, $fn) = explode('::', $handler);
  $controller = new $class();
  $response = $controller->$fn($request, $response, $args);
} elseif($pathMatched) {
  $response->setStatusCode(405);
  $response->setContent("Method not allowed\n");
} else {
  $response->setStatusCode(404);
  $response->setContent("Not Found\n");
}

$response->send();

function shutdown() {
  $error = error_get_last();
  if($error && $error['type'] === E_ERROR) {
    header('HTTP/1.1 500 Server Error');
    header('X-PHP-Error-Type: '.$error['type']);
    header('X-PHP-Error-Message: '.$error['message']);
    header('Content-Type: application/json');
    echo json_encode([
      'error' => 'internal_error',
      'error_code' => 500,
      'error_description' => $error['message'],
      'debug' => 'Please file an issue with any information you have about what caused this error: https://github.com/aaronpk/XRay/issues'
    ]);
    die();
  }
}
