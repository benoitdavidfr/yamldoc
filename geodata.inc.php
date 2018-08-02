<?php
/*PhpDoc:
name: geodata.inc.php
title: geodata.inc.php - sous-classe de documents pour la gestion des données géographiques
functions:
doc: <a href='/yamldoc/?action=version&name=geodata.inc.php'>doc intégrée en Php</a>
*/
{
$phpDocs['geodata.inc.php'] = <<<'EOT'
name: geodata.inc.php
title: geodata.inc.php - sous-classe GeoData pour la gestion des données géographiques
doc: |
  objectifs:
    - offrir une API d'accès aux objets géographiques

  Un GeoData est composé de couches. Une couche peut être réelle ou virtuelle.
  Une couche réelle est constitué d'objets géographiques ayant un schéma commun.
  Ces objets peuvent être stockés par exemple dans une table MySQL, correspondre à une requête
  ou être exposés par un web-service externe.
  Une couche virtuelle correspond à différentes autres couches d'autres bases en fonction du niveau de zoom. 
  Par exemple, la couche vituelle coastline de la base mult correspond en fonction du zoom aux lignes de côte
  de Natural Earth, de GéoFLA, de Route 500, de la BDCarto ou de la BD Topo.
  Les objets géographiques d'une couche virtuelle n'ont pas forcément le même schéma.

  Un GeoData peut être découpé en différents jeux de données (dataset) en fonction du territoire. 
  Par exemple la BDTopo est découpée par département. Ce découpage est transparent pour l'utilisation.
  
  Un document GeoData contient:
    - des métadonénes génériques
    - des infoss permettant de charger les SHP en bases
    - la description des datasets correspondant à un éventuel découpage
    - la description des couches (layers)

  Liste des points d'entrée de l'API:
  - /{database} : description de la base de données, y compris la liste de ses couches
  - /{database}/{layer} : description de la couche
  - /{database}/{layer}?{query} : requête sur la couche
    ex:
      /geodata/route500/commune?bbox=4.8,47,4.9,47.1&zoom=12
        retourne les objets inclus dans la boite
      /geodata/route500/noeud_commune?where=nom_comm~BEAUN%
        retourne les objets dont la propriété nom_comm correspond à BEAUNE%
  - /{database}/{layer}/id/{id} : renvoie l'objet d'id {id}
    
journal: |
  2/8/2018:
  - création
EOT;
}
require_once __DIR__.'/../ogr2php/feature.inc.php';

class GeoData extends YamlDoc {
  static $mysqli = null; // handle MySQL
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
    echo "GeoData::show($docid, $ypath)<br>\n";
    if (!$ypath || ($ypath=='/'))
      showDoc($docid, $this->_c);
    else
      showDoc($docid, $this->extract($ypath));
    //echo "<pre>"; print_r($this->_c); echo "</pre>\n";
  }
  
  // décapsule l'objet et retourne son contenu sous la forme d'un array
  // ce décapsulage ne s'effectue qu'à un seul niveau
  // Permet de maitriser l'ordre des champs
  function asArray() { return $this->_c; }

  // extrait le fragment du document défini par $ypath
  // Renvoie un array ou un objet qui sera ensuite transformé par YamlDoc::replaceYDEltByArray()
  // Utilisé par YamlDoc::yaml() et YamlDoc::json()
  // Evite de construire une structure intermédiaire volumineuse avec asArray()
  function extract(string $ypath) {
    return YamlDoc::sextract($this->_c, $ypath);
  }
    
  // extrait le fragment défini par $ypath, utilisé pour générer un retour à partir d'un URI
  function extractByUri(string $docuri, string $ypath) {
    //echo "GeoData::extractByUri($docuri, $ypath)<br>\n";
    if (!$ypath || ($ypath=='/'))
      return $this->_c;
    elseif (preg_match('!^/([^/]+)$!', $ypath, $matches)) {
      $lyrname = $matches[1];
      //echo "accès à la layer $lyrname\n";
      if (!isset($this->layers[$lyrname]))
        return null;
      elseif (isset($_GET['bbox']))
        return $this->queryByBbox($lyrname, $_GET['bbox']);
      elseif (isset($_GET['where']))
        return $this->queryByWhere($lyrname, $_GET['where']);
      else
        return ['title'=> $this->layers[$lyrname]['title']];
    }
    elseif (preg_match('!^/([^/]+)/id/([^/]+)$!', $ypath, $matches)) {
      $lyrname = $matches[1];
      $id = $matches[2];
      echo "accès à la layer $lyrname, objet $id\n";
    }
    else
      return null;
  }
  
  // ouvre une connexion avec MySQL, enregistre la variable en variable statique de classe et la renvoie
  // param sous la forme mysql://{user}:{passwd}@{host}/{database}
  static function openMySQL() {
    require_once __DIR__.'/mysqlparams.inc.php';
    $param = mysqlParams();
    if (!preg_match('!^mysql://([^:]+):([^@]+)@([^/]+)/(.*)$!', $param, $matches))
      throw new Exception("param \"".$param."\" incorrect");
    //print_r($matches);
    self::$mysqli = new mysqli($matches[3], $matches[1], $matches[2], $matches[4]);
    if (mysqli_connect_error())
  // La ligne ci-dessous ne s'affiche pas correctement si le serveur est arrêté !!!
  //    throw new Exception("Connexion MySQL impossible pour $server_name : ".mysqli_connect_error());
      throw new Exception("Connexion MySQL impossible sur $param");
    if (!self::$mysqli->set_charset ('utf8'))
      throw new Exception("mysqli->set_charset() impossible : ".self::$mysqli->error);
    return self::$mysqli;
  }
  
  // exécute une requête MySQL, soulève une exception en cas d'erreur, renvoie le résultat
  static function query(string $sql) {
    if (!($result = self::$mysqli->query($sql)))
      throw new Exception("Req. \"$sql\" invalide: ".self::$mysqli->error);
    return $result;
  }

  function queryByBbox(string $lyrname, string $bboxstr) {
    //4.8,47,4.9,47.1
    //POLYGON((-3.5667 48.19,-3.566 48.1902,-3.565 48.1899,-3.5667 48.19))
    $bbox = explode(',', $bboxstr);
    $bboxwkt = "POLYGON(($bbox[0] $bbox[1],$bbox[0] $bbox[3],$bbox[2] $bbox[3],$bbox[2] $bbox[1],$bbox[0] $bbox[1]))";
    $sql = "select ST_AsText(geom) geom from route500.$lyrname where MBRIntersects(geom, ST_GeomFromText('$bboxwkt'))";
    //echo "sql=$sql<br>\n";
    self::openMySQL();
    $result = self::query($sql);
    $fcoll = [
      'type'=> 'FeatureCollection',
      'features'=> [],
    ];
    $features = [];    
    while ($tuple = $result->fetch_array(MYSQLI_ASSOC)) {
      //echo "<pre>tuple="; print_r($tuple); echo "</pre>\n";
      $feature = new Feature(['properties'=>[], 'geometry'=> Geometry::fromWkt($tuple['geom'])]);
      //echo "feature=$feature<br>\n";
      $features[] = $feature->geojson();
    }
    return ['type'=> 'FeatureCollection', 'features'=> $features];
  }
};