<?php
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class Main {

  public function index(Request $request, Response $response) {
    $response->setContent(p3k\XRay\view('index', [
      'title' => 'X-Ray'
    ]));
    return $response;
  }

}
