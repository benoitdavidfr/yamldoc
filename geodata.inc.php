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
  
  A FAIRE:
  - autre requête que bbox ?
  - id ?
  
journal: |
  6/8/2018:
    - extraction des champs non géométriques
  5/8/2018:
    - première version opérationnelle
  4/8/2018:
    - optimisation du queryByBbox en temps de traitement
    - le json_encode() consomme beaucoup de mémoire, passage à 2 GB
    - je pourrais optimiser en générant directement le GeoJSON à la volée sans construire le FeatureCollection
  2/8/2018:
    - création
EOT;
}
require_once __DIR__.'/../ogr2php/feature.inc.php';
require_once __DIR__.'/../phplib/mysql.inc.php';

class GeoData extends YamlDoc {
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
  
  // retourne le nom de la base MySql dans laquelle les données sont stockées
  function dbname() {
    if (!$this->mysql_database)
      throw new Exception("Erreur dans GeoData::dbname() : champ mysql_database non défini");
    $mysqlServer = MySql::server();
    if (!isset($this->mysql_database[$mysqlServer])) 
      throw new Exception("Erreur dans GeoData::dbname() : champ mysql_database non défini pour $mysqlServer");
    return $this->mysql_database[$mysqlServer];
  }
  
  // fabrique la carte d'affichage des couches de la base
  function map(string $docuri) {
    $yaml = ['title'=> 'carte Route 500'];
    foreach ($this->layers as $lyrid => $layer) {
      $yaml['overlays'][$lyrid] = [
        'title'=> $layer['title'],
        'type'=> 'UGeoJSONLayer',
        'endpoint'=> "http://$_SERVER[SERVER_NAME]$_SERVER[SCRIPT_NAME]/$docuri/$lyrid",
      ];
    }
    $map = new Map($yaml);
    return $map;
  }
  
  // extrait le fragment défini par $ypath, utilisé pour générer un retour à partir d'un URI
  function extractByUri(string $docuri, string $ypath) {
    //echo "GeoData::extractByUri($docuri, $ypath)<br>\n";
    if (!$ypath || ($ypath=='/'))
      return $this->_c;
    elseif ($ypath == '/map') {
      //echo "fragment '/map'\n";
      return $this->map($docuri)->asArray();
    }
    elseif ($ypath == '/map/display') {
      $this->map($docuri)->display($docuri);
      die();
    }
    elseif (preg_match('!^/([^/]+)$!', $ypath, $matches)) {
      $lyrname = $matches[1];
      //echo "accès à la layer $lyrname\n";
      if (!isset($this->layers[$lyrname]))
        return null;
      elseif (isset($_GET['bbox']))
        return $this->queryByBbox($lyrname, $_GET['bbox']);
      elseif (isset($_POST['bbox']))
        return $this->queryByBbox($lyrname, $_POST['bbox']);
      elseif (isset($_GET['where']))
        return $this->queryByWhere($lyrname, $_GET['where']);
      else
        return ['title'=> $this->layers[$lyrname]['title']];
    }
    elseif (preg_match('!^/([^/]+)/properties$!', $ypath, $matches)) {
      $lyrname = $matches[1];
      //echo "accès à la layer $lyrname\n";
      MySql::open(require(__DIR__.'/mysqlparams.inc.php'));
      if (!isset($this->layers[$lyrname]))
        return null;
      elseif (isset($this->layers[$lyrname]['select'])) {
        if (!preg_match("!^([^ ]+) / (.*)$!", $this->layers[$lyrname]['select'], $matches))
          throw new Exception("No match on ".$this->layers[$lyrname]['select']);
        $table = $matches[1];
        return $this->properties($table);
      }
      else
        return $this->properties($lyrname);
    }
    elseif (preg_match('!^/([^/]+)/id/([^/]+)$!', $ypath, $matches)) {
      $lyrname = $matches[1];
      $id = $matches[2];
      echo "accès à la layer $lyrname, objet $id\n";
    }
    else
      return null;
  }

  // liste des propriétés d'une table hors geom
  function properties(string $table): array {
    $fields = [];
    $dbname = $this->dbname();
    foreach(MySql::query("describe $dbname.$table") as $tuple) {
      //echo "<pre>tuple="; print_r($tuple); echo "</pre>\n";
      if ($tuple['Type']<>'geometry')
        $fields[] = $tuple['Field'];
    }
    return $fields;
  }
  
  // version non optimisée désactivée
  function queryByBbox1(string $lyrname, string $bboxstr) {
    //4.8,47,4.9,47.1
    //POLYGON((-3.5667 48.19,-3.566 48.1902,-3.565 48.1899,-3.5667 48.19))
    $bbox = explode(',', $bboxstr);
    $bboxwkt = "POLYGON(($bbox[0] $bbox[1],$bbox[0] $bbox[3],$bbox[2] $bbox[3],$bbox[2] $bbox[1],$bbox[0] $bbox[1]))";
    MySql::open(require(__DIR__.'/mysqlparams.inc.php'));
    $dbname = $this->dbname();
    $sql = "select ST_AsText(geom) geom from $dbname.$lyrname where MBRIntersects(geom, ST_GeomFromText('$bboxwkt'))";
    $features = [];
    $nbFeatures = 0;
    foreach(MySql::query($sql) as $tuple) {
      if (0) {
        //echo "<pre>tuple="; print_r($tuple); echo "</pre>\n";
        $feature = new Feature(['properties'=>[], 'geometry'=> Geometry::fromWkt($tuple['geom'])]);
        //echo "feature=$feature<br>\n";
        $features[] = $feature->geojson();
      }
      else {
        $features[] = ['type'=>'Feature', 'properties'=>[], 'geometry'=> self::wkt2geojson($tuple['geom'])];
      }
      $nbFeatures++;
    }
    if (1) {
      file_put_contents(
          'id.log.yaml',
          YamlDoc::syaml([
            'version'=> 'sortie optimisée avec json_encode par FeatureCollection',
          ]),
          FILE_APPEND
      );
    }
    return ['type'=> 'FeatureCollection', 'features'=> $features, 'nbFeatures'=> $nbFeatures];
  }
  
  // URL de test:
  // http://127.0.0.1/yamldoc/id.php/geodata/route500/commune?bbox=-2.7,47.2,2.8,49.7&zoom=8
  // http://127.0.0.1/yamldoc/id.php/geodata/route500/troncon_voie_ferree?bbox=-2.7,47.2,2.8,49.7&zoom=8
  // http://127.0.0.1/yamldoc/id.php/geodata/route500/noeud_commune?bbox=-2.7,47.2,2.8,49.7&zoom=8
  // http://127.0.0.1/yamldoc/id.php/geodata/route500/troncon_hydrographique?bbox=-1.97,46.68,-1.92,46.70
  // http://127.0.0.1/yamldoc/id.php/geodata/route500/noeud_commune?bbox=-0.7,47.2,0.8,49.7&zoom=8
  
  
  // version optimisée avec sortie par feature
  // affiche le GeoJSON au fur et à mesure, ne retourne pas au script appellant
  function queryByBbox(string $lyrname, string $bboxstr) {
    $bbox = explode(',', $bboxstr);
    $bboxwkt = "POLYGON(($bbox[0] $bbox[1],$bbox[0] $bbox[3],$bbox[2] $bbox[3],$bbox[2] $bbox[1],$bbox[0] $bbox[1]))";
    MySql::open(require(__DIR__.'/mysqlparams.inc.php'));
    $dbname = $this->dbname();
    if (isset($this->layers[$lyrname]['select'])) {
      //print_r($this->layers[$lyrname]);
      //limite_administrative / nature='Limite côtière'
      if (!preg_match("!^([^ ]+) / (.*)$!", $this->layers[$lyrname]['select'], $matches))
        throw new Exception("No match on ".$this->layers[$lyrname]['select']);
      $table = $matches[1];
      $where = $matches[2];
    }
    else {
      $table = $lyrname;
      $where = '';
    }
    
    $props = $this->properties($table);
    if ($props)
      $props = implode(', ', $props).',';
    else
      $props = '';
    $sql = "select $props ST_AsText(geom) geom from $dbname.$table\n";
    $sql .= " where ".($where?"$where and ":'')."MBRIntersects(geom, ST_GeomFromText('$bboxwkt'))";
    //echo "sql=$sql<br>\n";
    //die("FIN ligne ".__LINE__);
    header('Access-Control-Allow-Origin: *');
    header('Content-type: application/json');
    echo '{"type":"FeatureCollection","features": [',"\n";
    $nbFeatures = 0;
    foreach(MySql::query($sql) as $tuple) {
      $geom = $tuple['geom'];
      unset($tuple['geom']);
      $feature = ['type'=>'Feature', 'properties'=>$tuple, 'geometry'=> self::wkt2geojson($geom)];
      if ($nbFeatures <> 0)
        echo ",\n";
      echo json_encode($feature, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
      $nbFeatures++;
    }
    echo "],\n\"nbfeatures\": $nbFeatures\n}\n";
    if (1) {
      global $t0;
      file_put_contents(
          'id.log.yaml',
          YamlDoc::syaml([
            'version'=> 'sortie optimisée avec json_encode par feature',
            'duration'=> microtime(true) - $t0,
            'nbFeatures'=> $nbFeatures,
          ]),
          FILE_APPEND
      );
    }
    die();
  }
  
  // génère à la volée un GeoJSON à partir d'un WKT
  static function wkt2geojson(string $wkt) {
    //echo "wkt=$wkt<br>\n";
    if (substr($wkt, 0, 6)=='POINT(') {
      $wkt = substr($wkt, 6);
      return ['type'=>'Point', 'coordinates'=> self::parseListPoints($wkt)[0]];
    }
    elseif (substr($wkt, 0, 11)=='LINESTRING(') {
      $wkt = substr($wkt, 11);
      return ['type'=>'LineString', 'coordinates'=> self::parseListPoints($wkt)];
    }
    elseif (substr($wkt, 0, 8)=='POLYGON(') {
      $wkt = substr($wkt, 8);
      return ['type'=>'Polygon', 'coordinates'=> self::parsePolygon($wkt)];
    }
    elseif (substr($wkt, 0, 13)=='MULTIPOLYGON(') {
      $wkt = substr($wkt, 13);
      return ['type'=>'MultiPolygon', 'coordinates'=> self::parseMultiPolygon($wkt)];
    }
    else
      die("erreur GeoData::wkt2geojson(), wkt=$wkt");
  }
  
  // traite le MultiPolygon
  static function parseMultiPolygon(string &$wkt): array {
    //echo "parseMultiPolygon($wkt)<br>\n";
    $geom = [];
    $n = 0;
    while ($wkt) {
      if (substr($wkt, 0, 1)=='(') {
        $wkt = substr($wkt, 1);
        $geom[$n] = self::parsePolygon($wkt);
        $n++;
        //echo "left parseMultiPolygon 1=$wkt<br>\n";
        if (substr($wkt, 0, 1) == ',') {
          $wkt = substr($wkt, 1);
          //echo "left parseMultiPolygon 2=$wkt<br>\n";
        }
      }
      elseif ($wkt==')')
        return $geom;
      else {
        //echo "left parseMultiPolygon 3=$wkt<br>\n";
        die("ligne ".__LINE__);
      }
    }
    return $geom;
  }
  
  // consomme une liste de points entourée d'une paire de parenthèses + parenthèse fermante
  static function parsePolygon(string &$wkt): array {
    $geom = [];
    $n = 0;
    while($wkt) {
      if (substr($wkt, 0, 1)=='(') {
        $wkt = substr($wkt, 1);
        $geom[$n++] = self::parseListPoints($wkt);
      }
      elseif (substr($wkt, 0, 3)=='),(') {
        $wkt = substr($wkt, 3);
        $geom[$n++] = self::parseListPoints($wkt);
      }
      elseif (substr($wkt, 0, 2)=='))') {
        $wkt = substr($wkt, 2);
        //echo "left parsePolygon 1=$wkt<br>\n";
        return $geom;
      }
      else {
        echo "left parsePolygon 2=$wkt<br>\n";
        die("ligne ".__LINE__);
      }
    }
    echo "left parsePolygon 3=$wkt<br>\n";
    die("ligne ".__LINE__);
  }
  
  // consomme une liste de points sans parenthèses
  static function parseListPoints(string &$wkt): array {
    $points = [];
    $pattern = '!^(-?\d+(\.\d+)?) (-?\d+(\.\d+)?),?!';
    while(preg_match($pattern, $wkt, $matches)) {
      //echo "matches="; print_r($matches); echo "<br>";
      $points[] = [$matches[1], $matches[3]];
      $wkt = preg_replace($pattern, '', $wkt, 1);
    }
    //echo "left parseListPoints=$wkt<br>\n";
    return $points;
  }
};