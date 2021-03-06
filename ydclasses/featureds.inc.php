<?php
/*PhpDoc:
name: featureds.inc.php
title: featureds.inc.php - document définissant une série de données géo constituée d'un ensemble de couches d'objets
functions:
doc: <a href='/yamldoc/?action=version&name=featureds.inc.php'>doc intégrée en Php</a>
includes: [ ../../geovect/ogr2php/feature.inc.php, ../../phplib/sql.inc.php, '../mysqlparams.inc.php' ]
*/
{ // doc 
$phpDocs['featureds.inc.php']['file'] = <<<'EOT'
name: featureds.inc.php
title: featureds.inc.php - document définissant une série de données géo constituée d'un ensemble de couches d'objets
doc: |
  objectifs:
  
    - offrir une API d'accès aux objets géographiques

  A FAIRE:
  
    - structurer la BD TOPO, définir une vue multi-échelles par défaut
    - définir une interface OAI
    - fabriquer une couche limite administrative pour la BD Parcellaire
  
journal:
  27/4/2019:
    - passage de MySql en Sql pour généraliser à PgSql
  22/1/2019:
    - passage des UGeoJSONLayer en https
  7/1/2019:
    - spécification JSON-Schema du contenu d'un FeatureDataset
  11/10/2018:
    - ajout méthode FeatureDataset::numberOfFeatures() et api '/{lyrname}/nof?bbox={bbox}'
  23/9/2018:
    - chgt de nom de classe de VectoDataset en FeatureDataset, évite la confusion avec ViewDataset (vds)
  4/9/2018:
    - WfsServer remplacé par WfsServerJson
  3/9/2018:
    - VectorDataset n'hérite plus de WfsServer mais référence un objet WfsServer
      afin permettre de référencer différents types d'objets
  20/8/2018:
    - ajout de symboles, test sur les pai_religieux de la BDTopo
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
{ // specs des docs 
$phpDocs['featureds.inc.php']['classes']['FeatureDataset'] = <<<'EOT'
title: série de données géo constituée d'un ensemble de couches d'objets
doc: |
  Une SD d'objets (FeatureDataset) est composée de couches d'objets, chacune correspondant à une FeatureCollection
  [GeoJSON](https://tools.ietf.org/html/rfc7946) ;
  chaque couche est composée d'objets vecteur, cad des Feature GeoJSON.  
  Un document décrivant une SD d'objets, d'une part, peut s'afficher et, d'autre part, expose une API
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

  Un document FeatureDataset est décrit par le [schéma FeatureDataset](ydclasses.php/FeatureDataset.sch.yaml)

EOT;
}
require_once __DIR__.'/../../geovect/ogr2php/feature.inc.php';
require_once __DIR__.'/../../phplib/sql.inc.php';
//require_once __DIR__.'/yamldoc.inc.php';

use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Yaml\Exception\ParseException;

class FeatureDataset extends YamlDoc {
  static $log = __DIR__.'/featureds.log.yaml'; // nom du fichier de log ou '' pour pas de log
  protected $_c; // contient les champs du document
  protected $wfsServer = null;
  
  // crée un nouveau doc, $yaml est le contenu Yaml externe issu de l'analyseur Yaml
  // $yaml est généralement un array mais peut aussi être du texte
  function __construct($yaml, string $docid) {
    $this->_c = [];
    $this->_id = $docid;
    foreach ($yaml as $prop => $value) {
      $this->_c[$prop] = $value;
    }
    //echo "<pre>"; print_r($this);
    if ($this->layersByTheme) {
      foreach ($this->layersByTheme as $themeid => $theme) {
        //echo "$themeid<br>";
        //echo "<pre>"; print_r($theme);
        if ($theme) {
          foreach ($theme as $lyrid => $layer) {
            $this->_c['layers'][$lyrid] = $layer;
          }
        }
      }
    }
    if ($this->wfsUrl) {
      $this->wfsServer = WfsServer::new_WfsServer(
        [ 'yamlClass'=> 'WfsServer',
          'wfsUrl'=> $this->wfsUrl,
          'wfsOptions'=> $this->wfsOptions ? $this->wfsOptions : [],
          'featureModifier'=> $this->featureModifier,
          'ftModContext'=> $this->ftModContext,
        ],
        "$docid/wfs"
      );
      //echo "<pre>wfsServer="; print_r($this->wfsServer ); echo "</pre>\n"; 
    }
    
    //unset($this->_c['layersByTheme']);
    //echo "<pre>"; print_r($this);
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
  function show(string $ypath=''): void {
    $docid = $this->_id;
    //echo "FeatureDataset::show($docid, $ypath)<br>\n";
    if (preg_match('!^/layers/([^/]+)$!', $ypath, $matches)) {
      $this->showLayer($docid, $matches[1]);
      return;
    }
    elseif (preg_match('!^/layers/([^/]+)/conformsTo$!', $ypath, $matches)) {
      $this->showConformsTo($docid, $matches[1]);
      return;
    }
    elseif (preg_match('!^/layers/([^/]+)/conformsTo/properties/([^/]+)$!', $ypath, $matches)) {
      echo "<h3>Spécifications de ",
           "<a href='?doc=$docid&ypath=/layers/$matches[1]'>$matches[1]</a>.$matches[2]</h3>\n";
      showDoc($docid, $this->extract($ypath));
      return;
    }
    elseif (preg_match('!^/layers/([^/]+)/conformsTo/properties/([^/]+)/enum$!', $ypath, $matches)) {
      echo "<h3>Valeurs possibles pour ",
           "<a href='?doc=$docid&ypath=/layers/$matches[1]'>$matches[1]</a>",
           ".<a href='?doc=$docid&ypath=/layers/$matches[1]/conformsTo/properties/$matches[2]'>$matches[2]</a></h3>\n";
      showDoc($docid, $this->extract($ypath));
      return;
    }
    elseif ($ypath && ($ypath <> '/')) {
      showDoc($docid, $this->extract($ypath));
      return;
    }
    echo "<h1>",$this->title,"</h1>\n";
    $yaml = $this->_c;
    unset($yaml['title']);
    unset($yaml['layersByTheme']);
    unset($yaml['layers']);
    showDoc($docid, $yaml);
    if ($this->layersByTheme) {
      foreach ($this->layersByTheme as $themeid => $theme) {
        echo "<h2>",str_replace('_',' ',$themeid),"</h2>\n";
        if ($theme)
          foreach ($theme as $lyrid => $layer)
            $this->showLayer($docid, $lyrid);
      }
    }
    elseif ($this->layers)
      foreach ($this->layers as $lyrid => $layer)
        $this->showLayer($docid, $lyrid);
    else
      echo "Aucune couche<br>\n";
  }
  
  function showLayer(string $docid, string $lyrid) {
    $layer = $this->layers[$lyrid];
    echo "<h3><a href='?doc=$docid&ypath=/layers/$lyrid'>$layer[title]</a></h3>\n";
    unset($layer['title']);
    if (isset($layer['conformsTo']))
      $layer['conformsTo'] = "<html>\n<a href='?doc=$docid&ypath=/layers/$lyrid/conformsTo'>spécifications</a>\n";
    if (isset($layer['style'])) {
      if (is_string($layer['style']))
        $layer['style'] = "<html>\n<pre>$layer[style]</pre>\n";
      elseif (is_array($layer['style']))
        $layer['style'] = "<html>\n<pre>".json_encode($layer['style'])."</pre>\n";
    }
    if (isset($layer['pointToLayer']))
      $layer['pointToLayer'] = "<html>\n<pre>$layer[pointToLayer]</pre>\n";
    showDoc($docid, $layer);
  }
  
  function showConformsTo(string $docid, string $lyrid) {
    $layer = $this->layers[$lyrid];
    echo "<h3>Spécifications de <a href='?doc=$docid&ypath=/layers/$lyrid'>$layer[title]</a></h3>\n";
    $conformsTo = $layer['conformsTo'];
    if (isset($conformsTo['properties']))
      foreach ($conformsTo['properties'] as $propid => $property)
        if (isset($property['enum']))
          $conformsTo['properties'][$propid]['enum'] = "<html>\n<a href='?doc=$docid&ypath=/layers/$lyrid/conformsTo/properties/$propid/enum'>Valeurs possibles</a>\n";
    showDoc($docid, $conformsTo);
  }
  
  // décapsule l'objet et retourne son contenu sous la forme d'un array
  // ce décapsulage ne s'effectue qu'à un seul niveau
  // Permet de maitriser l'ordre des champs
  function asArray() {
    $result = array_merge(['_id'=> $this->_id], $this->_c);
    if ($this->wfsServer)
      $result['wfs'] = $this->wfsServer->asArray();
    return $result;
  }

  // extrait le fragment du document défini par $ypath
  // Renvoie un array ou un objet qui sera ensuite transformé par YamlDoc::replaceYDEltByArray()
  // Utilisé par YamlDoc::yaml() et YamlDoc::json()
  // Evite de construire une structure intermédiaire volumineuse avec asArray()
  function extract(string $ypath) {
    return YamlDoc::sextract($this->_c, $ypath);
  }
  
  // retourne le nom de la base Sql dans laquelle les données sont stockées
  function dbname() {
    if (!$this->sql_database)
      throw new Exception("Erreur dans FeatureDataset::dbname() : champ sql_database non défini");
    $sqlServer = Sql::server();
    if (!isset($this->sql_database[$sqlServer])) 
      throw new Exception("Erreur dans FeatureDataset::dbname() : champ sql_database non défini pour $sqlServer");
    return $this->sql_database[$sqlServer];
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
        'url'=> 'https://igngp.geoapi.fr/tile.php/cartes/{z}/{x}/{y}.jpg',
        'options'=> [ 'minZoom'=> 0, 'maxZoom'=> 18, 'attribution'=> 'ign' ],
      ],
      'orthos'=> [
        'title'=> "Ortho-images",
        'type'=> 'TileLayer',
        'url'=> 'https://igngp.geoapi.fr/tile.php/orthos/{z}/{x}/{y}.jpg',
        'options'=> [ 'minZoom'=> 0, 'maxZoom'=> 18, 'attribution'=> 'ign' ],
      ],
      'whiteimg'=> [
        'title'=> "Fond blanc",
        'type'=> 'TileLayer',
        'url'=> 'https://visu.gexplor.fr/utilityserver.php/whiteimg/{z}/{x}/{y}.jpg',
        'options'=> [ 'minZoom'=> 0, 'maxZoom'=> 21 ],
      ],
    ];
    $map['defaultLayers'] = ['whiteimg'];
        
    $request_scheme = isset($_SERVER['REQUEST_SCHEME']) ? $_SERVER['REQUEST_SCHEME']
      : ((isset($_SERVER['HTTPS']) && ($_SERVER['HTTPS']=='on')) ? 'https' : 'http');
    foreach ($this->layers as $lyrid => $layer) {
      $overlay = [
        'title'=> $layer['title'],
        'type'=> 'UGeoJSONLayer',
        'endpoint'=> "$request_scheme://$_SERVER[SERVER_NAME]$_SERVER[SCRIPT_NAME]/$docuri/$lyrid",
      ];
      foreach (['pointToLayer','style','minZoom','maxZoom'] as $key)
        if (isset($layer[$key]))
          $overlay[$key] = $layer[$key];
        elseif ($this->$key !== null)
          $overlay[$key] = $this->$key;

      $map['overlays'][$lyrid] = $overlay;
      if (isset($layer['displayedByDefault']))
        $map['defaultLayers'][] = $lyrid;
    }
        
    return new Map($map, "$docuri/map");
  }
  
  static function api(): array {
    return [
      'class'=> get_class(), 
      'title'=> "description de l'API de la classe ".get_class(),
      'abstract'=> "document définissant une série de données géo constituée d'un ensemble de couches vecteur",
      'api'=> [
        '/'=> "retourne le contenu du document ".get_class(),
        '/api'=> "retourne les points d'accès de ".get_class(),
        '/map'=> "retourne le contenu de la carte affichant la SD",
        '/map/{param}'=> Map::api()['api'],
        '/wfs'=> "Affiche le WfsServer sous-jacent",
        '/wfs/{param}'=> WfsServer::api()['api'],
        '/{lyrname}'=> "Affiche les caractéritiques de la couche {lyrname} de la SD",
        '/{lyrname}?bbox={bbox}&zoom={zoom}'=> "Si les paramètres contiennent les chaines alors retourne les caractéritiques de la couche, sinon retourne les objets sélectionnés",
        '/{lyrname}/nof?bbox={bbox}'=> "retourne le nombre d'objets sélectionnés",
        '/{lyrname}/properties'=> "Retourne la liste des propriétés de la couche",
        '/{lyrname}/id/{id}'=> "Retourne l'objet {id} de la couche {lyrname} (non implémenté)",
      ]
    ];
  }
  
  // extrait le fragment défini par $ypath, utilisé pour générer un retour à partir d'un URI
  function extractByUri(string $ypath) {
    $docuri = $this->_id;
    if (!$ypath || ($ypath=='/')) {
      return $this->asArray();
    }
    elseif ($ypath == '/api') {
      return self::api();
    }
    elseif ($ypath == '/map') {
      return $this->map($docuri)->asArray();
    }
    elseif (preg_match('!^/map(/.*)$!', $ypath, $matches)) {
      $this->map($docuri)->extractByUri($matches[1]);
      die();
    }
    elseif ($ypath == '/wfs') {
      return $this->wfsServer ? $this->wfsServer->asArray() : null;
    }
    elseif (preg_match('!^/wfs(/.*)$!', $ypath, $matches)) {
      return $this->wfsServer ? $this->wfsServer->extractByUri($matches[1]) : null;
    }
    // fragment /{lyrname}
    elseif (preg_match('!^/([^/]+)$!', $ypath, $matches)) {
      $lyrname = $matches[1];
      $params = !isset($_GET) ? $_POST : (!isset($_POST) ? $_GET : array_merge($_GET, $_POST));
      $where = isset($params['where']) ? $params['where'] : '';
      $selfUri = (php_sapi_name()=='cli') ? '' : "http://$_SERVER[SERVER_NAME]$_SERVER[SCRIPT_NAME]$_SERVER[PATH_INFO]";
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
      } catch (Exception $e) {
        header('Access-Control-Allow-Origin: *');
        if (!isset($params['bbox']) || ($params['bbox']=='{bbox}')) {
          header('HTTP/1.1 500 Internal Server Error');
          header('Content-type: text/plain');
          die("Exception ".$e->getMessage());
        }
        header('Content-type: application/json');
        $bbox = WfsServer::decodeBbox($params['bbox']);
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
    // fragment /{lyrname}/nof?bbox={bbox}&where={where}
    elseif (preg_match('!^/([^/]+)/nof$!', $ypath, $matches)) {
      $lyrname = $matches[1];
      $params = !isset($_GET) ? $_POST : (!isset($_POST) ? $_GET : array_merge($_GET, $_POST));
      try {
        if (!isset($this->layers[$lyrname]))
          return null;        
        return $this->numberOfFeatures(
          $lyrname,
          isset($params['bbox']) ? WfsServer::decodeBbox($params['bbox']) : [],
          isset($params['where']) ? $params['where'] : '');
      } catch (Exception $e) {
        header('Access-Control-Allow-Origin: *');
        if (!isset($params['bbox'])) {
          header('HTTP/1.1 500 Internal Server Error');
          header('Content-type: text/plain');
          die("Exception ".$e->getMessage());
        }
        header('Content-type: application/json');
        $bbox = WfsServer::decodeBbox($params['bbox']);
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
      MySql::open(require(__DIR__.'/../mysqlparams.inc.php'));
      if (!isset($this->layers[$lyrname]))
        return null;
      elseif (isset($this->layers[$lyrname]['select'])) {
        if (!preg_match("!^([^ ]+) / (.*)$!", $this->layers[$lyrname]['select'], $matches))
          throw new Exception("In FeatureDataset::extractByUri() No match on ".$this->layers[$lyrname]['select']);
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
    
  //
  function numberOfFeatures(string $lyrname, array $bbox=[], string $where=''): int {
    if (isset($this->layers[$lyrname]['typename'])) {
      $typename = $this->layers[$lyrname]['typename'];
      return $this->wfsServer->getNumberMatched($typename, $bbox, $where);
    }
    elseif (isset($this->layers[$lyrname]['ogrPath'])) {
      MySql::open(require(__DIR__.'/../mysqlparams.inc.php'));
      $dbname = $this->dbname();
      $bboxwkt = WfsServer::bboxWktLngLat($bbox);
      $sql = "select count(*) nbre from $dbname.$lyrname\n";
      if ($where)
        $sql .= " where $where";
      if ($bboxwkt)
        $sql .= ($where ? ' and ':' where ')."MBRIntersects(geom, ST_GeomFromText('$bboxwkt'))";
      foreach(MySql::query($sql) as $tuple) {
        return $tuple['nbre'];
      }
    }
    else
      throw new Exception("Dans FeatureDataset::numberOfFeatures: cas non prévu");
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
      throw new Exception("Erreur dans FeatureDataset::queryFeaturesPrep() : zoom '$zoom' incorrect");
    $bbox = WfsServer::decodeBbox($bboxstr);
    
    if (isset($this->layers[$lyrname]['select'])) {
      //print_r($this->layers[$lyrname]);
      if (!preg_match("!^([^ ]+)( / (.*))?$!", $this->layers[$lyrname]['select'], $matches))
        throw new Exception("Erreur dans FeatureDataset::queryFeaturesPrep() : "
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
        throw new Exception("Dans FeatureDataset::queryFeaturesPrep: onZoomGeo de $lyrname incorrect");
    }
    else {
      return $this->queryFeatures($lyrname, $bbox, $zoom, '');
    }
  }
  
  // étape 2: traite ogrPath et typename
  function queryFeatures(string $lyrname, array $bbox, string $zoom, string $where) {
    //echo "FeatureDataset::queryFeatures($lyrname, (",implode(',',$bbox),"), $zoom, $where)<br>\n";
    // requête dans MySQL
    if (isset($this->layers[$lyrname]['ogrPath'])) {
      MySql::open(require(__DIR__.'/../mysqlparams.inc.php'));
      $dbname = $this->dbname();
    
      $props = $this->properties($lyrname);
      if ($props)
        $props = implode(', ', $props).',';
      else
        $props = '';
      $bboxwkt = WfsServer::bboxWktLngLat($bbox);
      
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
          $bboxwkt2 = WfsServer::bboxWktLngLat($bbox2);
          $sql = "select $props ST_AsText(geom) geom from $dbname.$lyrname\n";
          $sql .= " where ".($where?"$where and ":'')."MBRIntersects(geom, ST_GeomFromText('$bboxwkt2'))";
          $sqls[1] = $sql;
        }
        if ($bbox[0] < -180.0) { // la requête coupe l'antiméridien
          $bbox2 = [$bbox[0] + 360.0, $bbox[1], $bbox[2] + 360.0, $bbox[3]];
          $bboxwkt2 = WfsServer::bboxWktLngLat($bbox2);
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
              'method'=> 'FeatureDataset::queryFeatures',
              'zoom'=> $zoom,
            ]),
            FILE_APPEND
        );
      }
      $zoom = is_numeric($zoom) ? (int)$zoom : -1;
      if (self::$log) {
        file_put_contents(self::$log,
            YamlDoc::syaml([
              'method'=> 'FeatureDataset::queryFeatures',
              'typename'=> $typename,
              'bbox'=> $bbox,
              'zoom'=> $zoom,
              'where'=> $where,
            ]),
            FILE_APPEND
        );
      }
      header('Access-Control-Allow-Origin: *');
      header('Content-type: application/json');
      $this->wfsServer->printAllFeatures($typename, $bbox, $zoom, $where);
      if (self::$log) {
        global $t0;
        file_put_contents(self::$log, YamlDoc::syaml(['duration'=> microtime(true) - $t0]), FILE_APPEND);
      }
      die();  
    }
    else {
      throw new Exception("Erreur dans FeatureDataset::queryFeatures() : cas non prévu pour la couche $lyrname");
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