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
  28/7/2018:
    - ajout possibilité de sortie json
    - ajout log
  25/7/2018:
    première version minimum
*/
require_once __DIR__.'/yd.inc.php';
require_once __DIR__.'/store.inc.php';
require_once __DIR__.'/ydclasses.inc.php';

use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Yaml\Exception\ParseException;

ini_set('memory_limit', '2048M');
ini_set('max_execution_time', 600);

//$verbose = false; // le log n'est pas réinitialisé et contient uniquement les erreurs successives
$verbose = true; // log réinitialisé à chaque appel et contient les paramètres d'appel et erreur

// URL de tests
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

  echo "<h2>Tests avec paramètre format=</h2><ul>\n";
  foreach ([
    '/skos'=> "document simple",
  ] as $uri => $title)
    echo "<li><a href='$_SERVER[SCRIPT_NAME]$uri?format=json'>$title : $uri</a>\n";
  echo "</ul>\n";

  echo "<pre>_GET="; print_r($_GET); echo "</pre>\n";
  echo "<pre>_SERVER = "; print_r($_SERVER);
  die("Fin des tests");
}

// $docid est-il un doc du store ?
function is_doc(string $docid): bool {
  $storepath = Store::storepath();
  $filename = __DIR__."/$storepath/$docid";
  return (is_file("$filename.yaml") || is_file("$filename.pser") || is_file("$filename.php"));
}

function error(int $code, string $docid, string $ypath='') {
  static $codeErrorLabels = [
    404 => 'Not Found',
    500 => 'Internal Server Error',
  ];
  $storeid = Store::id();
  if (!isset($codeErrorLabels[$code])) {
    header('Content-type: text/plain');
    echo "code d'erreur $code inconnu sur $store/$docid/$ypath\n";
    die();
  }
  header("HTTP/1.1 $code $codeErrorLabels[$code]");
  header('Content-type: text/plain');
  if ($code == 500)
    echo "Erreur: le document $docid du store $storeid a généré une erreur d'analyse Yaml\n";
  elseif ($ypath)
    echo "Erreur: le fragment $ypath du document $docid n'existe pas dans le store $storeid\n";
  else
    echo "Erreur: le document $docid n'existe pas dans $storeid\n";
  file_put_contents(
    'id.log.yaml',
    YamlDoc::syaml(['error'=> [
      'date'=> date(DateTime::ATOM),
      'code'=> $code,
      'codeErrorLabels'=> isset($codeErrorLabels[$code]) ? $codeErrorLabels[$code] : 'unknown',
      'store'=> $storeid,
      'docid'=> $docid,
      'ypath'=> $ypath,
      '_SERVER'=> $_SERVER,
    ]]),
    FILE_APPEND);
  die();
}

//echo "<pre>_SERVER = "; print_r($_SERVER);
$store = in_array($_SERVER['HTTP_HOST'], ['127.0.0.1','bdavid.alwaysdata.net']) ? 'docs' : 'pub';
if (isset($_GET['uri']))
  $uri = substr($_GET['uri'], strlen('http://id.georef.eu'));
else {
  $uri = substr($_SERVER['REQUEST_URI'], strlen($_SERVER['SCRIPT_NAME']));
  if ($_SERVER['QUERY_STRING'])
    $uri = substr($uri, 0, strlen($uri)-strlen($_SERVER['QUERY_STRING'])-1);
}
//echo "uri=$uri<br>\n";
//echo "<pre>_SERVER = "; print_r($_SERVER);
if ($verbose) {
  file_put_contents('id.log.yaml', YamlDoc::syaml([
    'date'=> date(DateTime::ATOM),
    'uri'=> $uri,
    '_SERVER'=> $_SERVER,
    '_GET'=> $_GET,
    '_POST'=> $_POST,
  ]));
  $t0 = microtime(true);
}

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
  if (!is_doc($docid)) {
    if (!is_doc($dirpath.'index')) {
      error(404, "$dirpath$docid");
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
  $doc = new_doc($docid);
}
catch (ParseException $exception) {
  error(500, $docid);
}

$fragment = $doc->extractByUri($docid, $ypath);
if (!$fragment) {
  if (!$index)
    error(404, $docid, $ypath);
  elseif (in_array($index['ypath'],['','/']))
    error(404, $index['docid']);
  else
    error(404, $index['docid'], $index['ypath']);
}
header('Access-Control-Allow-Origin: *');
if (isset($_GET['format']) && ($_GET['format']=='yaml')) {
  header('Content-type: text/plain');
  echo YamlDoc::syaml($fragment);
}
else {
  header('Content-type: application/json');
  echo json_encode($fragment, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
}
if ($verbose) {
  file_put_contents(
      'id.log.yaml',
      YamlDoc::syaml([
        'duration'=> microtime(true) - $t0,
        'nbFeatures'=> (is_array($fragment) && isset($fragment['nbFeatures'])) ? $fragment['nbFeatures'] : 'unknown',
      ]),
      FILE_APPEND
  );
}
die();
