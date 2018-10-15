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
  
  La stratégie de remplissage du cache est la suivante:
    - on ne définit pas un niveau donné pour lequel le cache serait rempli
    - le remplissage s'effectue selon un algo récursif
    - je pars par exemple du niveau 6 pour lequel la métropole correspond à 31-33/21-23 soit 9 tuiles
    - Pour une tuile donnée:
      - si elle correspond à moins de 200 objets
      - alors je fabrique l'image sans conserver le résultat en cache
      - sinon:
        - j'effectue un appel récursif aux 4 sous-tuiles,
        - j'agrège les 4 sous-images et
        - je conserve le résultat en cache
      
  Pour l'affichage:
    - si la tuile est en cache
    - alors je l'utilise
    - sinon:
      - si elle fait moins de 200 objets
      - alors je la reconstruis interactivement
      - sinon j'abandonne avec une erreur 404
  L'affichage ne remplit pas le cache.
  
  Cet algo doit être réparti entre les 3 couches logicielles:
    - le cache qui enregistre les images en cache
    - le viewer qui fabrique l'image à partir du vecteur
    - le featureDs qui expose les objets vecteurs
    
  J'introduis pour cela dans le viewer la notion de dessin simple ou complexe.
  Un dessin simple peut être effectué en interactif à la volée.
  Un dessin complexe est uniquement effectué en traitement de type batch.
  xx
  
  Je peux construire les différents espaces concernés en donnant à chaque fois les qqs tuiles de départ.
  Je pourrais aussi partir initialement du Monde entier et le décomposer.
  La première approche est plus complexe mais plus efficace.
  
  Cet algo doit fonctionner pour les objets pas trop complexes comme les parcelles.
  Cela ne fonctionnerait probablement pas bien pour l'occupation du sol à cause de la complexité des zones.
  Dans le cas d'objets complexes, il faut probablemnt une première phase de simplification des objets.
  Il faut estimer le seuil des 10 objets en évaluant le temps nécessaire à la fabrication de l'image en fonction du nombre d'objets.
  
  
  Outre les champs de métadonnées, le document doit définir les champs suivants:
    - tileServer : identifiant d'un document iTileServer correspondant au serveur de tuiles à mettre en cache
    - layers
      - lyrName : nom de la couche du tileServer à mettre en cache

journal:
  15/10/2018:
    - première version un peu opérationnelle
  11/10/2018:
    - changement de stratégie de remplissage du cache
  8/10/2018:
    - création
EOT;
}

//require_once __DIR__.'/../inc.php';

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
    // récupération des titres des tuiles dans le tileServer
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
        '/layers'=> "retourne la liste des couches",
        '/layers/{layerName}'=> "retourne la description de la couche {layerName}",
        '/layers/{layerName}/{z}/{x}/{y}(.{fmt})?'=> "retourne la tuile {z} {x} {y} de la couche {layerName} en {fmt}",
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
    elseif ($ypath == '/layers') {
      return $this->layers();
    }
    elseif (preg_match('!^/layers/([^/]+)$!', $ypath, $matches)) {
      return $this->layer($matches[1]);
    }
    elseif (preg_match('!^/layers/([^/]+)/([0-9]+)/([0-9]+)/([0-9]+)(\.(.+))?$!', $ypath, $matches)) {
      //print_r($matches);
      $this->displayTile($matches[1], '', $matches[2], $matches[3], $matches[4], isset($matches[5]) ? $matches[6] : '');
    }
    // Affiche soit la tuile en cache soit une tuile simple soit une tuile complexe
    elseif (preg_match('!^/layers/([^/]+)/force/([0-9]+)/([0-9]+)/([0-9]+)(\.(.+))?$!', $ypath, $matches)) {
      //print_r($matches);
      $lyrName = $matches[1];
      $zoom = $matches[2];
      $x = $matches[3];
      $y = $matches[4];
      $fmt = isset($matches[5]) ? $matches[6] : '';
      $image = $this->tile($lyrName, '', $zoom, $x, $y, $fmt);
      if (!$image) {
        $image = $this->makeTile($lyrName, $zoom, $x, $y);
      }
      header('Content-type: image/png');
      if (!imagesavealpha($image, TRUE))
        throw new Exception("erreur sur imagesavealpha(TRUE)");
      @imagepng($image);
      die();
    }
    // /fill/{lyrName}/{zoom}/{xmin}-{xmax}/{ymin}-{ymax}
    elseif (preg_match('!^/fill/([^/]+)/([0-9]+)/([0-9]+)-([0-9]+)/([0-9]+)-([0-9]+)?$!', $ypath, $matches)) {
      $this->fill($matches[1], $matches[2], $matches[3], $matches[4], $matches[5], $matches[6]);
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
    
  function displayTile(string $lyrName, string $style, int $zoom, int $x, int $y, string $fmt): void {
    //echo "<pre>layer="; print_r($this->layers($lyrName)); die();
    //$layer = $this->layers($lyrName);
    $image = $this->tile($lyrName, $style, $zoom, $x, $y, $fmt);
    if (!$image) {
      header("HTTP/1.1 404 Not Found");
      die("Tuile absente du cache et complexe");
    }
    if (is_resource($image)) {
      if (!imagesavealpha($image, TRUE))
        throw new Exception("erreur sur imagesavealpha(TRUE)");
      header('Content-type: image/png');
      @imagepng($image);
      die();
    }
    else
      throw new Exception("image non reconnue");
  }
  
  // retourne le path de la tuile dans le cache
  private function tilePath(string $lyrName, int $zoom, int $x, int $y) {
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
    return $path;
  }
  
  // scenario d'affichage des tuiles à la volée
  // renvoie la tuile en cache si elle existe sinon interroge le tileServer pour lui demander une tuile simple
  // renvoie une ressource GD ou null si la tuile n'est pas dans le cache et est complexe
  function tile(string $lyrName, string $style, int $zoom, int $x, int $y, string $fmt) {
    //echo "TileCache::tile('$lyrName', '$style', $zoom, $x, $y, '$fmt')<br>\n";
    //$this->ts->extractByUri("/layers/$lyrName/$zoom/$x/$y");
    $path = $this->tilePath($lyrName, $zoom, $x, $y);
    //echo "path=$path<br>\n";
    if (is_file($path)) {
      if (!($image = @imagecreatefrompng($path)))
        throw new Exception("erreur de lecture de $path");
      return $image;
    }
    if ($this->ts->simpleTile($lyrName, $style, $zoom, $x, $y, $fmt))
      return $this->ts->tile($lyrName, $style, $zoom, $x, $y, $fmt);
    else
      return null;
  }
  
  // scenario de remplissage du cache
  // si la tuile est en cache
  // alors la renvoie
  // sinon
  //   si la tuile est simple
  //   alors la fabrique à partir du tileServer et la renvoie
  //   sinon
  //     appel récursif sur les 4 sous-tuiles et les agrège
  //     enregistre la nouvelle tuile en cache
  //     la renvoie
  // est utilisé en remplissage par fill() et en test par force
  function makeTile(string $lyrName, int $zoom, int $x, int $y) {
    if (php_sapi_name()=='cli')
      echo "TileCache::makeTile(lyrName=$lyrName, zoom=$zoom, x=$x, y=$y)<br>\n";
    
    $path = $this->tilePath($lyrName, $zoom, $x, $y);
    if (is_file($path)) {
      if (!($image = @imagecreatefrompng($path)))
        throw new Exception("erreur de lecture de $path");
      return $image;
    }

    if ($this->ts->simpleTile($lyrName, '', $zoom, $x, $y, ''))
      return $this->ts->tile($lyrName, '', $zoom, $x, $y, '');
    
    if (($image = @imagecreatetruecolor(256, 256))===FALSE)
      throw new Exception("Erreur imagecreatetruecolor");
    if (!imagealphablending($image, FALSE))
      throw new Exception("erreur sur imagealphablending(FALSE)");
    if (!($transparent = @imagecolorallocatealpha($image, 0xFF, 0xFF, 0xFF, 0x7F)))
      throw new Exception("erreur sur imagecolorallocatealpha");
    if (!imagefilledrectangle($image, 0, 0, 255, 255, $transparent))
      throw new Exception("Erreur dans imagerectangle");
    $im = $this->makeTile($lyrName, $zoom+1, $x*2, $y*2);
    // bool imagecopyresampled(resource $dst_image, resource $src_image,
    // int $dst_x , int $dst_y , int $src_x , int $src_y , int $dst_w , int $dst_h , int $src_w , int $src_h )
    if (!imagecopyresampled($image, $im, 0, 0, 0, 0, 128, 128, 255, 255))
      throw new Exception("Erreur imagecopyresampled");
    imagedestroy($im);
    $im = $this->makeTile($lyrName, $zoom+1, $x*2, $y*2+1, '');
    if (!imagecopyresampled($image, $im, 0, 128, 0, 0, 128, 128, 255, 255))
      throw new Exception("Erreur imagecopyresampled");
    imagedestroy($im);
    $im = $this->makeTile($lyrName, $zoom+1, $x*2+1, $y*2, '');
    if (!imagecopyresampled($image, $im, 128, 0, 0, 0, 128, 128, 255, 255))
      throw new Exception("Erreur imagecopyresampled");
    imagedestroy($im);
    $im = $this->makeTile($lyrName, $zoom+1, $x*2+1, $y*2+1, '');
    if (!imagecopyresampled($image, $im, 128, 128, 0, 0, 128, 128, 255, 255))
      throw new Exception("Erreur imagecopyresampled");
    imagedestroy($im);
    if (!imagesavealpha($image, TRUE))
      throw new Exception("erreur sur imagesavealpha(TRUE)");
    
    if (!@imagepng($image, $path))
      throw new Exception("erreur imagepng sur $path");
    return $image;
  }
  
  // remplit le cache à partir du serveur source
  function fill(string $lyrName, int $zoom, int $xmin, int $xmax, int $ymin, int $ymax): void {
    echo "TileCache::fill(lyrName=$lyrName, zoom=$zoom, xmin=$xmin, xmax=$xmax, ymin=$ymin, ymax=$ymax)<br>\n";
    for ($x=$xmin; $x <= $xmax; $x++) {
      for ($y=$ymin; $y <= $ymax; $y++) {
        echo "$lyrName, $zoom, $x / $xmax, $y / $ymax<br>\n";
        $image = $this->makeTile($lyrName, $zoom, $x, $y, 'force');
        imagedestroy($image);
      }
    }
  }
  
  // remplit le cache à partir du serveur source
  function Oldfill(string $lyrName, int $zoom, int $xmin, int $xmax, int $ymin, int $ymax): void {
    for ($x=$xmin; $x <= $xmax; $x++) {
      for ($y=$ymin; $y <= $ymax; $y++) {
        echo "$lyrName, $zoom, $x / $xmax, $y / $ymax<br>\n";
        $image = $this->makeTile($lyrName, $zoom, $x, $y, 'force');
        imagedestroy($image);
      }
    }
  }
  
  // derive les images à partir du zoom supérieur
  function Oldderive(string $lyrName, int $zoom, int $xmin, int $xmax, int $ymin, int $ymax): void {
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
        $im = $this->makeTile($lyrName, $zoom+1, $x*2, $y*2+1, '');
        if (!imagecopyresampled($image, $im, 0, 128, 0, 0, 128, 128, 255, 255))
          throw new Exception("Erreur imagecopyresampled");
        imagedestroy($im);
        $im = $this->makeTile($lyrName, $zoom+1, $x*2+1, $y*2, '');
        if (!imagecopyresampled($image, $im, 128, 0, 0, 0, 128, 128, 255, 255))
          throw new Exception("Erreur imagecopyresampled");
        imagedestroy($im);
        $im = $this->makeTile($lyrName, $zoom+1, $x*2+1, $y*2+1, '');
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
        'url'=> 'http://visu.gexplor.fr/utilityserver.php/whiteimg/{z}/{x}/{y}.png',
        'options'=> [ 'format'=> 'image/png', 'minZoom'=> 0, 'maxZoom'=> 21 ],
      ],
    ];
    $map['defaultLayers'] = ['whiteimg'];
        
    $docid = $this->_id;
    foreach ($this->layers as $lyrid => $layer) {
      $overlay = [
        'title'=> $layer['title'],
        'type'=> 'TileLayer',
        'url'=> "http://$_SERVER[SERVER_NAME]$_SERVER[SCRIPT_NAME]/$docid/layers/$lyrid/{z}/{x}/{y}.png",
        //'options'=> [ 'format'=> 'image/png', 'minZoom'=> $layer['minZoom'], 'maxZoom'=> $layer['maxZoom'] ],
        'options'=> [ 'format'=> 'image/png', 'minZoom'=> 0, 'maxZoom'=> 21 ],
      ];
      $map['overlays'][$lyrid] = $overlay;
      if (isset($layer['displayedByDefault']))
        $map['defaultLayers'][] = $lyrid;
    }
    $map['overlays']['debug'] = [
      'title'=> "Debug",
      'type'=> 'TileLayer',
      'url'=> 'http://visu.gexplor.fr/utilityserver.php/debug/{z}/{x}/{y}.png',
      'options'=> [ 'format'=> 'image/png', 'minZoom'=> 0, 'maxZoom'=> 21 ],
    ];
    
        
    return new Map($map, "$docid/map");
  }
};


if (basename(__FILE__)<>basename($_SERVER['PHP_SELF'])) return;
