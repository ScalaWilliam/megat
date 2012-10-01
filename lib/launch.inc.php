<?php
require_once "auth.inc.php";
require_once "actions.inc.php";
require_once "domtemplate.php";
require_once "auth.inc.php";
require_once "views.inc.php";





/** Authentications **/


function require_auth($u) {
  $auth = http_auth();
  return $auth($u, true);
}

function auth_reader($db) {
    $u = function($username) {
            if ( $username === USERNAME )
                return md5(USERNAME.':'.REALM.':'.PASSWORD);
         };
    return config('public') || require_auth($u);
}

function is_me($username) {
    return $username === USERNAME;
}

function auth_me($db) {
  $q = uauth_array(array(USERNAME=>md5(USERNAME.':'.REALM.':'.PASSWORD)));
  return require_auth($q);
}

function auth_in($db) {
  $q = $db->prepare("SELECT user AS username, a1 AS ha1 FROM subscriptions WHERE user = ?");
  return require_auth(uauth($q));
}

function auth_subscriber($db) {
  return auth_in($db);
}




/** Process user input **/

function get_vars() {
  $args = func_get_args();
  $src = array_shift($args);
  $func = function($v) use($src) {
    if ( isset($src[$v]) && !is_array($src[$v]) )
      return (string)$src[$v];
  };
  return array_map($func, $args);
}
function get_var($src, $v) {
    if ( isset($src[$v]) && !is_array($src[$v]) )
        return (string)$src[$v];
}

list($method, $slug, $ctype) = get_vars($_SERVER, 'REQUEST_METHOD',
					       'HTTP_SLUG', 'HTTP_CONTENT_TYPE');
list($coll, $id, $xml) = get_vars($_GET, 'collection', 'id', 'xml');

list($message) = get_vars($_POST, 'message');

$methods = array('GET', 'POST', 'DELETE', 'PUT');
array_walk( $methods,
            function($m) { $GLOBALS[$m] = $GLOBALS['method'] === $m; return true; });

if ( $POST )
  $data = file_get_contents('php://input');


/** Act on user input **/

/** Subscriptions: there's the API, and then there's the web interface. **/

/* First model: we allow subscriptions to anyone interested. */
/* Second model: we allow subscription requests and then approve them if we wish to. */

/**
 * C = Client, S = Server:
 * C POSTs to S its URL --> S
 * with credentials for
 * S to notify C.
 *                      <-- S adds a subscriber to its list, and with some credentials for identifying oneself, as well as identifying the client.
 *                          Then it sends back these credentials to the client, once approval is made.
 * 
 * Later, once a post is made,
 *                    C <-- S sends the entry to C, with credentials 
 * 
 * If the client wants to delete itself from notifications,
 * C queries S with     --> S
 *   credentials via DELETE
 * ... umm, maybe there's an easier way to do this unsubscription ;-)
 **/

/*

// retrieve an entry from a subscription server
if ( $POST && get_var($_GET, 'notify') && isset($data) && $in_user = auth_in($db) )
    die(action_entry_received($in_user, $data, $db));

// retrieve a subscription request from a client
if ( $POST && get_var($_GET, 'subscribe') && $clienturl = get_var($_POST, 'url') )
    die(action_request_subscribe($clienturl, $db));

// retrieve an unsubscription request from a client
if ( ($POST || $DELETE) && get_var($_GET, 'unsubscribe') && $in_user = auth_in($db) ) 
    die(action_request_unsubscribe($in_user, $db));

// retrieve an approval request from a server, with username & password.
if ( $POST && $approved = get_var($_GET, 'approved') &&
    $username = get_var($_POST, 'username') &&
    $password = get_var($_POST, 'password') &&
    $serverurl = get_var($_POST['url']) )
        $approved === 'approved'
            ? die(action_receive_approval($username, $password, $serverurl, $db))
            : die(action_receive_denial($serverurl, $db));

// If we want to approve/reject somebody
if ( $POST && $approved = get_var($_POST, 'approve') &&
    $clienturl = get_var($_POST, 'url') && auth_me($db) )
    die(action_approve($approved === 'approved', $clienturl, $db));

// If we want to subscribe to somebody
// We can make it subscribe to a standard Atom feed, as well.
if ( $POST && $serverurl = get_var($_POST['url']) && auth_me($db) )
    die(action_subscribe($serverurl, $db));

// If we want to unsubscribe from somebody
if ( ($POST || $DELETE) && get_var($_GET, 'unsubscribe') &&
    $serverurl = get_var($_POST['url']) && auth_me($db) )
    die(action_unsubscribe($serverurl, $db));
*/
/** BASIC MODEL: get and post MY, retrieve MY and ALL, index; DELETE **/

// View a single item
if ( $GET && isset($id) && $reader = auth_reader($db) ) {
    $all = is_me($reader) || config('in_public');
    $xml ? die(view_singleitem_xml($all, $id, $db))
         : die(view_singleitem_html($all, $id, $db));
}

// Delete a single item
if ( ($DELETE || ($POST && get_var($_GET, 'delete'))) && isset($id) && auth_me($db) ) {
    die(action_delete_my($id, $db));
}


// View list of all of my entries
if ( $GET && $coll === 'my' && auth_reader($db) ) {
    $xml ? die(view_index_xml(false, $db)) : die(view_index_html(false, $db));
}
// VIew list of all of {all = my + in} entries
if ( $GET && $coll === 'all' && $reader = auth_reader($db) )
    if ( is_me($reader) || config('in_public') )
        $xml ? die(view_index_xml(true, $db)) : die(view_index_html(true, $db));
    else
        view_403();
// View index page
if ( $GET && $reader = auth_reader($db) ) {
    $all = is_me($reader) || config('in_public');
    $xml ? die(view_index_xml($all, $db)) : die(view_index_html($all, $db));
}
// page for making a new message
if ( $GET && get_var($_GET, 'new') && auth_me($db) ) {
    die(view_page_postnew($db));
}
// making a new message
if ( $POST && isset($message) && auth_me($db) ) {
    die(action_post_new_message($message, $db));
}
// receiving a post request with entry data embedded.
if ( $POST && isset($data) && auth_me($db) ) {
    die(action_post_new($data, $db));
}


exit;
?>
