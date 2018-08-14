<?php
/*PhpDoc:
name: shapeds.inc.php
title: shapeds.inc.php - document définissant une série de données géo constituéen d'un ensemble de fichiers Shape
functions:
doc: <a href='/yamldoc/?action=version&name=shapeds.inc.php'>doc intégrée en Php</a>
*/
{
$phpDocs['shapeds.inc.php'] = <<<'EOT'
name: shapeds.inc.php
title: shapeds.inc.php - document définissant une série de données géo constituéen d'un ensemble de fichiers Shape
doc: |
  objectifs:
    - offrir une API d'accès aux objets géographiques

  Un ShapeDataset est composé de couches.
  Une couche est constitué d'objets géographiques ayant un schéma commun.
  Ces objets peuvent être stockés dans une table MySQL ou correspondre à une requête MySQL.
  
  Un document ShapeDataset contient:
    - des métadonnées génériques
    - des infos permettant de charger les SHP en base
    - la description du dictionnaire de couches (layers)
    
  Une couche peut être définie de 2 manières différentes:
    - soit par un champ path qui définit le (ou les) fichier (s) SHP correspondant, dans ce cas le(s) fichier(s) SHP
      est(sont) chargé(s) dans MySQL dans la table ayant pour nom l'id de la couche,
    - soit par un champ select de la forme "{lyrname} / {where}" qui définit une sélection dans la table {lyrname}
  En outre, une couche:
    - doit comporter un champ title qui est le titre de la couche pour un humain dans le contexte du document,
    - peut comporter un champ style qui définit le style Leaflet de la couche soit en JSOn soit en JavaScript
  En outre, une couche définie par un path peut comporter un champ filterOnZoom qui est un dictionnaire
      {zoomMin} : {where} | 'all'
    A un niveau de {zoom} donné, le filtre sera le dernier pour lequel {zoom} >= {zoomMin}.
    Si {filter} == 'all' alors aucune sélection n'est effectuée.

  Liste des points d'entrée de l'API:
  - /{database} : description de la base de données, y compris la liste de ses couches
  - /{database}/{layer} : description de la couche
  - /{database}/{layer}?{query} : requête sur la couche
    ex:
      /geodata/route500/commune?bbox=4.8,47,4.9,47.1&zoom=12
        retourne les objets inclus dans la boite
      /geodata/route500/noeud_commune?where=nom_comm like 'BEAUN%'
        retourne les objets dont la propriété nom_comm correspond à BEAUNE%
  - /{database}/{layer}/id/{id} : renvoie l'objet d'id {id}
  
  A FAIRE:
    - gestion des exceptions par renvoi d'un feature d'erreur
  
journal: |
  14/8/2018:
    - ajout possibilité d'afficher des données de l'autre côté de l'anti-méridien
  13/8/2018:
    - modif selectOnZoom renommé filterOnZoom
  12/8/2018:
    - nouvelle optimisation de la génération de GeoJSON à partir d'un WKT nécessaire pour ne_10m_physical/land
    - tranfert de la conversion WKT -> GeoJSON dans le package geometry
  10/8/2018:
    - ajout spécification du document
    - modif selectOnZoom
  6/8/2018:
    - extraction des champs non géométriques
    - mise en place de sélections d'objets dépendant du zoom
    - requête where
  5/8/2018:
    - première version opérationnelle
    - optimisation de la génération GeoJSON à la volée sans construction intermédiaire de FeatureCollection
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
require_once __DIR__.'/yamldoc.inc.php';

class ShapeDataset extends YamlDoc {
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
    //echo "GeoData::show($docid, $ypath)<br>\n";
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
    $yaml = ['title'=> 'carte '.$this->title];
    foreach ($this->layers as $lyrid => $layer) {
      $overlay = [
        'title'=> $layer['title'],
        'type'=> 'UGeoJSONLayer',
        'endpoint'=> "http://$_SERVER[SERVER_NAME]$_SERVER[SCRIPT_NAME]/$docuri/$lyrid",
      ];
      if (isset($layer['style']))
        $overlay['style'] = $layer['style'];
      $yaml['overlays'][$lyrid] = $overlay;
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
      elseif (isset($_GET['bbox']) && isset($_GET['zoom'])) {
        if (($_GET['bbox']=='{bbox}') && ($_GET['zoom']=='{zoom}'))
          return array_merge(['uri'=>$_SERVER['PATH_INFO']], $this->layers[$lyrname]);
        else
          return $this->queryByBbox($lyrname, $_GET['bbox'], $_GET['zoom']);
      }
      elseif (isset($_POST['bbox']) && isset($_POST['zoom']))
        return $this->queryByBbox($lyrname, $_POST['bbox'], $_POST['zoom']);
      elseif (isset($_GET['where']))
        return $this->queryByWhere($lyrname, $_GET['where']);
      else
        return array_merge(['uri'=>$_SERVER['PATH_INFO']], $this->layers[$lyrname]);
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
    //echo "describe $dbname.$table\n";
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
  // http://localhost/yamldoc/id.php/geodata/route500/commune?bbox=-2.7,47.2,2.8,49.7&zoom=8
  // http://localhost/yamldoc/id.php/geodata/route500/troncon_voie_ferree?bbox=-2.7,47.2,2.8,49.7&zoom=8
  // http://localhost/yamldoc/id.php/geodata/route500/noeud_commune?bbox=-2.7,47.2,2.8,49.7&zoom=8
  // http://localhost/yamldoc/id.php/geodata/route500/troncon_hydrographique?bbox=-1.97,46.68,-1.92,46.70
  // http://localhost/yamldoc/id.php/geodata/route500/noeud_commune?bbox=-0.7,47.2,0.8,49.7&zoom=8
  
  // http://localhost/yamldoc/id.php/geodata/ne_110m_physical/coastline?bbox=-95.8,-4.5,101.7,74.5&zoom=3
  
  
  // http://localhost/yamldoc/id.php/geodata/route500/noeud_commune?where=nom_comm%20like%20'BEAUN%'

  function queryByWhere(string $table, string $where) {
    MySql::open(require(__DIR__.'/mysqlparams.inc.php'));
    $dbname = $this->dbname();
    $props = $this->properties($table);
    if ($props)
      $props = implode(', ', $props).',';
    else
      $props = '';
    $sql = "select $props ST_AsText(geom) geom from $dbname.$table\n";
    $sql .= " where $where";
    //echo "sql=$sql<br>\n";
    //die("FIN ligne ".__LINE__);
    $this->queryAndShowInGeoJson($sql);
  }
  
  // retourne un polygon WKT à partir d'un bbox [lngMin, latMin, lngMax, latMax]
  static function bboxWkt(array $bbox) {
    return "POLYGON(($bbox[0] $bbox[1],$bbox[0] $bbox[3],$bbox[2] $bbox[3],$bbox[2] $bbox[1],$bbox[0] $bbox[1]))";
  }
  
  // version optimisée avec sortie par feature
  // affiche le GeoJSON au fur et à mesure, ne retourne pas au script appellant
  function queryByBbox(string $lyrname, string $bboxstr, string $zoom) {
    $bbox = explode(',', $bboxstr);
    if ((count($bbox)<>4) || !is_numeric($bbox[0]) || !is_numeric($bbox[1]) || !is_numeric($bbox[2]) || !is_numeric($bbox[3]))
      throw new Exception("Erreur dans ShapeDataset::queryByBbox() : bbox '$bboxstr' incorrect");
    if (!is_numeric($zoom))
      throw new Exception("Erreur dans ShapeDataset::queryByBbox() : zoom '$zoom' incorrect");
    $bboxwkt = self::bboxWkt($bbox);
    MySql::open(require(__DIR__.'/mysqlparams.inc.php'));
    $dbname = $this->dbname();
    
    if (isset($this->layers[$lyrname]['select'])) {
      //print_r($this->layers[$lyrname]);
      if (!preg_match("!^([^ ]+)( / (.*))?$!", $this->layers[$lyrname]['select'], $matches))
        throw new Exception("Erreur dans ShapeDataset::queryByBbox() : No match on ".$this->layers[$lyrname]['select']);
      $table = $matches[1];
      $where = isset($matches[3]) ? $matches[3] : '';
    }
    elseif (isset($this->layers[$lyrname]['filterOnZoom'])) {
      //echo "<pre>"; print_r($this->layers[$lyrname]);
      $filter = '';
      foreach ($this->layers[$lyrname]['filterOnZoom'] as $zoomMin => $filterOnZoom) {
        if ($zoom >= $zoomMin)
          $filter = $filterOnZoom;
      }
      if (!$filter) {
        header('Access-Control-Allow-Origin: *');
        header('Content-type: application/json');
        echo '{"type":"FeatureCollection","features": [],"nbfeatures": 0 }',"\n";
        if (1) {
          file_put_contents(
              'id.log.yaml',
              YamlDoc::syaml([
                'message'=> "Aucun filterOnZoom défini pour zoom $zoom",
              ]),
              FILE_APPEND
          );
        }
        die();
      }
      $table = $lyrname;
      $where = $filter <> 'all' ? $filter : '';
      if (1) { // log 
        file_put_contents(
            'id.log.yaml',
            YamlDoc::syaml([
              'zoom'=> $zoom,
              'table'=> $table,
              'where'=> $where,
            ]),
            FILE_APPEND
        );
      }
    }
    elseif (isset($this->layers[$lyrname]['path'])) {
      $table = $lyrname;
      $where = '';
    }
    else {
      throw new Exception("Erreur dans GeoData::queryByBbox() : cas non prévu pour la couche $lyrname");
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
    $sqls = [$sql, null, null];
    if ($bbox[2] > 180.0) { // la requête coupe l'antiméridien
      $bbox2 = [$bbox[0] - 360.0, $bbox[1], $bbox[2] - 360.0, $bbox[3]];
      $bboxwkt2 = self::bboxWkt($bbox2);
      $sql = "select $props ST_AsText(geom) geom from $dbname.$table\n";
      $sql .= " where ".($where?"$where and ":'')."MBRIntersects(geom, ST_GeomFromText('$bboxwkt2'))";
      $sqls[1] = $sql;
    }
    if ($bbox[0] < -180.0) { // la requête coupe l'antiméridien
      $bbox2 = [$bbox[0] + 360.0, $bbox[1], $bbox[2] + 360.0, $bbox[3]];
      $bboxwkt2 = self::bboxWkt($bbox2);
      $sql = "select $props ST_AsText(geom) geom from $dbname.$table\n";
      $sql .= " where ".($where?"$where and ":'')."MBRIntersects(geom, ST_GeomFromText('$bboxwkt2'))";
      $sqls[2] = $sql;
    }
    
    $this->queryAndShowInGeoJson($sqls);
  }
  
  // exécute les requêtes SQL et affiche le résultat en GeoJSON
  function queryAndShowInGeoJson(array $sqls) {
    header('Access-Control-Allow-Origin: *');
    header('Content-type: application/json');
    echo '{"type":"FeatureCollection","features": [',"\n";
    $nbFeatures = 0;
    foreach ($sqls as $n => $sql) {
      if (!$sql)
        continue;
      $shift = ($n == 0 ? 0.0 : ($n == 1 ? +360.0 : -360.0));
      foreach(MySql::query($sql) as $tuple) {
        $geom = $tuple['geom'];
        unset($tuple['geom']);
        $feature = ['type'=>'Feature', 'properties'=>$tuple, 'geometry'=> Wkt2GeoJson::convert($geom, $shift)];
        if ($nbFeatures <> 0)
          echo ",\n";
        echo json_encode($feature, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
        $nbFeatures++;
      }
    }
    echo "],\n\"nbfeatures\": $nbFeatures\n}\n";
    if (1) { // log
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
};
