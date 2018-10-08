<?php
/*PhpDoc:
name: tilecache.inc.php
title: tilecache.inc.php - cache de tuiles
functions:
doc: <a href='/yamldoc/?action=version&name=tilecache.inc.php'>doc intégrée en Php</a>
*/
{ // doc 
$phpDocs['tilecache.inc.php']['file'] = <<<'EOT'
name: tilecache.inc.php
title: tilecache.inc.php - Cache de tuiles implémentant l'interface iTileServer
doc: |
  La classe TileCache gère un cache de tuiles implémentant l'interface iTileServer
  
  Outre les champs de métadonnées, le document doit définir les champs suivants:
    - tileServer : identifiant d'un document iTileServer correspondant au serveur de tuiles à cacher
    - layers
      - lyrName : nom de la couche à cacher
        - zoom: niveau de zoom de référence à moissonner dans le 
        - minZoom: niveau min de zoom à déduire
        - xmin, xmax: intervalle de no de colonnes à cacher pour le zoom zoom
        - ymin, ymax: intervalle de no de lignes à cacher pour le zoom zoom

journal:
  8/10/2018:
    - création
EOT;
}

require_once __DIR__.'/../inc.php';

class TileCache extends YamlDoc implements iTileServer {
  static $log = __DIR__.'/tilecache.log.yaml'; // nom du fichier de log ou false pour pas de log
  protected $_c; // contient les champs du doc initial
  protected $ts; // le doc ts
  
  // crée un nouveau doc, $yaml est le contenu Yaml externe issu de l'analyseur Yaml
  function __construct($yaml, string $docid) {
    $this->_c = $yaml;
    $this->_id = $docid;
    if (!$this->tileServer)
      throw new Exception("Erreur dans TileCache::__construct : champ tileServer absent");
    if (!$this->layers)
      throw new Exception("Erreur dans TileCache::__construct : champ layers absent");
    $this->ts = new_doc($this->tileServer);
    $tslayers = $this->ts->extractByUri('/layers');
    foreach ($this->layers as $lyrNname => $layer) {
      //echo "lyrName: $lyrNname<br>\n";
      //echo "  title: ",$tslayers[$lyrNname]['title'],"<br>\n";
      $this->_c['layers'][$lyrNname]['title'] = $tslayers[$lyrNname]['title'];
    }
    //echo "<pre>tslayers="; print_r($tslayers); echo "</pre>\n";
  }
  
  // lit les champs
  function __get(string $name) { return isset($this->_c[$name]) ? $this->_c[$name] : null; }
  
  function show(string $ypath=''): void {
    $docid = $this->_id;
    echo "TileCache::show($docid, $ypath)<br>\n";
    if (!$ypath || ($ypath=='/')) {
      $c = $this->_c;
      $c['layers'] = "<html>\n<ul>";
      foreach ($this->layers as $lyrName => $layer)
        $c['layers'] .= "<li><a href='?doc=$docid&amp;ypath=/layers/$lyrName'>$layer[title]</a>";
      showDoc($docid, $c);
      //echo "<pre>fds="; print_r($this->fds); echo "</pre>\n";
    }
    elseif (preg_match('!^/layers/([^/]+)(/([0-9]+)/([0-9]+)/([0-9]+))?$!', $ypath, $matches)) {
      $lyrName = $matches[1];
      $zxy = isset($matches[2]) ? [$matches[3], $matches[4], $matches[5]] : [];
      $layer = $this->layer($lyrName);
      //print_r($layer);
      showDoc($docid, $layer);
      echo "<a href='id.php/$docid/layers/$lyrName/capabilities'>Capacités de la couche</a><br>\n";
      showTilesInHtml($docid, $lyrName, '', $zxy);
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
    //echo "TileCache::extractByUri($this->_id, $ypath)<br>\n";
    if (!$ypath || ($ypath=='/')) {
      return array_merge(['_id'=> $this->_id], $this->_c);
    }
    elseif ($ypath == '/api') {
      return self::api();
    }
    elseif (preg_match('!^/layers/([^/]+)$!', $ypath, $matches)) {
      return $this->layer($matches[1]);
    }
    elseif (preg_match('!^/layers/([^/]+)/([0-9]+)/([0-9]+)/([0-9]+)(\..+)?$!', $ypath, $matches)) {
      //print_r($matches);
      $this->tile($matches[1], '', $matches[2], $matches[3], $matches[4], isset($matches[5]) ? $matches[5] : '');
    }
    // /fill/{lyrName}/{zoom}/{xmin}-{xmax}/{ymin}-{ymax}
    elseif (preg_match('!^/fill/([^/]+)/([0-9]+)/([0-9]+)-([0-9]+)/([0-9]+)-([0-9]+)?$!', $ypath, $matches)) {
      $this->fill($matches[1], $matches[2], $matches[3], $matches[4], $matches[5], $matches[6]);
      return "ok";
    }
    // /derive/{lyrName}/{zoom}/{xmin}-{xmax}/{ymin}-{ymax}
    elseif (preg_match('!^/derive/([^/]+)/([0-9]+)/([0-9]+)-([0-9]+)/([0-9]+)-([0-9]+)?$!', $ypath, $matches)) {
      $this->derive($matches[1], $matches[2], $matches[3], $matches[4], $matches[5], $matches[6]);
      return "ok";
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
  
  function layers(): array { return $this->layers; }
  
  function layer(string $lyrName): array {
    return $this->layers[$lyrName];
  }
      
  function tile(string $lyrName, string $style, int $zoom, int $x, int $y, string $fmt): void {
    $image = $this->makeTile($lyrName, $style, $zoom, $x, $y);
    header('Content-type: image/png');
    @imagepng($image);
    die();
  }
  
  function makeTile(string $lyrName, string $style, int $zoom, int $x, int $y, bool $force=false) /* : resource */ {
    //echo "TileCache::tile('$lyrName', '$style', $zoom, $x, $y, '$fmt')<br>\n";
    //$this->ts->extractByUri("/layers/$lyrName/$zoom/$x/$y");
    $tsid = $this->tileServer;
    $path = __DIR__.'/tilecache';
    if (!is_dir($path)) mkdir($path);
    //echo "_id=",$this->_id,"<br>\n";
    $path .= '/'.str_replace('/','-',$this->_id);
    if (!is_dir($path)) mkdir($path);
    $path .= "/$lyrName";
    if (!is_dir($path)) mkdir($path);
    $path .= "/$zoom";
    if (!is_dir($path)) mkdir($path);
    $path .= "/$x";
    if (!is_dir($path)) mkdir($path);
    $path .= "/$y.png";
    //echo "path=$path<br>\n";
    if (!$force && is_file($path)) {
      if (!($image = @imagecreatefrompng($path)))
        throw new Exception("erreur de lecture de $path");
    }
    else {
      if (php_sapi_name() == 'cli')
        $url = "http://localhost/yamldoc/id.php";
      else
        $url = "http://$_SERVER[SERVER_NAME]$_SERVER[SCRIPT_NAME]";
      $url .= "/$tsid/layers/$lyrName/$zoom/$x/$y";
      if (($image = @imagecreatefrompng($url)) === FALSE)
        throw new Exception("erreur de lecture de $url");
      if (!@imagepng($image, $path))
        throw new Exception("erreur imagepng sur $path");
    }
    return $image;
  }
  
  // remplit le cache à partir du serveur source
  function fill(string $lyrName, int $zoom, int $xmin, int $xmax, int $ymin, int $ymax): void {
    for ($x=$xmin; $x <= $xmax; $x++) {
      for ($y=$ymin; $y <= $ymax; $y++) {
        echo "$lyrName, $zoom, $x / $xmax, $y / $ymax<br>\n";
        $image = $this->makeTile($lyrName, '', $zoom, $x, $y, true);
        imagedestroy($image);
      }
    }
  }
  
  // derive les images à partir du zoom supérieur
  function derive(string $lyrName, int $zoom, int $xmin, int $xmax, int $ymin, int $ymax): void {
    for ($x=$xmin; $x <= $xmax; $x++) {
      for ($y=$ymin; $y <= $ymax; $y++) {
        echo "$lyrName, $zoom, $x / $xmax, $y / $ymax<br>\n";
        if (($image = @imagecreatetruecolor(256, 256))===FALSE)
          throw new Exception("Erreur imagecreatetruecolor");
        if (!imagealphablending($image, FALSE))
          throw new Exception("erreur sur imagealphablending(FALSE)");
        if (!($transparent = @imagecolorallocatealpha($image, 0xFF, 0xFF, 0xFF, 0x7F)))
          throw new Exception("erreur sur imagecolorallocatealpha");
        if (!imagefilledrectangle($image, 0, 0, 255, 255, $transparent))
          throw new Exception("Erreur dans imagerectangle");
        $im = $this->makeTile($lyrName, '', $zoom+1, $x*2, $y*2, false);
        // bool imagecopyresampled(resource $dst_image, resource $src_image,
        // int $dst_x , int $dst_y , int $src_x , int $src_y , int $dst_w , int $dst_h , int $src_w , int $src_h )
        if (!imagecopyresampled($image, $im, 0, 0, 0, 0, 128, 128, 255, 255))
          throw new Exception("Erreur imagecopyresampled");
        imagedestroy($im);
        $im = $this->makeTile($lyrName, '', $zoom+1, $x*2, $y*2+1, false);
        if (!imagecopyresampled($image, $im, 0, 128, 0, 0, 128, 128, 255, 255))
          throw new Exception("Erreur imagecopyresampled");
        imagedestroy($im);
        $im = $this->makeTile($lyrName, '', $zoom+1, $x*2+1, $y*2, false);
        if (!imagecopyresampled($image, $im, 128, 0, 0, 0, 128, 128, 255, 255))
          throw new Exception("Erreur imagecopyresampled");
        imagedestroy($im);
        $im = $this->makeTile($lyrName, '', $zoom+1, $x*2+1, $y*2+1, false);
        if (!imagecopyresampled($image, $im, 128, 128, 0, 0, 128, 128, 255, 255))
          throw new Exception("Erreur imagecopyresampled");
        imagedestroy($im);
        if (!imagesavealpha($image, TRUE))
          throw new Exception("erreur sur imagesavealpha(TRUE)");
        
        $path = __DIR__.'/tilecache';
        if (!is_dir($path)) mkdir($path);
        $path .= '/'.str_replace('/','-',$this->_id);
        if (!is_dir($path)) mkdir($path);
        $path .= "/$lyrName";
        if (!is_dir($path)) mkdir($path);
        $path .= "/$zoom";
        if (!is_dir($path)) mkdir($path);
        $path .= "/$x";
        if (!is_dir($path)) mkdir($path);
        $path .= "/$y.png";
        if (!@imagepng($image, $path))
          throw new Exception("erreur imagepng sur $path");
        imagedestroy($image);
      }
    }
    if ($zoom <= 0) return;
    $this->derive($lyrName, $zoom-1, floor($xmin/2), floor($xmax/2), floor($ymin/2), floor($ymax/2));
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


if (basename(__FILE__)<>basename($_SERVER['PHP_SELF'])) return;
