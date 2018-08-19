<?php
/*PhpDoc:
name: vectords.inc.php
title: vectords.inc.php - document définissant une série de données géo constituée d'un ensemble de couches vecteur
functions:
doc: <a href='/yamldoc/?action=version&name=vectords.inc.php'>doc intégrée en Php</a>
*/
{
$phpDocs['vectords.inc.php'] = <<<'EOT'
name: vectords.inc.php
title: vectords.inc.php - document définissant une série de données géo constituéen d'un ensemble de couches vecteur
doc: |
  objectifs:
    - offrir une API d'accès aux objets géographiques

  Une SD vecteur (VectorDataset) est composée de couches vecteur, chacune correspondant à une FeatureCollection
  [GeoJSON](https://tools.ietf.org/html/rfc7946) ;
  chaque couche est composée d'objets vecteur, cad des Feature GeoJSON.  
  Un document décrivant une SD vecteur, d'une part, peut s'afficher et, d'autre part, expose une API
  constituée des 6 points d'entrée suivants :
  
    1. {docid} : description de la SD en JSON (ou en Yaml), y compris la liste de ses couches
      ([exemple de Route500](id.php/geodata/route500),
      [en Yaml](id.php/geodata/route500?format=yaml)),
    2. {docid}/{lyrname} : description de la couche en JSON (ou en Yaml), cette URI identifie la couche
      ([exemple de la couche commune de Route500](id.php/geodata/route500/commune)),
    3. {docid}/{lyrname}?{query} : requête sur la couche renvoyant un FeatureCollection GeoJSON  
      où {query} peut être:
        - bbox={lngMin},{latMin},{lngMax},{latMax}&zoom={zoom}
          ([exemple](id.php/geodata/route500/commune?bbox=-2.71,47.21,2.72,47.22&zoom=10)),
        - where={critère SQL/CQL}
          ([exemple des communes dont le nom commence par
          BEAUN](id.php/geodata/route500/noeud_commune?where=nom_comm%20like%20'BEAUN%')),
    4. {docid}/{lyrname}/id/{id} : renvoie l'objet d'id {id} (A FAIRE)
    5. {docid}/map : renvoie le document JSON décrivant la carte standard affichant la SD
      ([exemple de la carte Route500](id.php/geodata/route500/map)),
    6. {docid}/map/display : renvoie le code HTML d'affichage de la carte standard affichant la SD
      ([exemple d'affichage de la carte Route500](id.php/geodata/route500/map/display)),

  Un document VectorDataset contient:
    - des métadonnées génériques
    - des infos générales par exemple permettant de charger les SHP en base
    - la description du dictionnaire de couches (layers)

  Une couche vecteur peut être définie de 4 manières différentes:
  
    1. elle correspond à une (ou plusieurs) couche(s) OGR chargée(s) dans une table MySQL ;
      dans ce cas la couche doit comporter un champ *ogrPath* définissant le (ou les) couche(s) OGR correspondante(s),
      le(s) couche(s) OGR est(sont) chargée(s) dans MySQL dans la table ayant pour nom l'id de la couche ;
      la SD doit définir un champ *dbpath* qui définit le répertoire des couches OGR.
      Le prototype est la couche id.php/geodata/route500/limite_administrative
      
    2. elle peut aussi correspondre à une couche exposée par un service WFS ;
      dans ce cas la couche doit comporter un champ *typename* qui définit la couche dans le serveur WFS ;
      la SD doit définir un champ *urlWfs* qui définit l'URL du serveur WFS
      et peut définir un champ referer qui sera utilisé dans l'appel WFS.
      Le prototype est la couche id.php/geodata/bdcarto/troncon_hydrographique
      
      Une couche OGR ou WFS peut en outre être filtrée en fonction du zoom plus éventuellement en fonction du bbox ;
      la couche doit alors comporter un champ *onZoomGeo* qui est un dictionnaire
          {zoomMin} : {filtre}
        ou
          {zoomMin} : 
            {geoZone}: {filtre}
        où:
          {filtre} ::= {where} | 'all' | {select} | {layerDefinition}
      Pour un {zoom} donné, le filtre sera le dernier pour lequel {zoom} >= {zoomMin}.
      {geoZone} est un des codes prédéfinis de zone géographique définis plus bas.
      Un {filtre} retenu sera le premire pour lequel le bbox courant intersecte la {geoZone}.
      
      Le filtre peut prendre les valeurs suivantes:
        - Si {filtre} == 'all' alors aucune sélection n'est effectuée.
        - {where} est un critère SQL ou CQL, ex "nature in ('Limite côtière','Frontière internationale')",
        - {select} est une sélection dans une autre couche de la SD de la forme "{lyrname} / {where}"
        - {layerDefinition} est le chemin de définition d'une couche dans une autre SD commencant /,
          ex /geodata/ne_10m/admin_0_boundary_lines_land.
      Le prototype est la couche id.php/geodata/route500/limite_administrative
    
    3. elle peut être définie par une sélection dans une des couches précédentes définie dans la même SD ;  
      dans ce cas la couche comporte un champ *select* de la forme "{lyrname} / {where}"
      qui définit une sélection dans la couche {lyrname} définie dans la même SD ;
      Le prototype est la couche id.php/geodata/route500/coastline
    
    4. elle peut enfin être définie en fonction du zoom d'affichage et de la zone géographique
      par une des couches précédentes définie dans la même SD ou dans une autre ;
      dans ce cas la couche comporte un champ *onZoomGeo* défini comme précédemment
      en limitant les filtres possibles à {select} | {layerDefinition}
      Le prototype est la couche id.php/geodata/mscale/coastline
  
  En outre, une couche:
    - doit comporter un champ title qui fournit le titre de la couche pour un humain dans le contexte du document,
    - peut comporter les champs:
      - *abstract* qui fournit un résumé,
      - *style* qui définit le style Leaflet de la couche soit en JSON soit en JavaScript,
      - *conformsTo* qui fournit la spécification de la couche
      - *minZoom*
      - *maxZoom*

  Dans un onZoomGeo {geoZone} est un des codes prédéfinis suivants de zone géographique :
    - 'FXX' pour la métropole,
    - 'ANF' pour les Antilles françaises (Guadeloupe, Martinique, Saint-Barthélémy, Saint-Martin),
    - 'ASP' pour les îles Saint-Paul et Amsterdam,
    - 'CRZ' pour îles Crozet,
    - 'GUF' pour la Guyane,
    - 'KER' pour les îles Kerguelen,
    - 'MYT' pour Mayotte,
    - 'NCL' pour la Nouvelle Calédonie,
    - 'PYF' pour la Polynésie Française,
    - 'REU' pour la Réunion,
    - 'SPM' pour Saint-Pierre et Miquelon,
    - 'WLF' pour Wallis et Futuna,
    - 'WLD' pour le monde entier
    
  A FAIRE:
    - structurer la BD TOPO, définir une vue multi-échelles par défaut
    - fabriquer une couche limite administrative pour la BD Parcellaire
    - gérer des couches d'étiquettes associées ou remplacant certaines couches vecteur
  
journal: |
  19/8/2018:
    - modifs de la carte standard:
      - ajout minZoom et maxZoom sur les couches
      - modif du mécanisme de définition des calques affichés par défaut
      - modif des coordonnées et du zoom initial
    - ajout du view en paramètre optionnel de display
  18/8/2018:
    - fusion selectOnZoom et filterOnZoom en onZoomGeo
    - amélioration de la gestion du log, création d'un fichier log propre à la classe
    - gestion des exceptions par renvoi d'un feature d'erreur
    - verification du bon traitement des erreurs
  15-17/8/2018:
    - restructuration du code par fusion des 3 types dans VectorDataset des documents ShapeDataset WfsDataset
      et MultiscaleDataset
    - ajout dans selectOnZoom de la sélection en fonction de l'espace géographique
    - ajout dans filterOnZoom du renvoi vers une autre SD
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

class VectorDataset extends WfsServer {
  static $log = __DIR__.'/vectords.log.yaml'; // nom du fichier de log ou '' pour pas de log
  protected $_c; // contient les champs
  
  // crée un nouveau doc, $yaml est le contenu Yaml externe issu de l'analyseur Yaml
  // $yaml est généralement un array mais peut aussi être du texte
  function __construct(&$yaml) {
    $this->_c = [];
    foreach ($yaml as $prop => $value) {
      $this->_c[$prop] = $value;
    }
    if (self::$log) {
      if (php_sapi_name() <> 'cli') {
        $uri = substr($_SERVER['REQUEST_URI'], strlen($_SERVER['SCRIPT_NAME']));
        if (isset($_SERVER['QUERY_STRING']) && $_SERVER['QUERY_STRING'])
          $uri = substr($uri, 0, strlen($uri)-strlen($_SERVER['QUERY_STRING'])-1);
        $record = [ 'uri'=> $uri ];
      }
      else {
        $record = [ 'argv'=> $_SERVER['argv'] ];
      }
      $record['date'] = date(DateTime::ATOM);
      $record['_SERVER'] = $_SERVER;
      if (isset($_GET) && $_GET)
        $record['_GET'] = $_GET;
      if (isset($_POST) && $_POST)
        $record['_POST'] = $_POST;
      file_put_contents(self::$log, YamlDoc::syaml($record));
    }
  }
  
  // lit les champs
  function __get(string $name) { return isset($this->_c[$name]) ? $this->_c[$name] : null; }

  // affiche le sous-élément de l'élément défini par $ypath
  function show(string $docid, string $ypath): void {
    echo "VectorDataset::show($docid, $ypath)<br>\n";
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
      throw new Exception("Erreur dans VectorDataset::dbname() : champ mysql_database non défini");
    $mysqlServer = MySql::server();
    if (!isset($this->mysql_database[$mysqlServer])) 
      throw new Exception("Erreur dans VectorDataset::dbname() : champ mysql_database non défini pour $mysqlServer");
    return $this->mysql_database[$mysqlServer];
  }
  
  // fabrique la carte d'affichage des couches de la base
  function map(string $docuri) {
    $map = [
      'title'=> 'carte '.$this->title,
      'view'=> ['latlon'=> [47, 3], 'zoom'=> 6],
    ];
    $map['bases'] = [
      'cartes'=> [
        'title'=> "Cartes IGN",
        'type'=> 'TileLayer',
        'url'=> 'http://igngp.geoapi.fr/tile.php/cartes/{z}/{x}/{y}.jpg',
        'options'=> [ 'format'=> 'image/jpeg', 'minZoom'=> 0, 'maxZoom'=> 18, 'attribution'=> 'ign' ],
      ],
      'orthos'=> [
        'title'=> "Ortho-images",
        'type'=> 'TileLayer',
        'url'=> 'http://igngp.geoapi.fr/tile.php/orthos/{z}/{x}/{y}.jpg',
        'options'=> [ 'format'=> 'image/jpeg', 'minZoom'=> 0, 'maxZoom'=> 18, 'attribution'=> 'ign' ],
      ],
      'whiteimg'=> [
        'title'=> "Fond blanc",
        'type'=> 'TileLayer',
        'url'=> 'http://visu.gexplor.fr/utilityserver.php/whiteimg/{z}/{x}/{y}.jpg',
        'options'=> [ 'format'=> 'image/jpeg', 'minZoom'=> 0, 'maxZoom'=> 21 ],
      ],
    ];
    $map['defaultLayers'] = ['whiteimg'];
        
    foreach ($this->layers as $lyrid => $layer) {
      $overlay = [
        'title'=> $layer['title'],
        'type'=> 'UGeoJSONLayer',
        'endpoint'=> "http://$_SERVER[SERVER_NAME]$_SERVER[SCRIPT_NAME]/$docuri/$lyrid",
      ];
      foreach (['style','minZoom','maxZoom'] as $key)
        if (isset($layer[$key]))
          $overlay[$key] = $layer[$key];
        elseif ($this->$key !== null)
          $overlay[$key] = $this->$key;

      $map['overlays'][$lyrid] = $overlay;
      if (isset($layer['displayedByDefault']))
        $map['defaultLayers'][] = $lyrid;
    }
        
    return new Map($map);
  }
  
  // extrait le fragment défini par $ypath, utilisé pour générer un retour à partir d'un URI
  function extractByUri(string $docuri, string $ypath) {
    if (!$ypath || ($ypath=='/'))
      return $this->_c;
    elseif ($ypath == '/map')
      return $this->map($docuri)->asArray();
    elseif ($ypath == '/map/display') {
      $latlon = isset($_GET['latlon']) ? explode(',',$_GET['latlon']) : [];
      $zoom = isset($_GET['zoom']) ? $_GET['zoom']+0 : -1;
      $this->map($docuri)->display($docuri, $latlon, $zoom);
      die();
    }
    // fragment /{lyrname}
    elseif (preg_match('!^/([^/]+)$!', $ypath, $matches)) {
      $lyrname = $matches[1];
      $params = ((isset($_POST) && $_POST) ? $_POST : (isset($_GET) ? $_GET : []));
      $where = isset($params['where']) ? $params['where'] : '';
      $selfUri = "http://$_SERVER[SERVER_NAME]$_SERVER[SCRIPT_NAME]$_SERVER[PATH_INFO]";
      try {
        if (!isset($this->layers[$lyrname]))
          return null;
        elseif (isset($params['bbox']) && isset($params['zoom'])) { // affichage zoom bbox
          if (($params['bbox']=='{bbox}') && ($params['zoom']=='{zoom}')) // description de la couche
            return array_merge(['uri'=> $selfUri], $this->layers[$lyrname]);
          else // affichage réel
            return $this->queryFeaturesPrep($lyrname, $params['bbox'], $params['zoom']);
        }
        elseif ($where) // cas où where est seul défini
          return $this->queryFeatures($lyrname, [], '', $where);
        // cas de description d'une couche définie en fonction du zoom
        elseif (isset($this->layers[$lyrname]['onZoomGeo']) && isset($params['zoom'])) {
          if (!($select = $this->onZoomGeo($this->layers[$lyrname]['onZoomGeo'], $params['zoom'])))
            return array_merge(['uri'=> $selfUri], $this->layers[$lyrname]);
          elseif (is_array($select))
            return $select;
          elseif (strncmp($select, 'http://', 7) == 0) {
            header("Location: $select?zoom=$params[zoom]");
            die("header(Location: $select?zoom=$params[zoom])");
          }
          else
            return $select;
        }
        else // cas de description d'une couche standard
          return array_merge(['uri'=> $selfUri], $this->layers[$lyrname]);
      } catch(Exception $e) {
        header('Access-Control-Allow-Origin: *');
        if (!isset($params['bbox']) || ($params['bbox']=='{bbox}')) {
          header('HTTP/1.1 500 Internal Server Error');
          header('Content-type: text/plain');
          die("Exception ".$e->getMessage());
        }
        header('Content-type: application/json');
        $bbox = parent::decodeBbox($params['bbox']);
        $errorFeatureColl = [
          'type'=> 'FeatureCollection',
          'features'=> [
            [ 'type'=> 'Feature',
              'properties'=> [ 'errorMessage'=> $e->getMessage() ],
              'geometry'=> [
                'type'=> 'Point',
                'coordinates'=> [ ($bbox[0]+$bbox[2])/2, ($bbox[1]+$bbox[3])/2 ]
              ]
            ]
          ],
        ];
        echo json_encode($errorFeatureColl, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
        file_put_contents(self::$log, YamlDoc::syaml(['erreur'=> $e->getMessage()]), FILE_APPEND);
        die();
      }
    }
    elseif (preg_match('!^/([^/]+)/properties$!', $ypath, $matches)) {
      $lyrname = $matches[1];
      //echo "accès à la layer $lyrname\n";
      MySql::open(require(__DIR__.'/mysqlparams.inc.php'));
      if (!isset($this->layers[$lyrname]))
        return null;
      elseif (isset($this->layers[$lyrname]['select'])) {
        if (!preg_match("!^([^ ]+) / (.*)$!", $this->layers[$lyrname]['select'], $matches))
          throw new Exception("In VectorDataset::extractByUri() No match on ".$this->layers[$lyrname]['select']);
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
  
  // renvoie la définition du onZoomGeo correspondant au zoom
  // Utilisé dans 2 cas: affichage des features ou de la description de la couche
  // dans le 2ème cas, bbox n'est pas défini
  // le retour est normalement un chaine sauf dans le 2ème cas qd il y a une selection géographique
  function onZoomGeo(array $onZoomGeo, string $zoom, array $bbox=[]) {
    $select = '';
    foreach ($onZoomGeo as $zoomMin => $selectOnZoom) {
      if ($zoom >= $zoomMin)
        $select = $selectOnZoom;
    }
    if (is_array($select)) { // cas du second select sur zone
      $onGeo = $select;
      if (!$bbox) { // cas de la définition de la couche
        $ret = [];
        foreach ($onGeo as $zone => $select) {
          if (strncmp($select,'/',1)==0)
            $ret[$zone] = "http://$_SERVER[SERVER_NAME]$_SERVER[SCRIPT_NAME]$select";
          else
            $ret[$zone] = $select;
        }
        return $ret;
      }
      // cas de l'affichage des features
      $select = '';      
      foreach($onGeo as $zone => $selectOnZone) {
        if (GeoZone::intersects($zone, $bbox)) {
          $select = $selectOnZone;
          break;
        }
      }
    }
    if ($select === null)
      return '';
    if (strncmp($select,'/',1)==0) {
      $select = "http://$_SERVER[SERVER_NAME]$_SERVER[SCRIPT_NAME]$select";
    }
    //echo "<pre>select=$select\n"; die("FIN ligne ".__LINE__);
    return $select;
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
    
  // affiche une couche vide et enregistre le message dans le log
  static function emptyFeatureCollection(string $message) {
    header('Access-Control-Allow-Origin: *');
    header('Content-type: application/json');
    echo '{"type":"FeatureCollection","features": [],"nbfeatures": 0 }',"\n";
    if (self::$log) {
      file_put_contents(self::$log, YamlDoc::syaml(['message'=> $message,]), FILE_APPEND);
    }
    die();
  }
  
  // affiche le GeoJSON au fur et à mesure, ne retourne pas au script appellant
  // version optimisée avec sortie par feature
  // Fonctionne en 2 étapes:
  // - la première vérifie les paramètres, traduit les select et les onZoomGeo
  // Ne fonctionne pas si zoom et where sont tous les 2 définis
  function queryFeaturesPrep(string $lyrname, string $bboxstr, string $zoom) {
    if (($zoom<>'') && !is_numeric($zoom))
      throw new Exception("Erreur dans VectorDataset::queryFeaturesPrep() : zoom '$zoom' incorrect");
    $bbox = parent::decodeBbox($bboxstr);
    
    if (isset($this->layers[$lyrname]['select'])) {
      //print_r($this->layers[$lyrname]);
      if (!preg_match("!^([^ ]+)( / (.*))?$!", $this->layers[$lyrname]['select'], $matches))
        throw new Exception("Erreur dans VectorDataset::queryFeaturesPrep() : "
            .'select "'.$this->layers[$lyrname]['select'].'" incorrect');
      return $this->queryFeatures($matches[1], $bbox, $zoom, isset($matches[3]) ? $matches[3] : '');
    }
    elseif (isset($this->layers[$lyrname]['onZoomGeo'])) {
      if (!($select = $this->onZoomGeo($this->layers[$lyrname]['onZoomGeo'], $zoom, $bbox)))
        self::emptyFeatureCollection("Aucun onZoomGeo défini pour zoom $zoom sur layer $lyrname");
      if (strncmp($select, 'http://', 7) == 0) {
        header("Location: $select?bbox=$bboxstr&zoom=$zoom");
        die("Location: $select?bbox=$bboxstr&zoom=$zoom");
      }
      elseif (preg_match('!([^ ]+) / (.*)$!', $select, $matches))
        return $this->queryFeatures($matches[1], $bbox, $zoom, $matches[2]);
      elseif (isset($this->layers[$lyrname]['ogrPath']) || isset($this->layers[$lyrname]['typename']))
        return $this->queryFeatures($lyrname, $bbox, $zoom, $select == 'all' ? '' : $select);
      else
        throw new Exception("Dans VectorDataset::queryFeaturesPrep: onZoomGeo de $lyrname incorrect");
    }
    else {
      return $this->queryFeatures($lyrname, $bbox, $zoom, '');
    }
  }
  
  // étape 2: traite ogrPath et typename
  function queryFeatures(string $lyrname, array $bbox, string $zoom, string $where) {
    //echo "VectorDataset::queryFeatures($lyrname, (",implode(',',$bbox),"), $zoom, $where)<br>\n";
    // requête dans MySQL
    if (isset($this->layers[$lyrname]['ogrPath'])) {
      MySql::open(require(__DIR__.'/mysqlparams.inc.php'));
      $dbname = $this->dbname();
    
      $props = $this->properties($lyrname);
      if ($props)
        $props = implode(', ', $props).',';
      else
        $props = '';
      $bboxwkt = parent::bboxWktLngLat($bbox);
      
      $sql = "select $props ST_AsText(geom) geom from $dbname.$lyrname\n";
      if ($where)
        $sql .= " where $where";
      if ($bboxwkt)
        $sql .= ($where ? ' and ':' where ')."MBRIntersects(geom, ST_GeomFromText('$bboxwkt'))";
      //echo "sql=$sql<br>\n";
      //die("FIN ligne ".__LINE__);
      $sqls = [$sql, null, null];
      // si la bbox est de l'autre côté de l'anti-méridien ou à cheval dessus, décalage des données de 360°
      if ($bbox) {
        if ($bbox[2] > 180.0) { // la requête coupe l'antiméridien
          $bbox2 = [$bbox[0] - 360.0, $bbox[1], $bbox[2] - 360.0, $bbox[3]];
          $bboxwkt2 = parent::bboxWktLngLat($bbox2);
          $sql = "select $props ST_AsText(geom) geom from $dbname.$lyrname\n";
          $sql .= " where ".($where?"$where and ":'')."MBRIntersects(geom, ST_GeomFromText('$bboxwkt2'))";
          $sqls[1] = $sql;
        }
        if ($bbox[0] < -180.0) { // la requête coupe l'antiméridien
          $bbox2 = [$bbox[0] + 360.0, $bbox[1], $bbox[2] + 360.0, $bbox[3]];
          $bboxwkt2 = parent::bboxWktLngLat($bbox2);
          $sql = "select $props ST_AsText(geom) geom from $dbname.$lyrname\n";
          $sql .= " where ".($where?"$where and ":'')."MBRIntersects(geom, ST_GeomFromText('$bboxwkt2'))";
          $sqls[2] = $sql;
        }
      }
      header('Access-Control-Allow-Origin: *');
      header('Content-type: application/json');
      $this->queryMySqlAndPrintInGeoJson($sqls);
    }
    // requête WFS
    elseif (isset($this->layers[$lyrname]['typename'])) {
      $typename = $this->layers[$lyrname]['typename'];
      if (self::$log) {
        file_put_contents(self::$log,
            YamlDoc::syaml([
              'method'=> 'VectorDataset::queryFeatures',
              'typename'=> $typename,
              'where'=> $where,
              'bbox'=> $bbox,
            ]),
            FILE_APPEND
        );
      }
      header('Access-Control-Allow-Origin: *');
      header('Content-type: application/json');
      $this->printAllFeatures($typename, $bbox, $where);
      if (self::$log) {
        global $t0;
        file_put_contents(self::$log, YamlDoc::syaml(['duration'=> microtime(true) - $t0]), FILE_APPEND);
      }
      die();  
    }
    else {
      throw new Exception("Erreur dans VectorDataset::queryFeatures() : cas non prévu pour la couche $lyrname");
    }
  }
    
  // exécute les requêtes SQL, affiche le résultat en GeoJSON et s'arrête
  function queryMySqlAndPrintInGeoJson(array $sqls) {
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
    if (self::$log) {
      global $t0;
      file_put_contents(self::$log,
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

// la classe GeoZone permet de tester l'intersection entre un bbox et une des zones prédéfinies
class GeoZone {
  static $zones = [ // définition de qqs zones particulières en [lngMin, latMin, lngMax, latMax]
    'FXX'=> [-6, 41, 10, 52], // métropole
    'ANF'=> [-63.234823, 14.383330,-60.750000, 18.253533],
    'ASP'=> [ 77.480000,-38.760000, 77.625000,-37.770000],
    'CRZ'=> [ 50.000000,-46.750000, 52.500000,-45.750000],
    'GUF'=> [-55, 2, -50, 6],
    'KER'=> [68.108119,-50.033556, 70.897338,-48.379219],
    'MYT'=> [44.9, -13.1, 45.4, -12],
    'NCL'=> [163.5, -23, 168.25, -19.36],
    'PYF'=> [-152, -18, -149, -15],
    'REU'=> [55, -21.5, 56, -20.75],
    'SPM'=> [-56.5236, 46.74, -56.08, 47.1528],
    'WLF'=> [-178.5, -14.5, -175.5, -13.17],
    'WLD'=> [-999, -999, 999, 999], // le monde
  ];
  
  // teste l'intersection de 2 bbox
  // l'intersection est [xmin, ymin, xmax, ymax]
  // l'intersection est vrai ssi la bbox n'est pas dégénérée
  static function bboxIntersect(array $a, array $b): bool {
    $xmin = max($a[0], $b[0]);
    $ymin = max($a[1], $b[1]);
    $xmax = min($a[2], $b[2]);
    $ymax = min($a[3], $b[3]);
    return ($xmin < $xmax) && ($ymin < $ymax);
  }

  // teste l'intersection d'une des zones prédéfinies avec un bbox ([lngMin, latMin, lngMax, latMax])
  static function intersects(string $zone, array $bbox) {
    if (!isset(self::$zones[$zone]))
      throw new Exception("Erreur dans GeoZone::intersects: zone $zone non définie");
    return self::bboxIntersect(self::$zones[$zone], $bbox);
  }
}