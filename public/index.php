<?php
chdir('..');
include('config.php');
include('vendor/autoload.php');

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
$router = new League\Route\RouteCollection;
$templates = new League\Plates\Engine(dirname(__FILE__).'/../views');

$router->addRoute('GET', '/', 'Main::index');
$router->addRoute('GET', '/parse', 'Parse::parse');

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
