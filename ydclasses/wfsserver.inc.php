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
  La classe WfsServerJson expose différentes méthodes utilisant un serveur WFS capable de générer du GeoJSON.
  La classe WfsServerGml expose différentes méthodes utilisant un serveur WFS capable de générer du GML 3.1.2 EPSG:4306.
  Un GetFeature avec un WfsServerGml réalise un filtrage en fonction du bbox et du zoom:
    1) les polygones, les trous ou les linestring qui n'intersectent pas la bbox sont rejetés,
    2) les polygones, les trous ou les linestring dont la taille est inférieure à la résolution sont rejetés,
    3) dans les lignes et les contours, si un point est trop proche du point précédent alors il est rejeté.
    La résolution est fixée à 360 / 2**(zoom+8) degrés
  
  évolutions à réaliser:
  
    - faire un new_doc sur WfsServer et ne pas avoir à choisr entre les sous-classes
    - adapter au zoom le nbre de chiffres transmis dans les coordonnées
  
  Outre les champs de métadonnées, le document doit définir les champs suivants:
  
    - wfsUrl: fournissant l'URL du serveur à compléter avec les paramètres,
  
  Il peut aussi définir les champs suivants:
  
    - wfsOptions: définit des options parmi les suivantes
      - referer: définissant le referer à transmettre à chaque appel du serveur,
      - gml: booléen indiquant si le retour est en GML et non en GeoJSON
      - version: version WFS, par défaut 2.0.0
      - coordOrderInGml: 'lngLat' pour indiquer que les coordonnées GML sont en LngLat et non en LatLng

  Liste des points d'entrée de l'API:
  
    - /{document} : description du serveur
    - /{document}/query?{params} : envoi d'une requête, les champs SERVICE et VERSION sont prédéfinis, retour XML
    - /{document}/getCap(abilities)? : renvoie en XML les capacités du serveur en rafraichissant le cache
    - /{document}/cap(abilities)? : renvoie en XML les capacités du serveur sans rafraichir le cache
    - /{document}/ft : liste en JSON les featureTypes
    - /{document}/ft/{typeName} : description de la couche en JSON
    - /{document}/ft/{typeName}/geomPropertyName : nom de la propriété géométrique
    - /{document}/ft/{typeName}/numberMatched?bbox={bbox}&where={where} : renvoi du nbre d'objets
      correspondant à la requête définie par bbox et where, where est encodé en UTF-8
    - /{document}/ft/{typeName}/getFeature?bbox={bbox}&zoom={zoom}&where={where} : affiche en GeoJSON les objets
      correspondant à la requête définie par bbox, where et zoom, limité à 1000 objets
    - /{document}/ft/{typeName}/getAllFeatures?bbox={bbox}&zoom={zoom}&where={where} : affiche en GeoJSON les objets
      correspondant à la requête définie par bbox, where et zoom, utilise la pagination si plus de 100 objets
    
  Le document http://localhost/yamldoc/?doc=geodata/igngpwfs permet de tester la classe WfsServerJson.
      
  Le document http://localhost/yamldoc/?doc=geocats/sextant-dcsmm permet de tester la classe WfsServerGml
  avec un serveur WFS 2.0.0 et GML 3.2.1.
  
  Le document http://localhost/yamldoc/?doc=geocats/geoide-zvuln41 permet de tester la classe WfsServerGml
  avec un serveur WFS 12.0.0 et GML 2.
      
  Résolution:
    zoom = 0, image 256x256
    resolution(zoom=0) Lng à l'équateur = 360/256
    A chaque zoom supérieur, division par 2 de la résolution
    256 = 2 ** 8
    => resolution = 360 / 2**(zoom+8) degrés
  
  Sur le serveur WFS IGN:
  
    - un DescribeFeatureType sans paramètre typename n'est pas utilisable
      - en JSON, le schema de chaque type est bien fourni mais les noms de type ne comportent pas l'espace de noms,
        générant ainsi un risque de confusion entre typename
      - en XML, le schéma de chaque type n'est pas fourni
      - la solution retenue consiste à effectuer un appel JSON par typename et à le bufferiser en JSON 
  
  Des tests unitaires de la transformation GML -> JSON sont définis.
      
journal:
  15/9/2018:
    - ajout gestion Point en GML 2
  12/9/2018:
    - transfert des fichiers Php dans ydclasses
    - chgt urlWfs en wfsUrl
    - structuration wfsOptions avec l'option referer et l'option gml
    - ajout option version et possibilité d'interroger le serveur en WFS 1.0.0
  5-9/9/2018:
    - développement de la classe WfsServerGml implémentant les requêtes pour un serveur WFS EPSG:4326 + GML
    - mise en oeuvre du filtrage défini plus haut
  4/9/2018:
    - remplacement du prefixe t par ft pour featureType
    - refonte de la gestion du cache indépendamment du stockage du document car le doc peut être volatil
    - ajout de la récupération du nom de la propriété géométrique qui n'est pas toujours le même
  3/9/2018:
    - ajout d'une classe WfsServerGml implémentant les requêtes pour un serveur WFS GML + EPSG:4326
    en cours
  15/8/2018:
    - création
EOT;
}

//require_once __DIR__.'/../yd.inc.php';
//require_once __DIR__.'/../store.inc.php';
require_once __DIR__.'/inc.php';

use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Yaml\Exception\ParseException;

// classe simplifiant l'envoi de requêtes WFS
abstract class WfsServer extends YamlDoc {
  static $log = __DIR__.'/wfsserver.log.yaml'; // nom du fichier de log ou false pour pas de log
  static $capCache = __DIR__.'/wfscapcache'; // nom du répertoire dans lequel sont stockés les fichiers XML
                                           // de capacités ainsi que les DescribeFeatureType en json
  protected $_c; // contient les champs
  
  // crée un nouveau doc, $yaml est le contenu Yaml externe issu de l'analyseur Yaml
  function __construct(&$yaml) {
    $this->_c = [];
    foreach ($yaml as $prop => $value) {
      $this->_c[$prop] = $value;
    }
    if (!$this->wfsUrl)
      throw new Exception("Erreur dans WfsServer::__construct(): champ wfsUrl obligatoire");
  }
  
  // effectue soit un new WfsServerJson soit un new WfsServerGml
  static function new_WfsServer(array $wfsParams) {
    return isset($wfsParams['wfsOptions']['gml']) ? new WfsServerGml($wfsParams) : new WfsServerJSON($wfsParams);
  }
    
  // lit les champs
  function __get(string $name) { return isset($this->_c[$name]) ? $this->_c[$name] : null; }

  // affiche le sous-élément de l'élément défini par $ypath
  function show(string $docid, string $ypath): void {
    echo "WfsServerJson::show($docid, $ypath)<br>\n";
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
    return [(float)$bbox[0], (float)$bbox[1], (float)$bbox[2], (float)$bbox[3]];
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
    //echo "WfsServer::extractByUri($docuri, $ypath)<br>\n";
    $params = !isset($_GET) ? $_POST : (!isset($_POST) ? $_GET : array_merge($_GET, $_POST));
    if (!$ypath || ($ypath=='/'))
      return $this->_c;
    elseif ($ypath == '/query') {
      //$params = isset($_GET) ? $_GET : (isset($_POST) ? $_POST : []);
      if (isset($params['OUTPUTFORMAT']) && ($params['OUTPUTFORMAT']=='application/json'))
        header('Content-type: application/json');
      else
        header('Content-type: application/xml');
      echo $this->query($params);
      die();
    }
    // met à jour le cache des capacités et retourne les capacités
    elseif (preg_match('!^/getCap(abilities)?$!', $ypath, $matches)) {
      header('Content-type: application/xml');
      die($this->getCapabilities(true));
    }
    // retourne les capacités sans forcer la mise à jour du cache
    elseif (preg_match('!^/cap(abilities)?$!', $ypath, $matches)) {
      header('Content-type: application/xml');
      die($this->getCapabilities(false));
    }
    elseif ($ypath == '/ft') {
      $cap = $this->getCapabilities();
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
    // accès à la layer /ft/{typeName}
    // effectue la requête DescribeFeatureType et retourne le résultat
    elseif (preg_match('!^/ft/([^/]+)$!', $ypath, $matches)) {
      return $this->describeFeatureType($matches[1]);
    }
    elseif (preg_match('!^/ft/([^/]+)/geom(PropertyName)?$!', $ypath, $matches)) {
      return $this->geomPropertyName($matches[1]);
    }
    // accès à /t/{typeName}/numberMatched
    elseif (preg_match('!^/ft/([^/]+)/num(berMatched)?$!', $ypath, $matches)) {
      $typeName = $matches[1];
      $bbox = isset($params['bbox']) ? $params['bbox'] : '';
      $bbox = self::decodeBbox($bbox);
      $where = isset($params['where']) ? $params['where'] : '';
      return [ 'numberMatched'=> $this->getNumberMatched($typeName, $bbox, $where) ];
    }
    elseif (preg_match('!^/ft/([^/]+)/getFeature$!', $ypath, $matches)) {
      $typeName = $matches[1];
      header('Content-type: application/json');
      $bbox = isset($params['bbox']) ? $params['bbox'] : '';
      $bbox = self::decodeBbox($bbox);
      $zoom = isset($params['zoom']) ? $params['zoom'] : -1;
      $where = isset($params['where']) ? $params['where'] : '';
      //echo "where=$where\n";
      echo $this->getFeature($typeName, $bbox, $zoom, $where);
      die();
    }
    elseif (preg_match('!^/ft/([^/]+)/getAllFeatures$!', $ypath, $matches)) {
      $typeName = $matches[1];
      header('Content-type: application/json');
      $bbox = isset($params['bbox']) ? $params['bbox'] : '';
      $bbox = self::decodeBbox($bbox);
      $zoom = isset($params['zoom']) ? $params['zoom'] : -1;
      $where = isset($params['where']) ? $params['where'] : '';
      $this->printAllFeatures($typeName, $bbox, $zoom, $where);
      die();
    }
    else
      return null;
  }
  
  // renvoi l'URL de la requête
  function url(array $params): string {
    if (self::$log) { // log
      file_put_contents(
          self::$log,
          YamlDoc::syaml([
            'date'=> date(DateTime::ATOM),
            'appel'=> 'WfsServer::url',
            'params'=> $params,
          ]),
          FILE_APPEND
      );
    }
    $url = $this->wfsUrl;
    $url .= ((strpos($url, '?') === false) ? '?' : '&').'SERVICE=WFS';
    foreach($params as $key => $value)
      $url .= "&$key=$value";
    if (self::$log) { // log
      file_put_contents(self::$log, YamlDoc::syaml(['url'=> $url]), FILE_APPEND);
    }
    return $url;
  }
  
  // envoi une requête et récupère la réponse sous la forme d'un texte
  function query(array $params): string {
    $url = $this->url($params);
    $context = null;
    if ($this->wfsOptions && isset($this->wfsOptions['referer'])) {
      $referer = $this->wfsOptions['referer'];
      if (self::$log) { // log
        file_put_contents(
            self::$log,
            YamlDoc::syaml([
              'appel'=> 'WfsServer::query',
              'referer'=> $referer,
            ]),
            FILE_APPEND
        );
      }
      $context = stream_context_create(['http'=> ['header'=> "referer: $referer\r\n"]]);
    }
    if (($result = file_get_contents($url, false, $context)) === false) {
      var_dump($http_response_header);
      throw new Exception("Erreur dans WfsServer::query() : sur url=$url");
    }
    //die($result);
    if (substr($result, 0, 17) == '<ExceptionReport>') {
      if (!preg_match('!<ExceptionReport><[^>]*>([^<]*)!', $result, $matches))
        throw new Exception("Erreur dans WfsServer::query() : message d'erreur non détecté");
      throw new Exception ("Erreur dans WfsServer::query() : $matches[1]");
    }
    return $result;
  }
  
  // effectue un GetCapabities et retourne le XML. Utilise le cache sauf si force=true
  function getCapabilities(bool $force=false): string {
    //echo "wfsUrl=",$this->wfsUrl,"<br>\n";
    //print_r($this); die();
    $wfsVersion = ($this->wfsOptions && isset($this->wfsOptions['version'])) ? $this->wfsOptions['version'] : '';
    $filepath = self::$capCache.'/wfs'.md5($this->wfsUrl.$wfsVersion).'-cap.xml';
    if ((!$force) && file_exists($filepath))
      return file_get_contents($filepath);
    else {
      $query = ['request'=> 'GetCapabilities'];
      if ($wfsVersion)
        $query['VERSION'] = $wfsVersion;
      $cap = $this->query($query);
      if (!is_dir(self::$capCache))
        mkdir(self::$capCache);
      file_put_contents($filepath, $cap);
      return $cap;
    }
  }

  // liste les couches exposées evt filtré par l'URL des MD
  function featureTypeList(string $metadataUrl=null) {
    //echo "WfsServerJson::featureTypeList()<br>\n";
    $cap = $this->getCapabilities();
    $cap = str_replace(['xlink:href'], ['xlink_href'], $cap);
    //echo "<a href='/yamldoc/wfscapcache/",md5($this->wfsUrl),".xml'>capCache</a><br>\n";
    $featureTypeList = [];
    $cap = new SimpleXMLElement($cap);
    foreach ($cap->FeatureTypeList->FeatureType as $featureType) {
      $name = (string)$featureType->Name;
      $featureTypeRec = [
        'Title'=> (string)$featureType->Title,
        'MetadataURL'=> (string)$featureType->MetadataURL['xlink_href'],
      ];
      if (!$metadataUrl || ($featureTypeRec['MetadataURL'] == $metadataUrl))
        $featureTypeList[$name] = $featureTypeRec;
    }
    //echo '<pre>$featureTypeList = '; print_r($featureTypeList);
    return $featureTypeList;
  }
  
  abstract function describeFeatureType(string $typeName): array;
  
  abstract function geomPropertyName(string $typeName): ?string;
  
  abstract function getNumberMatched(string $typename, array $bbox=[], string $where=''): int;
  
  abstract function getFeature(string $typename, array $bbox=[], int $zoom=-1, string $where='', int $count=100, int $startindex=0): string;

  abstract function printAllFeatures(string $typename, array $bbox=[], int $zoom=-1, string $where=''): void;
};

// classe simplifiant l'envoi de requêtes WFS capable de fournir des données GeoJSON
class WfsServerJson extends WfsServer {
  
  function describeFeatureType(string $typeName): array {
    $filepath = self::$capCache.'/wfs'.md5($this->wfsUrl."/$typeName").'-ft.json';
    if (is_file($filepath)) {
      $featureType = file_get_contents($filepath);
    }
    else {
      $featureType = $this->query([
        'VERSION'=> '2.0.0',
        'REQUEST'=> 'DescribeFeatureType',
        'OUTPUTFORMAT'=> 'application/json',
        //'OUTPUTFORMAT'=> rawurlencode('text/xml; subtype=gml/3.2'),
        'TYPENAME'=> $typeName,
      ]);
      file_put_contents($filepath, $featureType);
    }
    $featureType = json_decode($featureType, true);
    return $featureType;
  }
  
  // nom de la propriété géométrique du featureType
  function geomPropertyName(string $typeName): ?string {
    $featureType = $this->describeFeatureType($typeName);
    //var_dump($featureType);
    foreach($featureType['featureTypes'] as $featureType) {
      foreach ($featureType['properties'] as $property) {
        if (preg_match('!^gml:!', $property['type']))
          return $property['name'];
      }
    }
    return null;
  }
  
  // retourne le nbre d'objets correspondant au résultat de la requête
  function getNumberMatched(string $typename, array $bbox=[], string $where=''): int {
    $geomPropertyName = $this->geomPropertyName($typename);
    $request = [
      'VERSION'=> '2.0.0',
      'REQUEST'=> 'GetFeature',
      'TYPENAMES'=> $typename,
      'SRSNAME'=> 'CRS:84', // système de coordonnées nécessaire pour du GeoJSON
      'RESULTTYPE'=> 'hits',
    ];
    $cql_filter = '';
    if ($bbox) {
      $bboxwkt = self::bboxWktLatLng($bbox);
      $cql_filter = "Intersects($geomPropertyName,$bboxwkt)";
    }
    if ($where) {
      $where = utf8_decode($where); // expérimentalement les requêtes doivent être encodées en ISO-8859-1
      $cql_filter .= ($cql_filter ? ' AND ':'').$where;
    }
    if ($cql_filter)
      $request['CQL_FILTER'] = urlencode($cql_filter);
    $result = $this->query($request);
    if (!preg_match('! numberMatched="(\d+)" !', $result, $matches)) {
      //echo "result=",$result,"\n";
      throw new Exception("Erreur dans WfsServerJson::getNumberMatched() : no match on result $result");
    }
    return (int)$matches[1];
  }
  
  // retourne le résultat de la requête en GeoJSON
  function getFeature(string $typename, array $bbox=[], int $zoom=-1, string $where='', int $count=100, int $startindex=0): string {
    $geomPropertyName = $this->geomPropertyName($typename);
    $request = [
      'VERSION'=> '2.0.0',
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
      $cql_filter = "Intersects($geomPropertyName,$bboxwkt)";
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
  function printAllFeatures(string $typename, array $bbox=[], int $zoom=-1, string $where=''): void {
    $numberMatched = $this->getNumberMatched($typename, $bbox, $where);
    if ($numberMatched <= 100) {
      echo $this->getFeature($typename, $bbox, $zoom, $where);
      return;
    }
    //$numberMatched = 12; POUR TESTS
    echo '{"type":"FeatureCollection","numberMatched":'.$numberMatched.',"features":[',"\n";
    $startindex = 0;
    $count = 100;
    while ($startindex < $numberMatched) {
      $fc = $this->getFeature($typename, $bbox, $zoom, $where, $count, $startindex);
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

// Essai d'une classe implémentant les requêtes pour un serveur WFS ne parlant pas JSON
class WfsServerGml extends WfsServer {
  private $xsltProcessors=[];
  
  function describeFeatureType(string $typeName): array {
    $filepath = self::$capCache.'/wfs'.md5($this->wfsUrl."/$typeName").'-ft.xml';
    if (is_file($filepath)) {
      $ftXml = file_get_contents($filepath);
    }
    else {
      if (!$this->wfsOptions || !isset($this->wfsOptions['version'])) {
        $ftXml = $this->query([
          'VERSION'=> '2.0.0',
          'REQUEST'=> 'DescribeFeatureType',
          'OUTPUTFORMAT'=> rawurlencode('text/xml; subtype=gml/3.2'),
          'TYPENAME'=> $typeName,
        ]);
      }
      else {
        $ftXml = $this->query([
          'VERSION'=> $this->wfsOptions['version'],
          'REQUEST'=> 'DescribeFeatureType',
          'TYPENAME'=> $typeName,
        ]);
      }
      file_put_contents($filepath, $ftXml);
    }
    $ft = new SimpleXMLElement($ftXml);
    $eltName = (string)$ft->element['name'];
    $eltType = (string)$ft->element['type'];
    list($prefix, $eltTypeSimple) = explode(':',$eltType);
    $featureTypes = [
      'targetNamespace'=> (string)$ft['targetNamespace'],
      'targetPrefix' => $prefix,
      //'eltName' => $eltName,
      //'eltType' => $eltType,
      //'eltTypeSimple' => $eltTypeSimple,
      'featureTypes'=> [],
    ];
    foreach ($ft->complexType as $ct) {
      $featureType = ['typeName'=> $eltName, 'properties'=> []];
      foreach ($ct->complexContent->extension->sequence->element as $elt) {
        $property = [
          'name'=> (string)$elt['name'],
          'localType'=> (string)$elt['type'],
        ];
        //$property[''] = (string)$elt['name'];
        $featureType['properties'][] = $property;
      }
      $featureTypes['featureTypes'][] = $featureType;
    }
    return $featureTypes;
  }
  
  // nom de la propriété géométrique du featureType
  function geomPropertyName(string $typeName): ?string {
    $featureType = $this->describeFeatureType($typeName);
    //var_dump($featureType);
    foreach($featureType['featureTypes'] as $featureType) {
      foreach ($featureType['properties'] as $property) {
        if (preg_match('!^gml:!', $property['localType']))
          return $property['name'];
      }
    }
    return null;
  }
  
  // retourne le nbre d'objets correspondant au résultat de la requête, si inconnu retourne -1
  function getNumberMatched(string $typename, array $bbox=[], string $where=''): int {
    if ($this->wfsOptions && isset($this->wfsOptions['version']) && ($this->wfsOptions['version']=='1.0.0'))
      return -1; 
    $version = ($this->wfsOptions && isset($this->wfsOptions['version'])) ? $this->wfsOptions['version'] : '2.0.0';
    $request = [
      'VERSION'=> $version,
      'REQUEST'=> 'GetFeature',
      'TYPENAMES'=> $typename,
      'SRSNAME'=> 'EPSG:4326',
      'RESULTTYPE'=> 'hits',
    ];
    if ($version <> '1.0.0') {
      $bbox = [$bbox[1], $bbox[0], $bbox[3], $bbox[2]]; // passage en LatLng
    }
    $request['BBOX'] = implode(',',$bbox);
    $result = $this->query($request);
    if (!preg_match('! numberMatched="(\d+)" !', $result, $matches)) {
      //echo "result=",$result,"\n";
      throw new Exception("Erreur dans WfsServerGml::getNumberMatched() : no match on result $result");
    }
    return (int)$matches[1];
  }
  
  // génère le code source de la feuille de style utilisée par wfs2GeoJson
  function xslForGeoJson(string $typename): string {
    $describeFeatureType = $this->describeFeatureType($typename);
    //echo '$describeFeatureType = '; print_r($describeFeatureType);
    $targetPrefix = $describeFeatureType['targetPrefix'];
    $targetNamespace = $describeFeatureType['targetNamespace'];
    $xsl_properties = '';
    foreach ($describeFeatureType['featureTypes'][0]['properties'] as $property) {
      if ($property['localType']=='string') {
        //echo '$property = '; print_r($property);
        $name = $property['name'];
        $xsl_properties .= "<xsl:if test=\"*/$targetPrefix:$name\">\n"
          ."      $name: <xsl:value-of select=\"*/$targetPrefix:$name\"/>\n"
          ."      </xsl:if>";
        //echo "xsl=$xsl_properties\n";
      }
    }
    if ($this->wfsOptions && isset($this->wfsOptions['version']) && ($this->wfsOptions['version']=='1.0.0'))
      $xslsrc = file_get_contents(__DIR__.'/wfsgml2togeojson.xsl'); // GML 2
    else
      $xslsrc = file_get_contents(__DIR__.'/wfsgml32togeojson.xsl'); // GML 3.2
    $targetNamespaceDef = "xmlns:$targetPrefix=\"$targetNamespace\"";
    $xslsrc = str_replace('{targetNamespaceDef}', $targetNamespaceDef, $xslsrc);
    $xslsrc = str_replace('{xslProperties}', $xsl_properties, $xslsrc);
    //die($xslsrc);
    return $xslsrc;
  }
  
  // effectue la transformation de pseudo GeoJSON en un GeoJSON
  function pseudo2GeoJson(string $pseudo, string $format, array $bbox, int $zoom): void {
    //die($pseudo);
    $res = 0;
    if ($zoom <> -1) {
      $res = 360.0 / (2 ** ($zoom+8)); // resolution en fonction du zoom
    }
    if (self::$log) { // log
      file_put_contents(
          self::$log,
          YamlDoc::syaml([
            'call'=> 'pseudo2GeoJson',
            'zoom'=> $zoom,
            'res'=> $res,
          ]),
          FILE_APPEND
      );
    }
    $pos = 0;
    $nofeature = 0;
    while ($pos != -1) {
      if (substr($pseudo, $pos, 1)=="\n") {
        $pos++;
        if ($format=='verbose')
          echo "ligne vide reconnue\n";
      }
      elseif (substr($pseudo, $pos, 11) == "- Feature:\n") {
        $pos += 11;
        if ($format=='json')
          echo $nofeature?",\n":'',"{ \"type\":\"Feature\",\n";
        $pos = $this->decodeFeature($pseudo, $pos, $format, $bbox, $res);
        if ($format=='json')
          echo "}";
        $nofeature++;
      }
      else {
        throw new Exception("Erreur dans pseudo2GeoJson pos=$pos sur '".substr($pseudo,$pos, 1000)."' line ".__LINE__);
      }
    }
    if ($format=='json')
      echo "\n";
  }
  
  function decodeFeature(string $pseudo, int $pos, string $format, array $bbox, float $res): int {
    if (substr($pseudo, $pos, 16) != "    properties:\n")
      throw new Exception("Erreur dans decodeFeature pos=$pos sur ".substr($pseudo,$pos, 1000)." line ".__LINE__);
    $pos += 16;
    if ($format == 'json')
      echo "  \"properties\": {";
    $noprop = 0;
    while (substr($pseudo, $pos, 6) == "      ") {
      $pos += 6;
      $possep = strpos($pseudo, ':', $pos);
      $name = substr($pseudo, $pos, $possep - $pos);
      $posret = strpos($pseudo, "\n", $possep);
      if ($posret === false)
        throw new Exception("Erreur dans decodeFeature pos=$pos sur ".substr($pseudo,$pos, 1000)." line ".__LINE__);
      $value = substr($pseudo, $possep + 2, $posret - $possep - 2);
      if ($format == 'verbose')
        echo "property: name=$name, value=$value\n";
      elseif ($format == 'json')
        echo $noprop?",\n":"\n","    \"$name\" : \"$value\"";
      $pos = $posret + 1;
      $noprop++;
    }
    if ($format == 'json')
      echo "\n  },\n";
    if (substr($pseudo, $pos, 16) == "    MultiCurve:\n") {
      $pos += 16;
      if ($format == 'verbose')
        echo "MultiCurve détectée pos=$pos\n";
      elseif ($format == 'json')
        echo "  \"geometry\" : {\n",
          "    \"type\" : \"MultiLineString\",\n",
          "    \"coordinates\" : [\n";
      $pos = $this->decodeMultiCurve($pseudo, $pos, $format, $bbox, $res);
      if ($format == 'json')
        echo "    ]\n",
          "  }\n";
      return $pos;
    }
    elseif (substr($pseudo, $pos, 18) == "    MultiSurface:\n") {
      $pos += 18;
      if ($format == 'verbose')
        echo "MultiSurface détectée pos=$pos\n";
      elseif ($format == 'json')
        echo "  \"geometry\" : {\n",
          "    \"type\" : \"MultiPolygon\",\n",
          "    \"coordinates\" : [\n";
      $pos = $this->decodeMultiSurface($pseudo, $pos, $format, $bbox, $res);
      if ($format == 'json')
        echo "    ]\n",
          "  }\n";
      return $pos;
    }
    elseif (substr($pseudo, $pos, 11) == "    Point: ") {
      $pos += 11;
      if (isset($this->wfsOptions['version']) && ($this->wfsOptions['version']=='1.0.0'))
        $coordSep = ','; // GML 2
      else
        $coordSep = ' '; // GML 3.2
      if (isset($this->wfsOptions['coordOrderInGml']) && ($this->wfsOptions['coordOrderInGml']=='lngLat'))
        $coordOrderInGml = 'lngLat'; // GML 2
      else
        $coordOrderInGml = 'latLng'; // GML 3.2
      $poseol = strpos($pseudo, "\n", $pos);
      $poswhite = strpos($pseudo, $coordSep, $pos);
      $x = substr($pseudo, $pos, $poswhite-$pos);
      $pos = $poswhite+1;
      //$poswhite = strpos($pseudo, ' ', $pos);
      if ($poseol === false)
        $y = substr($pseudo, $pos);
      else
        $y = substr($pseudo, $pos, $poseol-$pos);
      if ($format == 'verbose')
        echo "Point détecté pos=$pos\n";
      elseif ($format == 'json')
        echo "  \"geometry\" : {\n",
          "    \"type\" : \"Point\",\n",
          "    \"coordinates\" : ",$coordOrderInGml == 'lngLat' ? "[ $x, $y ]\n" : "[ $y, $x ]\n",
          "  }\n";
      $pos = ($poseol === false) ? -1 : $poseol + 1;
      return $pos;
    }
    else {
      throw new Exception("Erreur dans decodeFeature pos=$pos sur ".substr($pseudo,$pos, 1000)." line ".__LINE__);
    }
  }
  
  function decodeMultiCurve(string $pseudo, int $pos, string $format, array $bbox, float $res): int {
    $nols = 0;
    while (($pos != -1) && substr($pseudo, $pos, 21) == "      - LineString2: ") {
      $pos += 21;
      if ($format == 'verbose')
        echo "LineString2 détectée pos=$pos\n";
      $pts = $this->decodeListPoints2($pseudo, $pos, $format, $bbox, $res);
      if (($format == 'json') && $pts) {
        echo $nols?",\n":'',"      [";
        $this->encodeListPoints2($pts, $format);
        echo "]";
        $nols++;
      }
    }
    if ($format == 'json')
        echo "\n";
    return $pos;
  }
  
  function decodeMultiSurface(string $pseudo, int $pos, string $format, array $bbox, float $res): int {
    $nosurf = 0;
    while (($pos != -1) && substr($pseudo, $pos, 18) == "      - Polygon2:\n") {
      $pos += 18;
      if ($format == 'verbose')
        echo "Polygon2 détecté pos=$pos\n";
      $header = ($nosurf?",\n":'')."     [\n";
      $footer = "     ]";
      if ($this->decodePolygon2($pseudo, $pos, $format, $bbox, $res, $header, $footer))
        $nosurf++;
    }
    if ($format == 'json')
        echo "\n";
    return $pos;
  }
  
  // decode un polygon2
  // modifie $pos avec la position après la fin de ligne ou -1
  // retourne true si le polygone intersecte la bbox, false sinon
  // $header et $footer sont affichés avant et après si le polygone intersecte la bbox et si format json
  function decodePolygon2(string $pseudo, int &$pos, string $format, array $bbox, float $res, string $header, string $footer): bool {
    if (substr($pseudo, $pos, 20) != "          exterior: ")
      throw new Exception("Erreur dans decodePolygon2 exterior non détecté\n");
    $pos += 20;
    if ($format == 'verbose')
      echo "Polygon2 exterior détecté\n";
    $pts = $this->decodeListPoints2($pseudo, $pos, $format, $bbox, $res);
    if (!$pts) {
      $polygonIntersects = false;
      if ($format == 'verbose')
        echo "Polygon2 exterior hors bbox\n";
    }
    else {
      $polygonIntersects = true;
      if ($format == 'verbose')
        echo "Polygon2 exterior intersecte bbox\n";
      elseif ($format == 'json') {
        echo $header,"      [";
        $this->encodeListPoints2($pts, $format);
      }
    }
    if (substr($pseudo, $pos, 20) == "          interior:\n") {
      $pos += 20;
      if ($format == 'verbose')
        echo "Polygon2 interior détecté\n";
      while (substr($pseudo, $pos, 12) == "          - ") {
        $pos += 12;
        if ($format == 'verbose')
          echo "Polygon2interior LineString2 détectée\n";
        $pts = $this->decodeListPoints2($pseudo, $pos, $format, $bbox, $res);
        if (($format == 'json') && $polygonIntersects && $pts) {
          echo "],\n      [";
          $this->encodeListPoints2($pts, $format);
        }
      }
    }
    if (($format == 'json') && $polygonIntersects)
      echo "]\n",$footer;
    return $polygonIntersects;
  }
    
  // decode une liste de points de 2 coord dans pseudo à partir de pos
  // modifie $pos avec la position après la fin de ligne ou -1
  // renvoie la liste de points ou [] si aucun point n'est dans la qbbox
  // un filtre est effectué sur les points en fonction de la résolution $res si $res <> 0
  function decodeListPoints2(string $pseudo, int &$pos, string $format, array $qbbox, float $res): array {
    if ($this->wfsOptions && isset($this->wfsOptions['version']) && ($this->wfsOptions['version']=='1.0.0'))
      $gmlVersion = '2'; // GML 2
    else
      $gmlVersion = '3.2'; // GML 3.2
    $nbpts = 0; // le nbre de points retenus
    $pts = []; // la liste des points retenus
    $ptprec = []; // le dernier point retenu dans $pts
    $ptLost = []; // mémorise le dernier point traité s'il n'a pas été retenu, sinon []
    $bbox = []; // le bbox de la liste de points
    $poseol = strpos($pseudo, "\n", $pos);
    while (1) {
      if ($gmlVersion == '2')
        $poswhite = strpos($pseudo, ',', $pos);
      else
        $poswhite = strpos($pseudo, ' ', $pos);
      //echo "poswhite=$poswhite, poseol=$poseol, pos=$pos\n";
      if (($poswhite === false) || (($poseol !== FALSE) && ($poswhite > $poseol))) {
        if ($ptLost) // je force à retenir le dernier point s'il ne l'avait pas été
          $pts[] = $ptLost;
        break;
      }
      $x = substr($pseudo, $pos, $poswhite-$pos);
      //echo "x=$x\n";
      $pos = $poswhite+1;
      $poswhite = strpos($pseudo, ' ', $pos);
      //  echo "poswhite=$poswhite, posret=$posret\n";
      if (($poswhite === false) || (($poseol !== FALSE) && ($poswhite > $poseol))) {
        die("Erreur dans step=$step sur ".substr($pseudo,$pos,1000));
      }
      $y = substr($pseudo, $pos, $poswhite-$pos);
      $pos = $poswhite+1;
      if ($format=='verbose')
        echo "  pos=$pos, nopt=$nbpts, x=$x, y=$y\n";
      if (!$bbox)
        $bbox = [$x, $y, $x, $y];
      else { // maj bbox 
        if ($x < $bbox[0])
          $bbox[0] = $x;
        if ($y < $bbox[1])
          $bbox[1] = $y;
        if ($x > $bbox[2])
          $bbox[2] = $x;
        if ($y > $bbox[3])
          $bbox[3] = $y;
      }
      if ($ptprec && ($res <> 0.0)) {
        $dist = max(abs($x-$ptprec[0]),abs($y-$ptprec[1]));
      }
      if (!$ptprec || ($res == 0.0) || ($dist > $res)) { // le point courant est conservé dans $pts
        $ptprec = [$x,$y];
        $pts[] = $ptprec;
        $nbpts++;
        $ptLost = [];
      }
      else // le point courant n'est pas conservé dans $pts, il est mémorisé dans $ptLost
        $ptLost = [$x,$y];
    }
    if ($poseol === FALSE)
      $pos = -1;
    else
      $pos = $poseol + 1;
    $xmin = max($qbbox[0], $bbox[0]);
    $ymin = max($qbbox[1], $bbox[1]);
    $xmax = min($qbbox[2], $bbox[2]);
    $ymax = min($qbbox[3], $bbox[3]);
    $inters = (($xmax >= $xmin) && ($ymax >= $ymin)); // teste l'intersection entre qbbox et bbox
    if (!$inters)
      return [];
    if (max($bbox[2] - $bbox[0], $bbox[3] - $bbox[1]) < $res) // teste la taille de l'élément
      return [];
    return $pts;
  }

  // affiche la liste de points
  function encodeListPoints2(array $pts, string $format): void {
    if (isset($this->wfsOptions['coordOrderInGml']) && ($this->wfsOptions['coordOrderInGml']=='lngLat'))
      $coordOrderInGml = 'lngLat'; // GML 2
    else
      $coordOrderInGml = 'latLng'; // GML 3.2
    if ($format=='verbose')
      echo "$nbpts points détectés\n";
    elseif ($format=='json') {
      $nbpts = count($pts);
      for($i=0; $i<$nbpts; $i++) {
        if ($coordOrderInGml == 'lngLat')
          echo $i?',':'','[',$pts[$i][0],',',$pts[$i][1],']'; // génération en LngLat (CRS:84)
        else
          echo $i?',':'','[',$pts[$i][1],',',$pts[$i][0],']'; // génération en LngLat (CRS:84)
        //echo $i?',':'','"pt"';
      }
    }
  }
  
  // effectue la transformation du Gml en un pseudo GeoJSON puis affiche les Feature en JSON
  // l'affichage doit être encadré par '{"type":"FeatureCollection","features":' et ']}'
  // Cela permet d'afficher une seule FeatureCollection en cas de pagination
  // le bbox est en LatLng pour GML 3.2 et en LngLat pour GML 2
  function wfs2GeoJson(string $typename, string $xmlstr, string $format, array $bbox, int $zoom): void {
    if ($format == 'gml')
      die($xmlstr); // pour afficher le GML
    if (!isset($this->xsltProcessors['typename'])) {
      $xslsrc = $this->xslForGeoJson($typename);
      $stylesheet = new DOMDocument();
      $stylesheet->loadXML($xslsrc);
      $this->xsltProcessors['typename'] = new XSLTProcessor;
      $this->xsltProcessors['typename']->importStylesheet($stylesheet);
    }
    $getrecords = new DOMDocument();
    if (!$getrecords->loadXML($xmlstr)) {
      //echo "xml=",$xmlstr,"\n";
      throw new Exception("Erreur dans WfsServerGml::wfs2GeoJson() sur loadXML()");
    }
    $pseudo = $this->xsltProcessors['typename']->transformToXML($getrecords);
    if ($format == 'pseudo')
      die($pseudo); // pour afficher le pseudo intermédiaire
    $this->pseudo2GeoJson($pseudo, $format, $bbox, $zoom);
  }
  
  // Test unitaire de la méthode WfsServerGml::wfs2GeoJson()
  function wfs2GeoJsonTest() {
    if (0) { // sextant en WFS 2.0.0
      $this->_c['wfsUrl'] = 'http://www.ifremer.fr/services/wfs/dcsmm';
      $queries = [
        [ 'title'=> "ESPACES_TERRESTRES_P MultiSurface GML 3.2.1 EPSG:4326",
          'params'=> [
            'VERSION'=> '2.0.0', 'REQUEST'=> 'GetFeature', 'TYPENAMES'=> 'ms:ESPACES_TERRESTRES_P', 'RESULTTYPE'=> 'results',
            'SRSNAME'=> 'EPSG:4326', 'BBOX'=> '41,-10,51,16',
            'OUTPUTFORMAT'=> rawurlencode('text/xml; subtype=gml/3.2.1'), 'COUNT'=> '2',
          ],
          'zoom'=> 9,
        ],
        [ 'title'=> "DCSMM_SRM_TERRITORIALE_201806_L MultiCurve 41,-10,51,16 zoom=-1",
          'params'=> [
            'VERSION'=> '2.0.0', 'REQUEST'=> 'GetFeature', 'TYPENAMES'=> 'ms:DCSMM_SRM_TERRITORIALE_201806_L',
            'RESULTTYPE'=> 'results', 'SRSNAME'=> 'EPSG:4326', 'BBOX'=> '41,-10,51,16',
            'OUTPUTFORMAT'=> rawurlencode('text/xml; subtype=gml/3.2.1'), 'COUNT'=> '2',
          ],
          'zoom'=> -1,
        ],
        [ 'title'=> "DCSMM_SRM_TERRITORIALE_201806_L MultiCurve 41,-10,51,16 zoom=1",
          'params'=> [
            'VERSION'=> '2.0.0', 'REQUEST'=> 'GetFeature', 'TYPENAMES'=> 'ms:DCSMM_SRM_TERRITORIALE_201806_L',
            'RESULTTYPE'=> 'results', 'SRSNAME'=> 'EPSG:4326', 'BBOX'=> '41,-10,51,16',
            'OUTPUTFORMAT'=> rawurlencode('text/xml; subtype=gml/3.2.1'), 'COUNT'=> '2',
          ],
          'zoom'=> 1,
        ],
        [ 'title'=> "DCSMM_SRM_TERRITORIALE_201806_P MultiPolygone bbox=-7,47,-2,49",
          'params'=> [
            'VERSION'=> '2.0.0', 'REQUEST'=> 'GetFeature', 'TYPENAMES'=> 'ms:DCSMM_SRM_TERRITORIALE_201806_P',
            'RESULTTYPE'=> 'results', 'SRSNAME'=> 'EPSG:4326', 'BBOX'=> '47,-7,49,-2',
            'OUTPUTFORMAT'=> rawurlencode('text/xml; subtype=gml/3.2.1'), 'COUNT'=> '2',
          ],
          'zoom'=> 9,
        ],
      ];
    }
    elseif (0) { // geoide en WFS 1.0.0 N_VULNERABLE_ZSUP_041 Polygones 
      $this->_c['wfsUrl'] = 'http://ogc.geo-ide.developpement-durable.gouv.fr/wxs?'
        .'map=/opt/data/carto/geoide-catalogue/1.4/org_38024/f19f7c24-c605-43f5-b4a0-74676524d00a.internet.map';
      $this->_c['wfsOptions'] = [
        'version'=> '1.0.0',
        'coordOrderInGml'=> 'lngLat',
      ];

      $queries = [
        [ 'title'=> "GeoIde, WFS 1.0.0, GML 2, N_VULNERABLE_ZSUP_041",
          'params'=> [
            'VERSION'=> '1.0.0', 'REQUEST'=> 'GetFeature', 'TYPENAME'=> 'N_VULNERABLE_ZSUP_041',
            'SRSNAME'=> 'EPSG:4326', 'BBOX'=> '-8.0,42.4,14.0,51.1',
          ],
          'zoom'=> 18,
        ],
        [ 'title'=> "GeoIde, WFS 1.0.0, GML 2, N_VULNERABLE_ZSUP_041",
          'params'=> [
            'VERSION'=> '1.0.0', 'REQUEST'=> 'GetFeature', 'TYPENAME'=> 'N_VULNERABLE_ZSUP_041',
            'SRSNAME'=> 'EPSG:4326', 'BBOX'=> '-8.0,42.4,14.0,51.1',
          ],
          'zoom'=> 10,
        ],
        [ 'title'=> "GeoIde, WFS 1.0.0, GML 2, N_VULNERABLE_ZSUP_041",
          'params'=> [
            'VERSION'=> '1.0.0', 'REQUEST'=> 'GetFeature', 'TYPENAME'=> 'N_VULNERABLE_ZSUP_041',
            'SRSNAME'=> 'EPSG:4326', 'BBOX'=> '-8.0,42.4,14.0,51.1',
          ],
          'zoom'=> 10,
        ],
      ];
    }
    elseif (1) { // geoide en WFS 1.0.0 L_MUSEE_CHATEAU_041 Point 
      $this->_c['wfsUrl'] = 'http://ogc.geo-ide.developpement-durable.gouv.fr/wxs?'
        .'map=/opt/data/carto/geoide-catalogue/1.4/org_38024/f31dbfdd-1038-451b-a539-668ac27b6526.internet.map';
      $this->_c['wfsOptions'] = [
        'version'=> '1.0.0',
        'coordOrderInGml'=> 'lngLat',
      ];
      $queries = [
        [ 'title'=> "GeoIde, WFS 1.0.0, GML 2, L_MUSEE_CHATEAU_041",
          'params'=> [
            'VERSION'=> '1.0.0', 'REQUEST'=> 'GetFeature', 'TYPENAME'=> 'L_MUSEE_CHATEAU_041',
            'SRSNAME'=> 'EPSG:4326', 'BBOX'=> '-7.294921875,42.09822241119,13.3154296875,51.495064730144',
          ],
          'zoom'=> 6,
        ],
      ];
    }
    if (!isset($_GET['action'])) {
      echo "<h3>wfs2GeoJson Queries</h3><ul>\n";
      foreach ($queries as $num => $query) {
        $url = $this->url($query['params']);
        echo "<li>$query[title] ",
          "(<a href='$url'>url</a>, ", // appel de l'URL du WFS
          "<a href='?action=wfs&query=$num'>wfs</a>, ", // appel du WFS et stockage
          "<a href='?action=xml&query=$num'>xml</a>, ", // si en cache affiche
          "<a href='?action=xsl&query=$num'>xsl</a>, ", // affiche la feuille de style
          "<a href='?action=geojson&query=$num&format=pseudo'>pseudoGeoJSON</a>, ",
          "<a href='?action=geojson&query=$num&format=verbose'>GeoJSON verbose</a>, ",
          "<a href='?action=geojson&query=$num&format=json'>GeoJSON json</a>)\n"; // transforme en GeoJSON
      }
      echo "</ul>\n",
        "<a href='?action=ex0.txt'>Appel de pseudo2GeoJson() sur le fichier ex0.txt</a><br>\n";
      die();
    }

    if ($_GET['action']=='xsl') {
      header('Content-type: text/xml');
      $query = $queries[$_GET['query']];
      $typename = isset($query['params']['TYPENAMES']) ? $query['params']['TYPENAMES'] : $query['params']['TYPENAME'];
      echo $this->xslForGeoJson($typename);
      die();
    }
    
    if ($_GET['action']=='ex0.txt') {
      //header('Content-type: application/json');
      header('Content-type: text/plain');
      echo "{\"type\":\"FeatureCollection\",\"features\":[\n";
      $this->pseudo2GeoJson(file_get_contents(__DIR__.$_SERVER['PATH_INFO']."/ex0.txt"), 'json');
      echo "]}\n";
      die();
    }

    // le nom du du fichier de cache du résultat de la requête est construit avec le MD5 de la requete
    $query = $queries[$_GET['query']];
    $md5 = md5($this->url($query['params']));
    $filepath = __DIR__.$_SERVER['PATH_INFO']."/$md5.xml";
    if ($_GET['action']=='wfs') {
      $getrecords = $this->query($query['params']);
      file_put_contents($filepath, $getrecords);
      header('Content-type: text/xml');
      die($getrecords);
    }
    
    if (is_file($filepath))
      $getrecords = file_get_contents($filepath);
    else {
      $getrecords = $this->query($query['params']);
      file_put_contents($filepath, $getrecords);
    }
    if ($_GET['action']=='xml') {
      header('Content-type: text/xml');
      die($getrecords);
    }
    if ($_GET['action']=='geojson') {
      if ($_GET['format']=='json')
        header('Content-type: application/json');
      else
        header('Content-type: text/plain');
      echo "{\"type\":\"FeatureCollection\",\"features\":[\n";
      $typename = isset($query['params']['TYPENAMES']) ? $query['params']['TYPENAMES'] : $query['params']['TYPENAME'];
      $this->wfs2GeoJson(
        $typename,
        $getrecords,
        $_GET['format'],
        explode(',', $query['params']['BBOX']),
        $query['zoom']
      );
      echo "]}\n";
      die();
    }
    echo "action $_GET[action] inconnue\n";
  }
  
  // n'affiche pas le header/tailer GeoJSON
  function getFeatureWoHd(string $typename, array $bbox, int $zoom, string $where, int $count, int $startindex): void {
    if ($this->wfsOptions && isset($this->wfsOptions['version']) && ($this->wfsOptions['version']=='1.0.0')) {
      $request = [
        'VERSION'=> '1.0.0',
        'REQUEST'=> 'GetFeature',
        'TYPENAME'=> $typename,
        'SRSNAME'=> 'EPSG:4326',
        'BBOX'=> implode(',',$bbox),
      ];
    }
    else {
      $request = [
        'VERSION'=> '2.0.0',
        'REQUEST'=> 'GetFeature',
        'TYPENAMES'=> $typename,
        'OUTPUTFORMAT'=> rawurlencode('application/gml+xml; version=3.2'),
        'SRSNAME'=> 'EPSG:4326',
        'COUNT'=> $count,
        'STARTINDEX'=> $startindex,
      ];
      $bbox = [$bbox[1], $bbox[0], $bbox[3], $bbox[2]]; // passage en EPSG:4326
      $request['BBOX'] = implode(',',$bbox);
    }
    //$format = 'gml'; // pour afficher le GML
    //$format = 'pseudo';  // pour afficher le pseudo intermédiaire
    //$format = 'verbose'; // affichage des commentaires de la transfo pseudo en GeoJSON
    $format = 'json'; // affichage GeoJSON
    $this->wfs2GeoJson($typename, $this->query($request), $format, $bbox, $zoom);
  }

  // affiche le résultat de la requête en GeoJSON
  function getFeature(string $typename, array $bbox=[], int $zoom=-1, string $where='', int $count=100, int $startindex=0): string {
    //die($this->query($request)); // affichage du GML
    echo "{ \"type\":\"FeatureCollection\",\n",
      "  \"typename\":\"$typename\",\n",
      "  \"bbox\":",json_encode($bbox),",\n",
      "  \"zoom\":\"$zoom\",\n",
      "  \"where\":\"$where\",\n",
      "  \"count\":\"$count\",\n",
      "  \"startindex\":\"$startindex\",\n",
      "  \"features\":[\n";
    $this->getFeatureWoHd($typename, $bbox, $zoom, $where, $count, $startindex);
    echo "]}\n";
    return '';
  }
  
  function getFeatureTest() {
    header('Content-type: application/json');
    //header('Content-type: application/xml');
    //header('Content-type: text/plain');
    if (0) { // Sextant GML 3.2 
      $this->_c['wfsUrl'] = 'http://www.ifremer.fr/services/wfs/dcsmm';
      $this->getFeature('ms:DCSMM_SRM_TERRITORIALE_201806_L', [-10,41,16,51], 8);
    }
    elseif (0) { // Géo-IDE GML 2 N_VULNERABLE_ZSUP_041 polygones 
      $this->_c['wfsUrl'] = 'http://ogc.geo-ide.developpement-durable.gouv.fr/wxs?'
        .'map=/opt/data/carto/geoide-catalogue/1.4/org_38024/f19f7c24-c605-43f5-b4a0-74676524d00a.internet.map';
      $this->_c['wfsOptions'] = [
        'version'=> '1.0.0',
        'coordOrderInGml'=> 'lngLat',
      ];
      $this->getFeature('N_VULNERABLE_ZSUP_041', [-8.0,42.4,14.0,51.1], 8);
    }
    elseif (1) { // Géo-IDE GML 2 L_MUSEE_CHATEAU_041 point 
      $this->_c['wfsUrl'] = 'http://ogc.geo-ide.developpement-durable.gouv.fr/wxs?'
        .'map=/opt/data/carto/geoide-catalogue/1.4/org_38024/f31dbfdd-1038-451b-a539-668ac27b6526.internet.map';
      $this->_c['wfsOptions'] = [
        'version'=> '1.0.0',
        'coordOrderInGml'=> 'lngLat',
      ];
      $this->getFeature('L_MUSEE_CHATEAU_041', [-7.294921875,42.09822241119,13.3154296875,51.495064730144], 6);
    }
  }
  
  // affiche le résultat de la requête en GeoJSON
  function printAllFeatures(string $typename, array $bbox=[], int $zoom=-1, string $where=''): void {
    $numberMatched = $this->getNumberMatched($typename, $bbox, $where);
    if ($numberMatched <= 100) {
      $this->getFeature($typename, $bbox, $zoom, $where);
      return;
    }
    //$numberMatched = 12; POUR TESTS
    echo '{"type":"FeatureCollection","numberMatched":'.$numberMatched.',"features":[',"\n";
    $startindex = 0;
    $count = 100;
    while ($startindex < $numberMatched) {
      $this->getFeatureWoHd($typename, $bbox, $zoom, $where, $count, $startindex);
      if ($startindex<>0)
        echo ",\n";
      $startindex += $count;
    }
    echo "\n]}\n";
  }
};

if (basename(__FILE__)<>basename($_SERVER['SCRIPT_NAME'])) return;

ini_set('max_execution_time', 300);
ini_set('memory_limit', '512M');

if (!isset($_SERVER['PATH_INFO'])) {
  echo "<h3>Tests unitaires</h3><ul>\n";
  echo "<li><a href='$_SERVER[SCRIPT_NAME]/wfs2GeoJsonTest'>Test de la méthode WfsServerGml::wfs2GeoJson()</a>\n";
  echo "<li><a href='$_SERVER[SCRIPT_NAME]/getFeatureTest'>Test de la méthode WfsServerGml::getFeature()</a>\n";
  echo "</ul>\n";
  die();
}

$testMethod = substr($_SERVER['PATH_INFO'], 1);
$wfsDoc = ['wfsUrl'=>'test'];
$wfsServer = new WfsServerGml($wfsDoc);
$wfsServer->$testMethod();
