<?php
// $ sudo apt-get install php5-curl
error_reporting(E_ALL);
$args = $_SERVER['argv'];
if ( count($args) < 2 )
    die("php tests.inc.php <url>\n");

$url = $args[1];
if ( !$parts = parse_url($url) )
    die("Invalid URL\n");
if ( !in_array($parts['scheme'], array('http','https')) )
    die("Invalid scheme\n");
// <link rel="alternate" type="application/atom+xml" title="abc"/>
function curl($url=null, $raw = null) {
    static $curl;
    if ( !isset($curl) ) {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_USERPWD, "test:test");
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_DIGEST);
    };
    if ( isset($url) )
        curl_setopt($curl, CURLOPT_URL, $url);
    return $curl;
}

function test($function) {
    static $test_no, $lines, $url;
    if ( !isset($test_no) ) $test_no = 0;
    if ( !isset($lines) ) $lines = explode("\n", file_get_contents(__FILE__));
    if ( !isset($url) ) $url = $GLOBALS['url'];
    $test_no = $test_no + 1;
    
    $test_info = debug_backtrace();
    echo "Testing #{$test_no} (".(pathinfo($test_info[0]['file'])['basename'])."#{$test_info[0]['line']})... ";
    $message = $function($url);
    
    if ( $message === true ) {
        echo "Passed.\n";
        return true;
    } else {
        echo "Failed: {$message}\n";
        echo "Exiting.\n";
        exit;
        return;
    }
}

curl($url);

function get_index() {
    static $xml;
    if ( isset($xml) )
        return $xml;
    $data = curl_exec(curl());
    $xml = @simplexml_load_string($data);
    if ( !$xml ) return $data;
    return $xml;
}
function get_feedurl($xml=null) {
    static $link;
    if ( !isset($link) ) {
        $z = $xml->xpath("//link[@rel='alternate' and @type='application/atom+xml' and @title and @href]");
        if ( count($z) > 0 )
            $link = $z[0]['href'];
    }
    return $link;
}
function get_feed($feedurl=null) {
    static $feed;
    if ( isset($feed) )
        return $feed;
    $data = curl_exec(curl($feedurl));
    $feed = @simplexml_load_string($data);
    return $feed;
}

test(function() { return true; return "true"; });

test(function() {
    $xml = get_index();
    if ( !is_object($xml) )
        return "Failed to load data '{$data}' as XML";
    return true;
});

test(function() {
    if ( !get_feedurl(get_index()) )
        return "Failed to load feed URL.";
    return true;
});
test(function() {
    if ( !($feed = get_feed(get_feedurl())) )
        return "Failed to retrieve the feed XML";
    if ( empty($feed->id) )
        return "It doesn't seem to be a valid feed?";
    return true;
});


curl_close(curl());

?>
