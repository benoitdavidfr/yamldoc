<?php
/*PhpDoc:
name: mscaleds.inc.php
title: mscaleds.inc.php - sous-classe de documents pour la gestion des données géographiques multi-échelles
functions:
doc: <a href='/yamldoc/?action=version&name=mscaleds.inc.php'>doc intégrée en Php</a>
*/
{
$phpDocs['mscaleds.inc.php'] = <<<'EOT'
name: mscaleds.inc.php
title: mscaleds.inc.php - documents décrivant des SD multi-échelles
doc: |
  objectifs:
    - définir des bases de données géographiques multi-échelles

  Un MultiScaleGeoData est composé de couches, chacune définie en fonction du niveau de zoom par une couche d'une BD 
  géographique vecteur.
  
  Un document GeoData contient:
    - des métadonnées génériques
    - la description des couches (layers)

  Liste des points d'entrée de l'API:
  - /{database} : description de la base de données, y compris la liste de ses couches
  - /{database}/{layer} : description de la couche
  - /{database}/{layer}?bbox={bbox}&zoom={zoom} : requête sur la couche
    ex:
      /geodata/mscalegd/coastline?bbox=4.8,47,4.9,47.1&zoom=12
        retourne les objets inclus dans la boite
  
  A FAIRE:
    - gestion des exceptions par renvoi d'un feature d'erreur
  
journal: |
  9/8/2018:
    - création
EOT;
}

class MultiScaleDataset extends YamlDoc {
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
    //echo "MultiScaleDataset::show($docid, $ypath)<br>\n";
    if (!$ypath || ($ypath=='/'))
      showDoc($docid, $this->_c);
    else
      showDoc($docid, $this->extract($ypath));
    //echo "<pre>"; print_r($this->_c); echo "</pre>\n";
  }
  
  // décapsule l'objet et retourne son contenu sous la forme d'un array
  function asArray() { return $this->_c; }

  // extrait le fragment du document défini par $ypath
  function extract(string $ypath) {
    return YamlDoc::sextract($this->_c, $ypath);
  }
  
  // fabrique la carte d'affichage des couches de la base
  function map(string $docuri): Map {
    $map = ['title'=> 'carte '.$this->title];
    foreach ($this->layers as $lyrid => $layer) {
      $overlay = [
        'title'=> $layer['title'],
        'type'=> 'UGeoJSONLayer',
        'endpoint'=> "http://$_SERVER[SERVER_NAME]$_SERVER[SCRIPT_NAME]/$docuri/$lyrid",
      ];
      if (isset($layer['style']))
        $overlay['style'] = $layer['style'];
      $map['overlays'][$lyrid] = $overlay;
    }
    $map['addLayer'] = 'whiteimg';
    $map['view'] = [
      'latlon'=> [48, 3],
      'zoom'=> 3,
    ];
    return new Map($map);
  }
  
  // identifie la définition adaptée au zoom
  // effectue le remplacement de {server} par le nom du serveur courant
  function defByZoom(string $lyrname, int $zoom) {
    $def = null;
    foreach ($this->layers[$lyrname]['definition'] as $zmin => $zdef) {
      //echo "$zmin: $zdef\n";
      if ($zoom >= $zmin) {
        //echo "$zoom >= $zmin\n";
        $def = $zdef;
      }
    }
    if ($def && (strncmp($def,'http://{server}/',16)==0))
      $def = "http://$_SERVER[SERVER_NAME]/".substr($def,16);
    return $def;
  }
  
  // http://localhost/yamldoc/id.php/geodata/mscale/coastline?zoom=15
  
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
      elseif (isset($_GET['zoom'])) {
        if (!($def = $this->defByZoom($lyrname, $_GET['zoom']))) {
          return $this->layers[$lyrname];
        }
        else {
          header("Location: $def?zoom=$_GET[zoom]");
          die();
        }
      }
      else
        return $this->layers[$lyrname];
    }
    else
      return null;
  }

  // http://localhost/yamldoc/id.php/geodata/mscale/coastline?bbox=-95.8,-4.5,101.7,74.5&zoom=3
  
  function queryByBbox(string $lyrname, string $bboxstr, string $zoom) {
    echo "MultiScaleGeoData::queryByBbox<br>\n";
    //echo "<pre>_SERVER="; print_r($_SERVER);
    if (!($def = $this->defByZoom($lyrname, $zoom)))
      return null;
    //die("Location: $def?bbox=$bboxstr&zoom=$zoom");
    header("Location: $def?bbox=$bboxstr&zoom=$zoom");
    die();
  }
};