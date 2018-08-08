<?php
/*PhpDoc:
name: wfsserver.inc.php
title: wfsserver.inc.php - sous-classe de documents pour l'utilisation d'un serveur WFS
functions:
doc: <a href='/yamldoc/?action=version&name=wfsserver.inc.php'>doc intégrée en Php</a>
*/
{
$phpDocs['wfsserver.inc.php'] = <<<'EOT'
name: wfsserver.inc.php
title: wfsserver.inc.php - sous-classe de documents pour l'utilisation d'un serveur WFS
doc: |
journal: |
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
  
  // fabrique la carte d'affichage des couches de la base
  function map(string $docuri) {
    $yaml = ['title'=> 'carte WFS GP IGN'];
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
      elseif (isset($_GET['bbox']) && isset($_GET['zoom']))
        return $this->queryByBbox($lyrname, $_GET['bbox'], $_GET['zoom']);
      elseif (isset($_POST['bbox']) && isset($_POST['zoom']))
        return $this->queryByBbox($lyrname, $_POST['bbox'], $_POST['zoom']);
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
  
  // http://127.0.0.1/yamldoc/id.php/geodata/adminexpress/ADMINEXPRESS_COG_2018:region?bbox=3.41,48.35,3.50,48.39&zoom=14

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

  // version optimisée
  function queryByBbox(string $lyrname, string $bboxstr, string $zoom) {
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
    header('Content-type: application/json');
    if (!readfile($url, false, $context)) {
      throw new Exception("Erreur dans WfsServer::queryByBbox() : sur url=$url");
    }
    if (1) {
      global $t0;
      file_put_contents(
          'id.log.yaml',
          YamlDoc::syaml([
            'version'=> 'version optimisée avec readfile',
            'duration'=> microtime(true) - $t0,
          ]),
          FILE_APPEND
      );
    }
    die();  
  }
};
