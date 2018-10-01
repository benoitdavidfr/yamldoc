<?php
/*PhpDoc:
name: wmtsserver.inc.php
title: wmtsserver.inc.php - serveur WMTS
functions:
doc: <a href='/yamldoc/?action=version&name=wmtsserver.inc.php'>doc intégrée en Php</a>
*/
{ // doc 
$phpDocs['wmtsserver.inc.php']['file'] = <<<'EOT'
name: wmtsserver.inc.php
title: wmtsserver.inc.php - serveur WMTS
doc: |
  La classe WmtsServer définit des serveurs WMTS.

  Outre les champs de métadonnées, le document doit définir les champs suivants:

    - url : url du serveur

  Il peut aussi définir les champs suivants:
  
    - referer: referer à transmettre à chaque appel du serveur.

journal:
  22/9/2018:
    - création
EOT;
}
//require_once __DIR__.'/yamldoc.inc.php';
//require_once __DIR__.'/search.inc.php';
require_once __DIR__.'/inc.php';

class WmtsServer extends WmsServer {
  static $log = __DIR__.'/wmtsserver.log.yaml'; // nom du fichier de log ou false pour pas de log
  //static $log = false; // nom du fichier de log ou false pour pas de log
  static $capCache = __DIR__.'/tscapcache'; // nom du répertoire dans lequel sont stockés les fichiers XML de capacités
  protected $_c; // contient les champs
  
  // crée un nouveau doc, $yaml est le contenu Yaml externe issu de l'analyseur Yaml
  function __construct($yaml, string $docid) {
    $this->_c = $yaml;
    $this->_id = $docid;
    if (!$this->url)
      throw new Exception("Erreur dans WmtsServer::__construct(): champ url obligatoire");
  }
  
  // renvoi l'URL de la requête
  function url(array $params): string {
    if (self::$log) { // log
      file_put_contents(
          self::$log,
          YamlDoc::syaml([
            'date'=> date(DateTime::ATOM),
            'appel'=> 'WmsServer::url',
            'params'=> $params,
          ]),
          FILE_APPEND
      );
    }
    $url = $this->url;
    $url .= ((strpos($url, '?') === false) ? '?' : '&').'SERVICE=WMTS';
    foreach($params as $key => $value)
      //$url .= "&$key=$value";
      $url .= '&'.strtoupper($key).'='.rawurlencode($value);
    if (self::$log) { // log
      file_put_contents(self::$log, YamlDoc::syaml(['url'=> $url]), FILE_APPEND);
    }
    return $url;
  }

  function layers(): array {
    $cap = $this->getCapabilities();
    $cap = str_replace(['<ows:','</ows:'], ['<ows_','</ows_'], $cap);
    //die($cap);
    $cap = new SimpleXMLElement($cap);
    //echo "<pre>"; print_r($cap); echo "</pre>\n";
    $lyrs = [];
    foreach ($cap->Contents->Layer as $layer) {
      //echo "<pre>"; print_r($layer); echo "</pre>\n";
      $lyrs[(string)$layer->ows_Identifier] = [
        'title'=> (string)$layer->ows_Title,
        'abstract'=> (string)$layer->ows_Abstract,
        'format'=> (string)$layer->Format,
        'tileMatrixSet'=> (string)$layer->TileMatrixSetLink->TileMatrixSet,
      ];
    }
    return $lyrs;
  }
  
  function layer(string $id): array {
    $cap = $this->getCapabilities();
    $cap = str_replace(['<ows:','</ows:'], ['<ows_','</ows_'], $cap);
    //die($cap);
    $cap = new SimpleXMLElement($cap);
    //echo "<pre>"; print_r($cap); echo "</pre>\n";
    foreach ($cap->Contents->Layer as $layer) {
      if ((string)$layer->ows_Identifier == $id) {
        //echo "<pre>"; print_r($layer); echo "</pre>\n";
        $minZoom = 99;
        $maxZoom = -1;
        foreach ($layer->TileMatrixSetLink->TileMatrixSetLimits->TileMatrixLimits as $limit) {
          if ($limit->TileMatrix < $minZoom)
            $minZoom = (int)$limit->TileMatrix;
          if ($limit->TileMatrix > $maxZoom)
            $maxZoom = (int)$limit->TileMatrix;
        }
        $lyr = [
          'title'=> (string)$layer->ows_Title,
          'abstract'=> (string)$layer->ows_Abstract,
          'format'=> (string)$layer->Format,
          'tileMatrixSet'=> (string)$layer->TileMatrixSetLink->TileMatrixSet,
          'minZoom'=> $minZoom,
          'maxZoom'=> $maxZoom,
        ];
        
        if ($layer->Style) {
          foreach ($layer->Style as $style) {
            $lyr['styles'][(string)$style->ows_Identifier] = [
              'title'=> (string)$style->ows_Title,
              'abstract'=> (string)$style->ows_Abstract,
            ];
          }
        }
        return $lyr;
      }
    }
    return [];
  }
  
  // affiche le TileMatrixLimits d'une couche pour un zoom
  function tileMatrixLimits(string $lyrName, string $zoom) {
    $cap = $this->getCapabilities();
    $cap = str_replace(['<ows:','</ows:'], ['<ows_','</ows_'], $cap);
    //die($cap);
    $cap = new SimpleXMLElement($cap);
    //echo "<pre>"; print_r($cap); echo "</pre>\n";
    foreach ($cap->Contents->Layer as $layer) {
      if ((string)$layer->ows_Identifier == $lyrName) {
        foreach ($layer->TileMatrixSetLink->TileMatrixSetLimits->TileMatrixLimits as $limit) {
          if ($limit->TileMatrix == $zoom) {
            echo "<pre>limit="; print_r($limit); echo "</pre>\n";
          }
        }
      }
    }
  }
  
  // renvoie les capacités de la couche sous la forme d'un SimpleXMLElement
  function layerCap(string $id): ?SimpleXMLElement {
    $cap = $this->getCapabilities();
    $cap = str_replace(['<ows:','</ows:',' xlink:href='], ['<ows_','</ows_',' xlink_href='], $cap);
    //die($cap);
    $cap = new SimpleXMLElement($cap);
    //echo "<pre>"; print_r($cap); echo "</pre>\n";
    foreach ($cap->Contents->Layer as $layer) {
      if ((string)$layer->ows_Identifier == $id) {
        //echo "<pre>"; print_r($layer); echo "</pre>\n";
        return $layer;
      }
    }
    return null;
  }
  
  function printLayerCap(SimpleXMLElement $layerCap) {
    echo '<?xml version="1.0" encoding="UTF-8"?>',
      '<Capabilities',
      ' xmlns="http://www.opengis.net/wmts/1.0"',
      ' xmlns:gml="http://www.opengis.net/gml"',
      ' xmlns:ows="http://www.opengis.net/ows/1.1"',
      ' xmlns:xlink="http://www.w3.org/1999/xlink">',
      str_replace(['<ows_','</ows_',' xlink_href='], ['<ows:','</ows:',' xlink:href='], $layerCap->asXml()),
      '</Capabilities>';
  }
  
  function tile(string $lyrName, string $style, int $zoom, int $x, int $y, string $fmt): void {
    $layer = $this->layer($lyrName);
    //echo "<pre>"; print_r($layer); echo "</pre>\n";
    if (!$layer) {
      header("HTTP/1.1 404 Not Found");
      die("Erreur couche $lyrName inexistante");
    }
    if (!$style || !in_array($style, array_keys($layer['styles']))) {
      $style = array_keys($layer['styles'])[0];
      //die();
    }
    $query = [
      'version'=> '1.0.0',
      'request'=> 'GetTile',
      'layer'=> $lyrName,
      'format'=> $layer['format'],
      'style'=> $style,
      'tilematrixSet'=> $layer['tileMatrixSet'],
      'tilematrix'=> $zoom,
      'tilecol'=> $x,
      'tilerow'=> $y,
      'height'=> 256,
      'width'=> 256,
    ];
    $this->sendImageOrError($query);
  }
  
  // fabrique la carte d'affichage des couches de la base
  function map() {
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
        
    $docid = $this->_id;
    foreach ($this->layers() as $lyrid => $layer) {
      $overlay = [
        'title'=> $layer['title'],
        'type'=> 'TileLayer',
        'url'=> "http://$_SERVER[SERVER_NAME]$_SERVER[SCRIPT_NAME]/$docid/layers/$lyrid/{z}/{x}/{y}.jpg",
        'options'=> [ 'format'=> 'image/jpeg', 'minZoom'=> 0, 'maxZoom'=> 21 ],
      ];
      $map['overlays'][$lyrid] = $overlay;
      if (isset($layer['displayedByDefault']))
        $map['defaultLayers'][] = $lyrid;
    }
        
    return new Map($map, "$docid/map");
  }
};

