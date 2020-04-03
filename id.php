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
  3/4/2020:
    transfert dans getDocidYpathFromPath() définie dans yd.inc.php de la logique de décomposition
    du chemin d'un fragment en [$docid, $ypath]
  1-2/3/2020:
    - première version de gestion de l'autentification
  4/1/2019:
    - chgt du site Alwaysdata eu.georef.id en Php sur yamldoc/id.php qui permet que les URI ne soient pas
      réécrites
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

//print_r($_GET);
//echo "user=",($_SERVER['PHP_AUTH_USER'] ?? "non défini"),
//     ", passwd=",$_SERVER['PHP_AUTH_PW'] ?? "non défini","\n";
if (isset($_GET['action'])) {
  // URL de tests
  if ($_GET['action']=='tests') {
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
  // Force le changement de login
  // Pour se déloguer -> cliquer sur annuler
  // Pour se loguer -> entrer login + mdp et cliquer sur ok puis sur annuler
  elseif ($_GET['action']=='login') {
    header('WWW-Authenticate: Basic realm="Yamldoc"');
    header('HTTP/1.0 401 Unauthorized');
    echo "user=",($_SERVER['PHP_AUTH_USER'] ?? "non défini"),
         ", passwd=",$_SERVER['PHP_AUTH_PW'] ?? "non défini","\n";
    die();
  }
  else {
    die("Action $_GET[action] non prévue");
  }
}

// $docid est-il un doc du store ?
function is_doc(string $docid): bool {
  $storepath = Store::storepath();
  $filename = __DIR__."/$storepath/$docid";
  return (is_file("$filename.yaml") || is_file("$filename.pser") || is_file("$filename.php")
    || is_file("$filename.pdf") || is_file("$filename.odt"));
}

// liste des utilisateurs autorisés [login=>mdp] - NON UTILISEE
function securisationParLoginMotDePasse(array $authusers): string {
  if (!isset($_SERVER['PHP_AUTH_USER'])) {
    header('WWW-Authenticate: Basic realm="My Realm"');
    header('HTTP/1.0 401 Unauthorized');
    die("Login/mdp obligatoire"); // absence d'authentification
  }
  elseif (!isset($authusers[$_SERVER['PHP_AUTH_USER']])
      || ($authusers[$_SERVER['PHP_AUTH_USER']]<>$_SERVER['PHP_AUTH_PW'])) {
    header('HTTP/1.0 403 Forbidden');
    die("$_SERVER[PHP_AUTH_USER] non autorisé"); // erreur d'authentification
  }
  return $_SERVER['PHP_AUTH_USER']; // authentification réussie, retourne l'utilisateur
}

function error(int $code, string $docid, string $ypath='') {
  static $codeErrorLabels = [
    400 => 'Bad Request',
    401 => 'Unauthorized',
    403 => 'Forbidden',
    404 => 'Not Found',
    500 => 'Internal Server Error',
  ];
  $storeid = Store::id();
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
  switch($code) {
    case 400:
      die("Erreur: fragment $ypath du document $docid du store $storeid a généré une erreur 'Bad Request'\n");
    case 401:
      die("Erreur: fragment $ypath du document $docid du store $storeid a généré une demande d'authentification\n");
    case 403:
      die("Erreur: fragment $ypath du document $docid du store $storeid a généré une erreur d'accès\n");
    case 404:
      if ($ypath)
        die("Erreur: le fragment $ypath du document $docid n'existe pas dans le store $storeid\n");
      else
        die("Erreur: le document $docid n'existe pas dans $storeid\n");
    case 500:
      die("Erreur: le document $docid du store $storeid a généré une erreur interne (500)\n");
    default:
      die("Erreur ligne ".__LINE__);
  }
  die("Erreur ligne ".__LINE__);
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

list ($docid, $ypath) = getDocidYpathFromPath($uri);
// Ici $docid est l'id du doc et ypath le chemin du sous-doc
//echo "docid=$docid ligne ",__LINE__,"<br>\n";
//echo "ypath=$ypath<br>\n";
//echo "index="; print_r($index);

try {
  $doc = new_doc($docid);
}
catch (ParseException $exception) {
  error(500, $docid);
}

if (!$doc->checkPassword() || !$doc->authorizedReader('')) { // si le doc est protégé par mdp ou liste de lecture
  if (!isset($_SERVER['PHP_AUTH_USER'])) { // Si aucun login effectué
    header('WWW-Authenticate: Basic realm="Yamldoc"');
    header('HTTP/1.0 401 Unauthorized');
    die("Authentification nécessaire pour document $docid"); // absence d'authentification
  }
  //echo "docid=$docid, user=$_SERVER[PHP_AUTH_USER], passwd=$_SERVER[PHP_AUTH_PW]\n";
  if (!$doc->checkPassword($_SERVER['PHP_AUTH_PW'])) {
    header('WWW-Authenticate: Basic realm="Yamldoc"');
    header('HTTP/1.0 401 Unauthorized');
    die("Mot de passe incorrect pour document $docid\n"); // erreur de mot de passe
  }
  else {
    if (!$doc->authorizedReader($_SERVER['PHP_AUTH_USER'])) {
      header('HTTP/1.0 403 Forbidden');
      die("Utilisateur $_SERVER[PHP_AUTH_USER] non autorisé pour ce document $docid"); // erreur d'authentification
    }
    $homePage = new_doc($_SERVER['PHP_AUTH_USER']);
    if (!$homePage) {
      header('HTTP/1.0 403 Forbidden');
      die("Utilisateur $_SERVER[PHP_AUTH_USER] non défini");
    }
    if (!$doc->authorizedReader($_SERVER['PHP_AUTH_USER'])) {
      header('HTTP/1.0 403 Forbidden');
      die("Utilisateur $_SERVER[PHP_AUTH_USER] non autorisé pour ce document $docid"); // erreur d'authentification
    }
  }
}

//echo "docid=$docid ligne ",__LINE__,"<br>\n";
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
