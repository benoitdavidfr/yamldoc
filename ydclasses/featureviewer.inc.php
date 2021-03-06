<?php
/*PhpDoc:
name: featureviewer.inc.php
title: featureviewer.inc.php - Dessinateur d'objets
functions:
doc: <a href='/yamldoc/?action=version&name=featureviewer.inc.php'>doc intégrée en Php</a>
includes: [ ../inc.php ]
*/
{ // doc 
$phpDocs['featureviewer.inc.php']['file'] = <<<'EOT'
name: featureviewer.inc.php
title: featureviewer.inc.php - Viewer d'objets implémentant l'interface iTileServer
journal:
  7/10/2018:
    - création
EOT;
}

require_once __DIR__.'/../inc.php';

{ // doc 
$phpDocs['featureviewer.inc.php']['classes']['FeatureViewer'] = <<<'EOT'
title: Viewer d'objets géographiques implémentant l'interface iTileServer
doc: |
  La classe FeatureViewer dessine les objets d'un featureDataset.
  
  Outre les champs de métadonnées, le document doit définir les champs suivants:
    - featureDataset : identifiant d'un featureDataset
EOT;
}
class FeatureViewer extends YamlDoc implements iTileServer {
  static $log = __DIR__.'/featureviewer.log.yaml'; // nom du fichier de log ou false pour pas de log
  protected $_c; // contient les champs du doc initial
  protected $fds; // le doc fds
  protected $layers;
  
  // crée un nouveau doc, $yaml est le contenu Yaml externe issu de l'analyseur Yaml
  function __construct($yaml, string $docid) {
    $this->_c = $yaml;
    $this->_id = $docid;
    if (!$this->featureDataset)
      throw new Exception("Erreur dans FeatureViewer::__construct : champ featureDataset absent");
    $this->fds = new_doc($this->featureDataset);
    $fds = $this->fds->extractByUri('');
    foreach ($fds['layers'] as $lyrName => $layer) {
      $lyr = ['title'=> $layer['title']];
      $lyr['minZoom'] = isset($layer['minZoom']) ? $layer['minZoom'] : $fds['minZoom'];
      $lyr['maxZoom'] = isset($layer['maxZoom']) ? $layer['maxZoom'] : $fds['maxZoom'];
      if (isset($layer['styleMap']))
        $lyr['styleMap'] = $layer['styleMap'];
      $this->layers[$lyrName] = $lyr;
    }
    //echo "<pre>fds="; print_r($fds); echo "</pre>\n";
  }
  
  // lit les champs
  function __get(string $name) { return isset($this->_c[$name]) ? $this->_c[$name] : null; }
  
  function show(string $ypath=''): void {
    $docid = $this->_id;
    echo "FeatureDrawer::show($docid, $ypath)<br>\n";
    if (!$ypath || ($ypath=='/')) {
      $c = $this->_c;
      $c['layers'] = "<html>\n<ul>";
      foreach ($this->layers as $lyrName => $layer)
        $c['layers'] .= "<li><a href='?doc=$docid&amp;ypath=/layers/$lyrName'>$layer[title]</a>";
      showDoc($docid, $c);
      //echo "<pre>fds="; print_r($this->fds); echo "</pre>\n";
    }
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
        '/layers/{layerName}'=> "retourne la description de la couche {layerName}",
        '/layers/{layerName}/{z}/{x}/{y}.{fmt}'=> "retourne la tuile {z} {x} {y} de la couche {layerName} en {fmt}",
        '/map'=> "retourne le contenu de la carte affichant les couches du jeu de données",
        '/map/{param}'=> Map::api()['api'],
      ]
    ];
  }
   
  // extrait le fragment défini par $ypath, utilisé pour générer un retour à partir d'un URI
  function extractByUri(string $ypath) {
    $docid = $this->_id;
    //echo "FeatureViewer::extractByUri($this->_id, $ypath)<br>\n";
    //$params = !isset($_GET) ? $_POST : (!isset($_POST) ? $_GET : array_merge($_GET, $_POST));
    if (!$ypath || ($ypath=='/')) {
      return array_merge(['_id'=> $this->_id], $this->_c, ['tileServer' => $this->tileServer]);
    }
    elseif ($ypath == '/api') {
      return self::api();
    }
    elseif ($ypath == '/map') {
      return $this->map()->asArray();
    }
    elseif ($ypath == '/layers') {
      return $this->layers();
    }
    elseif (preg_match('!^/layers/([^/]+)$!', $ypath, $matches)) {
      return $this->layer($matches[1]);
    }
    elseif (preg_match('!^/layers/([^/]+)/([0-9]+)/([0-9]+)/([0-9]+)(\.(.+))?$!', $ypath, $matches)) {
      //print_r($matches);
      $this->displayTile($matches[1], '', $matches[2], $matches[3], $matches[4], isset($matches[5]) ? $matches[6] : '');
      die();
    }
    elseif (preg_match('!^/map(/.*)$!', $ypath, $matches)) {
      $this->map()->extractByUri($matches[1]);
      die();
    }
    else
      return null;
  }
  
  function layers(): array { return $this->layers; }
  
  function layer(string $lyrName): array {
    return $this->layers[$lyrName];
  }
  
  // renvoie un bbox en EPSG:3857 à partir du no de tuile
  static function bbox(int $zoom, int $ix, int $iy): array {
    $base = 20037508.3427892476320267;
    $size0 = $base * 2;
    $x0 = - $base;
    $y0 =   $base;
    $size = $size0 / pow(2, $zoom);
    return [ $x0 + $size * $ix, $y0 - $size * ($iy+1), $x0 + $size * ($ix+1), $y0 - $size * $iy ];
  }
  
  // retourne un booléen indiquant si la tuile est simple ou non
  function simpleTile(string $lyrName, string $style, int $zoom, int $x, int $y, string $fmt): bool {
    $bboxwm = self::bbox($zoom, $x, $y);
    $ptmin = WebMercator::geo($bboxwm[0], $bboxwm[1]);
    $ptmax = WebMercator::geo($bboxwm[2], $bboxwm[3]);
    $bbox = [$ptmin[0], $ptmin[1], $ptmax[0], $ptmax[1]];
    $numberOfFeatures = $this->fds->numberOfFeatures($lyrName, $bbox);
    //echo "numberOfFeatures=$numberOfFeatures<br>\n";
    if ($numberOfFeatures > 200)
      return false;
    else
      return true;
  }
  
  // retourne la ressource GD correspondant à l'image de la tuile
  function tile(string $lyrName, string $style, int $zoom, int $x, int $y, string $fmt) {
    //echo "FeatureViewer::tile('$lyrName', '$style', $zoom, $x, $y, '$fmt')<br>\n";
    if ($zoom < $this->layers[$lyrName]['minZoom']) {
      $drawing = new FVDrawing();
      return $drawing->image();
    }
    $fdsid = $this->featureDataset;
    $bboxwm = self::bbox($zoom, $x, $y);
    $ptmin = WebMercator::geo($bboxwm[0], $bboxwm[1]);
    $ptmax = WebMercator::geo($bboxwm[2], $bboxwm[3]);
    if (php_sapi_name()=='cli') {
      //throw new Exception("opération FeatureViewer::tile impossible en CLI");
      //  string exec ( string $command [, array &$output [, int &$return_var ]] )
      $command = "php id.php pub /$fdsid/$lyrName bbox=$ptmin[0],$ptmin[1],$ptmax[0],$ptmax[1] zoom=$zoom";
      //echo "command=$command\n";
      $output = '';
      $return_var = '';
      exec($command, $output, $return_var);
      if ($return_var <> 0)
        echo "return_var=$return_var\n";
      $featColl = '';
      foreach ($output as $line)
        $featColl .= "$line\n";
    }
    else {
      $url = "http://$_SERVER[SERVER_NAME]$_SERVER[SCRIPT_NAME]/$fdsid/$lyrName"
          ."?bbox=$ptmin[0],$ptmin[1],$ptmax[0],$ptmax[1]&zoom=$zoom";
      $featColl = file_get_contents($url);
    }
    $featColl = json_decode($featColl, true);
    //echo "<pre>featColl="; print_r($featColl); echo "</pre>\n";
    $styler = isset($this->layers[$lyrName]['styleMap']) ? new Styler($this->layers[$lyrName]['styleMap']) : null;
    $drawing = new FVDrawing($bboxwm);
    if (isset($featColl['features']) && $featColl['features']) {
      foreach ($featColl['features'] as $feature) {
        $this->drawFeature($drawing, $feature, $styler);
      }
    }
    return $drawing->image();
  }
  
  // affiche la tuile de la couche $lyrName pour $zoom/$x/$y, $fmt est l'extension: 'png', 'jpg' ou ''
  // ou transmet une exception
  function displayTile(string $lyrName, string $style, int $zoom, int $x, int $y, string $fmt): void {
    $drawing = $this->tile($lyrName, $style, $zoom, $x, $y, $fmt);
    $drawing->display();
  }
  
  function drawFeature(FVDrawing $drawing, array $feature, ?Styler $styler): void {
    //echo "FeatureDrawer::drawFeature(feature, ptmin=[$bboxwm[0], $bboxwm[1]])<br>\n";
    //echo "<pre>FeatureViewer::drawFeature() feature[properties]="; print_r($feature['properties']); echo "</pre>\n";
    $style = $styler ? $styler->style($feature) : null;
    //print_r($style); die();
    if ($feature['geometry']['type'] == 'LineString') {
      $ls = new LineString($feature['geometry']['coordinates']);
      $ls = $ls->chgCoordSys('geo', 'WM');
      $ls->draw($drawing,
        isset($style['color']) ? $style['color'] : '',
        '',
        isset($style['weight']) ? $style['weight'] : -1);
    }
    elseif ($feature['geometry']['type'] == 'MultiLineString') {
      //echo "<pre>feature="; print_r($feature); echo "</pre>\n";
      foreach ($feature['geometry']['coordinates'] as $lscoord) {
        $ls = new LineString($lscoord);
        $ls = $ls->chgCoordSys('geo', 'WM');
        $ls->draw($drawing,
          isset($style['color']) ? $style['color'] : '',
          '',
          isset($style['weight']) ? $style['weight'] : -1);
      }
    }
    elseif ($feature['geometry']['type'] == 'Polygon') {
      $pol = new Polygon($feature['geometry']['coordinates']);
      $pol = $pol->chgCoordSys('geo', 'WM');
      $pol->draw($drawing,
        '',
        isset($style['color']) ? $style['color'] : '',
        isset($style['weight']) ? $style['weight'] : -1);
    }
    elseif ($feature['geometry']['type'] == 'MultiPolygon') {
      foreach ($feature['geometry']['coordinates'] as $polcoord) {
        $pol = new Polygon($polcoord);
        $pol = $pol->chgCoordSys('geo', 'WM');
        $pol->draw($drawing,
          '',
          isset($style['color']) ? $style['color'] : '',
          isset($style['weight']) ? $style['weight'] : -1);
      }
    }
    else
      throw new Exception("Erreur geometrie ".$feature['geometry']['type']." non prévue");
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
    foreach ($this->layers as $lyrid => $layer) {
      $overlay = [
        'title'=> $layer['title'],
        'type'=> 'TileLayer',
        'url'=> "http://$_SERVER[SERVER_NAME]$_SERVER[SCRIPT_NAME]/$docid/layers/$lyrid/{z}/{x}/{y}.png",
        'options'=> [ 'format'=> 'image/png', 'minZoom'=> $layer['minZoom'], 'maxZoom'=> $layer['maxZoom'] ],
      ];
      $map['overlays'][$lyrid] = $overlay;
      if (isset($layer['displayedByDefault']))
        $map['defaultLayers'][] = $lyrid;
    }
        
    return new Map($map, "$docid/map");
  }
};

{ // doc 
$phpDocs['featureviewer.inc.php']['classes']['FVDrawing'] = <<<'EOT'
title: génère le dessin d'une tuile correspondant au bbox en WM, les ordres sont transmis au travers du package geometry
doc: |
EOT;
}
class FVDrawing {
  static $colorDef = [ // 'RRGGBB' en hexa, voir https://www.rapidtables.com/web/color/html-color-codes.html
    'black' => '000000',
    'red' => 'FF0000',
    'green' => '00FF00',
    'lightGreen' => '90EE90',
    'blue' => '0000FF',
    'chocolate' => 'D2691E',
    'orange' => 'FFA500',
    'darkOrange'=> 'FF8C00',
    'lightGrey' => 'D3D3D3',
    'yellow' => 'FFFF00',
    
  ];
  protected $bbox; // bbox en WM
  protected $image; // l'image 256 X 256 contenant le dessin
  protected $colors=[];
  
  function __construct(array $bbox=[]) {
    $this->bbox = $bbox;
    if (($this->image = @imagecreatetruecolor(256, 256))===FALSE)
      throw new Exception("Erreur imagecreatetruecolor");
    if (!imagealphablending($this->image, FALSE))
      throw new Exception("erreur sur imagealphablending(FALSE)");
    if (!($color = @imagecolorallocatealpha($this->image, 0xFF, 0xFF, 0xFF, 0x7F)))
      throw new Exception("erreur sur imagecolorallocatealpha");
    if (!imagefilledrectangle($this->image, 0, 0, 255, 255, $color))
      throw new Exception("Erreur dans imagerectangle");
    if (!imagealphablending($this->image, TRUE))
      throw new Exception("erreur sur imagealphablending(TRUE)");
    $this->colors['black'] = imagecolorallocate($this->image, 0, 0, 0);
  }
  
  function color(string $colorName) {
    if (isset($this->colors[$colorName]))
      return $this->colors[$colorName];
    if (isset(self::$colorDef[$colorName])) {
      $def = hexdec(self::$colorDef[$colorName]);
      $this->colors[$colorName] = imagecolorallocate($this->image,
        ($def & 0x0FF0000) >> 16, ($def & 0x0FF00) >> 8, ($def & 0x0FF));
      return $this->colors[$colorName];
    }
    //throw new Exception("Couleur $colorName non définie dans FVDrawing");
    return null;
  }
  
  // renvoie l'image GD
  function image() {
    if (!imagealphablending($this->image, FALSE))
      throw new Exception("erreur sur imagealphablending(FALSE)");
    if (!imagesavealpha($this->image, TRUE))
      throw new Exception("erreur sur imagesavealpha(TRUE)");
    return $this->image;
  }
  
  // affiche l'image et la détruit
  function display() {
    if (!imagealphablending($this->image, FALSE))
      throw new Exception("erreur sur imagealphablending(FALSE)");
    if (!imagesavealpha($this->image, TRUE))
      throw new Exception("erreur sur imagesavealpha(TRUE)");
    header('Content-type: image/png');
    if (!imagepng($this->image))
      throw new Exception("Erreur dans imagepng");
    imagedestroy($this->image);
  }
  
  function drawLineString(array $geom, string $stroke, string $fill, int $stroke_with) {
    //print_r($geom);
    if (!$this->bbox)
      throw new Exception("Erreur dans FVDrawing::drawLineString: bbox vide");
    $color = $this->color($stroke) ? $this->color($stroke) : $this->color('black');
    $prevpt = null;
    foreach ($geom as $pt) {
      $x = round(($pt->x()-$this->bbox[0])/($this->bbox[2]-$this->bbox[0])*256);
      $y = round(256 - ($pt->y()-$this->bbox[1])/($this->bbox[3]-$this->bbox[1])*256);
      if ($prevpt) {
        if (!imageline($this->image, $prevpt[0], $prevpt[1], $x, $y, $color))
          throw new Exception("Erreur dans imageline $prevpt[0], $prevpt[1], $x, $y, $stroke");
      }
      $prevpt = [ $x, $y ];
    }
  }
  
  function drawPolygon(array $geom, string $stroke, string $fill, int $stroke_with) {
    //echo "FVDrawing::drawPolygon($stroke, $fill, $stroke_with)<br>\n"; //die();
    if (!$this->bbox)
      throw new Exception("Erreur dans FVDrawing::drawPolygon: bbox vide");
    $coord = [];
    $numpoints = 0;
    foreach ($geom[0]->points() as $pt) {
      $coord[] = round(($pt->x()-$this->bbox[0])/($this->bbox[2]-$this->bbox[0])*256);
      $coord[] = round(256 - ($pt->y()-$this->bbox[1])/($this->bbox[3]-$this->bbox[1])*256);
      $numpoints++;
    }
    //$color = $this->color($fill) ? $this->color($fill) : $this->color('lightGrey');
    if (!$this->color($fill))
      throw new Exception("Erreur dans FVDrawing::drawPolygon: couleur '$fill' non définie");
    $color = $this->color($fill);
    if (!imagefilledpolygon($this->image, $coord, $numpoints, $color)) {
      throw new Exception("Erreur dans imagepolygon [".implode(',',$coord)."] $fill");
    }
  }
};

// Un styler est initialisé avec un styleMap et sait calculer le style d'un feature
class Styler {
  protected $styleMap; // [ [ propName => [ value => style ] | 'default' => style ] ]
  
  function __construct(array $styleMap) { $this->styleMap = $styleMap; }
    
  function style(array $feature): array {
    //echo "<pre>Styler::style() sur:"; print_r($feature); echo "</pre>\n";
    foreach ($this->styleMap as $propName => $propStyleMap) {
      if ($propName == 'default')
        return $propStyleMap;
      if (isset($feature['properties'][$propName])) {
        $value = $feature['properties'][$propName];
        if (isset($propStyleMap[$value]))
          return $propStyleMap[$value];
      }
    }
    return [];
  }
};


if (basename(__FILE__)<>basename($_SERVER['PHP_SELF'])) return;


// Test de la classe FVDrawing
$drawing =  new FVDrawing([0, 0, 100, 100]);
$polygon = new Polygon([[[10,90], [40,50], [90,90], [10,90]]]);
$drawing->drawPolygon($polygon->linestrings(), '', 'blue', 1);
$lineString = new LineString([[10,90], [40,20], [70,90]]);
$drawing->drawLineString($lineString->points(), 'red', '', 1);
$drawing->flush();
