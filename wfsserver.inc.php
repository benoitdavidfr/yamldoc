<?php
/*PhpDoc:
name: wfsserver.inc.php
title: wfsserver.inc.php - document définissant un ensemble de couches exposées par un serveur WFS
functions:
doc: <a href='/yamldoc/?action=version&name=wfsserver.inc.php'>doc intégrée en Php</a>
*/
{
$phpDocs['wfsserver.inc.php'] = <<<'EOT'
name: wfsserver.inc.php
title: wfsserver.inc.php - document définissant un ensemble de couches exposées par un serveur WFS
doc: |
  Outre les champs de métadonnées, le document doit définir les champs suivants:
    - urlWfs: fournissant l'URL du serveur à compléter avec les paramètres,
    - layers: définissant la dictionnaire des couches avec un identifiant et des champs.
  Chaque couche doit définir les champs suivants:
    - title: titre de la couche pour un humain dans le contexte du document
    - abstract (facultatif): résumé de la couche lisible par un humain
    - select: soit le typename dans le serveur WFS, soit le typename suivi d'un / et d'un critère ECQL
  Voir par exemple: http://127.0.0.1/yamldoc/id.php/geodata/bdcarto
  
  Liste des points d'entrée de l'API:
  - /{document} : description de la base de données, y compris la liste de ses couches
  - /{document}/{layer} : description de la couche
  - /{document}/{layer}?bbox={bbox}&zoom={zoom} : requête sur la couche renvoyant un FeatureCollection
    ex:
      /geodata/bdcarto/commune?bbox=4.8,47,4.9,47.1&zoom=12
        retourne les objets inclus dans la boite
  - /{document}/map : génère une carte Leaflet standard avec les couches définies par le document
  - /{document}/map/display : génère le code HTML de la carte Leaflet standard

journal: |
  9/8/2018:
    - création
  7/8/2018:
    - création
EOT;
}

class WfsServer extends YamlDoc {
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
  
  // fabrique la carte d'affichage des couches du document
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
  
  // http://127.0.0.1/yamldoc/id.php/geodata/igngpwfs/ADMINEXPRESS_COG_2018_CARTO:departement_carto?bbox=-0.087890625,46.73986059969267,6.08642578125,49.228360140901295&zoom=8
  
  // extrait le fragment défini par $ypath, utilisé pour générer un retour à partir d'un URI
  function extractByUri(string $docuri, string $ypath) {
    //echo "WfsServer::extractByUri($docuri, $ypath)<br>\n";
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
    // accès à la layer /{lyrname}
    elseif (preg_match('!^/([^/]+)$!', $ypath, $matches)) {
      $lyrname = $matches[1];
      //echo "accès à la layer $lyrname\n";
      if (!isset($this->layers[$lyrname]))
        return null;
      elseif (isset($_GET['bbox']) && isset($_GET['zoom']))
        return $this->queryByBbox($lyrname, $_GET['bbox'], $_GET['zoom']);
      elseif (isset($_POST['bbox']) && isset($_POST['zoom']))
        return $this->queryByBbox($lyrname, $_POST['bbox'], $_POST['zoom']);
      elseif (isset($_GET['where']))
        return $this->queryByWhere($lyrname, $_GET['where']);
      else
        return $this->layers[$lyrname];
    }
    else
      return null;
  }
  
  // version non optimisée
  function queryByBbox0(string $lyrname, string $bboxstr, string $zoom) {
    $bbox = explode(',', $bboxstr);
    $bboxwkt = "POLYGON(($bbox[1] $bbox[0],$bbox[1] $bbox[2],$bbox[3] $bbox[2],$bbox[3] $bbox[0],$bbox[1] $bbox[0]))";
    $url = $this->urlWfs.'?service=WFS';
    foreach([
      'version'=> '2.0.0',
      'request'=> 'GetFeature',
      'TYPENAME'=> $lyrname,
      'outputFormat'=> 'application/json',
      'srsName'=> 'CRS:84',
      'cql_filter'=> urlencode("Intersects(the_geom,$bboxwkt)"),
      ] as $k => $v)
        $url .= "&$k=$v";
    $context = stream_context_create(['http'=> ['header'=> "referer: http://gexplor.fr/\r\n"]]);
    if (!($result = file_get_contents($url, false, $context))) {
      throw new Exception("Erreur dans WfsServer::queryByBbox() : sur url=$url");
    }
    header('Content-type: application/json');
    echo $result;  
    if (1) {
      global $t0;
      file_put_contents(
          'id.log.yaml',
          YamlDoc::syaml([
            'version'=> 'version initiale',
            'duration'=> microtime(true) - $t0,
          ]),
          FILE_APPEND
      );
    }
    die();  
  }

  // http://127.0.0.1/yamldoc/id.php/geodata/bdcarto/coastline?bbox=-2.25,46.90,-2.06,46.98&zoom=13
  
  // version optimisée
  function queryByBbox(string $lyrname, string $bboxstr, string $zoom) {
    
    if (isset($this->layers[$lyrname]['select'])) {
      //print_r($this->layers[$lyrname]);
      if (!preg_match("!^([^ ]+)( / (.*))?$!", $this->layers[$lyrname]['select'], $matches))
        throw new Exception("Erreur dans WfsServer::queryByBbox() : No match on ".$this->layers[$lyrname]['select']);
      $typename = $matches[1];
      $where = isset($matches[3]) ? $matches[3] : '';
      $this->sendWfsRequest($typename, $where, $bboxstr);
    }
    elseif (isset($this->layers[$lyrname]['selectOnZoom'])) {
      foreach ($this->layers[$lyrname]['selectOnZoom'] as $zoomMin => $select) {
        if ($zoom >= $zoomMin)
          break;
      }
      if ($zoom < $zoomMin) {
        header('Access-Control-Allow-Origin: *');
        header('Content-type: application/json');
        echo '{"type":"FeatureCollection","features": [],nbfeatures: 0 }',"\n";
        die();
      }
      if (!preg_match("!^([^ ]+)( / (.*))?$!", $select, $matches))
        throw new Exception("Erreur dans GeoData::queryByBbox() : No match on ".$select);
      $typename = $matches[1];
      $where = isset($matches[3]) ? $matches[3] : '';
      if (1) {
        file_put_contents(
            'id.log.yaml',
            YamlDoc::syaml([
              'zoom'=> $zoom,
              'typename'=> $typename,
              'where'=> $where,
            ]),
            FILE_APPEND
        );
      }
      $this->sendWfsRequest($typename, $where, $bboxstr);
    }
    else {
      throw new Exception("Erreur dans GeoData::queryByBbox() : layer $lyrname mal définie");
    }
  }
  
  function sendWfsRequest(string $typename, string $where, string $bboxstr) {
    if (1) {
      file_put_contents(
          'id.log.yaml',
          YamlDoc::syaml([
            'appel'=> 'WfsServer::sendWfsRequest',
            'typename'=> $typename,
            'where'=> $where,
            'bboxstr'=> $bboxstr,
          ]),
          FILE_APPEND
      );
    }
    $bbox = explode(',', $bboxstr);
    $bboxwkt = "POLYGON(($bbox[1] $bbox[0],$bbox[1] $bbox[2],$bbox[3] $bbox[2],$bbox[3] $bbox[0],$bbox[1] $bbox[0]))";
    $where = utf8_decode($where); // expérimentalement les requêtes doivent être encodées en ISO-8859-1
    $url = $this->urlWfs.'?service=WFS';
    foreach([
      'version'=> '2.0.0',
      'request'=> 'GetFeature',
      'typename'=> $typename,
      'outputFormat'=> 'application/json',
      'srsName'=> 'CRS:84', // système de coordonnées nécessaire pour du GeoJSON
      'cql_filter'=> urlencode("Intersects(the_geom,$bboxwkt)".($where?" AND $where":'')),
      ] as $k => $v)
        $url .= "&$k=$v";
    $context = stream_context_create(['http'=> ['header'=> "referer: http://gexplor.fr/\r\n"]]);
    header('Content-type: application/json');
    if (!readfile($url, false, $context)) {
      file_put_contents(
          'id.log.yaml',
          YamlDoc::syaml([
            'erreur'=> "Erreur dans WfsServer::sendWfsRequest() sur readfile()",
            'bbox'=> $bboxstr,
            'urlWfs'=> $url,
          ]),
          FILE_APPEND
      );
      throw new Exception("Erreur dans WfsServer::queryByBbox() : sur url=$url");
    }
    if (1) {
      global $t0;
      file_put_contents(
          'id.log.yaml',
          YamlDoc::syaml([
            'version'=> 'version optimisée avec readfile',
            'duration'=> microtime(true) - $t0,
            'cql_filter'=> urlencode("Intersects(the_geom,$bboxwkt)".($where?" AND $where":'')),
          ]),
          FILE_APPEND
      );
    }
    die();  
  }
};
