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
  La classe WmtsServer permet d'utiliser des serveurs WMTS.
  Seules sont utilisables les couches définies dans un TileMatrixSet compatible avec GoogleMaps
  (urn:ogc:def:wkss:OGC:1.0:GoogleMapsCompatible)

  Outre les champs de métadonnées, le document doit définir les champs suivants:

    - url : url du serveur

  Il peut aussi définir les champs suivants:
  
    - referer: referer à transmettre à chaque appel du serveur.

journal:
  22/9/2018:
    - création
EOT;
}

class WmtsServer extends OgcServer {
  static $log = __DIR__.'/wmtsserver.log.yaml'; // nom du fichier de log ou false pour pas de log
  static $serviceTag = 'WMTS';
  
  // affiche le sous-élément de l'élément défini par $ypath
  function show(string $ypath=''): void {
    $docid = $this->_id;
    echo "WmtsServer::show($docid, $ypath)<br>\n";
    /*
    elseif (preg_match('!^/layers/([^/]+)(/style/([^/]+))?(/([0-9]+)/([0-9]+)/([0-9]+))?$!', $ypath, $matches)) {
      $lyrName = $matches[1];
      $style = (isset($matches[2]) && $matches[2]) ? $matches[3] : '';
      $zxy = isset($matches[4]) ? [$matches[5], $matches[6], $matches[7]] : [];
      echo "style='$style'<br>\n";
      $layer = $this->layer($lyrName);
      //print_r($layer);
      if (isset($layer['tileMatrixSet']))
        $layer['tileMatrixSet'] = "<html>\n<a href='?doc=$docid&amp;ypath=/tileMatrixSets/$layer[tileMatrixSet]'>"
            ."$layer[tileMatrixSet]</a>";
      if (isset($layer['styles'])) {
        $styles = $layer['styles'];
        $layer['styles'] = [];
        foreach ($styles as $styleName => $styl) {
          $hrefTitle = "?doc=$docid&amp;ypath=/layers/$lyrName/legend/".rawurlencode($styleName);
          $styl['title'] = "<html>\n<a href='$hrefTitle'>$styl[title]</a>";
          $hrefName = "?doc=$docid&amp;ypath=/layers/$lyrName/style/".rawurlencode($styleName)
            .($zxy ? '/'.implode('/',$zxy) : '');
          $layer['styles']["<html>\n<a href='$hrefName'>$styleName</a>"] = $styl;
        }
      }
      showDoc($docid, $layer);
      echo "<a href='id.php/$docid/layers/$lyrName/capabilities'>Capacités de la couche</a><br>\n";
      showTilesInHtml($docid, $lyrName, $style, $zxy);
    }
    // affiche la légende d'un style donné
    elseif (preg_match('!^/layers/([^/]+)/legend/([^/]+)$!', $ypath, $matches)) {
      $lyrName = $matches[1];
      $styleName = $matches[2];
      echo "<a href='id.php/$docid/layers/$lyrName/capabilities'>capabilities</a><br>";
      $cap = $this->layerCap($lyrName);
      //echo "<pre>cap="; print_r($cap); echo "</pre>\n";
      foreach ($cap->Style as $style) {
        //echo "<pre>style="; print_r($style); echo "</pre>\n";
        //echo "ows_Identifier=",$style->ows_Identifier,"<br>\n";
        if (((string)$style->ows_Identifier == $styleName) || ((string)$style->Name == $styleName)) {
          if (($legendUrl = (string)$style->LegendURL['xlink_href']) // WMTS
              || ($legendUrl = (string)$style->LegendURL->OnlineResource['xlink_href'])) // WMS
            echo "<img src='$legendUrl' alt='erreur'><br>\n";
          else
            echo "Pas de fichier de légende<br>\n";
          echo "<pre>style="; print_r($style); echo "</pre>\n";
          die();
        }
      }
    }
    */
    if (preg_match('!^/tileMatrixSets$!', $ypath, $matches)) {
      $cap = $this->getCapabilities();
      $cap = str_replace(['<ows:','</ows:'], ['<ows_','</ows_'], $cap);
      //die($cap);
      $cap = new SimpleXMLElement($cap);
      //echo "<pre>cap="; print_r($cap->Contents->TileMatrixSet);
      echo "<ul>\n";
      foreach ($cap->Contents->TileMatrixSet as $tileMatrixSet) {
        $id = (string)$tileMatrixSet->ows_Identifier;
        echo "<li><a href='?doc=$docid&amp;ypath=/tileMatrixSets/$id'>$id (",
          (string)$tileMatrixSet->ows_SupportedCRS,")</a>\n";
        //echo "<pre>tileMatrixSet="; print_r($tileMatrixSet);
      }
      echo "</ul>\n";
    }
    elseif (preg_match('!^/tileMatrixSets/([^/]+)$!', $ypath, $matches)) {
      $id = $matches[1];
      $cap = $this->getCapabilities();
      $cap = str_replace(['<ows:','</ows:'], ['<ows_','</ows_'], $cap);
      //die($cap);
      $cap = new SimpleXMLElement($cap);
      //echo "<pre>cap="; print_r($cap->Contents->TileMatrixSet);
      foreach ($cap->Contents->TileMatrixSet as $tileMatrixSet) {
        if ($id == (string)$tileMatrixSet->ows_Identifier) {
          echo "<h2>tileMatrixSet $id</h2>\n";
          echo "SupportedCRS: ",$tileMatrixSet->ows_SupportedCRS,"<br>\n";
          echo "<table border=1>",
            "<th>Identifier</th><th>ScaleDenominator</th><th>TopLeftCorner</th>",
            "<th>TileWidth</th><th>TileHeight</th>",
            "<th>MatrixWidth</th><th>MatrixHeight</th>\n";
          foreach ($tileMatrixSet->TileMatrix as $tileMatrix) {
            echo "<tr><td>",$tileMatrix->ows_Identifier,"</td>\n",
              "<td>",$tileMatrix->ScaleDenominator,"</td>\n",
              "<td>",$tileMatrix->TopLeftCorner,"</td>\n",
              "<td>",$tileMatrix->TileWidth,"</td>\n",
              "<td>",$tileMatrix->TileHeight,"</td>\n",
              "<td>",$tileMatrix->MatrixWidth,"</td>\n",
              "<td>",$tileMatrix->MatrixHeight,"</td>\n",
              "</tr>\n";
          }
          echo "</table>\n";
          echo "<pre>tileMatrixSet"; print_r($tileMatrixSet);
          die();
        }
      }
      echo "</ul>\n";
    }
    else
      parent::show($ypath);
    //echo "<pre>"; print_r($this->_c); echo "</pre>\n";
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
    
  // retourne un array ['format'=>format, 'image'=> image]
  // où image est la tuile de la couche $lyrName pour $zoom/$x/$y, $fmt est l'extension: 'png', 'jpg' ou ''
  // ou transmet l'exception générée par query()
  function tile(string $lyrName, string $style, int $zoom, int $x, int $y, string $fmt): array {
    $layer = $this->layer($lyrName);
    //echo "<pre>"; print_r($layer); echo "</pre>\n";
    if (!$layer) {
      throw new Exception("Erreur couche $lyrName inexistante dans WmtsServer::tile()");
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
    return ['format'=> $layer['format'], 'image'=> $this->query($query)];
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

