<?php

function _atomxml_load_string($data) {
  $ret = @simplexml_load_string($data);
  if ( !($ret instanceof SimpleXMLElement) )
    throw new InvalidAtomException($data);
  return $ret;
}

function form_feed($f, $sql) {
    $ff = clone $f;
    $ff->addChild('title', TITLE);
    $ff->id = TAG.",main,".TAG_POSTFIX;
  $domfeed = dom_import_simplexml($ff);
  $dom = new DOMDocument;
  $feednode = $dom->importNode($domfeed, true);
  $dom->appendChild($feednode);
  while ( $row = $sql->fetch() ) {
    $nd = new DOMDocument;
    $nd->loadXML($row->atomXML);
    
    $entrynode = $dom->importNode($nd->documentElement, true);
    $dom->documentElement->appendChild($entrynode);
  }
  return simplexml_import_dom($dom);
}

function action_make_delete($id, $db) {
  $sql = $db->prepare("DELETE FROM my WHERE id = ?");
  $sql->execute(array($id));
  if ( $sql->rowCount() == 1 )
    return true;
  return view_404();
}
function action_make_post($slug, $data, $db, $_201=true) {
  $message = post_message($slug, $data, $db);
  if ( !$message )
    return;
  if ( $_201 )
    header("HTTP/1.1 201 Created");
  header("Location: ?id=".$message->id);
  //  header("Location: ".$message->link->href);
  echo $message->saveXML();
  return;
}
function make_tag($xml, $db) {
  $make_id = function() {
    static $num;
    $num = $num or 1;
    return TAG.",".date("Y-m-d").":".TAG_POSTFIX."".(string)(++$num);
  };
  $check_id = $db->prepare("SELECT id FROM items WHERE id = ?");
  // We find the first available ID 
  $id = $make_id();
  $check_id->execute(array($id));
  while ( $check_id->fetch() ) {
    $id = $make_id();
    $check_id->execute(array($id));
  };
  return $id;
}

function post_message($slug, $data, $db) {
  // verify if good atom.
  try {
    $dat = atomxml_load_string($data);
  } catch (InvalidAtomException $e) {
    return view_400("Invalid Atom data.");
  } catch (Exception $e) {
    return view_500("Error with '".get_class($e)."'");
  };
  $db->beginTransaction();
  $dat->id = make_tag($dat, $db);
  $dat = load_my_message($dat, $dat->content, AUTHOR_NAME);
  $sql = $db->prepare("INSERT INTO \"my\" (id, message, messageHTML, published, atomXML) VALUES (?, ?, ?, ?, ?)");
  $sql->execute(array($dat->id, $dat->content, $dat->content, $dat->published, $dat->saveXML()));
  $db->commit();
  if ($sql->rowCount() === 1) 
    return $dat;
  throw new Exception("Failed to add.");
}
function load_my_message($x, $message, $author = null) {
  $msg = load_message($x, $message, $author);
  $source = $msg->source or $msg->addChild('source');
  $source->title = TITLE;
  $source->author = '';
  $source->author->name = AUTHOR_NAME;
  return $msg;
}
function load_message($x, $message, $author = null) {
  $xml = clone $x;
  $xml->title = $message;
  $xml->content = $message;
  $a = $xml->author or $xml->addChild('author');
  $a->name = (string)$author;
  $xml->published = $xml->updated = date('c');
  return $xml;
}
function action_post_message($message, $db) {
  $atomXML = '<entry xmlns="'.XMLNS.'"></entry>';
  $sx = @simplexml_load_string($atomXML);
  if ( !($sx instanceof SimpleXMLElement)  )
    return view_400("Failed to parse XML feed");
  $sx = load_my_message(simplexml_load_string($atomXML), $message, AUTHOR_NAME);
  $sx->id = 'tag:temporary';
  return action_make_post(null, $sx->saveXML(), $db, false);
}

?>
