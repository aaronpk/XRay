<?php
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class Main {

  public function index(Request $request, Response $response) {
    $response->setContent(view('index', [
      'title' => 'Percolator'
    ]));
    return $response;
  }

}
