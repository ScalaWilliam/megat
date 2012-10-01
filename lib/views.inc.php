<?php

require_once "domtemplate.php";

function template() {
  $template = new DOMTemplate(file_get_contents("lib/template.html"));
  $template->setValue('//title | //h1 | //link[@rel=\'alternate\' and @type=\'application/atom+xml\']/@title', TITLE);
  $template->setValue('//link[@rel=\'alternate\' and @type=\'application/atom+xml\']/@href | //header/p[2]/a/@href', config('feed'));
  return $template;
}
function view_xml_my($db) {
  $feed = simplexml_load_string("<feed xmlns=\"http://www.w3.org/2005/Atom\"></feed>");
  $sql = $db->prepare('SELECT * FROM "my" ORDER BY published DESC');
  $sql->execute(array());
  $feed = form_feed($feed, $sql);
  return view_xml_feed($feed);
}
function view_xml_all($db) {
  $feed = simplexml_load_string("<feed xmlns=\"http://www.w3.org/2005/Atom\"></feed>");
  $sql = $db->prepare('SELECT * FROM "items" ORDER BY published DESC');
  $sql->execute(array());
  $feed = form_feed($feed, $sql);
  return view_xml_feed($feed);
}
function view_xml_feed($feed) {
  $feed->title = TITLE;
  //header("Content-Type: text/plain");
  header("Content-Type: application/atom+xml");
  echo $feed->saveXML();
  return;
}
function view_item_raw($id, $db) {
  $q = $db->prepare('SELECT * FROM "items" WHERE id = ?');
  $q->execute(array($id));
  $item = $q->fetch();
  if ( !$item )
    return view_404();
  header("Content-type: application/atom+xml;type=entry");
  echo $item->atomXML;
  return;
}
function view_index($db) {
  $tpl = template();
  $query = $db->query('SELECT * FROM items ORDER BY published DESC;');
  $htmlitem = $tpl->repeat('//div[@id=\'item-list\']/ol/li');
  while ($item = $query->fetch() ) {
    $htmlitem->setValue('.', $item->message);
    $htmlitem->next();
  }
  echo $tpl->html();
}
function view_404() {
  $title = "404 Not Found";
  header("HTTP/1.1 {$title}");
  return view_err($title);
}
function view_403() {
  $title = "403 Not Authorized";
  header("HTTP/1.1 {$title}");
  return view_err($title);
}
function view_500($msg) {
  $title = '500 Server Error';
  header("HTTP/1.1 {$title}");
  return view_err($title, $msg);
}
function view_err($title, $message = null) {
    $tpl = new DOMTemplate('<html><head><title>Error</title></head><body><h1 style="text-align:center">404 Not Found</h1><hr/><p style="text-align:center"></p></body></html>');
  $tpl->setValue('//title | //h1', $title);
  if ( $message )
    $tpl->setValue('//p', $message);
  else
    $tpl->remove('//p | //hr');
  echo $tpl->html();
}
function view_400($message = null) {
  $title = '400 Bad Request';
  header("HTTP/1.1 {$title}");
  return view_err($title, $message);
}
function view_item($id, $db) {
  $tpl = template();
  $query = $db->prepare('SELECT * FROM items WHERE id = ?');
  $query->execute(array($id));
  $item = $query->fetch();
  if ( !$item )
    return view_404();
  $htmlitem = $tpl->repeat('//div[@id=\'item-single\']');
  $htmlitem->setValue('div', $item->messageHTML);
  $htmlitem->setValue('datetime | datetime/@datetime', $item->published);

    echo $tpl->html();
}
?>
