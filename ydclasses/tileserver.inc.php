<?php
/*PhpDoc:
name: tileserver.inc.php
title: tileserver.inc.php - serveur de tuiles
functions:
doc: <a href='/yamldoc/?action=version&name=tileserver.inc.php'>doc intégrée en Php</a>
*/
{ // doc 
$phpDocs['tileserver.inc.php']['file'] = <<<'EOT'
name: tileserver.inc.php
title: tileserver.inc.php - serveur de tuiles
EOT;
}
require_once __DIR__.'/inc.php';

{ // doc 
$phpDocs['tileserver.inc.php']['classes']['TileServer'] = <<<'EOT'
title: serveur de tuiles
doc: |
  La classe TileServer permet d'utiliser des serveurs de tuiles.
  
  Outre les champs de métadonnées, le document doit définir les champs suivants:
    - url : url du serveur

  Le code fait l'hypothèse que l'URL du serveur renvoie un document JSON de description contenant un champ layers
  contenant la liste des couches dans le format:
    - 'name': identifiant de la couche
    - 'title': titre de la couche lisible par un humain
    - 'minZoom': niveau min de zoom
    - 'maxZoom': niveau max de zoom
    - 'format': format des tuiles, soit 'image/png', soit 'image/jpeg', soit 'png', 
EOT;
}
class TileServer extends YamlDoc implements iTileServer {
  static $log = __DIR__.'/tileserver.log.yaml'; // nom du fichier de log ou false pour pas de log
  //static $log = false; // nom du fichier de log ou false pour pas de log
  protected $_c; // contient les champs du doc initial
  protected $tileServer;
  
  // crée un nouveau doc, $yaml est le contenu Yaml externe issu de l'analyseur Yaml
  function __construct($yaml, string $docid) {
    $this->_c = $yaml;
    $this->_id = $docid;
    if (!$this->url)
      throw new Exception("Erreur dans TileServer::__construct(): champ url obligatoire");
    $this->tileServer = json_decode(file_get_contents($this->url), true);
    $layers = [];
    foreach ($this->tileServer['layers'] as $layer) {
      $layers[$layer['name']] = $layer;
    }
    $this->tileServer['layers'] = $layers;
  }
  
  // lit les champs
  function __get(string $name) { return isset($this->_c[$name]) ? $this->_c[$name] : null; }

  // affiche le sous-élément de l'élément défini par $ypath
  function show(string $ypath=''): void {
    $docid = $this->_id;
    echo "TileServer::show($docid, $ypath)<br>\n";
    if (!$ypath || ($ypath=='/')) {
      $c = $this->_c;
      $c['tileServer'] = $this->tileServer;
      showDoc($docid, $c);
    }
    elseif (preg_match('!^/([^/]+)(/([^/]+))?(/([0-9]+)/([0-9]+)/([0-9]+))?$!', $ypath, $matches)) {
      $lyrName = $matches[1];
      $zone = (isset($matches[2]) && $matches[2]) ? $matches[3] : '';
      $zxy = isset($matches[4]) ? [$matches[5], $matches[6], $matches[7]] : [];
      $layer = $this->tileServer['layers'][$lyrName];
      //print_r($layer);
      showDoc($docid, $layer);
      $zoom = $zxy ? $zxy[0] : 2;
      //$this->tileMatrixLimits($lyrName, $zoom);
      $col = $zxy ? max($zxy[1], 0) : 0;
      $cmin = $zxy ? max($zxy[1]-1, 0) : 0;
      $cmax = $zxy ? min($zxy[1]+2, 2**$zoom - 1) : 2**$zoom - 1;
      $row = $zxy ? $zxy[2] : 0;
      $rmin = $zxy ? max($zxy[2]-1, 0) : 0;
      $rmax = $zxy ? min($zxy[2]+2, 2**$zoom - 1): 2**$zoom - 1;
      echo "<table style='border:1px solid black; border-collapse:collapse;'>\n";
      if ($zoom) { // bouton de zoom-out si zoom > 0
        $href = sprintf("?doc=$docid&amp;ypath=/$lyrName/%d/%d/%d", $zoom-1, $col/2, $row/2);
        echo "<tr><td><a href='$href'>$zoom</a></td>";
      }
      else // sinon si zoom == 0 affichage du niveau de zoom
        echo "<tr><td>$zoom</td>";
      for($col=$cmin; $col <= $cmax; $col++) {
        echo "<td align='center'>col=$col</td>";
      }
      echo "<tr>\n";
      for($row=$rmin; $row <= $rmax; $row++) {
        echo "<tr><td>row=<br>$row</td>";
        for($col=$cmin; $col <= $cmax; $col++) {
          if (($row==$rmin) || ($row==$rmax) || ($col==$cmin) || ($col==$cmax))
            $href = sprintf("?doc=$docid&amp;ypath=/$lyrName/%d/%d/%d", $zoom, $col, $row);
          else
            $href = sprintf("?doc=$docid&amp;ypath=/$lyrName/%d/%d/%d", $zoom+1, $col*2, $row*2);
          $style = " style='border:1px solid blue;'";
          $style = " style='border-collapse: collapse;'";
          $style = " style='padding: 0px; border:1px solid blue;'";
          $src = "http://$_SERVER[SERVER_NAME]/yamldoc/id.php/$docid/$lyrName/$zoom/$col/$row";
          $img = "<img src='$src' alt='$lyrName/$zoom/$col/$row' height='256' width='256'>";
          echo "<td$style><a href='$href'>$img</a></td>\n";
        }
        echo "</tr>\n";
      }
    }
    else
      showDoc($docid, $this->extract($ypath));
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
      'abstract'=> "document correspondant à un serveur de tuiles",
      'api'=> [
        '/'=> "retourne le contenu du document ".get_class(),
        '/api'=> "retourne les points d'accès de ".get_class(),
        '/{layerName}'=> "retourne la description de la couche {layerName}",
        '/{layerName}/{z}/{x}/{y}.{fmt}'=> "retourne la tuile {z} {x} {y} de la couche {layerName} en {fmt}",
        '/map'=> "retourne le contenu de la carte affichant les couches du serveur WMS",
        '/map/{param}'=> Map::api()['api'],
      ]
    ];
  }
   
  // extrait le fragment défini par $ypath, utilisé pour générer un retour à partir d'un URI
  function extractByUri(string $ypath) {
    $docid = $this->_id;
    //echo "WmsServer::extractByUri($this->_id, $ypath)<br>\n";
    //$params = !isset($_GET) ? $_POST : (!isset($_POST) ? $_GET : array_merge($_GET, $_POST));
    if (!$ypath || ($ypath=='/')) {
      return array_merge(['_id'=> $this->_id], $this->_c, ['tileServer' => $this->tileServer]);
    }
    elseif ($ypath == '/api') {
      return self::api();
    }
    elseif (preg_match('!^/([^/]+)$!', $ypath, $matches)) {
      return $this->layer($matches[1]);
    }
    elseif (preg_match('!^/([^/]+)/([0-9]+)/([0-9]+)/([0-9]+)(\.(.+))?$!', $ypath, $matches)) {
      //print_r($matches);
      $this->displayTile($matches[1], '', $matches[2], $matches[3], $matches[4], isset($matches[5]) ? $matches[6] : '');
    }
    elseif ($ypath == '/map') {
      return $this->map()->asArray();
    }
    elseif (preg_match('!^/map(/.*)$!', $ypath, $matches)) {
      $this->map()->extractByUri($matches[1]);
      die();
    }
    else
      return null;
  }
  
  function layers(): array {
    return $this->tileServer['layers'];
  }
  
  function layer(string $lyrName): array {
    return $this->tileServer['layers'][$lyrName];
  }
  
  function tile(string $lyrName, string $style, int $zoom, int $x, int $y, string $fmt): array {
    if (!$fmt) {
      $layer = $this->tileServer['layers'][$lyrName];
      $fmt = $layer['format'];
    }
    $url = $this->url."/$lyrName/$zoom/$x/$y.$fmt";
    //echo "url=$url\n";
    if (($image = @file_get_contents($url))===false) {
      throw new Exception("Erreur dans la lecture de $url");
    }
    return ['format'=> ($fmt=='png') ? 'image/png' : 'image/jpeg', 'image'=> $image];
  }
  
  // affiche la tuile de la couche $lyrName pour $zoom/$x/$y, $fmt est l'extension: 'png', 'jpg' ou ''
  // ou transmet une exception
  function displayTile(string $lyrName, string $style, int $zoom, int $x, int $y, string $fmt): void {
    $tile = $this->tile($lyrName, $style, $zoom, $x, $y, $fmt);
    header('Content-type: '.$tile['format']);
    die($tile['image']);
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
