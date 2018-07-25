<?php
/*PhpDoc:
name: id.php
title: id.php - resolveur d'URI de YamlDoc
doc: |
  S'utilise soit par appel direct de l'URI,
  soit par appel du script avec l'URI en paramètre uri={URI}

  La difficulté est de traiter tous les cas, notamment:
    - document index d'un répertoire
    - document existant avec un répertoire du même nom
    - document Yaml incorrect
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

use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Yaml\Exception\ParseException;

if (isset($_GET['action']) && ($_GET['action']=='tests')) {
  echo "<h2>Cas de tests directs</h2><ul>\n";
  foreach ([
    '/'=> "répertoire racine",
    '/contents'=> "fragment du répertoire racine",
    '/iso'=> "document simple",
    '/iso/language'=> "document simple, fragment simple",
    '/iso/xxx'=> "document simple, fragment inexistant",
    '/xxx'=> "document inexistant",
    '/iso639'=> "document simple de type requete",
    '/iso639/concepts/fre'=> "document simple requete, fragment simple",
    '/yamldoc'=> "document index d'un répertoire",
    '/yamldoc/title'=> "fragment d'un document index d'un répertoire",
    '/yamldoc/xxx'=> "fragment inexistant d'un document index d'un répertoire",
    '/topovoc'=> "document ayant le même nom qu'un répertoire",
    '/topovoc/schemes'=> "fragment d'un document ayant le même nom qu'un répertoire",
    '/geohisto'=> "doc index",
    '/geohisto/regions'=> "doc dans répertoire",
    '/geohisto/regions/data/32'=> "entrée dans un YamlData",
    '/badyaml'=> "document Yaml incorrect",
  ] as $uri=> $title)
    echo "<li><a href='$_SERVER[SCRIPT_NAME]$uri'>$title : $uri</a>\n";
  echo "</ul>\n";
  
  echo "<h2>Tests avec paramètre uri=</h2><ul>\n";
  foreach ([
    'http://id.georef.eu/iso639/concepts/fre'=> "document simple requete, fragment simple",
  ] as $uri => $title)
    echo "<li><a href='?uri=",urlencode($uri),"'>$title : $uri</a>\n";
  echo "</ul>\n";
  echo "<pre>_GET="; print_r($_GET); echo "</pre>\n";
  echo "<pre>_SERVER = "; print_r($_SERVER);
  die("Fin des tests");
}

// $docid est-il un doc du store ?
function is_doc(string $store, string $docid): bool {
  $filename = __DIR__."/$store/$docid";
  return (is_file("$filename.yaml") || is_file("$filename.pser") || is_file("$filename.php"));
}

function notFound(string $store, string $docid, string $ypath='') {
  header('HTTP/1.1 404 Not Found');
  header('Content-type: text/plain');
  if ($ypath)
    echo "Erreur: le fragment $ypath du document $docid n'existe pas dans le store $store\n";
  else
    echo "Erreur: le document $docid n'existe pas dans $store\n";
  die();
}

$store = in_array($_SERVER['HTTP_HOST'], ['127.0.0.1','bdavid.alwaysdata.net']) ? 'docs' : 'pub';
if ($_SERVER['QUERY_STRING']=='')
  $uri = substr($_SERVER['REQUEST_URI'], strlen($_SERVER['SCRIPT_NAME']));
elseif (isset($_GET['uri']))
  $uri = substr($_GET['uri'], strlen('http://id.georef.eu'));
//echo "uri=$uri<br>\n";
//echo "<pre>_SERVER = "; print_r($_SERVER);
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
  //echo "dirpath=$dirpath<br>\n";
  $docid = "$dirpath$id0";
  //print_r($ids);
  $ypath = '/'.implode('/', $ids);
  //echo "docid avant test=$docid<br>\n";
  $index = [];
  if (!is_doc($store, $docid)) {
    if (!is_doc($store, $dirpath.'index')) {
      notFound($store, "$dirpath$docid");
    }
    else {
      $index = ['docid'=>$docid, 'ypath'=>$ypath]; // mémorisation
      $docid = $dirpath.'index';
      $ypath = '/'.$id0.($ids? $ypath : '');
      //echo "après test: docid=$docid, ypath=$ypath<br>\n";
    }
  }
}
//echo "docid=$docid<br>\n";
//echo "ypath=$ypath<br>\n";
try {
  $doc = new_yamlDoc($store, $docid);
}
catch (ParseException $exception) {
  header('HTTP/1.1 500 Internal Server Error');
  header('Content-type: text/plain');
  echo "Erreur: le document $docid du store $store a généré une erreur d'analyse Yaml\n";
  die();
}

$fragment = $doc->extractByUri($docid, $ypath);
if (!$fragment) {
  if (!$index)
    notFound($store, $docid, $ypath);
  elseif (in_array($index['ypath'],['','/']))
    notFound($store, $index['docid']);
  else
    notFound($store, $index['docid'], $index['ypath']);
}
header('Content-type: text/plain');
echo YamlDoc::syaml($fragment);
die();
