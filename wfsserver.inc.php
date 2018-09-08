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
  La classe WfsServerJson expose différentes méthodes utilisant un serveur WFS capable de retour du JSON.
  
  Outre les champs de métadonnées, le document doit définir les champs suivants:
  
    - urlWfs: fournissant l'URL du serveur à compléter avec les paramètres,
  
  Il peut aussi définir les champs suivants:
  
    - referer: définissant le referer à transmettre à chaque appel du serveur.

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
    - /{document}/ft/{typeName}/getFeature?bbox={bbox}&where={where} : affiche en GeoJSON les objets
      correspondant à la requête définie par bbox et where, limité à 1000 objets
    - /{document}/ft/{typeName}/getAllFeatures?bbox={bbox}&where={where} : affiche en GeoJSON les objets
      correspondant à la requête définie par bbox et where, utilise la pagination si plus de 100 objets
    
  Le document http://localhost/yamldoc/?doc=geodata/igngpwfs permet de tester cette classe.
  
  Sur le serveur WFS IGN:
  
    - un DescribeFeatureType sans paramètre typename n'est pas utilisable
      - en JSON, le schema de chaque type est bien fourni mais les noms de type ne comportent pas l'espace de noms,
        générant ainsi un risque de confusion entre typename
      - en XML, le schéma de chaque type n'est pas fourni
      - la solution retenue consiste à effectuer un appel JSON par typename et à le bufferiser en JSON 
  
journal:
  4/9/2018:
    - remplacement du prefixe t par ft pour featureType
    - refonte de la gestion du cache indépendamment du stockage du document car le doc peut être volatil
    - ajout de la récupération du nom de la propriété géométrique qui n'est pas toujours le même
  3/9/2018:
    - ajout d'une classe WfsServer4326Gml implémentant les requêtes pour un serveur WFS EPSG:4326 + GML
    en cours
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
abstract class WfsServer extends YamlDoc {
  static $log = __DIR__.'/wfs.log.yaml'; // nom du fichier de log ou false pour pas de log
  static $capCache = __DIR__.'/wfscapcache'; // nom du répertoire dans lequel sont stockés les fichiers XML
                                           // de capacités ainsi que les DescribeFeatureType en json
  protected $_c; // contient les champs
  
  // crée un nouveau doc, $yaml est le contenu Yaml externe issu de l'analyseur Yaml
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
      throw new Exception("Erreur dans WfsServerJson::decodeBbox() : bbox '$bboxstr' incorrect");
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
      $bbox = isset($_GET['bbox']) ? $_GET['bbox'] : (isset($_POST['bbox']) ? $_POST['bbox'] : '');
      $bbox = self::decodeBbox($bbox);
      $where = isset($_GET['where']) ? $_GET['where'] : (isset($_POST['where']) ? $_POST['where'] : '');
      return [ 'numberMatched'=> $this->getNumberMatched($typeName, $bbox, $where) ];
    }
    elseif (preg_match('!^/ft/([^/]+)/getFeature$!', $ypath, $matches)) {
      $typeName = $matches[1];
      header('Content-type: application/json');
      $bbox = isset($_GET['bbox']) ? $_GET['bbox'] : (isset($_POST['bbox']) ? $_POST['bbox'] : '');
      $bbox = self::decodeBbox($bbox);
      $where = isset($_GET['where']) ? $_GET['where'] : (isset($_POST['where']) ? $_POST['where'] : '');
      //echo "where=$where\n";
      echo $this->getFeature($typeName, $bbox, $where);
      die();
    }
    elseif (preg_match('!^/ft/([^/]+)/getAllFeatures$!', $ypath, $matches)) {
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
    $url = $this->urlWfs.'?SERVICE=WFS';
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
    if ($this->referer) {
      $referer = $this->referer;
      //echo "referer=$referer\n";
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
    //echo "urlWfs=",$this->urlWfs,"<br>\n";
    $filepath = self::$capCache.'/wfs'.md5($this->urlWfs).'-cap.xml';
    if ((!$force) && file_exists($filepath))
      return file_get_contents($filepath);
    else {
      $cap = $this->query(['request'=> 'GetCapabilities']);
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
    //echo "<a href='/yamldoc/wfscapcache/",md5($this->urlWfs),".xml'>capCache</a><br>\n";
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
};

// classe simplifiant l'envoi de requêtes WFS capable de fournir des données GeoJSON
class WfsServerJson extends WfsServer {
  
  function describeFeatureType(string $typeName) {
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
  function getFeature(string $typename, array $bbox=[], string $where='', int $count=100, int $startindex=0): string {
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

// Essai d'une classe implémentant les requêtes pour un serveur WFS ne parlant pas JSON
class WfsServerGml extends WfsServer {
  private $xsltProcessors=[];
  
  /* {
<?xml version='1.0' encoding="UTF-8" ?>
<schema
   targetNamespace="http://mapserver.gis.umn.edu/mapserver" 
   xmlns:ms="http://mapserver.gis.umn.edu/mapserver" 
   xmlns:xsd="http://www.w3.org/2001/XMLSchema"
   xmlns="http://www.w3.org/2001/XMLSchema"
   xmlns:gml="http://www.opengis.net/gml/3.2"
   elementFormDefault="qualified" version="0.1" >

  <import namespace="http://www.opengis.net/gml/3.2"
          schemaLocation="http://schemas.opengis.net/gml/3.2.1/gml.xsd" />

  <element name="ESPACES_TERRESTRES_P" 
           type="ms:ESPACES_TERRESTRES_PType" 
           substitutionGroup="gml:AbstractFeature" />

  <complexType name="ESPACES_TERRESTRES_PType">
    <complexContent>
      <extension base="gml:AbstractFeatureType">
        <sequence>
          <element name="msGeometry" type="gml:GeometryPropertyType" minOccurs="0" maxOccurs="1"/>
          <element name="OBJECTID" minOccurs="0" type="string"/>
          <element name="objet" minOccurs="0" type="string"/>
          <element name="Shape_Leng" minOccurs="0" type="string"/>
          <element name="Shape_Area" minOccurs="0" type="string"/>
        </sequence>
      </extension>
    </complexContent>
  </complexType>

</schema>
  } */
  /*{
    "elementFormDefault": "qualified",
    "targetNamespace": "http://wxs.ign.fr/datastore/BDCARTO_BDD_WLD_WGS84G",
    "targetPrefix": "BDCARTO_BDD_WLD_WGS84G",
    "featureTypes": [
        {
            "typeName": "troncon_hydrographique",
            "properties": [
                {
                    "name": "id",
                    "maxOccurs": 1,
                    "minOccurs": 1,
                    "nillable": false,
                    "type": "xsd:string",
                    "localType": "string"
                },
                {
                    "name": "the_geom",
                    "maxOccurs": 1,
                    "minOccurs": 0,
                    "nillable": true,
                    "type": "gml:MultiLineString",
                    "localType": "MultiLineString"
                }
            ]
        }
    ]
}*/
  function describeFeatureType(string $typeName) {
    $filepath = self::$capCache.'/wfs'.md5($this->wfsUrl."/$typeName").'-ft.xml';
    if (is_file($filepath)) {
      $ftXml = file_get_contents($filepath);
    }
    else {
      $ftXml = $this->query([
        'VERSION'=> '2.0.0',
        'REQUEST'=> 'DescribeFeatureType',
        //'OUTPUTFORMAT'=> 'application/json',
        'OUTPUTFORMAT'=> rawurlencode('text/xml; subtype=gml/3.2'),
        'TYPENAME'=> $typeName,
      ]);
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
        if (preg_match('!^gml:!', $property['type']))
          return $property['name'];
      }
    }
    return null;
  }
  
  // retourne le nbre d'objets correspondant au résultat de la requête
  function getNumberMatched(string $typename, array $bbox=[], string $where=''): int {
    $request = [
      'VERSION'=> '2.0.0',
      'REQUEST'=> 'GetFeature',
      'TYPENAMES'=> $typename,
      'SRSNAME'=> 'EPSG:4326',
      'RESULTTYPE'=> 'hits',
    ];
    $bbox4326 = [$bbox[1], $bbox[0], $bbox[3], $bbox[2]]; // passage en EPSG:4326
    $request['BBOX'] = implode(',',$bbox4326);
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
    $xslsrc = file_get_contents(__DIR__.'/wfs2geojson.xsl');
    $targetNamespaceDef = "xmlns:$targetPrefix=\"$targetNamespace\"";
    $xslsrc = str_replace('{targetNamespaceDef}', $targetNamespaceDef, $xslsrc);
    $xslsrc = str_replace('{xslProperties}', $xsl_properties, $xslsrc);
    //die($xslsrc);
    return $xslsrc;
  }
  
  // effectue la transformation de pseudo GeoJSON en un GeoJSON
  function pseudo2GeoJson(string $pseudo, string $format) {
    //die($pseudo);
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
        $pos = $this->decodeFeature($pseudo, $pos, $format);
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
    return 'OK';
  }
  
  function decodeFeature(string $pseudo, int $pos, string $format): int {
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
      $pos = $this->decodeMultiCurve($pseudo, $pos, $format);
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
      $pos = $this->decodeMultiSurface($pseudo, $pos, $format);
      if ($format == 'json')
        echo "    ]\n",
          "  }\n";
      return $pos;
    }
    else {
      die("Erreur dans decodeFeature pos=$pos sur ".substr($pseudo,$pos, 1000)." line ".__LINE__);
    }
  }
  
  function decodeMultiCurve(string $pseudo, int $pos, string $format): int {
    $nols = 0;
    while (($pos != -1) && substr($pseudo, $pos, 21) == "      - LineString2: ") {
      $pos += 21;
      if ($format == 'verbose')
        echo "LineString2 détectée pos=$pos\n";
      elseif ($format == 'json')
        echo $nols?",\n":'',"      [";
      $pos = $this->decodeListPoints2($pseudo, $pos, $format);
      if ($format == 'json')
        echo "]";
      $nols++;
    }
    if ($format == 'json')
        echo "\n";
    return $pos;
  }
  
  function decodeMultiSurface(string $pseudo, int $pos, string $format): int {
    $nosurf = 0;
    while (($pos != -1) && substr($pseudo, $pos, 18) == "      - Polygon2:\n") {
      $pos += 18;
      if ($format == 'verbose')
        echo "Polygon2 détecté pos=$pos\n";
      elseif ($format == 'json')
        echo $nosurf?",\n":'',"     [\n";
      $pos = $this->decodePolygon2($pseudo, $pos, $format);
      if ($format == 'json')
        echo "     ]";
      $nosurf++;
    }
    if ($format == 'json')
        echo "\n";
    return $pos;
  }
  
  function decodePolygon2(string $pseudo, int $pos, string $format): int {
    if (substr($pseudo, $pos, 20) != "          exterior: ")
      throw new Exception("Erreur dans decodePolygon2 exterior non détecté\n");
    $pos += 20;
    if ($format == 'verbose')
      echo "Polygon2 exterior détecté\n";
    elseif ($format == 'json')
      echo "      [";
    $pos = $this->decodeListPoints2($pseudo, $pos, $format);
    if (substr($pseudo, $pos, 20) == "          interior:\n") {
      $pos += 20;
      if ($format == 'verbose')
        echo "Polygon2 interior détecté\n";
      while (substr($pseudo, $pos, 12) == "          - ") {
        $pos += 12;
        if ($format == 'verbose')
          echo "Polygon2interior LineString2 détectée\n";
        elseif ($format == 'json')
          echo "],\n      [";
        $pos = $this->decodeListPoints2($pseudo, $pos, $format);
      }
    }
    if ($format == 'json')
      echo "]\n";
    return $pos;
  }
  
  // decode une liste de points de 2 coord dans pseudo à partir de pos, renvoie la position après la fin de ligne ou -1
  function decodeListPoints2(string $pseudo, int $pos, string $format): int {
    $nbpts = 0;
    $poseol = strpos($pseudo, "\n", $pos);
    while (1) {
      $poswhite = strpos($pseudo, ' ', $pos);
      //echo "poswhite=$poswhite, poseol=$poseol, pos=$pos\n";
      if (($poswhite === false) || (($poseol !== FALSE) && ($poswhite > $poseol))) {
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
      //echo "  pos=$pos, nopt=$nbpts, x=$x, y=$y\n";
      if ($format=='json')
        echo $nbpts?',':'',"[$x,$y]";
        //echo '';
      $nbpts++;
    }
    if ($format=='verbose')
      echo "$nbpts points détectés\n";
    if ($poseol === FALSE)
      return -1;
    else
      return $poseol + 1;
  }

  // effectue la transformation du Gml en un pseudo GeoJSON puis affiche les Feature en JSON
  // l'affichage doit être encadré par '{"type":"FeatureCollection","features":' et ']}'
  // Cela permet d'afficher une seule FeatureCollection en cas de pagination
  function wfs2GeoJson(string $typename, string $xmlstr): void {
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
    $format = 'verbose';
    $format = 'json';
    $this->pseudo2GeoJson($pseudo, $format);
  }
  
  // Test unitaire de la méthode WfsServerGml::wfs2GeoJson()
  function wfs2GeoJsonTest() {
    $this->_c['urlWfs'] = 'http://www.ifremer.fr/services/wfs/dcsmm';
    $queries = [
      [ 'title'=> "ESPACES_TERRESTRES_P MultiSurface GML 3.2.1 EPSG:4326",
        'params'=> [
          'VERSION'=> '2.0.0', 'REQUEST'=> 'GetFeature', 'TYPENAMES'=> 'ms:ESPACES_TERRESTRES_P', 'RESULTTYPE'=> 'results',
          'SRSNAME'=> 'EPSG:4326', 'BBOX'=> '41,-10,51,16',
          'OUTPUTFORMAT'=> rawurlencode('text/xml; subtype=gml/3.2.1'), 'COUNT'=> '2',
        ],
      ],
      [ 'title'=> "DCSMM_SRM_TERRITORIALE_201806_L MultiCurve renvoyant du GML 3.2.1 en EPSG:4326",
        'params'=> [
          'VERSION'=> '2.0.0', 'REQUEST'=> 'GetFeature', 'TYPENAMES'=> 'ms:DCSMM_SRM_TERRITORIALE_201806_L',
          'RESULTTYPE'=> 'results', 'SRSNAME'=> 'EPSG:4326', 'BBOX'=> '41,-10,51,16',
          'OUTPUTFORMAT'=> rawurlencode('text/xml; subtype=gml/3.2.1'), 'COUNT'=> '2',
        ],
      ],
    ];
    if (!isset($_GET['action'])) {
      echo "<h3>Queries</h3><ul>\n";
      foreach ($queries as $num => $query) {
        $url = $this->url($query['params']);
        echo "<li>$query[title] ",
          "(<a href='$url'>url</a>, ", // appel de l'URL du WFS
          "<a href='?action=wfs&query=$num'>wfs</a>, ", // appel du WFS et stockage
          "<a href='?action=xml&query=$num'>xml</a>, ", // si en cache affiche
          "<a href='?action=xsl&query=$num'>xsl</a>, ", // affiche la feuille de style
          "<a href='?action=geojson&query=$num'>GeoJSON</a>)\n"; // transforme en GeoJSON
      }
      echo "</ul>\n",
        "<a href='?action=ex0.txt'>Appel de pseudo2GeoJson() sur le fichier ex0.txt</a><br>\n";
      die();
    }

    if ($_GET['action']=='xsl') {
      header('Content-type: text/xml');
      echo $this->xslForGeoJson($queries[$_GET['query']]['params']['TYPENAMES']);
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

    $filepath = __DIR__.$_SERVER['PATH_INFO']."/$_GET[query].xml";
    if ($_GET['action']=='wfs') {
      $getrecords = $this->query($queries[$_GET['query']]['params']);
      file_put_contents($filepath, $getrecords);
      header('Content-type: text/xml');
      die($getrecords);
    }
    
    if (is_file($filepath))
      $getrecords = file_get_contents($filepath);
    else {
      $getrecords = $this->query($queries[$_GET['query']]['params']);
      file_put_contents($filepath, $getrecords);
    }
    if ($_GET['action']=='xml') {
      header('Content-type: text/xml');
      die($getrecords);
    }
    if ($_GET['action']=='geojson') {
      header('Content-type: application/json');
      //header('Content-type: text/plain');
      echo "{\"type\":\"FeatureCollection\",\"features\":[\n";
      $this->wfs2GeoJson($queries[$_GET['query']]['params']['TYPENAMES'], $getrecords);
      echo "]}\n";
      die();
    }
    echo "action $_GET[action] inconnue\n";
  }
  
  // retourne le résultat de la requête en GeoJSON
  function getFeature(string $typename, array $bbox=[], string $where='', int $count=100, int $startindex=0): string {
    $request = [
      'VERSION'=> '2.0.0',
      'REQUEST'=> 'GetFeature',
      'TYPENAMES'=> $typename,
      'OUTPUTFORMAT'=> rawurlencode('application/gml+xml; version=3.2'),
      'SRSNAME'=> 'EPSG:4326',
      'COUNT'=> $count,
      'STARTINDEX'=> $startindex,
    ];
    $bbox4326 = [$bbox[1], $bbox[0], $bbox[3], $bbox[2]]; // passage en EPSG:4326
    $request['BBOX'] = implode(',',$bbox4326);
    return $this->query($request);
  }
  
  // affiche le résultat de la requête en GeoJSON
  function printAllFeatures(string $typename, array $bbox=[], string $where=''): void {
    $numberMatched = $this->getNumberMatched($typename, $bbox, $where);
    if ($numberMatched <= 100) {
      echo self::gml2GeoJson($this->getFeature($typename, $bbox, $where));
      return;
    }
    //$numberMatched = 12; POUR TESTS
    echo '{"type":"FeatureCollection","numberMatched":'.$numberMatched.',"features":[',"\n";
    $startindex = 0;
    $count = 100;
    while ($startindex < $numberMatched) {
      $gml = $this->getFeature($typename, $bbox, $where, $count, $startindex);
      echo self::gml2GeoJson($gml);
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

if (basename(__FILE__)<>basename($_SERVER['SCRIPT_NAME'])) return;

ini_set('max_execution_time', 300);
// 30 -> 1_959_681
// 120 -> 3_004_141
// 300 -> 13 978 083

// Test unitaire de la méthode WfsServerGml::wfs2GeoJson()
//echo "<pre>"; print_r($_SERVER); echo "</pre>\n";

if (!isset($_SERVER['PATH_INFO'])) {
  echo "<h3>Tests unitaires</h3><ul>\n";
  echo "<li><a href='$_SERVER[SCRIPT_NAME]/wfs2GeoJsonTest'>Test de la méthode WfsServerGml::wfs2GeoJson()</a>\n";
  echo "</ul>\n";
  die();
}


if ($_SERVER['PATH_INFO'] == '/wfs2GeoJsonTest') {
  $wfsDoc = [];
  $wfsServer = new WfsServerGml($wfsDoc);
  $wfsServer->wfs2GeoJsonTest();
}
