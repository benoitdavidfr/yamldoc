<?php
/*PhpDoc:
name: id.php
title: id.php - resolveur d'URI de YamlDoc
doc: |
  S'utilise soit par appel direct de l'URI,
  soit par appel du script avec l'URI en paramètre uri={URI}
  L'appel à l'URI utilise la définition sur Alwaysdata d'un site id.georef.eu comme site Php pointant sur
    ~/prod/georef/yamldoc/id.php/

  Exemples:
    http://id.georef.eu/iso639/concepts/fre
    http://localhost/yamldoc/id.php/iso639/concepts/fre
    http://localhost/yamldoc/id.php?uri=http://id.georef.eu/iso639/concepts/fre
    http://localhost/yamldoc/id.php/view/igngp

  La difficulté est de traiter tous les cas, notamment:
    - document index d'un répertoire
    - document existant avec un répertoire du même nom
    - document Yaml incorrect
journal: |
  4/1/2019:
    - chgt du site Alwaysdata eu.georef.id en Php sur yamldoc/id.php qui permet que les URI ne soient pas réécrites
  15/10/2018:
    - ajout paramètres CLI
  25/8/2018:
    - ajout possibilité d'appel CLI
    - correction bug sur la gestion du store
  28/7/2018:
    - ajout possibilité de sortie json
    - ajout log
  25/7/2018:
    première version minimum
includes: [ store.inc.php, yd.inc.php, ydclasses/inc.php ]
*/
require_once __DIR__.'/yd.inc.php';
require_once __DIR__.'/store.inc.php';
require_once __DIR__.'/ydclasses/inc.php';

use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Yaml\Exception\ParseException;

ini_set('memory_limit', '2048M');
if (php_sapi_name()<>'cli')
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
  return (is_file("$filename.yaml") || is_file("$filename.pser") || is_file("$filename.php")
    || is_file("$filename.pdf") || is_file("$filename.odt"));
}

function error(int $code, string $docid, string $ypath='') {
  static $codeErrorLabels = [
    404 => 'Not Found',
    500 => 'Internal Server Error',
  ];
  $storeid = Store::id();
  if (!isset($codeErrorLabels[$code])) {
    if (php_sapi_name() <> 'cli')
      header('Content-type: text/plain');
    echo "code d'erreur $code inconnu sur $store/$docid/$ypath\n";
    die();
  }
  if (php_sapi_name()<>'cli') {
    header("HTTP/1.1 $code $codeErrorLabels[$code]");
    header('Content-type: text/plain');
  }
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

if (php_sapi_name()=='cli') {
  //echo "argc=$argc\n";
  //print_r($argv);
  if ($argc < 3) {
    echo "usage: php id.php {store} {uri} [format=yaml]\n";
    die();
  }
  Store::setStoreid($argv[1]);
  $uri = $argv[2];
  if ($argc > 3) {
    for($a=3; $a<$argc; $a++) {
      //echo "$a> ",$argv[$a],"\n";
      $pos = strpos($argv[$a], '=');
      if ($pos === false)
        die("Erreur: Argument $argv[$a] non compris\n");
      //echo "pos=$pos\n";
      $key = substr($argv[$a], 0, $pos);
      $value = substr($argv[$a], $pos+1);
      //echo "key='$key', value='$value'\n";
      $_GET[$key] = $value;
    }
  }
  //die("Fin ligne ".__LINE__."\n");
}
else {
  //echo "<pre>_SERVER = "; print_r($_SERVER);
  //$store = in_array($_SERVER['HTTP_HOST'], ['127.0.0.1','bdavid.alwaysdata.net']) ? 'docs' : 'pub';
  if (isset($_GET['uri']))
    $uri = substr($_GET['uri'], strlen('http://id.georef.eu'));
  else {
    $uri = substr($_SERVER['REQUEST_URI'], strlen($_SERVER['SCRIPT_NAME']));
    if ($_SERVER['QUERY_STRING'])
      $uri = substr($uri, 0, strlen($uri)-strlen($_SERVER['QUERY_STRING'])-1);
  }
  //echo "<pre>_SERVER = "; print_r($_SERVER);
}
//echo "uri=$uri<br>\n";
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
  //echo "ids="; print_r($ids);

  $dirpath = ''; // vide ou se termine par /
  $id0 = array_shift($ids); // uri commencant par /, je saute le premier élément
  $id0 = array_shift($ids);
  //echo "id0=$id0<br>\n";
  $storeRoot = __DIR__.'/'.Store::storepath();
  //echo "storeRoot=$storeRoot<br>\n";
  while ($id0 && !is_doc("$dirpath$id0") && is_dir("$storeRoot/$dirpath$id0")) {
    $dirpath = "$dirpath$id0/";
    $id0 = array_shift($ids);
  }
  if (!$id0)
    $id0 = 'index';
  //echo "dirpath=$dirpath<br>\n";
  $docid = "$dirpath$id0";
  //echo "ids="; print_r($ids);
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

// Ici $docid est l'id du doc et ypath le chemin du sous-doc
//echo "docid=$docid<br>\n";
//echo "ypath=$ypath<br>\n";
try {
  $doc = new_doc($docid);
}
catch (ParseException $exception) {
  error(500, $docid);
}

$fragment = $doc->extractByUri($ypath);
if (!$fragment) {
  if (!$index)
    error(404, $docid, $ypath);
  elseif (in_array($index['ypath'],['','/']))
    error(404, $index['docid']);
  else
    error(404, $index['docid'], $index['ypath']);
}
if (php_sapi_name() <> 'cli')
  header('Access-Control-Allow-Origin: *');

$format = $_GET['format'] ?? '';
if ($format == 'yaml') {
  if (php_sapi_name() <> 'cli')
    // il n'y a pas de type MIME enregistré pour Yaml
    // de plus l'utilisation de text/plain permet de l'afficher dans Firefox
    header('Content-type: text/plain');
  echo YamlDoc::syaml($fragment);
}
elseif ($format == 'ttl') {
  if (php_sapi_name() <> 'cli')
    //header('Content-type: text/turtle');
    header('Content-type: text/plain');
  $doc->printTtl($ypath);
}
else { // par défaut en json
  if (php_sapi_name() <> 'cli')
    header('Content-type: application/json');
  echo json_encode($fragment, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE),"\n";
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
