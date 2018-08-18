<?php
/*PhpDoc:
name: wfsserver.inc.php
title: wfsserver.inc.php - document correspondant à un serveur WFS
functions:
doc: <a href='/yamldoc/?action=version&name=wfsserver.inc.php'>doc intégrée en Php</a>
*/
{
$phpDocs['wfsserver.inc.php'] = <<<'EOT'
name: wfsserver.inc.php
title: wfsserver.inc.php - document correspondant à un serveur WFS
doc: |
  La classe WfsServer expose différentes méthodes utilisant un serveur WFS.
  
  Outre les champs de métadonnées, le document doit définir les champs suivants:
    - urlWfs: fournissant l'URL du serveur à compléter avec les paramètres,
  Il peut aussi définir les champs suivants:
    - referer: définissant le referer à transmettre à chaque appel du serveur.

  Liste des points d'entrée de l'API:
    - /{document} : description du serveur
    - /{document}/getCapabilities : lecture des capacités du serveur et renvoi en XML, rafraichit le cache
    - /{document}/query?{params} : envoi d'une requête, les champs SERVICE et VERSION sont prédéfinis, retour XML
    - /{document}/t : liste en JSON les couches
    - /{document}/t/{typeName} : description de la couche en JSON
    - /{document}/t/{typeName}/numberMatched?bbox={bbox}&where={where} : renvoi du nbre d'objets
      correspondant à la requête définie par bbox et where,
      where est encodé en UTF-8
    - /{document}/t/{typeName}/getFeature?bbox={bbox}&where={where} : affiche en GeoJSON les objets
      correspondant à la requête définie par bbox et where, limité à 1000 objets
    - /{document}/t/{typeName}/getAllFeatures?bbox={bbox}&where={where} : affiche en GeoJSON les objets
      correspondant à la requête définie par bbox et where, utilise la pagination si plus de 1000 objets
    
  Le document http://localhost/yamldoc/?doc=geodata/igngpwfs permet de tester cette classe.
  
journal: |
  15/8/2018:
    - création
EOT;
}

require_once __DIR__.'/yd.inc.php';
require_once __DIR__.'/store.inc.php';
require_once __DIR__.'/ydclasses.inc.php';

use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Yaml\Exception\ParseException;

// classe simplifiant l'envoi de requêtes WFS
class WfsServer extends YamlDoc {
  static $log = __DIR__.'/wfs.log.yaml'; // nom du fichier de log ou false pour pas de log
  protected $_c; // contient les champs
  
  // crée un nouveau doc, $yaml est le contenu Yaml externe issu de l'analyseur Yaml
  // $yaml est généralement un array mais peut aussi être du texte
  function __construct(&$yaml) {
    $this->_c = [];
    foreach ($yaml as $prop => $value) {
      $this->_c[$prop] = $value;
    }
  }
  
  // lit les champs
  function __get(string $name) { return isset($this->_c[$name]) ? $this->_c[$name] : null; }

  // affiche le sous-élément de l'élément défini par $ypath
  function show(string $docid, string $ypath): void {
    echo "WfsServer::show($docid, $ypath)<br>\n";
    if (!$ypath || ($ypath=='/'))
      showDoc($docid, $this->_c);
    else
      showDoc($docid, $this->extract($ypath));
    //echo "<pre>"; print_r($this->_c); echo "</pre>\n";
  }
  
  // décapsule l'objet et retourne son contenu sous la forme d'un array
  function asArray() { return $this->_c; }

  // extrait le fragment du document défini par $ypath
  function extract(string $ypath) { return YamlDoc::sextract($this->_c, $ypath); }
  
  // retourne bbox [lngMin, latMin, lngMax, latMax] à partir d'un bbox sous forme de chaine
  static function decodeBbox(string $bboxstr): array {
    if (!$bboxstr)
      return [];
    $bbox = explode(',', $bboxstr);
    if ((count($bbox)<>4) || !is_numeric($bbox[0]) || !is_numeric($bbox[1]) || !is_numeric($bbox[2]) || !is_numeric($bbox[3]))
      throw new Exception("Erreur dans WfsServer::decodeBbox() : bbox '$bboxstr' incorrect");
    return $bbox;
  }
  
  // retourne un polygon WKT LatLng à partir d'un bbox [lngMin, latMin, lngMax, latMax]
  static function bboxWktLatLng(array $bbox) {
    if (!$bbox)
      return '';
    return "POLYGON(($bbox[1] $bbox[0],$bbox[1] $bbox[2],$bbox[3] $bbox[2],$bbox[3] $bbox[0],$bbox[1] $bbox[0]))";
  }
  
  // retourne un polygon WKT LngLat à partir d'un bbox [lngMin, latMin, lngMax, latMax]
  static function bboxWktLngLat(array $bbox) {
    if (!$bbox)
      return '';
    return "POLYGON(($bbox[0] $bbox[1],$bbox[2] $bbox[1],$bbox[2] $bbox[3],$bbox[0] $bbox[3],$bbox[0] $bbox[1]))";
  }
  
  // extrait le fragment défini par $ypath, utilisé pour générer un retour à partir d'un URI
  function extractByUri(string $docuri, string $ypath) {
    //echo "WfsLayers::extractByUri($docuri, $ypath)<br>\n";
    if (!$ypath || ($ypath=='/'))
      return $this->_c;
    elseif ($ypath == '/query') {
      $params = isset($_GET) ? $_GET : (isset($_POST) ? $_POST : []);
      if (isset($params['OUTPUTFORMAT']) && ($params['OUTPUTFORMAT']=='application/json'))
        header('Content-type: application/json');
      else
        header('Content-type: application/xml');
      echo $this->query($params);
      die();
    }
    elseif ($ypath == '/getCapabilities') {
      $filepath = __DIR__.'/'.Store::id().'/'.$docuri.'.xml';
      $result = $this->query(['request'=> 'GetCapabilities']);
      header('Content-type: application/xml');
      echo $result;
      file_put_contents($filepath, $result);
      die();
    }
    elseif ($ypath == '/t') {
      $filepath = __DIR__.'/'.Store::id().'/'.$docuri.'.xml';
      if (file_exists($filepath))
        $cap = file_get_contents($filepath);
      else
        $cap = $this->query(['request'=> 'GetCapabilities']);
      $cap = new SimpleXMLElement($cap);
      $typeNames = [];
      foreach ($cap->FeatureTypeList->FeatureType as $FeatureType) {
        $typeNames[(string)$FeatureType->Name] = [
          'title'=> (string)$FeatureType->Title,
          'abstract'=> (string)$FeatureType->Abstract,
        ];
      }
      return $typeNames;
    }
    // accès à la layer /t/{typeName}
    // effectue la requête DescribeFeatureType et retourne le résultat comme texte JSON
    elseif (preg_match('!^/t/([^/]+)$!', $ypath, $matches)) {
      $typeName = $matches[1];
      //echo "accès à la layer $typeName\n";
      header('Content-type: application/json');
      echo $this->query([
        'REQUEST'=> 'DescribeFeatureType',
        'TYPENAME'=> $typeName,
        'OUTPUTFORMAT'=> 'application/json',
      ]);
      die();
    }
    // accès à /t/{typeName}/numberMatched
    elseif (preg_match('!^/t/([^/]+)/numberMatched$!', $ypath, $matches)) {
      $typeName = $matches[1];
      header('Content-type: application/json');
      $bbox = isset($_GET['bbox']) ? $_GET['bbox'] : (isset($_POST['bbox']) ? $_POST['bbox'] : '');
      $bbox = self::decodeBbox($bbox);
      $where = isset($_GET['where']) ? $_GET['where'] : (isset($_POST['where']) ? $_POST['where'] : '');
      echo '{"numberMatched": ', $this->getNumberMatched($typeName, $bbox, $where), "}\n";
      die();
    }
    elseif (preg_match('!^/t/([^/]+)/getFeature$!', $ypath, $matches)) {
      $typeName = $matches[1];
      header('Content-type: application/json');
      $bbox = isset($_GET['bbox']) ? $_GET['bbox'] : (isset($_POST['bbox']) ? $_POST['bbox'] : '');
      $bbox = self::decodeBbox($bbox);
      $where = isset($_GET['where']) ? $_GET['where'] : (isset($_POST['where']) ? $_POST['where'] : '');
      //echo "where=$where\n";
      echo $this->getFeature($typeName, $bbox, $where);
      die();
    }
    elseif (preg_match('!^/t/([^/]+)/getAllFeatures$!', $ypath, $matches)) {
      $typeName = $matches[1];
      header('Content-type: application/json');
      $bbox = isset($_GET['bbox']) ? $_GET['bbox'] : (isset($_POST['bbox']) ? $_POST['bbox'] : '');
      $bbox = self::decodeBbox($bbox);
      $where = isset($_GET['where']) ? $_GET['where'] : (isset($_POST['where']) ? $_POST['where'] : '');
      $this->printAllFeatures($typeName, $bbox, $where);
      die();
    }
    else
      return null;
  }
  
  // envoi une requête et récupère la réponse sous la forme d'un texte
  function query(array $params): string {
    if (self::$log) { // log
      file_put_contents(
          self::$log,
          YamlDoc::syaml([
            'date'=> date(DateTime::ATOM),
            'appel'=> 'WfsServer::request',
            'params'=> $params,
          ]),
          FILE_APPEND
      );
    }
    $url = $this->urlWfs.'?SERVICE=WFS&VERSION=2.0.0';
    foreach($params as $key => $value)
      $url .= "&$key=$value";
    $referer = $this->referer;
    //echo "referer=$referer\n";
    $context = stream_context_create(['http'=> ['header'=> "referer: $referer\r\n"]]);
    if (($result = file_get_contents($url, false, $context)) === false) {
      var_dump($http_response_header);
      throw new Exception("Erreur dans WfsServer::request() : sur url=$url");
    }
    if (self::$log) { // log
      file_put_contents(self::$log, YamlDoc::syaml(['url'=> $url]), FILE_APPEND);
    }
    //die($result);
    if (substr($result, 0, 17) == '<ExceptionReport>') {
      if (!preg_match('!^<ExceptionReport><[^>]*>([^<]*)!', $result, $matches))
        throw new Exception("Erreur dans WfsServer::request() : message d'erreur non détecté");
      throw new Exception ("Erreur dans WfsServer::request() : $matches[1]");
    }
    return $result;
  }
    
  // retourne le nbre d'objets correspondant au résultat de la requête
  function getNumberMatched(string $typename, array $bbox=[], string $where=''): int {
    $request = [
      'REQUEST'=> 'GetFeature',
      'TYPENAMES'=> $typename,
      'SRSNAME'=> 'CRS:84', // système de coordonnées nécessaire pour du GeoJSON
      'RESULTTYPE'=> 'hits',
    ];
    $cql_filter = '';
    if ($bbox) {
      $bboxwkt = self::bboxWktLatLng($bbox);
      $cql_filter = "Intersects(the_geom,$bboxwkt)";
    }
    if ($where) {
      $where = utf8_decode($where); // expérimentalement les requêtes doivent être encodées en ISO-8859-1
      $cql_filter .= ($cql_filter ? ' AND ':'').$where;
    }
    if ($cql_filter)
      $request['CQL_FILTER'] = urlencode($cql_filter);
    $result = $this->query($request);
    if (!preg_match('! numberMatched="(\d+)" !', $result, $matches)) {
      echo "result=",$result,"\n";
      throw new Exception("Erreur dans WfsServer::getNumberMatched() : no match on result");
    }
    return (int)$matches[1];
  }
  
  // retourne le résultat de la requête en GeoJSON
  function getFeature(string $typename, array $bbox=[], string $where='', int $count=100, int $startindex=0): string {
    $request = [
      'REQUEST'=> 'GetFeature',
      'TYPENAMES'=> $typename,
      'OUTPUTFORMAT'=> 'application/json',
      'SRSNAME'=> 'CRS:84', // système de coordonnées nécessaire pour du GeoJSON
      'COUNT'=> $count,
      'STARTINDEX'=> $startindex,
    ];
    $cql_filter = '';
    if ($bbox) {
      $bboxwkt = self::bboxWktLatLng($bbox);
      $cql_filter = "Intersects(the_geom,$bboxwkt)";
    }
    if ($where) {
      $where = utf8_decode($where); // expérimentalement les requêtes doivent être encodées en ISO-8859-1
      $cql_filter .= ($cql_filter ? ' AND ':'').$where;
    }
    if ($cql_filter)
      $request['CQL_FILTER'] = urlencode($cql_filter);
    return $this->query($request);
  }
  
  // affiche le résultat de la requête en GeoJSON
  function printAllFeatures(string $typename, array $bbox=[], string $where=''): void {
    $numberMatched = $this->getNumberMatched($typename, $bbox, $where);
    if ($numberMatched <= 100) {
      echo $this->getFeature($typename, $bbox, $where);
      return;
    }
    //$numberMatched = 12; POUR TESTS
    echo '{"type":"FeatureCollection","numberMatched":'.$numberMatched.',"features":[',"\n";
    $startindex = 0;
    $count = 100;
    while ($startindex < $numberMatched) {
      $fc = $this->getFeature($typename, $bbox, $where, $count, $startindex);
      // recherche de la position de la fin du dernier Feature dans la FeatureCollection
      $pos = strpos($fc, '],"crs":{"type":"EPSG","properties":{"code":');
      // affichage de la liste des features sans les []
      if ($startindex<>0)
        echo ",\n";
      echo substr($fc, 40, $pos-40);
      $startindex += $count;
      //die(']}');
    }
    echo "\n]}\n";
  }
};
