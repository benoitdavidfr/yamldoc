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
  La classe abstraite OgcServer implémente des méthodes communes aux serveurs OGC.
  La classe WmsServer permet d'utiliser des serveurs WMS.

  Outre les champs de métadonnées, le document doit définir les champs suivants:
    - url : url du serveur

  Il peut aussi définir les champs suivants:
    - referer: referer à transmettre à chaque appel du serveur.

journal:
  3/11/2018:
    - prise en compte de la possibilité d'avoir un " dans le titre d'une couche
  22/9/2018:
    - création
EOT;
}

// implémente des méthodes communes à WmsServer et WmtsServer
abstract class OgcServer extends YamlDoc implements iTileServer {
  static $capCache = __DIR__.'/ogccapcache'; // nom du répertoire dans lequel sont stockés les fichiers XML de capacités
  protected $_c; // contient les champs
  
  // crée un nouveau doc, $yaml est le contenu Yaml externe issu de l'analyseur Yaml
  function __construct($yaml, string $docid) {
    $this->_c = $yaml;
    $this->_id = $docid;
    if (!$this->url)
      throw new Exception("Erreur dans OgcServer::__construct(): champ url obligatoire");
  }
  
  // lit les champs
  function __get(string $name) { return isset($this->_c[$name]) ? $this->_c[$name] : null; }
  
  // décapsule l'objet et retourne son contenu sous la forme d'un array
  function asArray() { return array_merge(['_id'=> $this->_id], $this->_c); }

  // extrait le fragment du document défini par $ypath
  function extract(string $ypath) { return YamlDoc::sextract($this->_c, $ypath); }

  // affiche le sous-élément de l'élément défini par $ypath
  function show(string $ypath=''): void {
    $docid = $this->_id;
    echo "OgcServer::show($docid, $ypath)<br>\n";
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
      showDoc($docid, $layers);
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
    else
      showDoc($docid, $this->extract($ypath));
    //echo "<pre>"; print_r($this->_c); echo "</pre>\n";
  }
  
  static function api(): array {
    return [
      'class'=> get_class(), 
      'title'=> "description de l'API de la classe ".get_class(), 
      'abstract'=> "document correspondant à un serveur OGC",
      'api'=> [
        '/'=> "retourne le contenu du document ".get_class(),
        '/api'=> "retourne les points d'accès de ".get_class(),
        '/?{params}'=> "envoi une requête construite avec les paramètres GET et affiche le résultat en PNG ou JPG, le paramètre SERVICE est prédéfini",
        '/getCap(abilities)?'=> "envoi une requête GetCapabilities, raffraichit le cache et affiche le résultat en XML",
        '/cap(abilities)?'=> "affiche en XML le contenu du cache s'il existe, sinon envoi une requête GetCapabilities, affiche le résultat en XML et l'enregistre dans le cache",
        '/layers'=> "retourne la liste des couches exposées par le serveur avec pour chacune son titre et son résumé",
        '/layers/{layerName}'=> "retourne la description de la couche {layerName}",
        '/layers/{layerName}/{z}/{x}/{y}.{fmt}'=> "retourne la tuile {z} {x} {y} de la couche {layerName} en {fmt}",
        '/layers/{layerName}/style/{style}/{z}/{x}/{y}.{fmt}'=>
            "retourne la tuile {z} {x} {y} de la couche {layerName} dans le style {style} et le format {fmt}",
        '/layers/{layerName}/capabilities'=> "affiche le fragment XML des capacités de la couche",
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
    // /layers/{lyrName}(/style/{styleName})?/{zoom}/{x}/{y}(.(png|jpg))?
    elseif (preg_match('!^/layers/([^/]+)(/style/([^/]+))?/([0-9]+)/([0-9]+)/([0-9]+)(\.(png|jpg))?$!', $ypath, $matches)) {
      try {
        $tile = $this->tile($matches[1], $matches[2] ? $matches[3] : '',
            $matches[4], $matches[5], $matches[6],
            isset($matches[7]) ? $matches[8] : '');
        header('Content-type: '.$tile['format']);
        die($tile['image']);
      }
      catch(Exception $e) {
        if (get_called_class()::$log) { // log
          file_put_contents(
              get_called_class()::$log,
              YamlDoc::syaml([
                'date'=> date(DateTime::ATOM),
                'appel'=> 'OgcServer::extractByUri',
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
    elseif (preg_match('!^/layers/([^/]+)/capabilities$!', $ypath, $matches)) {
      if (!($layerCap = $this->layerCap($matches[1]))) {
        header("HTTP/1.1 404 Not Found");
        die("Layer $matches[1] not found");
      }
      header('Content-type: application/xml');
      $this->printLayerCap($layerCap);
      die();
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
  
  // fabrique l'URL de la requête en ajoutant SERVICE aux paramètres
  function url(array $params): string {
    if (get_called_class()::$log) { // log
      file_put_contents(
          get_called_class()::$log,
          YamlDoc::syaml([
            'date'=> date(DateTime::ATOM),
            'appel'=> 'WmsServer::url',
            'params'=> $params,
          ]),
          FILE_APPEND
      );
    }
    $url = $this->url;
    $url .= ((strpos($url, '?') === false) ? '?' : '&').'SERVICE='.get_called_class()::$serviceTag;
    foreach($params as $key => $value)
      //$url .= "&$key=$value";
      $url .= '&'.strtoupper($key).'='.rawurlencode($value);
    if (get_called_class()::$log) { // log
      file_put_contents(get_called_class()::$log, YamlDoc::syaml(['url'=> $url]), FILE_APPEND);
    }
    return $url;
  }
  
  // envoi une requête et récupère la réponse sous la forme d'un texte
  // En cas d'erreur génère une exception HHTP ou OGC
  function query(array $params): string {
    $url = $this->url($params);
    $context = null;
    if ($this->referer) {
      $referer = $this->referer;
      if (get_called_class()::$log) { // log
        file_put_contents(
            get_called_class()::$log,
            YamlDoc::syaml([
              'appel'=> 'OgcServer::query',
              'referer'=> $referer,
            ]),
            FILE_APPEND
        );
      }
      $context = stream_context_create(['http'=> ['header'=> "referer: $referer\r\n"]]);
    }
    if (($result = @file_get_contents($url, false, $context)) === false) {
      if (isset($http_response_header)) {
        if (get_called_class()::$log) { // log
          file_put_contents(
              get_called_class()::$log,
              YamlDoc::syaml(['http_response_header'=> $http_response_header]),
              FILE_APPEND
          );
        }
        if (preg_match('!^HTTP/1.. !', $http_response_header[0]))
          throw new Exception("Erreur http '$http_response_header[0]' dans OgcServer::query() : sur url=$url");
        else {
          echo "http_response_header=";
          var_dump($http_response_header);
        }
      }
      throw new Exception("Erreur dans OgcServer::query() : sur url=$url");
    }
    //die($result);
    if (substr($result, 0, 17) == '<ExceptionReport>') {
      if (!preg_match('!<ExceptionReport><[^>]*>([^<]*)!', $result, $matches))
        throw new Exception("Erreur OGC dans OgcServer::query() : message d'erreur non détecté");
      throw new Exception ("Erreur OGC dans OgcServer::query() : $matches[1]");
    }
    return $result;
  }
  
  // effectue un GetCapabities et retourne le XML. Utilise le cache sauf si force=true
  function getCapabilities(bool $force=false): string {
    //print_r($this); die();
    $filepath = self::$capCache.'/ogc'.md5($this->url).'-cap.xml';
    if (!$force && file_exists($filepath))
      return file_get_contents($filepath);
    else {
      $cap = $this->query(['request'=> 'GetCapabilities']);
      if (!is_dir(self::$capCache))
        mkdir(self::$capCache);
      file_put_contents($filepath, $cap);
      return $cap;
    }
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
  
  // affiche la tuile de la couche $lyrName pour $zoom/$x/$y, $fmt est l'extension: 'png', 'jpg' ou ''
  // ou transmet une exception
  function displayTile(string $lyrName, string $style, int $zoom, int $x, int $y, string $fmt): void {
    $tile = $this->tile($lyrName, $style, $zoom, $x, $y, $fmt);
    header('Content-type: '.$tile['format']);
    die($tile['image']);
  }
  
  function xxsendImageOrError(array $query): void {
    try {
      $image = $this->query($query);
      header("Content-type: $query[format]");
      die($image);
    }
    catch(Exception $e) {
      if (get_called_class()::$log) { // log
        file_put_contents(
            get_called_class()::$log,
            YamlDoc::syaml([
              'date'=> date(DateTime::ATOM),
              'appel'=> 'OgcServer::sendImageOrError',
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
};

class WmsServer extends OgcServer {
  static $log = __DIR__.'/wmsserver.log.yaml'; // nom du fichier de log ou false pour pas de log
  static $serviceTag = 'WMS';
  
  // renvoie la liste des couches sous la forme d'un array [ lyrName => ['title', 'abstract'] ]
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
  
  // renvoie un array décrivant la layer ['title', 'abstract', 'styles']
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
  
  // renvoie les capacités de la couche sous la forme d'un SimpleXMLElement
  function layerCap(string $name): ?SimpleXMLElement {
    $cap = $this->getCapabilities();
    $cap = str_replace([' xlink:href='], [' xlink_href='], $cap);
    $cap = new SimpleXMLElement($cap);
    //echo "<pre>"; print_r($cap); echo "</pre>\n";
    foreach ($cap->Capability->Layer->Layer as $layer) {
      if ((string)$layer->Name == $name) {
        //echo "<pre>"; print_r($layer); echo "</pre>\n";
        return $layer;
      }
    }
    return null;
  }
  
  // affiche les capacités de couche en XML
  function printLayerCap(SimpleXMLElement $layerCap) {
    echo '<?xml version="1.0" encoding="UTF-8"?>',
      '<WMS_Capabilities',
      ' xmlns="http://www.opengis.net/wms"',
      ' xmlns:xlink="http://www.w3.org/1999/xlink">',
      $layerCap->asXml(),
      '</WMS_Capabilities>';
  }
    
  // retourne un array ['format'=>format, 'image'=> image]
  // où image est la tuile de la couche $lyrName pour $zoom/$x/$y, $fmt est l'extension: 'png', 'jpg' ou ''
  // ou transmet l'exception générée par query()
  function tile(string $lyrName, string $style, int $zoom, int $x, int $y, string $fmt): array {
    //$style = (isset($layers[$layername]['style']) ? $layers[$layername]['style'] : '');
    $format = $fmt=='png' ? 'image/png' : 'image/jpeg';
    $query = [
      'version'=> '1.3.0',
      'request'=> 'GetMap',
      'layers'=> $lyrName,
      'format'=> $format,
      'transparent'=> $fmt=='png' ? 'true' : 'false',
      'styles'=> $style,
      'crs'=> 'EPSG:3857',
      'bbox'=> implode(',',self::bbox($zoom, $x, $y)),
      'height'=> 256,
      'width'=> 256,
    ];
    return ['format'=> $format, 'image'=> $this->query($query)];
    
//    $this->sendImageOrError($query);
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
        'title'=> str_replace('"','\"', $layer['title']),
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

