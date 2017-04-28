<?php
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class Certbot {

  private $mc;
  private $http;

  public function index(Request $request, Response $response) {
    session_start();

    $state = mt_rand(10000,99999);
    $_SESSION['state'] = $state;

    $response->setContent(p3k\XRay\view('certbot', [
      'title' => 'X-Ray',
      'state' => $state
    ]));
    return $response;
  }

  public function start_auth(Request $request, Response $response) {
    session_start();

    $_SESSION['client_id'] = $request->get('client_id');
    $_SESSION['redirect_uri'] = $request->get('redirect_uri');

    $query = http_build_query([
      'me' => $request->get('me'),
      'client_id' => $request->get('client_id'),
      'redirect_uri' => $request->get('redirect_uri'),
      'state' => $request->get('state'),
    ]);

    $response->headers->set('Location', 'https://indieauth.com/auth?'.$query);
    $response->setStatusCode(302);
    return $response;
  }

  public function redirect(Request $request, Response $response) {
    session_start();

    $this->http = new p3k\HTTP();

    if(!isset($_SESSION['state']) || $_SESSION['state'] != $request->get('state')) {
      $response->headers->set('Location', '/cert?error=invalid_state');
      $response->setStatusCode(302);
      return $response;
    }

    if($code = $request->get('code')) {

      $res = $this->http->post('https://indieauth.com/auth', http_build_query([
        'code' => $code,
        'client_id' => $_SESSION['client_id'],
        'redirect_uri' => $_SESSION['redirect_uri'],
        'state' => $_SESSION['state']
      ]), [
        'Accept: application/json'
      ]);
      $verify = json_decode($res['body'], true);

      unset($_SESSION['state']);

      if(isset($verify['me'])) {

        if(in_array($verify['me'], Config::$admins)) {
          $_SESSION['me'] = $verify['me'];
          $response->headers->set('Location', '/cert');
        } else {
          $response->headers->set('Location', '/cert?error=invalid_user');
        }

      } else {
        $response->headers->set('Location', '/cert?error=invalid');
      }

    } else {
      $response->headers->set('Location', '/cert?error=missing_code');
    }

    $response->setStatusCode(302);
    return $response;
  }

  public function save_challenge(Request $request, Response $response) {
    session_start();

    if(!isset($_SESSION['me']) || !in_array($_SESSION['me'], Config::$admins)) {
      $response->headers->set('Location', '/cert?error=forbidden');
      $response->setStatusCode(302);
      return $response;
    }

    $token = $request->get('token');
    $challenge = $request->get('challenge');

    if(preg_match('/acme-challenge\/(.+)/', $token, $match)) {
      $token = $match[1];
    } elseif(!preg_match('/^[_a-zA-Z0-9]+$/', $token)) {
      echo "Invalid token format\n";
      die();
    }

    $this->_mc();
    $this->mc->set('acme-challenge-'.$token, json_encode([
      'token' => $token,
      'challenge' => $challenge
    ]), 0, 600);

    $response->setContent(p3k\XRay\view('certbot', [
      'title' => 'X-Ray',
      'challenge' => $challenge,
      'token' => $token,
      'verified' => true
    ]));
    return $response;
  }

  public function logout(Request $request, Response $response) {
    session_start();
    unset($_SESSION['me']);
    unset($_SESSION['client_id']);
    unset($_SESSION['redirect_uri']);
    unset($_SESSION['state']);
    session_destroy();
    $response->headers->set('Location', '/cert');
    $response->setStatusCode(302);
    return $response;
  }

  public function challenge(Request $request, Response $response, array $args) {
    $this->_mc();

    $token = $args['token'];

    if($cache = $this->mc->get('acme-challenge-'.$token)) {
      $acme = json_decode($cache, true);

      $response->setContent($acme['challenge']);
    } else {
      $response->setStatusCode(404);
      $response->setContent("Not Found\n");
    }

    $response->headers->set('Content-Type', 'text/plain');
    return $response;
  }

  private function _mc() {
    $this->mc = new Memcache();
    $this->mc->addServer('127.0.0.1');
  }

}
