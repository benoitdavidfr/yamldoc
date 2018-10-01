<?php
/*PhpDoc:
name: viewds.inc.php
title: viewds.inc.php - Série de données de consultation
functions:
doc: <a href='/yamldoc/?action=version&name=viewds.inc.php'>doc intégrée en Php</a>
*/
{ // doc 
$phpDocs['viewds.inc.php']['file'] = <<<'EOT'
name: viewds.inc.php
title: viewds.inc.php - serveur de tuiles
doc: |
  La classe ViewDataset définit une série de données (SD) de consultation constituée de couches de consultation
  provenant de serveurs WMS/WMTS.  
  Les objectifs sont:
    1) exposer les couches indépendamment des types de serveur sous-jacents (WMS, WMTS)
    2) définir une liste de couches en:
      a) simplifiant les noms de couche
      b) définissant un ordre plus pratique, notamment avec:
        i) les couches les plus utilisées en premier
        ii) un regroupement des couches similaires
      c) mieux documenter les couches
    3) corriger des paramètres posant problème
    4) fournir une API XYZ
  
  Outre les champs de métadonnées, le document doit définir les champs suivants:

    - layersByGroups: liste de couches de la SD structurée par sous-liste, chaque couche identifiée est définie par:
      - title: son titre (obligatoire)
      - server: l'id du document définissant son serveur qui doit être un WmsServer ou un WmtsServer (obligatoire)
      - name: identifiant de la couche dans le serveur (obligatoire)
      - abstract: résumé expliquant le contenu de la couche
      - doc:
        - soit l'URL d'une doc complémentaire,
        - soit, si la doc dépend du zoom, un array avec comme clé le niveau de zoom minimum et comme champs:
          - max: le zoom maximum correspondant à cette doc
          - title: le titre
          - www: l'URL de la doc
      - format: le format d'images de la couche, pour forcer un format quand il n'est pas imposé (WMS)
      - minZoom: zoom minimum pour lequel la couche est définie, pour forcer une valeur quand elle n'est pas définie
        ou qu'elle est incorrecte
      - maxZoom: zoom maximum pour lequel la couche est définie, pour forcer une valeur quand elle n'est pas définie
        ou qu'elle est incorrecte

  A faire:
    - Génération de la carte
      
  Exemples:
    - view/igngp.yaml
    - view/shomgt.yaml

journal:
  24-30/9/2018:
    - améliorations
  23/9/2018:
    - création
EOT;
}
//require_once __DIR__.'/yamldoc.inc.php';
//require_once __DIR__.'/inc.php';

class ViewDataset extends YamlDoc {
  static $log = __DIR__.'/viewds.log.yaml'; // nom du fichier de log ou false pour pas de log
  protected $_c; // contient les champs
  protected $layers = []; // les couches [ id => ViewLayer ]
  protected $servers = []; // les serveurs
  
  // crée un nouveau doc, $yaml est le contenu Yaml externe issu de l'analyseur Yaml
  function __construct($yaml, string $docid) {
    $this->_c = $yaml;
    $this->_id = $docid;
    if (!$this->layersByGroup)
      throw new Exception("Erreur dans ViewDataset::__construct(): champ layersByGroup obligatoire");
    foreach ($this->layersByGroup as $group) {
      foreach ($group as $lyrid => $layer) {
        if (!isset($layer['server']))
          throw new Exception("Erreur dans ViewDataset::__construct(): champ server obligatoire pour la couche $lyrid");
        if (!isset($this->servers[$layer['server']]))
          $layer['server'] = new_doc($layer['server']);
        else
          $layer['server'] = $this->servers[$layer['server']];
        $layer['_id'] = $lyrid;
        $this->layers[$lyrid] = new ViewLayer($layer);
      }
    }
  }
  
  // lit les champs
  function __get(string $name) { return isset($this->_c[$name]) ? $this->_c[$name] : null; }

  // affiche le sous-élément de l'élément défini par $ypath
  function show(string $ypath=''): void {
    $docid = $this->_id;
    echo "ViewDataset::show($docid, $ypath)<br>\n";
    if (!$ypath || ($ypath=='/')) {
      echo "<h1>",$this->title,"</h1>\n";
      showDoc($docid, [
        'abstract'=> $this->abstract,
        'mapDisplay'=> "http://$_SERVER[SERVER_NAME]/yamldoc/id.php/$docid/map/display",
      ]);
      echo "<h2>Couches</h2>";
      foreach ($this->layersByGroup as $gid => $group) {
        echo "<h3>$gid</h3><ul>\n";
        foreach ($group as $lyrid => $layer) {
          echo "<li><a href='?doc=$docid&amp;ypath=/$lyrid'>$layer[title]</a></li>\n";
        }
        echo "</ul>\n";
      }
    }
    // /{lyrName}(/{style})?(/{zone})?(/{zoom}/{x}/{y})?
    elseif (preg_match('!^/([^/]+)(/([^/]+))?(/([^/]+))?(/([0-9]+)/([0-9]+)/([0-9]+))?$!', $ypath, $matches)) {
      { // zones géographiques prédéfinies 
        $zones = [ // code => [zoom, x, y]
          'wld' => [2, 1, 1], // monde
          'fxx' => [6, 32, 22], // métropole
          'anf' => [7, 41, 57], // Antilles françaises
          'guf' => [8, 89, 124], // Guyane
          'reu' => [11, 1339, 1146], // Réunion
          'myt' => [11, 1280, 1097], // Mayonne
          'spm' => [11, 703, 720], // Saint-Pierre-et-Miquelon
          'pyf' => [7, 10, 70], // Polynésie française
          'wlf' => [9, 3, 275], // Wallis-et-Futuna
          'ncl' => [8, 245, 143], // Nouvelle-Calédonie
          'ker' => [8, 177, 168], // Iles Kerguelen
          'asp' => [10, 732, 629], // Iles Saint-Paul et Amsterdam
          'crz' => [9, 328, 330], // Iles Crozet
          'glorieuse' => [13, 5172, 4360], // îles Glorieuses
          'tromelin' => [15, 21346, 17848], // île Tromelin
          'juanDeNova' => [11, 1266, 1121], // île Juan de Nova
          'bassasDaIndia' => [10, 624, 574], // Bassas da India
          'europa' => [10, 626, 577], // île Europa
          'clipperton' => [15, 6442, 15440], // Ile Clipperton
        ];
      }
      $lyrName = $matches[1];
      $style = (isset($matches[2]) && $matches[2]) ? $matches[3] : '';
      $zone = (isset($matches[4]) && $matches[5]) ? $matches[5] : '';
      if (isset($zones[$style])) {
        $zone = $style;
        $style = '';
      }
      $zxy = isset($matches[6]) ? [$matches[7], $matches[8], $matches[9]] : [];
      if ($style)
        echo "style='$style'<br>\n";
      $layer = $this->layers[$lyrName];
      //print_r($layer);
      $layer->show($docid, $lyrName, $zxy);
      if ($style)
        $lyrName = "$lyrName/$style";
      if (!$zxy && !$zone) {
        foreach(array_keys($zones) as $z)
          echo "<a href='?doc=$docid&amp;ypath=/$lyrName/$z'>$z</a> ";
        echo "<br>\n";
      }
      list($zoom, $col, $row) = $zxy ? $zxy : (isset($zones[$zone]) ? $zones[$zone] : $zones['wld']);
      $cmin = max($col-1, 0);
      $cmax = min($col+2, 2**$zoom - 1);
      $rmin = max($row-1, 0);
      $rmax = min($row+2, 2**$zoom - 1);
      echo "<table style='border:1px solid black; border-collapse:collapse;'>\n";
      // affichage du zoom dans le coin cliquable pour faire un zoom-out
      if ($zoom) {
        $href = sprintf("?doc=$docid&amp;ypath=/$lyrName/%d/%d/%d", $zoom-1, $col/2, $row/2);
        echo "<tr><td><a href='$href'>$zoom</a></td>";
      }
      else
        echo "<tr><td>$zoom</td>";
      // affichage dans la première ligne des numéros de colonne
      for($col=$cmin; $col <= $cmax; $col++) {
        echo "<td align='center'>col=$col</td>";
      }
      echo "</tr>\n";
      for($row=$rmin; $row <= $rmax; $row++) {
        echo "<tr><td>row=<br>$row</td>"; // premère colonne: no de ligne
        for($col=$cmin; $col <= $cmax; $col++) {
          // si la tuile cliquée est une de celles du bord, déplacement à zoom constant
          if (($row==$rmin) || ($row==$rmax) || ($col==$cmin) || ($col==$cmax))
            $href = sprintf("?doc=$docid&amp;ypath=/$lyrName/%d/%d/%d", $zoom, $col, $row);
          else // sinon zoom in de manière à ce que la tuile cliquée soit remplacée par les 4 tuiles du centre
            $href = sprintf("?doc=$docid&amp;ypath=/$lyrName/%d/%d/%d", $zoom+1, $col*2, $row*2);
          //$style = " style='border:1px solid blue;'";
          //$style = " style='border-collapse: collapse;'";
          $style = " style='padding: 0px; border:1px solid blue;'";
          $src = "http://$_SERVER[SERVER_NAME]/yamldoc/id.php/$docid/$lyrName/$zoom/$col/$row";
          $img = "<img src='$src' alt='$lyrName/$zoom/$col/$row' height='256' width='256'>";
          echo "<td$style><a href='$href'>$img</a></td>\n";
        }
        echo "</tr>\n";
      }
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
          echo "<pre>style="; print_r($style); echo "</pre>\n";
          if (!($legendUrl = (string)$style->LegendURL['xlink_href'])) // WMTS
            $legendUrl = (string)$style->LegendURL->OnlineResource['xlink_href']; // WMS
          echo "<img src='$legendUrl' alt='erreur'><br>\n";
          die();
        }
      }
    }
    else {
      $lyrid = substr($ypath, 1);
      showDoc($docid, $this->layers[$lyrid]);
    }
    //echo "<pre>"; print_r($this->_c); echo "</pre>\n";
  }
  
  // décapsule l'objet et retourne son contenu sous la forme d'un array
  function asArray() { return array_merge(['_id'=> $this->_id], $this->_c); }

  // extrait le fragment du document défini par $ypath
  function extract(string $ypath) { return YamlDoc::sextract($this->_c, $ypath); }
  
  static function api(): array {
    return [
      'class'=> get_class(), 
      'title'=> "description de l'API de la classe ".get_class(), 
      'abstract'=> "série de données de consultation",
      'api'=> [
        '/'=> "retourne le contenu du document ".get_class(),
        '/api'=> "retourne les points d'accès de ".get_class(),
        '/layers'=> "retourne la liste des couches exposées par le serveur avec pour chacune son titre et son résumé",
        '/{layerName}'=> "retourne la description de la couche {layerName}",
        '/{layerName}/{z}/{x}/{y}'=> "retourne la tuile {z} {x} {y} de la couche {layerName}",
        '/{layerName}/{z}/{x}/{y}.{fmt}'=> "retourne la tuile {z} {x} {y} de la couche {layerName} dans le format {fmt}",
        '/{layerName}/{style}/{z}/{x}/{y}'=>
            "retourne la tuile {z} {x} {y} de la couche {layerName} dans le style {style}",
        '/{layerName}/{style}/{z}/{x}/{y}.{fmt}'=>
            "retourne la tuile {z} {x} {y} de la couche {layerName} dans le style {style} et le format {fmt}",
        '/{layerName}/{style}/{z}/{x}/{y}'=>
            "retourne la tuile {z} {x} {y} de la couche {layerName} dans le style {style}",
        '/map'=> "retourne le contenu de la carte affichant les couches du serveur WMS",
        '/map/{param}'=> Map::api()['api'],
      ]
    ];
  }

  // extrait le fragment défini par $ypath, utilisé pour générer un retour à partir d'un URI
  function extractByUri(string $ypath) {
    $docuri = $this->_id;
    //echo "WfsServer::extractByUri($docuri, $ypath)<br>\n";
    if (!$ypath || ($ypath=='/')) {
      return array_merge(['_id'=> $this->_id], $this->_c);
    }
    elseif ($ypath == '/api') {
      return self::api();
    }
    elseif ($ypath == '/map') {
      return $this->map()->asArray();
    }
    elseif (preg_match('!^/map(/.*)$!', $ypath, $matches)) {
      $this->map()->extractByUri($matches[1]);
      die();
    }
    // /{layerName}
    elseif (preg_match('!^/([^/]+)$!', $ypath, $matches)) {
      return $this->layers[$matches[1]]->asArray();
    }
    // /{layerName}/...
    elseif (preg_match('!^/([^/]+)(/.*)$!', $ypath, $matches)) {
      return $this->layers[$matches[1]]->extractByUri($matches[2]);
    }
    else
      return null;
  }
  
  // fabrique la carte d'affichage des couches
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
    foreach ($this->layers as $lyrid => $layer) {
      $layer = $layer->asArray();
      $format = $layer['format'];
      $ext = ($format=='image/png') ? 'png' : 'jpg';
      $overlay = [
        'title'=> $layer['title'],
        //'layer'=> $layer,
        'type'=> 'TileLayer',
        'url'=> "http://$_SERVER[SERVER_NAME]$_SERVER[SCRIPT_NAME]/$docid/$lyrid/{z}/{x}/{y}.$ext",
        'options'=> [ 'format'=> $format, 'minZoom'=> $layer['minZoom'], 'maxZoom'=> $layer['maxZoom'] ],
      ];
      $map['overlays'][$lyrid] = $overlay;
      if (isset($layer['displayedByDefault']))
        $map['defaultLayers'][] = $lyrid;
    }
        
    return new Map($map, "$docid/map");
  }
};


class ViewLayer {
  private $_id, $title, $server, $name, $abstract, $doc, $format, $minZoom, $maxZoom;
  
  function __construct(array $layer) {
    $this->_id = $layer['_id'];
    $this->title = $layer['title'];
    $this->server = $layer['server'];
    $this->name = $layer['name'];
    $this->abstract = isset($layer['abstract']) ? $layer['abstract'] : null;
    $this->doc = isset($layer['doc']) ? $layer['doc'] : null;
    $this->format = isset($layer['format']) ? $layer['format'] : null;
    $this->minZoom = isset($layer['minZoom']) ? $layer['minZoom'] : null;
    $this->maxZoom = isset($layer['maxZoom']) ? $layer['maxZoom'] : null;
  }
  
  function asArray(): array {
    //echo "<pre>layer="; print_r($this); die();
    if (!$this->format || !$this->minZoom || !$this->maxZoom)
      $serverLayer = $this->server->layer($this->name);
    return [
      '_id'=> $this->_id,
      'title'=> $this->title,
      'server'=> $this->server->asArray(),
      'name'=> $this->name,
      'abstract'=> $this->abstract,
      'doc'=> $this->doc,
      'format'=> $this->format ? $this->format : (isset($serverLayer['format']) ? $serverLayer['format'] : 'image/jpeg'),
      'minZoom'=> $this->minZoom ? $this->minZoom : (isset($serverLayer['minZoom']) ? $serverLayer['minZoom'] : 0),
      'maxZoom'=> $this->maxZoom ? $this->maxZoom : (isset($serverLayer['maxZoom']) ? $serverLayer['maxZoom'] : 21),
    ];
  }
    
  function styles(): array {
    $layer = $this->server->layer($this->name);
    return isset($layer['styles']) ? $layer['styles'] : [];
  }
  
  function show(string $vdsid, string $lyrName, array $zxy): void {
    $layer = $this->asArray();
    $serverId = $layer['server']['_id'];
    $serverTitle = $layer['server']['title'];
    $layer['server'] = "[$serverTitle](?doc=$serverId)";
    $layer['styles'] = [];
    foreach ($this->styles() as $styleName => $style) {
      $hrefName = "?doc=$vdsid&amp;ypath=/$lyrName/$styleName".($zxy ? '/'.implode('/',$zxy) : '');
      $newStyleName = "<html>\n<a href='$hrefName'>$styleName</a>";
      $hrefTitle = "?doc=$vdsid&amp;ypath=/layers/$lyrName/legend/".rawurlencode($styleName);
      $style['title'] = "<html>\n<a href='$hrefTitle'>$style[title]</a>";
      $layer['styles'][$newStyleName] = $style;
    }
    showDoc($vdsid, $layer);
  }
  
  function extractByUri(string $ypath) {
    if (preg_match('!^(/([^/]+))?/([0-9]+)/([0-9]+)/([0-9]+)(\..+)?$!', $ypath, $matches)) {
      $this->server->tile($this->name, $matches[1] ? $matches[2] : '',
          $matches[3], $matches[4], $matches[5],
          isset($matches[6]) ? $matches[6] : '');
    }
  }
};
