<?php

require_once "auth.inc.php";
require_once "views.inc.php";
require_once "parts.inc.php";

ini_set("date.timezone","UTC");
mb_internal_encoding ('UTF-8');
mb_regex_encoding    ('UTF-8');
define("ROOT", dirname(__FILE__));
define("XMLNS", "http://www.w3.org/2005/Atom");

function config($name) {
    if ( $name === 'public' ) { return false; }
    if ( $name === 'in_public' ) { return true; }
    if ( $name === 'feed' ) { return HTTP_ROOT.'?collection=my&xml=true'; }
    if ( $name === 'index' ) { return HTTP_ROOT; }
    if ( $name === 'xml-id' ) { return TAG.",".BEGIN_DATE.":".TAG_POSTFIX."main"; }
    if ( $name === 'title' ) { return TITLE; }
    throw new Exception("Config name '{$name}' unrecognised");
}

function item_url($id) {
    return HTTP_ROOT.'?id='.$id;
}
function item_xml_url($id) {
    return HTTP_ROOT.'?id='.$id.'&xml=true';
}
if ( !function_exists("init_me") )
  trigger_error("init_me does not exist", E_USER_ERROR);

$uri_scheme = null;

init_me();

if ( !is_callable($uri_scheme) ) {
  $uri_scheme = function($action) {
    return HTTP_ROOT;
  };
}

$db = new PDO(PDO_CONN);
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_OBJ);
$collections = array("my", "in", "subscribers", "subscriptions");


function init_db($db) {
    $db->beginTransaction();
    $db->query('CREATE TABLE IF NOT EXISTS "in" (id TEXT UNIQUE, message TEXT, messageHTML TEXT, published TEXT, atomXML TEXT);');
    $db->query('CREATE TABLE IF NOT EXISTS "my" (id TEXT UNIQUE, message TEXT, messageHTML TEXT, published TEXT, atomXML TEXT);');
    $db->query('CREATE VIEW IF NOT EXISTS "all" AS SELECT * FROM "in" UNION ALL SELECT * FROM "my" ORDER BY published DESC;');
    $db->query('CREATE VIEW IF NOT EXISTS "items" AS SELECT * FROM "in" UNION ALL SELECT * FROM "my";');
    $db->commit();
}
init_db($db);
$collections = array("my", "in");

class InvalidAtomException extends Exception {
  private $xml;
  function __construct($xml) {
    $this->xml = $xml;
    $this->message = "Invalid Atom feed";
  }
  function getXML() {
    return $xml;
  }
}

require_once "launch.inc.php";

?>
