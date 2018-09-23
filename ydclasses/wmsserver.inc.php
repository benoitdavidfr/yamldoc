<?php
/*PhpDoc:
name: wmsserver.inc.php
title: wmsserver.inc.php - serveur WMS
functions:
doc: <a href='/yamldoc/?action=version&name=wmsserver.inc.php'>doc intégrée en Php</a>
*/
{ // doc 
$phpDocs['wmsserver.inc.php']['file'] = <<<'EOT'
name: wmsserver.inc.php
title: wmsserver.inc.php - serveur WMS
doc: |
  La classe WmsServer définit des serveurs WMS.

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
require_once __DIR__.'/../isometadata.inc.php';
require_once __DIR__.'/inc.php';

class WmsServer extends YamlDoc {
  //static $log = __DIR__.'/wmsserver.log.yaml'; // nom du fichier de log ou false pour pas de log
  static $log = false; // nom du fichier de log ou false pour pas de log
  static $capCache = __DIR__.'/tscapcache'; // nom du répertoire dans lequel sont stockés les fichiers XML de capacités
  protected $_c; // contient les champs
  
  // crée un nouveau doc, $yaml est le contenu Yaml externe issu de l'analyseur Yaml
  function __construct($yaml, string $docid) {
    $this->_c = $yaml;
    $this->_id = $docid;
    if (!$this->url)
      throw new Exception("Erreur dans WmsServer::__construct(): champ url obligatoire");
  }
  
  // lit les champs
  function __get(string $name) { return isset($this->_c[$name]) ? $this->_c[$name] : null; }

  // affiche le sous-élément de l'élément défini par $ypath
  function show(string $ypath=''): void {
    $docid = $this->_id;
    echo "WmsServer::show($docid, $ypath)<br>\n";
    if (!$ypath || ($ypath=='/')) {
      $c = $this->_c;
      unset($c['referer']);
      $c['layers'] = '';
      foreach ($this->layers() as $lyrid => $layer)
        $c['layers'] .= "- [$layer[title]](?ypath=/layers/$lyrid)\n";
      $c['capabilities'] = "http://$_SERVER[SERVER_NAME]/yamldoc/id.php/$docid/capabilities";
      $c['mapDisplay'] = "http://$_SERVER[SERVER_NAME]/yamldoc/id.php/$docid/map/display";
      showDoc($docid, $c);
    }
    elseif ($ypath == '/layers') {
      $layers = '';
      foreach ($this->layers() as $lyrid => $layer)
        $layers .= "- [$layer[title]](?ypath=/layers/$lyrid)\n";
      showDoc("$docid/layers", $layers);
    }
    elseif (preg_match('!^/layers/([^/]+)(/([^/]+))?(/([0-9]+)/([0-9]+)/([0-9]+))?$!', $ypath, $matches)) {
      $lyrName = $matches[1];
      $style = (isset($matches[2]) && $matches[2]) ? $matches[3] : '';
      echo "style='$style'<br>\n";
      $layer = $this->layer($lyrName);
      //print_r($layer);
      foreach ($layer['styles'] as $styleName => $s) {
        $href = "?doc=$docid&amp;ypath=/layers/$lyrName/$styleName"
          .(isset($matches[4]) ? "/$matches[5]/$matches[6]/$matches[7]" : '');
        $layer['styles'][$styleName]['title'] = "<html>\n<a href='$href'>$s[title]</a>";
      }
      showDoc($docid, $layer);
      $zoom = isset($matches[4]) ? $matches[5]: 2;
      $col = isset($matches[4]) ? max($matches[6], 0) : 0;
      $cmin = isset($matches[4]) ? max($matches[6]-1, 0) : 0;
      $cmax = isset($matches[4]) ? min($matches[6]+2, 2**$zoom - 1) : 2**$zoom - 1;
      $row = isset($matches[4]) ? $matches[7] : 0;
      $rmin = isset($matches[4]) ? max($matches[7]-1, 0) : 0;
      $rmax = isset($matches[4]) ? min($matches[7]+2, 2**$zoom - 1): 2**$zoom - 1;
      if ($style)
        $lyrName = "$lyrName/$style";
      echo "<table style='border:1px solid black; border-collapse:collapse;'>\n";
      if ($zoom) {
        $href = sprintf("?doc=$docid&amp;ypath=/layers/$lyrName/%d/%d/%d", $zoom-1, $col/2, $row/2);
        echo "<tr><td><a href='$href'>$zoom</a></td>";
      }
      else
        echo "<tr><td>$zoom</td>";
      for($col=$cmin; $col <= $cmax; $col++) {
        echo "<td align='center'>col=$col</td>";
      }
      echo "<tr>\n";
      for($row=$rmin; $row <= $rmax; $row++) {
        echo "<tr><td>row=<br>$row</td>";
        for($col=$cmin; $col <= $cmax; $col++) {
          if (($row==$rmin) || ($row==$rmax) || ($col==$cmin) || ($col==$cmax))
            $href = sprintf("?doc=$docid&amp;ypath=/layers/$lyrName/%d/%d/%d", $zoom, $col, $row);
          else
            $href = sprintf("?doc=$docid&amp;ypath=/layers/$lyrName/%d/%d/%d", $zoom+1, $col*2, $row*2);
          $style = " style='border:1px solid blue;'";
          $style = " style='border-collapse: collapse;'";
          $style = " style='padding: 0px; border:1px solid blue;'";
          $src = "http://$_SERVER[SERVER_NAME]/yamldoc/id.php/$docid/layers/$lyrName/$zoom/$col/$row";
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
      'abstract'=> "document correspondant à un serveur WMS",
      'api'=> [
        '/'=> "retourne le contenu du document ".get_class(),
        '/api'=> "retourne les points d'accès de ".get_class(),
        '/?{params}'=> "envoi une requête construite avec les paramètres GET et affiche le résultat en PNG ou JPG, le paramètre SERVICE est prédéfini",
        '/getCap(abilities)?'=> "envoi une requête GetCapabilities, affiche le résultat en XML et raffraichit le cache",
        '/cap(abilities)?'=> "affiche en XML le contenu du cache s'il existe, sinon envoi une requête GetCapabilities, affiche le résultat en XML et l'enregistre dans le cache",
        '/layers'=> "retourne la liste des couches exposées par le serveur avec pour chacune son titre et son résumé",
        '/layers/{layerName}'=> "retourne la description de la couche {layerName}",
        '/layers/{layerName}/{z}/{x}/{y}.{fmt}'=> "retourne la tuile {z} {x} {y} de la couche {layerName} en {fmt}",
        '/layers/{layerName}/{style}/{z}/{x}/{y}.{fmt}'=>
            "retourne la tuile {z} {x} {y} de la couche {layerName} dans le style {style} et le format {fmt}",
        '/{layerName}'=> "retourne la description de la couche {layerName}",
        '/{layerName}/{z}/{x}/{y}.{fmt}'=> "retourne la tuile {x} {y} de la couche {layerName} en {fmt}",
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
      if (!$_GET)
        return array_merge(['_id'=> $this->_id], $this->_c);
      $params = [];
      if ($_GET) {
        foreach ($_GET as $k => $v) {
          $params[strtoupper($k)] = $v;
        }
      }
      if (0)
        echo '';
      elseif (isset($params['REQUEST']) && (strtoupper($params['REQUEST'])=='GETCAPABILITIES'))
        header('Content-type: application/xml');
      elseif (isset($params['FORMAT']) && (strtoupper($params['FORMAT'])=='PNG'))
        header('Content-type: image/png');
      else
        header('Content-type: image/jpg');
      die($this->query($params));
    }
    elseif ($ypath == '/api') {
      return self::api();
    }
    // met à jour le cache des capacités et retourne les capacités
    elseif (preg_match('!^/getCap(abilities)?$!', $ypath, $matches)) {
      header('Content-type: application/xml');
      die($this->getCapabilities(true));
    }
    // retourne les capacités sans forcer la mise à jour du cache
    elseif (preg_match('!^/cap(abilities)?$!', $ypath, $matches)) {
      header('Content-type: application/xml');
      die($this->getCapabilities(false));
    }
    elseif ($ypath == '/layers') {
      return $this->layers();
    }
    elseif (preg_match('!^/layers/([^/]+)$!', $ypath, $matches)) {
      return $this->layer($matches[1]);
    }
    elseif (preg_match('!^/layers/([^/]+)(/([^/]+))?/([0-9]+)/([0-9]+)/([0-9]+)(\..+)?$!', $ypath, $matches)) {
      $this->tile($matches[1], $matches[2] ? $matches[3] : '',
          $matches[4], $matches[5], $matches[6],
          isset($matches[7]) ? $matches[7] : '');
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
    $url .= ((strpos($url, '?') === false) ? '?' : '&').'SERVICE=WMS';
    foreach($params as $key => $value)
      //$url .= "&$key=$value";
      $url .= '&'.strtoupper($key).'='.rawurlencode($value);
    if (self::$log) { // log
      file_put_contents(self::$log, YamlDoc::syaml(['url'=> $url]), FILE_APPEND);
    }
    return $url;
  }
  
  // envoi une requête et récupère la réponse sous la forme d'un texte
  function query(array $params): string {
    $url = $this->url($params);
    $context = null;
    if ($this->referer) {
      $referer = $this->referer;
      if (self::$log) { // log
        file_put_contents(
            self::$log,
            YamlDoc::syaml([
              'appel'=> 'WmsServer::query',
              'referer'=> $referer,
            ]),
            FILE_APPEND
        );
      }
      $context = stream_context_create(['http'=> ['header'=> "referer: $referer\r\n"]]);
    }
    if (($result = @file_get_contents($url, false, $context)) === false) {
      if (isset($http_response_header)) {
        if (self::$log) { // log
          file_put_contents(
              self::$log,
              YamlDoc::syaml(['http_response_header'=> $http_response_header]),
              FILE_APPEND
          );
        }
        if (preg_match('!^HTTP/1.. !', $http_response_header[0]))
          throw new Exception("Erreur http '$http_response_header[0]' dans WmsServer::query() : sur url=$url");
        else {
          echo "http_response_header=";
          var_dump($http_response_header);
        }
      }
      throw new Exception("Erreur dans WmsServer::query() : sur url=$url");
    }
    //die($result);
    if (substr($result, 0, 17) == '<ExceptionReport>') {
      if (!preg_match('!<ExceptionReport><[^>]*>([^<]*)!', $result, $matches))
        throw new Exception("Erreur dans WmsServer::query() : message d'erreur non détecté");
      throw new Exception ("Erreur dans WmsServer::query() : $matches[1]");
    }
    return $result;
  }
  
  // effectue un GetCapabities et retourne le XML. Utilise le cache sauf si force=true
  function getCapabilities(bool $force=false): string {
    //print_r($this); die();
    $filepath = self::$capCache.'/wms'.md5($this->url).'-cap.xml';
    if ((!$force) && file_exists($filepath))
      return file_get_contents($filepath);
    else {
      $cap = $this->query(['request'=> 'GetCapabilities']);
      if (!is_dir(self::$capCache))
        mkdir(self::$capCache);
      file_put_contents($filepath, $cap);
      return $cap;
    }
  }

  function layers(): array {
    $cap = new SimpleXMLElement($this->getCapabilities());
    //echo "<pre>"; print_r($cap); echo "</pre>\n";
    $lyrs = [];
    foreach ($cap->Capability->Layer->Layer as $layer) {
      //echo "<pre>"; print_r($layer); echo "</pre>\n";
      $lyrs[(string)$layer->Name] = [
        'title'=> (string)$layer->Title,
        'abstract'=> (string)$layer->Abstract,
      ];
    }
    return $lyrs;
  }
  
  function layer(string $name): array {
    $cap = new SimpleXMLElement($this->getCapabilities());
    //echo "<pre>"; print_r($cap); echo "</pre>\n";
    foreach ($cap->Capability->Layer->Layer as $layer) {
      if ((string)$layer->Name == $name) {
        //echo "<pre>"; print_r($layer); echo "</pre>\n";
        $lyr = [
          'title'=> (string)$layer->Title,
          'abstract'=> (string)$layer->Abstract,
        ];
        if ($layer->Style) {
          foreach ($layer->Style as $style) {
            $lyr['styles'][(string)$style->Name] = [
              'title'=> (string)$style->Title,
              'abstract'=> (string)$style->Abstract,
            ];
          }
        }
        return $lyr;
      }
    }
    return [];
  }
  
  // renvoie un bbox en EPSG:3857 à partir du no de tuile
  static function bbox(int $zoom, int $ix, int $iy): array {
    $base = 20037508.3427892476320267;
    $size0 = $base * 2;
    $x0 = - $base;
    $y0 =   $base;
    $size = $size0 / pow(2, $zoom);
    return [
      $x0 + $size * $ix,
      $y0 - $size * ($iy+1),
      $x0 + $size * ($ix+1),
      $y0 - $size * $iy,
    ];
  }
    
  function tile(string $lyrName, string $style, int $zoom, int $x, int $y, string $fmt): void {
    //$style = (isset($layers[$layername]['style']) ? $layers[$layername]['style'] : '');
    $query = [
      'version'=> '1.3.0',
      'request'=> 'GetMap',
      'layers'=> $lyrName,
      'format'=> $fmt=='.png' ? 'image/png' : 'image/jpeg',
      'styles'=> $style,
      'crs'=> 'EPSG:3857',
      'bbox'=> implode(',',self::bbox($zoom, $x, $y)),
      'height'=> 256,
      'width'=> 256,
    ];
    if ($fmt == '.png')
      $query['transparent'] = 'true';
    try {
      $image = $this->query($query);
      if (0) echo '';
      elseif ($fmt == '.png')
        header('Content-type: image/png');
      else
        header('Content-type: image/jpg');
      die($image);
    }
    catch(Exception $e) {
      if (self::$log) { // log
        file_put_contents(
            self::$log,
            YamlDoc::syaml([
              'date'=> date(DateTime::ATOM),
              'appel'=> 'WmsServer::tile',
              'erreur'=> $e->getMessage(),
            ]),
            FILE_APPEND
        );
      }
      if (preg_match("!^Erreur http '([^']*)'!", $e->getMessage(), $matches))
        header($matches[1]);
      die($e->getMessage());
    }
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

