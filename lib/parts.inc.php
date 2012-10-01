<?php
function ctype_atom($entry = false) {
    header("Content-Type: application/atom+xml".($entry ? ";type=entry" : "").";charset=\"utf-8\"");
}
function ctype_entry() {
    return ctype_atom(true);
}
function ctype_feed() {
    return ctype_atom(false);
}
function array_each($array, $callback) {
    $result = true;
    foreach($array as $key => $value) {
        $result = $result && $callback($value, $key);
    }
    return $result;
}

function index_get($all, $db) {
    $sql = "SELECT * FROM ".($all ? '"all"' : '"my"')." ORDER BY published DESC";
    $sql = $db->prepare($sql);
    $sql->execute(array());
    return $sql;
}
function view_index_html($all, $db) {
    $sql = index_get($all, $db);
    return view_index_render_html($sql);
}
function view_index_xml($all, $db) {
    $sql = index_get($all, $db);
    return view_index_render_xml($sql);
}

function view_index_render_html($stmt) {
    $tpl = template();
    $htmlitem = $tpl->repeat("//div[@id='item-list']/ol/li");
    while ( $item = $stmt->fetch() ) {
        $htmlitem->setValue('.', $item->message);
        $htmlitem->next();
    }
    echo $tpl->html();
}
function view_index_render_xml($stmt) {
    $dom = new DOMDocument;
    $dom->loadXML('<'.'?xml version="1.0" encoding="utf-8"?><feed xmlns="'.XMLNS.'"></feed>');
    
    while($item = $stmt->fetch()) {
        $xml = @simplexml_load_string($item->atomXML);
        if (!$xml) continue;
        $ndom = dom_import_simplexml($xml);
        $node = $dom->importNode($ndom, true);
        $dom->documentElement->appendChild($node);
    }
    //header("Content-type: application/atom+xml");
    echo $dom->saveXML();
}

function atomxml_load_string($data) {
  $ret = simplexml_load_string($data);
  if ( !($ret instanceof SimpleXMLElement) )
    return;
  return $ret;
}

function atom_entry($message = null) {
    $data = '<entry xmlns="'.XMLNS.'"><published></published><author><name></name></author><title></title><summary></summary><id></id><!--<source><id></id><author><name></name></author><title></title></source>--></entry>';
    $data = '<entry xmlns="'.XMLNS.'"><published></published><author><name></name></author><title></title><summary></summary><id></id></entry>';
    $xml = @simplexml_load_string($data) or die(view_400("Could not initialise message."));
    if ( $message ) {
        $xml->title = $message;
        $xml->summary = $message;
    }
    return $xml;
}

function action_post_new_message($message, $db) {
    $entry = atom_entry($message);
    $entry->id = 'tag:temporary';
    return action_post_new($entry->saveXML(), $db);
}


function generate_tag($db) {
  $make_id = function() {
    static $num; $num = $num or 1;
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

function my_message_insert($xml, $db) {
    $stmt = $db->prepare('INSERT INTO "my" (id, message, published, atomXML) VALUES (?, ?, ?, ?);');
    $stmt->execute(array($xml->id, $xml->title, $xml->published, $xml->saveXML()));
    return $stmt->rowCount() === 1;
}
function action_post_new($data, $db) {
    $xml = @atomxml_load_string($data);
    if ( !$xml )
        return view_400("Could not process data as Atom entry.");
    $db->beginTransaction();
    $xml->id = generate_tag($db);
    $xml->published = date('c');
    $xml->author->name = AUTHOR_NAME;
    if ( !my_message_insert($xml, $db) ) {
        $db->rollback();
        return view_400("Failed to insert message for some reason.");
    }
    $db->commit();
    header("Status: 201 Created");
    header("Location: ?id=".$xml->id);
    header("Content-Location: ?id=".$xml->id);
    ctype_entry();
    echo $xml->saveXML();
    //echo '<a href="?id='.$xml->id.'">Go here</a>';
}
function action_delete_my($id, $db) {
    if ( !singleitem_delete_my($id, $db) )
        return view_400("Failed to delete.");
    return "Deleted successfully";
}
function singleitem_delete_my($id, $db) {
    $db->beginTransaction();
    $sql = "DELETE FROM \"my\" WHERE id = ?";
    $sql = $db->prepare($sql);
    $sql->execute(array($id));
    return $sql->rowCount() === 1 && $db->commit();
}
function singleitem_get($all, $id, $db) {
    $sql = "SELECT * FROM ".($all ? '"all"' : '"my"')." WHERE id = ?";
    $sql = $db->prepare($sql);
    $sql->execute(array($id));
    return $sql->fetch();
}
function view_singleitem_xml($all, $id, $db) {
    $item = singleitem_get($all, $id, $db);
    if ( !$item )
        return view_404();
    ctype_entry();
    echo $item->atomXML;
}
function view_singleitem_html($all, $id, $db) {
    $item = singleitem_get($all, $id, $db);
    if ( !$item )
        return view_404();
    echo "<pre>";
    echo htmlspecialchars($item->atomXML);
    echo "</pre>";
}
?>
