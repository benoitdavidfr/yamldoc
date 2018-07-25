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
*/
require_once __DIR__.'/yd.inc.php';
require_once __DIR__.'/ydclasses.inc.php';

//echo "<pre>_SERVER = "; print_r($_SERVER);
$uri = substr($_SERVER['REQUEST_URI'], strlen($_SERVER['SCRIPT_NAME']));
//echo "uri=$uri<br>\n";
$ids = explode('/', $uri);
$store = 'pub';

$dirpath = ''; // vide ou se termine par /
$id0 = array_shift($ids);
$id0 = array_shift($ids);
//echo "id0=$id0<br>\n";
while (is_dir(__DIR__."/$store/$dirpath$id0")) {
  $dirpath = "$dirpath$id0/";
  $id0 = array_shift($ids);
}
//echo "dirpath=$dirpath<br>\n";
$filename = __DIR__."/$store/$dirpath$id0";
if (!is_file("$filename.yaml") && !is_file("$filename.pser") && !is_file("$filename.php")) {
  header("HTTP/1.1 404 Not Found");
  header('Content-type: text/plain');
  echo "Erreur: le document $dirpath$id0 n'existe pas dans $store\n";
  die();
}
$ypath = '/'.implode('/', $ids);
$doc = new_yamlDoc($store, "$dirpath$id0");
header('Content-type: text/plain');
echo YamlDoc::syaml($doc->extractByUri("$dirpath$id0", $ypath));
die();
