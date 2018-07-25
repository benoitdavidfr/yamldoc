<?php
/*PhpDoc:
name: id.php
title: id.php - resolveur d'URI de YamlDoc
doc: |
  Exemples:
    http://id.georef.eu/iso639/concepts/fre
    -> http://georef.eu/yamldoc/id.php/iso639/concepts/fre
  Un site id.georef.eu doit être défini avec redirection vers http://georef.eu/yamldoc/id.php/
journal: |
  25/7/2018:
    première version minimum
test
*/
require_once __DIR__.'/yd.inc.php';
require_once __DIR__.'/ydclasses.inc.php';

// $docid est-il un doc du store ?
function is_doc(string $store, string $docid): bool {
  $filename = __DIR__."/$store/$docid";
  return (is_file("$filename.yaml") || is_file("$filename.pser") || is_file("$filename.php"));
}

//echo "<pre>_SERVER = "; print_r($_SERVER);
$store = 'pub';
$uri = substr($_SERVER['REQUEST_URI'], strlen($_SERVER['SCRIPT_NAME']));
//echo "uri=$uri<br>\n";
if (in_array($uri,['','/'])) {
  $docid = 'index';
  $ypath = '';
}
else {
  $ids = explode('/', $uri);

  $dirpath = ''; // vide ou se termine par /
  $id0 = array_shift($ids);
  $id0 = array_shift($ids);
  //echo "id0=$id0<br>\n";
  while ($id0 && !is_doc($store, "$dirpath$id0") && is_dir(__DIR__."/$store/$dirpath$id0")) {
    $dirpath = "$dirpath$id0/";
    $id0 = array_shift($ids);
  }
  if (!$id0)
    $id0 = 'index';
  echo "dirpath=$dirpath<br>\n";
  $docid = "$dirpath$id0";
  print_r($ids);
  $ypath = '/'.implode('/', $ids);
  echo "docid=$docid<br>\n";
  if (!is_doc($store, $docid)) {
    if (is_doc($store, $dirpath.'index')) {
      $docid = $dirpath.'index';
      $ypath = '/'.$id0.($ids? $ypath : '');
    }
    else {
      header("HTTP/1.1 404 Not Found");
      header('Content-type: text/plain');
      echo "Erreur: le document $dirpath$id0 n'existe pas dans $store\n";
      die();
    }
  }
}
echo "docid=$docid<br>\n";
echo "ypath=$ypath<br>\n";
$doc = new_yamlDoc($store, $docid);
header('Content-type: text/plain');
echo YamlDoc::syaml($doc->extractByUri($docid, $ypath));
die();
