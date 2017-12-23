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
$router = new League\Route\RouteCollection;
$templates = new League\Plates\Engine(dirname(__FILE__).'/../views');

$router->addRoute('GET', '/', 'Main::index');
$router->addRoute('GET', '/parse', 'Parse::parse');
$router->addRoute('POST', '/parse', 'Parse::parse');
$router->addRoute('POST', '/token', 'Token::token');

$router->addRoute('GET', '/feeds', 'Feeds::find');
$router->addRoute('POST', '/feeds', 'Feeds::find');

$router->addRoute('GET', '/rels', 'Rels::fetch');

$router->addRoute('GET', '/cert', 'Certbot::index');
$router->addRoute('GET', '/cert/auth', 'Certbot::start_auth');
$router->addRoute('GET', '/cert/logout', 'Certbot::logout');
$router->addRoute('GET', '/cert/redirect', 'Certbot::redirect');
$router->addRoute('POST', '/cert/save-challenge', 'Certbot::save_challenge');
$router->addRoute('GET', '/.well-known/acme-challenge/{token}', 'Certbot::challenge');

$dispatcher = $router->getDispatcher();
$request = Request::createFromGlobals();

try {
  $response = $dispatcher->dispatch($request->getMethod(), $request->getPathInfo());
  $response->send();
} catch(League\Route\Http\Exception\NotFoundException $e) {
  $response = new Response;
  $response->setStatusCode(404);
  $response->setContent("Not Found\n");
  $response->send();
} catch(League\Route\Http\Exception\MethodNotAllowedException $e) {
  $response = new Response;
  $response->setStatusCode(405);
  $response->setContent("Method not allowed\n");
  $response->send();
}

function shutdown() {
  $error = error_get_last();
  if($error['type'] === E_ERROR) {
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
