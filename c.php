<?php

function init_me() {
  define("PDO_CONN", "sqlite:".substr(md5($_SERVER['SCRIPT_NAME']),0,10).".db");
  define("USERNAME", "test");
  define("PASSWORD", "test");
  define("REALM", "Authentication");
  define("BEGIN_DATE", "2012-10-01");
  define("TITLE", "My Awesome Microblog");
  //var_dump($_SERVER);
  //define("HTTP_ROOT", "http://localhost:6060/megat/a.php");
  define("HTTP_ROOT", ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') ? 'https' : 'http') . '://'.$_SERVER['HTTP_HOST'].''.$_SERVER['SCRIPT_NAME']);
  define("TAG", 'tag:localhost');
  define("TAG_POSTFIX", 'mt-');
  define("AUTHOR_NAME", "The Newbie");
}
require_once "lib/init.inc.php";

?>
