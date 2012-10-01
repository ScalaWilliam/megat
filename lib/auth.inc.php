<?php
function http_auth($opaque = 'opaque', $realm = 'Authentication',
		   $baseURL = '/', $hashed = true,
		   $privateKey = 'privateKey', $nonceLife = 1300) {

  $http_nonce = md5(date('Y-m-d H:i', ceil(time()/$nonceLife)*$nonceLife)
		    .':'.$_SERVER['REMOTE_ADDR'].':'.$privateKey);
  $http_opaque = md5($opaque);

  $send = function() use($realm, $baseURL, $http_nonce, $http_opaque) {
    header( 'WWW-Authenticate: Digest '.
            'realm="'.$realm.'", '.
            'domain="'.$baseURL.'", '.
            'qop=auth, '.
            'algorithm=MD5, '.
            'nonce="'.$http_nonce.'", '.
            'opaque="'.$http_opaque.'"'
        );
    header('HTTP/1.0 401 Unauthorized');
    echo "Unauthorized.\n";
    exit;
    return true;
  };
  $auth = function($users, $require, $send2=null) use ($send, $http_nonce, $http_opaque, $realm, $baseURL, $hashed, $privateKey) {
      $sendf = isset($send2) ? $send2 : $send;
      
    $ret = function() use($sendf, $require) { return $require && !$sendf(); };

    if(!isset($_SERVER['PHP_AUTH_DIGEST']))
      return $ret();

    $auth = $_SERVER['PHP_AUTH_DIGEST'];
    if ( substr($auth, 0, 5) === 'Basic' )
      return $ret();
    
    $matches =  preg_match('/username="([^"]+)"/',    $auth, $username)
                && preg_match('/nonce="([^"]+)"/',    $auth, $nonce)
                && preg_match('/response="([^"]+)"/', $auth, $response)
                && preg_match('/opaque="([^"]+)"/',   $auth, $opaque)
                && preg_match('/uri="([^"]+)"/',      $auth, $uri);
    if ( !$matches )
      return $ret();
    $username = $username[1];
    $requestURI = $_SERVER['REQUEST_URI'];
    if ( strpos($requestURI, '?') !== FALSE )
      $requestURI = substr($requestURI, 0, strlen($uri[1]));
    if ( !($users($username)
	   && $opaque[1] === $http_opaque
	   && $uri[1] === $requestURI
	   && $nonce[1] === $http_nonce) )
      return $ret();
    $password = $users($username);
    $a1 = $hashed ? $password : md5($username.':'.$realm.':'.$password);
    $a2 = md5($_SERVER['REQUEST_METHOD'].':'.$requestURI);
    $matches2 = preg_match('/qop="?([^,\s"]+)/',    $auth, $qop)
                && preg_match('/nc=([^,\s"]+)/',    $auth, $nc)
                && preg_match('/cnonce="([^"]+)"/', $auth, $cnonce);
    if ( $matches2 )
      $expected = md5($a1.':'.$nonce[1].':'.$nc[1].':'.$cnonce[1].':'.$qop[1].':'.$a2);
    else
      $expected = md5($a1.':'.$nonce[1].':'.$a2);
    if ( $response[1] !== $expected )
      return $ret();
    return $username;
  };
  return $auth;
}

function uauth_array($users) {
  return function($username) use ($users) {
    if ( !isset($users[$username]) )
      return;
    $user = $users[$username];
    if ( is_array($user) )
      return $user['ha1'];
    return $user;
  };
}
function uauth($stmt) {
  return function($username) use ($stmt) {
    static $user;
    if ( is_object($user) && $user->username === $username && isset($user->ha1) )
      return $user->ha1;
    $stmt->execute(array($username));
    $user = $stmt->fetch(PDO::FETCH_CLASS);
    if ( is_object($user) && isset($user->ha1) )
      return $user->ha1;
  };
}
class UClass {
  function __construct($stmt) {
    $this->__stmt = $stmt;
  }
  public $__users = array();
  protected $__stmt;
  function __isset($username) {
    if ( isset($this->__users[$username]) )
      return $this->__users[$username] !== false;

    $this->__stmt->execute(array($username));
    $row = $this->__stmt->fetch(PDO::FETCH_ASSOC);
    $this->__users[$username] = $row;
    return !!$row;
  }
  function __get($username) {
    return $this->__users[$username]['ha1'];
  }
}
?>
