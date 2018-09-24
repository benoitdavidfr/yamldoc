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
  La classe ViewDataset définit série de données (SD) de consultation à partir de serveurs WMS/WMTS.  
  Une telle SD est constituée de couches de consultation.
  
  Outre les champs de métadonnées, le document doit définir les champs suivants:

    - layersByGroups: liste de couches de la SD structurée par sous-liste, chaque couche identifiée est définie par:
      - title: son titre (obligatoire)
      - server: l'id du document définissant son serveur qui doit être un WmsServer ou un WmtsServer (obligatoire)
      - name: identifiant de la couche dans le serveur (obligatoire)
      - abstract: résumé expliquant le contenu de la couche
      - doc:
        - soit l'URL d'une doc complémentaire,
        - soit si la doc dépend du zoom un un array avec comme clé le niveau de zoom minimum et comme champs:
          - max: le zoom maximum correspondant à cette doc
          - title: le titre
          - www: l'URL de la doc
      - format: le format d'images de la couche, pour forcer un format quand il n'est pas imposé (WMS)
      - minZoom: zoom minimum pour lequel la couche est définie, pour forcer une valeur quand elle n'est pas définie
        ou qu'elle est incorrecte
      - maxZoom: zoom maximum pour lequel la couche est définie, pour forcer une valeur quand elle n'est pas définie
        ou qu'elle est incorrecte

  A faire:
    - Tester l'utilisation des styles
    - Génération de la carte
      
  Exemples:
  - view/igngp.yaml

journal:
  23/9/2018:
    - création
EOT;
}
//require_once __DIR__.'/yamldoc.inc.php';
//require_once __DIR__.'/search.inc.php';
//require_once __DIR__.'/../isometadata.inc.php';
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
      showDoc($docid, ['abstract'=> $this->abstract]);
      echo "<h2>Couches</h2>";
      foreach ($this->layersByGroup as $gid => $group) {
        echo "<h3>$gid</h3><ul>\n";
        foreach ($group as $lyrid => $layer) {
          echo "<li><a href='?doc=$docid&amp;ypath=/$lyrid'>$layer[title]</a></li>\n";
        }
        echo "</ul>\n";
      }
    }
    elseif (preg_match('!^/([^/]+)(/([^/]+))?(/([0-9]+)/([0-9]+)/([0-9]+))?$!', $ypath, $matches)) {
      $lyrName = $matches[1];
      $style = (isset($matches[2]) && $matches[2]) ? $matches[3] : '';
      $zxy = isset($matches[4]) ? [$matches[5], $matches[6], $matches[7]] : [];
      echo "style='$style'<br>\n";
      $layer = $this->layers[$lyrName];
      //print_r($layer);
      $layer->show($docid, $lyrName, $zxy);
      $zoom = $zxy ? $zxy[0] : 2;
      $col = $zxy ? max($zxy[1], 0) : 0;
      $cmin = $zxy ? max($zxy[1]-1, 0) : 0;
      $cmax = $zxy ? min($zxy[1]+2, 2**$zoom - 1) : 2**$zoom - 1;
      $row = $zxy ? $zxy[2] : 0;
      $rmin = $zxy ? max($zxy[2]-1, 0) : 0;
      $rmax = $zxy ? min($zxy[2]+2, 2**$zoom - 1): 2**$zoom - 1;
      if ($style)
        $lyrName = "$lyrName/$style";
      echo "<table style='border:1px solid black; border-collapse:collapse;'>\n";
      if ($zoom) {
        $href = sprintf("?doc=$docid&amp;ypath=/$lyrName/%d/%d/%d", $zoom-1, $col/2, $row/2);
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
    $params = !isset($_GET) ? $_POST : (!isset($_POST) ? $_GET : array_merge($_GET, $_POST));
    if (!$ypath || ($ypath=='/')) {
      return array_merge(['_id'=> $this->_id], $this->_c);
    }
    elseif ($ypath == '/api') {
      return self::api();
    }
    elseif ($ypath == '/layers') {
      $layers = [];
      foreach ($this->layers as $lyrid => $lyr) {
        $layer = $lyr->asArray();
        $layers[$lyrid] = [
          'title'=> $layer['title'],
          'abstract'=> $layer['abstract'],
          'doc'=> $layer['doc'],
        ];
      }
      return $layers;
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
    
  function styles(): array { return $this->server->layer($this->name)['styles']; }
  
  function show(string $vdsid, string $lyrName, array $zxy): void {
    $layer = $this->asArray();
    $layer['styles'] = [];
    foreach ($this->styles() as $styleName => $s) {
      $layer['styles'][$styleName] = $s;
      $href = "?doc=$vdsid&amp;ypath=/$lyrName/$styleName".($zxy ? '/'.implode('/',$zxy) : '');
      $layer['styles'][$styleName]['title'] = "<html>\n<a href='$href'>$s[title]</a>";
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


if (basename(__FILE__)<>basename($_SERVER['SCRIPT_NAME'])) return;

// classe simplifiant l'envoi de requêtes WFS
abstract class WfsServerxx {
    

  // extrait le fragment défini par $ypath, utilisé pour générer un retour à partir d'un URI
  function extractByUri(string $ypath) {
    $docuri = $this->_id;
    //echo "WfsServer::extractByUri($docuri, $ypath)<br>\n";
    $params = !isset($_GET) ? $_POST : (!isset($_POST) ? $_GET : array_merge($_GET, $_POST));
    if (!$ypath || ($ypath=='/')) {
      return array_merge(['_id'=> $this->_id], $this->_c);
    }
    elseif ($ypath == '/api') {
      return self::api();
    }
    elseif ($ypath == '/query') {
      //$params = isset($_GET) ? $_GET : (isset($_POST) ? $_POST : []);
      if (isset($params['OUTPUTFORMAT']) && ($params['OUTPUTFORMAT']=='application/json'))
        header('Content-type: application/json');
      else
        header('Content-type: application/xml');
      echo $this->query($params);
      die();
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
    elseif ($ypath == '/ft') {
      $cap = $this->getCapabilities();
      $cap = new SimpleXMLElement($cap);
      $typeNames = [];
      foreach ($cap->FeatureTypeList->FeatureType as $FeatureType) {
        $typeNames[(string)$FeatureType->Name] = [
          'title'=> (string)$FeatureType->Title,
          'abstract'=> (string)$FeatureType->Abstract,
        ];
      }
      return $typeNames;
    }
    // accès à la layer /ft/{typeName}
    // effectue la requête DescribeFeatureType et retourne le résultat
    elseif (preg_match('!^/ft/([^/]+)$!', $ypath, $matches)) {
      return $this->describeFeatureType($matches[1]);
    }
    elseif (preg_match('!^/ft/([^/]+)/geom(PropertyName)?$!', $ypath, $matches)) {
      return $this->geomPropertyName($matches[1]);
    }
    // accès à /t/{typeName}/numberMatched
    elseif (preg_match('!^/ft/([^/]+)/num(berMatched)?$!', $ypath, $matches)) {
      $typeName = $matches[1];
      $bbox = isset($params['bbox']) ? $params['bbox'] : '';
      $bbox = self::decodeBbox($bbox);
      $where = isset($params['where']) ? $params['where'] : '';
      return [ 'numberMatched'=> $this->getNumberMatched($typeName, $bbox, $where) ];
    }
    elseif (preg_match('!^/ft/([^/]+)/getFeature$!', $ypath, $matches)) {
      $typeName = $matches[1];
      header('Content-type: application/json');
      $bbox = isset($params['bbox']) ? $params['bbox'] : '';
      $bbox = self::decodeBbox($bbox);
      $zoom = isset($params['zoom']) ? $params['zoom'] : -1;
      $where = isset($params['where']) ? $params['where'] : '';
      //echo "where=$where\n";
      echo $this->getFeature($typeName, $bbox, $zoom, $where);
      die();
    }
    elseif (preg_match('!^/ft/([^/]+)/getAllFeatures$!', $ypath, $matches)) {
      $typeName = $matches[1];
      header('Content-type: application/json');
      $bbox = isset($params['bbox']) ? $params['bbox'] : '';
      $bbox = self::decodeBbox($bbox);
      $zoom = isset($params['zoom']) ? $params['zoom'] : -1;
      $where = isset($params['where']) ? $params['where'] : '';
      $this->printAllFeatures($typeName, $bbox, $zoom, $where);
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
            'appel'=> 'WfsServer::url',
            'params'=> $params,
          ]),
          FILE_APPEND
      );
    }
    $url = $this->wfsUrl;
    $url .= ((strpos($url, '?') === false) ? '?' : '&').'SERVICE=WFS';
    foreach($params as $key => $value)
      $url .= "&$key=$value";
    if (self::$log) { // log
      file_put_contents(self::$log, YamlDoc::syaml(['url'=> $url]), FILE_APPEND);
    }
    return $url;
  }
  
  // envoi une requête et récupère la réponse sous la forme d'un texte
  function query(array $params): string {
    $url = $this->url($params);
    $context = null;
    if ($this->wfsOptions && isset($this->wfsOptions['referer'])) {
      $referer = $this->wfsOptions['referer'];
      if (self::$log) { // log
        file_put_contents(
            self::$log,
            YamlDoc::syaml([
              'appel'=> 'WfsServer::query',
              'referer'=> $referer,
            ]),
            FILE_APPEND
        );
      }
      $context = stream_context_create(['http'=> ['header'=> "referer: $referer\r\n"]]);
    }
    if (($result = file_get_contents($url, false, $context)) === false) {
      var_dump($http_response_header);
      throw new Exception("Erreur dans WfsServer::query() : sur url=$url");
    }
    //die($result);
    if (substr($result, 0, 17) == '<ExceptionReport>') {
      if (!preg_match('!<ExceptionReport><[^>]*>([^<]*)!', $result, $matches))
        throw new Exception("Erreur dans WfsServer::query() : message d'erreur non détecté");
      throw new Exception ("Erreur dans WfsServer::query() : $matches[1]");
    }
    return $result;
  }
  
  // effectue un GetCapabities et retourne le XML. Utilise le cache sauf si force=true
  function getCapabilities(bool $force=false): string {
    //echo "wfsUrl=",$this->wfsUrl,"<br>\n";
    //print_r($this); die();
    $wfsVersion = ($this->wfsOptions && isset($this->wfsOptions['version'])) ? $this->wfsOptions['version'] : '';
    $filepath = self::$capCache.'/wfs'.md5($this->wfsUrl.$wfsVersion).'-cap.xml';
    if ((!$force) && file_exists($filepath))
      return file_get_contents($filepath);
    else {
      $query = ['request'=> 'GetCapabilities'];
      if ($wfsVersion)
        $query['VERSION'] = $wfsVersion;
      $cap = $this->query($query);
      if (!is_dir(self::$capCache))
        mkdir(self::$capCache);
      file_put_contents($filepath, $cap);
      return $cap;
    }
  }

  // liste les couches exposées evt filtré par l'URL des MD
  function featureTypeList(string $metadataUrl=null) {
    //echo "WfsServerJson::featureTypeList()<br>\n";
    $cap = $this->getCapabilities();
    $cap = str_replace(['xlink:href'], ['xlink_href'], $cap);
    //echo "<a href='/yamldoc/wfscapcache/",md5($this->wfsUrl),".xml'>capCache</a><br>\n";
    $featureTypeList = [];
    $cap = new SimpleXMLElement($cap);
    foreach ($cap->FeatureTypeList->FeatureType as $featureType) {
      $name = (string)$featureType->Name;
      $featureTypeRec = [
        'Title'=> (string)$featureType->Title,
        'MetadataURL'=> (string)$featureType->MetadataURL['xlink_href'],
      ];
      if (!$metadataUrl || ($featureTypeRec['MetadataURL'] == $metadataUrl))
        $featureTypeList[$name] = $featureTypeRec;
    }
    //echo '<pre>$featureTypeList = '; print_r($featureTypeList);
    return $featureTypeList;
  }
  
  abstract function describeFeatureType(string $typeName): array;
  
  abstract function geomPropertyName(string $typeName): ?string;
  
  abstract function getNumberMatched(string $typename, array $bbox=[], string $where=''): int;
  
  abstract function getFeature(string $typename, array $bbox=[], int $zoom=-1, string $where='', int $count=100, int $startindex=0): string;

  abstract function printAllFeatures(string $typename, array $bbox=[], int $zoom=-1, string $where=''): void;
};

// classe simplifiant l'envoi de requêtes WFS capable de fournir des données GeoJSON
class WfsServerJsonxx {
  
  function describeFeatureType(string $typeName): array {
    $filepath = self::$capCache.'/wfs'.md5($this->wfsUrl."/$typeName").'-ft.json';
    if (is_file($filepath)) {
      $featureType = file_get_contents($filepath);
    }
    else {
      $featureType = $this->query([
        'VERSION'=> '2.0.0',
        'REQUEST'=> 'DescribeFeatureType',
        'OUTPUTFORMAT'=> 'application/json',
        //'OUTPUTFORMAT'=> rawurlencode('text/xml; subtype=gml/3.2'),
        'TYPENAME'=> $typeName,
      ]);
      file_put_contents($filepath, $featureType);
    }
    $featureType = json_decode($featureType, true);
    return $featureType;
  }
  
  // nom de la propriété géométrique du featureType
  function geomPropertyName(string $typeName): ?string {
    $featureType = $this->describeFeatureType($typeName);
    //var_dump($featureType);
    foreach($featureType['featureTypes'] as $featureType) {
      foreach ($featureType['properties'] as $property) {
        if (preg_match('!^gml:!', $property['type']))
          return $property['name'];
      }
    }
    return null;
  }
  
  // retourne le nbre d'objets correspondant au résultat de la requête
  function getNumberMatched(string $typename, array $bbox=[], string $where=''): int {
    $geomPropertyName = $this->geomPropertyName($typename);
    $request = [
      'VERSION'=> '2.0.0',
      'REQUEST'=> 'GetFeature',
      'TYPENAMES'=> $typename,
      'SRSNAME'=> 'CRS:84', // système de coordonnées nécessaire pour du GeoJSON
      'RESULTTYPE'=> 'hits',
    ];
    $cql_filter = '';
    if ($bbox) {
      $bboxwkt = self::bboxWktLatLng($bbox);
      $cql_filter = "Intersects($geomPropertyName,$bboxwkt)";
    }
    if ($where) {
      $where = utf8_decode($where); // expérimentalement les requêtes doivent être encodées en ISO-8859-1
      $cql_filter .= ($cql_filter ? ' AND ':'').$where;
    }
    if ($cql_filter)
      $request['CQL_FILTER'] = urlencode($cql_filter);
    $result = $this->query($request);
    if (!preg_match('! numberMatched="(\d+)" !', $result, $matches)) {
      //echo "result=",$result,"\n";
      throw new Exception("Erreur dans WfsServerJson::getNumberMatched() : no match on result $result");
    }
    return (int)$matches[1];
  }
  
  // retourne le résultat de la requête en GeoJSON
  function getFeature(string $typename, array $bbox=[], int $zoom=-1, string $where='', int $count=100, int $startindex=0): string {
    $geomPropertyName = $this->geomPropertyName($typename);
    $request = [
      'VERSION'=> '2.0.0',
      'REQUEST'=> 'GetFeature',
      'TYPENAMES'=> $typename,
      'OUTPUTFORMAT'=> 'application/json',
      'SRSNAME'=> 'CRS:84', // système de coordonnées nécessaire pour du GeoJSON
      'COUNT'=> $count,
      'STARTINDEX'=> $startindex,
    ];
    $cql_filter = '';
    if ($bbox) {
      $bboxwkt = self::bboxWktLatLng($bbox);
      $cql_filter = "Intersects($geomPropertyName,$bboxwkt)";
    }
    if ($where) {
      $where = utf8_decode($where); // expérimentalement les requêtes doivent être encodées en ISO-8859-1
      $cql_filter .= ($cql_filter ? ' AND ':'').$where;
    }
    if ($cql_filter)
      $request['CQL_FILTER'] = urlencode($cql_filter);
    return $this->query($request);
  }
  
  // affiche le résultat de la requête en GeoJSON
  function printAllFeatures(string $typename, array $bbox=[], int $zoom=-1, string $where=''): void {
    $numberMatched = $this->getNumberMatched($typename, $bbox, $where);
    if ($numberMatched <= 100) {
      echo $this->getFeature($typename, $bbox, $zoom, $where);
      return;
    }
    //$numberMatched = 12; POUR TESTS
    echo '{"type":"FeatureCollection","numberMatched":'.$numberMatched.',"features":[',"\n";
    $startindex = 0;
    $count = 100;
    while ($startindex < $numberMatched) {
      $fc = $this->getFeature($typename, $bbox, $zoom, $where, $count, $startindex);
      // recherche de la position de la fin du dernier Feature dans la FeatureCollection
      $pos = strpos($fc, '],"crs":{"type":"EPSG","properties":{"code":');
      // affichage de la liste des features sans les []
      if ($startindex<>0)
        echo ",\n";
      echo substr($fc, 40, $pos-40);
      $startindex += $count;
      //die(']}');
    }
    echo "\n]}\n";
  }
};

// Essai d'une classe implémentant les requêtes pour un serveur WFS ne parlant pas JSON
class WfsServerGmlxx {
  private $xsltProcessors=[];
  
  function describeFeatureType(string $typeName): array {
    $filepath = self::$capCache.'/wfs'.md5($this->wfsUrl."/$typeName").'-ft.xml';
    if (is_file($filepath)) {
      $ftXml = file_get_contents($filepath);
    }
    else {
      if (!$this->wfsOptions || !isset($this->wfsOptions['version'])) {
        $ftXml = $this->query([
          'VERSION'=> '2.0.0',
          'REQUEST'=> 'DescribeFeatureType',
          'OUTPUTFORMAT'=> rawurlencode('text/xml; subtype=gml/3.2'),
          'TYPENAME'=> $typeName,
        ]);
      }
      else {
        $ftXml = $this->query([
          'VERSION'=> $this->wfsOptions['version'],
          'REQUEST'=> 'DescribeFeatureType',
          'TYPENAME'=> $typeName,
        ]);
      }
      file_put_contents($filepath, $ftXml);
    }
    $ft = new SimpleXMLElement($ftXml);
    $eltName = (string)$ft->element['name'];
    $eltType = (string)$ft->element['type'];
    list($prefix, $eltTypeSimple) = explode(':',$eltType);
    $featureTypes = [
      'targetNamespace'=> (string)$ft['targetNamespace'],
      'targetPrefix' => $prefix,
      //'eltName' => $eltName,
      //'eltType' => $eltType,
      //'eltTypeSimple' => $eltTypeSimple,
      'featureTypes'=> [],
    ];
    foreach ($ft->complexType as $ct) {
      $featureType = ['typeName'=> $eltName, 'properties'=> []];
      foreach ($ct->complexContent->extension->sequence->element as $elt) {
        $property = [
          'name'=> (string)$elt['name'],
          'localType'=> (string)$elt['type'],
        ];
        //$property[''] = (string)$elt['name'];
        $featureType['properties'][] = $property;
      }
      $featureTypes['featureTypes'][] = $featureType;
    }
    return $featureTypes;
  }
  
  // nom de la propriété géométrique du featureType
  function geomPropertyName(string $typeName): ?string {
    $featureType = $this->describeFeatureType($typeName);
    //var_dump($featureType);
    foreach($featureType['featureTypes'] as $featureType) {
      foreach ($featureType['properties'] as $property) {
        if (preg_match('!^gml:!', $property['localType']))
          return $property['name'];
      }
    }
    return null;
  }
  
  // retourne le nbre d'objets correspondant au résultat de la requête, si inconnu retourne -1
  function getNumberMatched(string $typename, array $bbox=[], string $where=''): int {
    if ($this->wfsOptions && isset($this->wfsOptions['version']) && ($this->wfsOptions['version']=='1.0.0'))
      return -1; 
    $version = ($this->wfsOptions && isset($this->wfsOptions['version'])) ? $this->wfsOptions['version'] : '2.0.0';
    $request = [
      'VERSION'=> $version,
      'REQUEST'=> 'GetFeature',
      'TYPENAMES'=> $typename,
      'SRSNAME'=> 'EPSG:4326',
      'RESULTTYPE'=> 'hits',
    ];
    if ($version <> '1.0.0') {
      $bbox = [$bbox[1], $bbox[0], $bbox[3], $bbox[2]]; // passage en LatLng
    }
    $request['BBOX'] = implode(',',$bbox);
    $result = $this->query($request);
    if (!preg_match('! numberMatched="(\d+)" !', $result, $matches)) {
      //echo "result=",$result,"\n";
      throw new Exception("Erreur dans WfsServerGml::getNumberMatched() : no match on result $result");
    }
    return (int)$matches[1];
  }
  
  // génère le code source de la feuille de style utilisée par wfs2GeoJson
  function xslForGeoJson(string $typename): string {
    $describeFeatureType = $this->describeFeatureType($typename);
    //echo '$describeFeatureType = '; print_r($describeFeatureType);
    $targetPrefix = $describeFeatureType['targetPrefix'];
    $targetNamespace = $describeFeatureType['targetNamespace'];
    $xsl_properties = '';
    foreach ($describeFeatureType['featureTypes'][0]['properties'] as $property) {
      if ($property['localType']=='string') {
        //echo '$property = '; print_r($property);
        $name = $property['name'];
        $xsl_properties .= "<xsl:if test=\"*/$targetPrefix:$name\">"
          ."<property name='$name'><xsl:value-of select=\"*/$targetPrefix:$name\"/></property>"
          ."</xsl:if>\n";
        //echo "xsl=$xsl_properties\n";
      }
    }
    if ($this->wfsOptions && isset($this->wfsOptions['version']) && ($this->wfsOptions['version']=='1.0.0'))
      $xslsrc = file_get_contents(__DIR__.'/wfsgml2simpl.xsl'); // GML 2
    else
      $xslsrc = file_get_contents(__DIR__.'/wfsgml3simpl.xsl'); // GML 3.2
    $targetNamespaceDef = "xmlns:$targetPrefix=\"$targetNamespace\"";
    $xslsrc = str_replace('{targetNamespaceDef}', $targetNamespaceDef, $xslsrc);
    $xslsrc = str_replace('{xslProperties}', $xsl_properties, $xslsrc);
    //die($xslsrc);
    return $xslsrc;
  }
  
  // effectue la transformation de simpleXml en GeoJSON
  function simple2GeoJson(string $simpleXml, string $format, array $bbox, int $zoom): void {
    //die($simpleXml);
    $res = 0;
    if ($zoom <> -1) {
      $res = 360.0 / (2 ** ($zoom+8)); // resolution en fonction du zoom
    }
    if (self::$log) { // log
      file_put_contents(
          self::$log,
          YamlDoc::syaml([
            'call'=> 'simple2GeoJson',
            'zoom'=> $zoom,
            'res'=> $res,
          ]),
          FILE_APPEND
      );
    }
    $pos = 0;
    $nofeature = 0;
    while ($pos != -1) {
      if (substrcmpp($simpleXml, $pos, "<?xml version=\"1.0\"?>\n")) {
        if ($format=='verbose')
          echo "ligne en-tête reconnue\n";
      }
      elseif (substrcmpp($simpleXml, $pos, '<FeatureCollection')) {
        $pos = strpos($simpleXml, '<', $pos); // je pointe sur le prochain '<' pour sauter les déclarations d'espaces
        if ($format=='verbose')
          echo "FeatureCollection reconnu\n";
      }
      elseif (substrcmpp($simpleXml, $pos, '</FeatureCollection>')) {
        if ($format=='verbose')
          echo "/FeatureCollection reconnu\n";
        return;
      }
      elseif (substrcmpp($simpleXml, $pos, '<Feature>')) {
        if ($format=='json')
          echo $nofeature?",\n":'',"{ \"type\":\"Feature\",\n";
        $this->decodeFeature($simpleXml, $pos, $format, $bbox, $res);
        if (!substrcmpp($simpleXml, $pos, '</Feature>'))
          throw new Exception("Erreur dans simple2GeoJson pos=$pos sur '".substr($simpleXml,$pos, 1000)."' line ".__LINE__);
        if ($format=='json')
          echo "}";
        $nofeature++;
      }
      else {
        throw new Exception("Erreur dans simple2GeoJson pos=$pos sur '".substr($simpleXml,$pos, 1000)."' line ".__LINE__);
      }
    }
    if ($format=='json')
      echo "\n";
  }
  
  // decode un Feature, modifie le pos pour pointer sur </Feature>
  function decodeFeature(string $simpleXml, int &$pos, string $format, array $bbox, float $res): void {
    if (!substrcmpp($simpleXml, $pos, '<properties>'))
      throw new Exception("Erreur dans decodeFeature pos=$pos sur ".substr($simpleXml,$pos, 1000)." line ".__LINE__);
    if ($format == 'json')
      echo "  \"properties\": {";
    $noprop = 0;
    while (substrcmpp($simpleXml, $pos, '<property name="')) {
      $possep = strpos($simpleXml, '"', $pos);
      $name = substr($simpleXml, $pos, $possep - $pos);
      $pos = $possep + 1;
      if (substrcmpp($simpleXml, $pos, '>')) {
        $posend = strpos($simpleXml, '<', $pos);
        if ($posend === false)
          throw new Exception("Erreur dans decodeFeature pos=$pos sur ".substr($simpleXml,$pos, 1000)." line ".__LINE__);
        $value = substr($simpleXml, $pos, $posend - $pos);
        // remplacement des caractères spéciaux XML
        $value = str_replace(['&lt;','&gt;','&quot;','&amp;'], ['<','>','"','&'], $value);
        // encodage des caractère spéciaux JSON
        $value = str_replace(['\\','"',"\n","\r","\t"], ['\\\\','\"','\n','\r','\t'], $value);
        $pos = $posend;
        if (!substrcmpp($simpleXml, $pos, '</property>'))
          throw new Exception("Erreur dans decodeFeature pos=$pos sur ".substr($simpleXml,$pos, 1000)." line ".__LINE__);
      }
      elseif (substrcmpp($simpleXml, $pos, '/>')) {
        $value = '';
      }
      else
        throw new Exception("Erreur dans decodeFeature pos=$pos sur ".substr($simpleXml,$pos, 1000)." line ".__LINE__);
      if ($format == 'verbose')
        echo "property: name=$name, value=$value\n";
      elseif ($format == 'json')
        echo $noprop?",\n":"\n","    \"$name\" : \"$value\"";
      $noprop++;
    }
    if (!substrcmpp($simpleXml, $pos, '</properties>'))
      throw new Exception("Erreur dans decodeFeature pos=$pos sur ".substr($simpleXml,$pos, 1000)." line ".__LINE__);
    if ($format == 'json')
      echo "\n  },\n";
    if (substrcmpp($simpleXml, $pos, '<MultiLineString>')) {
      if ($format == 'verbose')
        echo "MultiLineString détectée pos=$pos\n";
      $this->decodeMultiLineString($simpleXml, $pos, $format, $bbox, $res);
      if (!substrcmpp($simpleXml, $pos, '</MultiLineString>'))
        throw new Exception("Erreur dans decodeFeature pos=$pos sur ".substr($simpleXml,$pos, 1000)." line ".__LINE__);
      if ($format == 'json')
        echo "    ]\n",
          "  }\n";
    }
    elseif (substrcmpp($simpleXml, $pos, '<MultiPolygon>')) {
      if ($format == 'verbose')
        echo "MultiPolygon détecté pos=$pos\n";
      $this->decodeMultiPolygon($simpleXml, $pos, $format, $bbox, $res);
      if (!substrcmpp($simpleXml, $pos, '</MultiPolygon>'))
        throw new Exception("Erreur dans decodeFeature pos=$pos sur ".substr($simpleXml,$pos, 1000)." line ".__LINE__);
    }
    elseif (substrcmpp($simpleXml, $pos, '<Point>')) {
      if (isset($this->wfsOptions['version']) && ($this->wfsOptions['version']=='1.0.0'))
        $coordSep = ','; // GML 2
      else
        $coordSep = ' '; // GML 3.2
      if (isset($this->wfsOptions['coordOrderInGml']) && ($this->wfsOptions['coordOrderInGml']=='lngLat'))
        $coordOrderInGml = 'lngLat'; // GML 2
      else
        $coordOrderInGml = 'latLng'; // GML 3.2
      $poseoc = strpos($simpleXml, '<', $pos);
      $poswhite = strpos($simpleXml, $coordSep, $pos);
      $x = substr($simpleXml, $pos, $poswhite-$pos);
      $pos = $poswhite+1;
      //$poswhite = strpos($pseudo, ' ', $pos);
      if ($poseoc === false)
        $y = substr($simpleXml, $pos);
      else
        $y = substr($simpleXml, $pos, $poseoc-$pos);
      if ($format == 'verbose')
        echo "Point détecté pos=$pos, x=$x, y=$y\n";
      elseif ($format == 'json')
        echo "  \"geometry\" : {\n",
          "    \"type\" : \"Point\",\n",
          "    \"coordinates\" : ",$coordOrderInGml == 'lngLat' ? "[ $x, $y ]\n" : "[ $y, $x ]\n",
          "  }\n";
      $pos = ($poseoc === false) ? -1 : $poseoc;
      if (!substrcmpp($simpleXml, $pos, '</Point>'))
        throw new Exception("Erreur dans decodeFeature pos=$pos sur '".substr($simpleXml,$pos, 1000)."' line ".__LINE__);
    }
    // Il faudrait modifier les propriétés pour ajouter un message d'erreur
    elseif (substr($simpleXml, $pos, 10) == '</Feature>') {
      if ($format == 'verbose')
        echo "Erreur GML, aucune géométrie détectée pos=$pos\n";
      elseif ($format == 'json') // affichage de l'objet au centre de la fenêtre
        echo "  \"geometry\" : { \"type\": \"Point\", \"coordinates\": [",
          ($bbox[0]+$bbox[2])/2,',',($bbox[1]+$bbox[3])/2,"]}";
    }
    else
      throw new Exception("Erreur dans decodeFeature pos=$pos sur ".substr($simpleXml,$pos, 1000)." line ".__LINE__);
  }
  
  // decode un MultiLineString, modifie $pos pour pointer sur </MultiLineString>
  function decodeMultiLineString(string $simpleXml, int &$pos, string $format, array $bbox, float $res): void {
    $headerMLs = "  \"geometry\" : {\n"
      ."    \"type\" : \"MultiLineString\",\n"
      ."    \"coordinates\" : [\n";
    $nols = 0;
    while (($pos != -1) && substrcmpp($simpleXml, $pos, '<LineString>')) {
      if (substrcmpp($simpleXml, $pos, '<srsDimension>2</srsDimension>'))
        $pos += 0; // en GML 3.2 dimension
      if ($format == 'verbose')
        echo "LineString détectée pos=$pos\n";
      if (substrcmpp($simpleXml, $pos, '<posList>'))
        $pos += 0; // en GML 3.2 dimension
      $pts = $this->decodeListPoints2($simpleXml, $pos, $format, $bbox, $res);
      if (substrcmpp($simpleXml, $pos, '</posList>'))
        $pos += 0; // en GML 3.2 dimension
      if (!substrcmpp($simpleXml, $pos, '</LineString>'))
        throw new Exception("Erreur dans decodeFeature pos=$pos sur '".substr($simpleXml,$pos, 1000)."' line ".__LINE__);
      if ($format == 'json') {
        if (count($pts) > 1) {
          echo $nols ? ",\n" : $headerMLs,"      [";
          $this->encodeListPoints2($pts, $format);
          echo "]";
          $nols++;
        }
      }
    }
    if ($format == 'json') {
      if ($nols > 0) { // des lignes ont été affichées
        echo "\n";
      }
      else { // aucune ligne n'a été affichée, affichage d'un Point
        echo "  \"geometry\" : {\n    \"type\" : \"Point\",\n    \"coordinates\" : [ $pts[0][0], $pts[0][1]]\n  }\n";
      }
    }
  }
  
  // decode un MultiPolygon, modifie $pos pour pointer sur </MultiPolygone>
  function decodeMultiPolygon(string $simpleXml, int &$pos, string $format, array $bbox, float $res): void {
    $headerMPol = "  \"geometry\" : {\n    \"type\" : \"MultiPolygon\",\n    \"coordinates\" : [\n";
    $nopol = 0;
    while (($pos != -1) && substrcmpp($simpleXml, $pos, '<Polygon>')) {
      if ($format == 'verbose')
        echo "Polygon détecté pos=$pos\n";
      $header = ($nopol?",\n":$headerMPol)."     [\n";
      $footer = "     ]";
      if (!($center = $this->decodePolygon2($simpleXml, $pos, $format, $bbox, $res, $header, $footer)))
        $nopol++;
      if (!substrcmpp($simpleXml, $pos, '</Polygon>'))
        throw new Exception("Erreur dans decodeMultiPolygon pos=$pos sur ".substr($simpleXml,$pos, 1000)." line ".__LINE__);
    }
    if ($format == 'json') {
      if ($nopol <> 0) // au moins un polygone a été affiché
        echo "    ]\n  }\n";
      else // aucun polygone n'a été affiché
        echo "  \"geometry\" : {\n    \"type\" : \"Point\",\n    \"coordinates\" : [ $center[0], $center[1]]\n  }\n";
    }
    //if ($format == 'json')
        //echo "\n";
  }
  
  // decode un polygon2, modifie $pos pour pointer sur '</Polygon>'
  // retourne [] si le polygone intersecte la bbox et est suffisament grand,
  // sinon le centre du rectangle englobant l'extérieur du polygone
  // $header et $footer sont affichés avant et après si le polygone intersecte la bbox et si format est json
  function decodePolygon2(string $simpleXml, int &$pos, string $format, array $bbox, float $res, string $header, string $footer): array {
    if (substrcmpp($simpleXml, $pos, '<srsDimension>2</srsDimension>'))
      $pos += 0; // en GML 3.2 dimension
    if (!substrcmpp($simpleXml, $pos, '<outerBoundaryIs>'))
      throw new Exception("Erreur dans decodePolygon2 outerBoundaryIs non détecté pos=$pos sur ".substr($simpleXml,$pos, 1000).", ligne ".__LINE__);
    if ($format == 'verbose')
      echo "Polygon2 outerBoundaryIs détecté\n";
    $extpts = $this->decodeListPoints2($simpleXml, $pos, $format, $bbox, $res);
    if (!substrcmpp($simpleXml, $pos, '</outerBoundaryIs>'))
      throw new Exception("Erreur dans decodePolygon2 pos=$pos sur ".substr($simpleXml,$pos, 1000)." line ".__LINE__);
    if (count($extpts) == 1) { // Si la liste ne contient qu'un point
      $polygonIntersects = false;
      if ($format == 'verbose')
        echo "Polygon2 exterior hors bbox ou trop petit\n";
    }
    else {
      $polygonIntersects = true;
      if ($format == 'verbose')
        echo "Polygon2 exterior intersecte bbox\n";
      elseif ($format == 'json') {
        echo $header,"      [";
        $this->encodeListPoints2($extpts, $format);
      }
    }
    while (substrcmpp($simpleXml, $pos, '<innerBoundaryIs>')) {
      if ($format == 'verbose')
        echo "Polygon2interior détecté\n";
      $intpts = $this->decodeListPoints2($simpleXml, $pos, $format, $bbox, $res);
      if (!substrcmpp($simpleXml, $pos, '</innerBoundaryIs>'))
        throw new Exception("Erreur dans decodePolygon2 pos=$pos sur ".substr($simpleXml,$pos, 1000)." line ".__LINE__);
      if (($format == 'json') && $polygonIntersects && (count($intpts) > 1)) {
        echo "],\n      [";
        $this->encodeListPoints2($intpts, $format);
      }
    }
    if (($format == 'json') && $polygonIntersects)
      echo "]\n",$footer;
    return $polygonIntersects ? [] : $extpts[0];
  }
    
  // decode une liste de points de 2 coord dans simpleXml à partir de pos en GML 2 ou 3.2
  // modifie $pos pour qu'il pointe sur le '<' suivant
  // renvoie soit:
  // - si au moins un point est dans la $qbox et si la taille de la bbox est > resolution : la liste de points
  // - sinon le centre du rectangle englobant
  // Dans le premier cas la liste de points :
  // - est filtrée en fonction de la résolution $res si $res <> 0
  // - contient toujours au moins le premier et le dernier des points initiaux
  function decodeListPoints2(string $simpleXml, int &$pos, string $format, array $qbbox, float $res): array {
    if ($this->wfsOptions && isset($this->wfsOptions['version']) && ($this->wfsOptions['version']=='1.0.0'))
      $sepcoord = ','; // en GML 2 le séparateur entre les 2 coordonnées est ','
    else
      $sepcoord = ' '; // en GML 3.2 le séparateur entre les 2 coordonnées est ' '
    $nbpts = 0; // le nbre de points retenus
    $pts = []; // la liste des points retenus
    $ptprec = []; // le dernier point retenu dans $pts
    $ptLost = []; // mémorise le dernier point traité s'il n'a pas été retenu, sinon []
    $bbox = []; // le bbox de la liste de points
    $poseoc = strpos($simpleXml, '<', $pos);
    while (1) {
      $possep = strpos($simpleXml, $sepcoord, $pos);
      if (($possep === false) || (($poseoc !== FALSE) && ($possep > $poseoc))) {
        if ($ptLost) // je force à retenir le dernier point s'il ne l'avait pas été
          $pts[] = $ptLost;
        break;
      }
      $x = substr($simpleXml, $pos, $possep-$pos);
      //echo "x=$x\n";
      $pos = $possep + 1;
      $poswhite = strpos($simpleXml, ' ', $pos);
      //  echo "poswhite=$poswhite, posret=$posret\n";
      if (($poswhite === false) || (($poseoc !== FALSE) && ($poswhite > $poseoc))) {
        throw new Exception("Erreur sur ".substr($simpleXml,$pos,1000).", ligne ".__LINE__);
      }
      $y = substr($simpleXml, $pos, $poswhite-$pos);
      $pos = $poswhite + 1;
      if ($format=='verbose')
        echo "  pos=$pos, nopt=$nbpts, x=$x, y=$y\n";
      if (!$bbox)
        $bbox = [$x, $y, $x, $y];
      else { // maj bbox 
        if ($x < $bbox[0])
          $bbox[0] = $x;
        if ($y < $bbox[1])
          $bbox[1] = $y;
        if ($x > $bbox[2])
          $bbox[2] = $x;
        if ($y > $bbox[3])
          $bbox[3] = $y;
      }
      // Le point courant n'est conservé que si sa distance au point précédent est supérieur à la résolution
      if ($ptprec && ($res <> 0.0)) {
        $dist = max(abs($x-$ptprec[0]),abs($y-$ptprec[1]));
      }
      if (!$ptprec || ($res == 0.0) || ($dist > $res)) { // le point courant est conservé dans $pts
        $ptprec = [$x,$y];
        $pts[] = $ptprec;
        $nbpts++;
        $ptLost = [];
      }
      else // le point courant n'est pas conservé dans $pts, il est mémorisé dans $ptLost
        $ptLost = [$x,$y];
    }
    $pos = ($poseoc === FALSE) ? -1 : $poseoc;
    $xmin = max($qbbox[0], $bbox[0]);
    $ymin = max($qbbox[1], $bbox[1]);
    $xmax = min($qbbox[2], $bbox[2]);
    $ymax = min($qbbox[3], $bbox[3]);
    $inters = (($xmax >= $xmin) && ($ymax >= $ymin)); // teste l'intersection entre qbbox et bbox
    // si pas intersection ou taille de l'élément < resolution retourne le centre de la bbox
    if (!$inters || (max($bbox[2] - $bbox[0], $bbox[3] - $bbox[1]) < $res))
      return [[($bbox[0] + $bbox[2])/2, ($bbox[1] + $bbox[3])/2]]; // retourne une liste contenant le centre de la bbox
    else
      return $pts;
  }

  // affiche la liste de points
  function encodeListPoints2(array $pts, string $format): void {
    if (isset($this->wfsOptions['coordOrderInGml']) && ($this->wfsOptions['coordOrderInGml']=='lngLat'))
      $coordOrderInGml = 'lngLat'; // GML 2
    else
      $coordOrderInGml = 'latLng'; // GML 3.2
    if ($format=='verbose')
      echo "$nbpts points détectés\n";
    elseif ($format=='json') {
      $nbpts = count($pts);
      for($i=0; $i<$nbpts; $i++) {
        if ($coordOrderInGml == 'lngLat')
          echo $i?',':'','[',$pts[$i][0],',',$pts[$i][1],']'; // génération en LngLat (CRS:84)
        else
          echo $i?',':'','[',$pts[$i][1],',',$pts[$i][0],']'; // génération en LngLat (CRS:84)
        //echo $i?',':'','"pt"';
      }
    }
  }
  
  // effectue la transformation du Gml en un pseudo GeoJSON puis affiche les Feature en JSON
  // l'affichage doit être encadré par '{"type":"FeatureCollection","features":' et ']}'
  // Cela permet d'afficher une seule FeatureCollection en cas de pagination
  // le bbox est en LatLng pour GML 3.2 et en LngLat pour GML 2
  function wfs2GeoJson(string $typename, string $xmlstr, string $format, array $bbox, int $zoom): void {
    if ($format == 'gml')
      die($xmlstr); // pour afficher le GML
    if (!isset($this->xsltProcessors['typename'])) {
      $xslsrc = $this->xslForGeoJson($typename);
      $stylesheet = new DOMDocument();
      $stylesheet->loadXML($xslsrc);
      $this->xsltProcessors['typename'] = new XSLTProcessor;
      $this->xsltProcessors['typename']->importStylesheet($stylesheet);
    }
    $getrecords = new DOMDocument();
    if (!@$getrecords->loadXML($xmlstr)) {
      // En cas d'erreur, essai de modif de l'encodage, cas effectif sur Géo-IDE 
      // http://localhost/yamldoc/id.php/geocats/geoide/db/items/fr-120066022-jdd-468ef944-fb92-4351-a8a6-2fca649261f8
      // /wfs/L_SERVITUDE_AC1_MH_S_060?bbox=1.6,48.4,4.2,49.5&zoom=9
      $xmlstr2 = str_replace(
        '<?xml version=\'1.0\' encoding="UTF-8" ?>',
        '<?xml version=\'1.0\' encoding="ISO-8859-1" ?>',
        $xmlstr);
      if (!@$getrecords->loadXML($xmlstr2)) {
        echo "xml=",$xmlstr2,"\n";
        throw new Exception("Erreur dans WfsServerGml::wfs2GeoJson() sur loadXML()");
      }
    }
    $simpleXml = $this->xsltProcessors['typename']->transformToXML($getrecords);
    if ($format == 'simpleXml')
      die($simpleXml); // pour afficher le simpleXml intermédiaire
    $this->simple2GeoJson($simpleXml, $format, $bbox, $zoom);
  }
  
  // Test unitaire de la méthode WfsServerGml::wfs2GeoJson()
  function wfs2GeoJsonTest() {
    if (1) {
      $queries = [
        [
          'title'=> "sextant/WFS 2.0.0 ESPACES_TERRESTRES_P MultiSurface GML 3.2.1 EPSG:4326",
          'wfsUrl'=> 'http://www.ifremer.fr/services/wfs/dcsmm',
          'params'=> [
            'VERSION'=> '2.0.0', 'REQUEST'=> 'GetFeature', 'TYPENAMES'=> 'ms:ESPACES_TERRESTRES_P', 'RESULTTYPE'=> 'results',
            'SRSNAME'=> 'EPSG:4326', 'BBOX'=> '41,-10,51,16',
            'OUTPUTFORMAT'=> rawurlencode('text/xml; subtype=gml/3.2.1'), 'COUNT'=> '2',
          ],
          'zoom'=> 9,
        ],
        [ 'title'=> "sextant/WFS 2.0.0 DCSMM_SRM_TERRITORIALE_201806_L MultiCurve 41,-10,51,16 zoom=-1",
          'wfsUrl'=> 'http://www.ifremer.fr/services/wfs/dcsmm',
          'params'=> [
            'VERSION'=> '2.0.0', 'REQUEST'=> 'GetFeature', 'TYPENAMES'=> 'ms:DCSMM_SRM_TERRITORIALE_201806_L',
            'RESULTTYPE'=> 'results', 'SRSNAME'=> 'EPSG:4326', 'BBOX'=> '41,-10,51,16',
            'OUTPUTFORMAT'=> rawurlencode('text/xml; subtype=gml/3.2.1'), 'COUNT'=> '2',
          ],
          'zoom'=> -1,
        ],
        [ 'title'=> "sextant/WFS 2.0.0 DCSMM_SRM_TERRITORIALE_201806_L MultiCurve 41,-10,51,16 zoom=1",
          'wfsUrl'=> 'http://www.ifremer.fr/services/wfs/dcsmm',
          'params'=> [
            'VERSION'=> '2.0.0', 'REQUEST'=> 'GetFeature', 'TYPENAMES'=> 'ms:DCSMM_SRM_TERRITORIALE_201806_L',
            'RESULTTYPE'=> 'results', 'SRSNAME'=> 'EPSG:4326', 'BBOX'=> '41,-10,51,16',
            'OUTPUTFORMAT'=> rawurlencode('text/xml; subtype=gml/3.2.1'), 'COUNT'=> '2',
          ],
          'zoom'=> 1,
        ],
        [ 'title'=> "sextant/WFS 2.0.0 DCSMM_SRM_TERRITORIALE_201806_P MultiPolygone bbox=-7,47,-2,49",
          'wfsUrl'=> 'http://www.ifremer.fr/services/wfs/dcsmm',
          'params'=> [
            'VERSION'=> '2.0.0', 'REQUEST'=> 'GetFeature', 'TYPENAMES'=> 'ms:DCSMM_SRM_TERRITORIALE_201806_P',
            'RESULTTYPE'=> 'results', 'SRSNAME'=> 'EPSG:4326', 'BBOX'=> '47,-7,49,-2',
            'OUTPUTFORMAT'=> rawurlencode('text/xml; subtype=gml/3.2.1'), 'COUNT'=> '2',
          ],
          'zoom'=> 9,
        ],
        // GeoIde
        [ 'title'=> "GeoIde, WFS 1.0.0, GML 2, N_VULNERABLE_ZSUP_041 Polygones",
          'wfsUrl'=> 'http://ogc.geo-ide.developpement-durable.gouv.fr/wxs?'
            .'map=/opt/data/carto/geoide-catalogue/1.4/org_38024/f19f7c24-c605-43f5-b4a0-74676524d00a.internet.map',
          'wfsOptions' => ['version'=> '1.0.0', 'coordOrderInGml'=> 'lngLat' ],
          'params'=> [
            'VERSION'=> '1.0.0', 'REQUEST'=> 'GetFeature', 'TYPENAME'=> 'N_VULNERABLE_ZSUP_041',
            'SRSNAME'=> 'EPSG:4326', 'BBOX'=> '-8.0,42.4,14.0,51.1',
          ],
          'zoom'=> 18,
        ],
        [ 'title'=> "GeoIde, WFS 1.0.0, GML 2, L_MUSEE_CHATEAU_041 Point",
          'wfsUrl'=> 'http://ogc.geo-ide.developpement-durable.gouv.fr/wxs?'
            .'map=/opt/data/carto/geoide-catalogue/1.4/org_38024/f31dbfdd-1038-451b-a539-668ac27b6526.internet.map',
          'wfsOptions'=> ['version'=> '1.0.0', 'coordOrderInGml'=> 'lngLat'],
          'params'=> [
            'VERSION'=> '1.0.0', 'REQUEST'=> 'GetFeature', 'TYPENAME'=> 'L_MUSEE_CHATEAU_041',
            'SRSNAME'=> 'EPSG:4326', 'BBOX'=> '-7.294921875,42.09822241119,13.3154296875,51.495064730144',
          ],
          'zoom'=> 6,
        ],
        [ 'title'=> "geoide en WFS 1.0.0 L_SERVITUDE_AC1_MH_S_060 caractères incorrects ",
          'wfsUrl'=> 'http://ogc.geo-ide.developpement-durable.gouv.fr/wxs?'
            .'map=/opt/data/carto/geoide-catalogue/1.4/org_38062/83c16694-3470-46e5-b0ad-3f374e1337f3.internet.map',
          'wfsOptions'=> ['version'=> '1.0.0','coordOrderInGml'=> 'lngLat'],
          'params'=> [
            'VERSION'=> '1.0.0', 'REQUEST'=> 'GetFeature', 'TYPENAME'=> 'L_SERVITUDE_AC1_MH_S_060',
            'SRSNAME'=> 'EPSG:4326', 'BBOX'=> '1.6,48.4,4.2,49.5',
          ],
          'zoom'=> 9,
        ],
        [ 'title'=> "geoide en WFS 1.0.0 N_ZONE_ALEA_PPRN_19960002_S_048 Polygon avec trou + eol dans propriété",
          'wfsUrl'=> 'http://ogc.geo-ide.developpement-durable.gouv.fr/wxs?'
            .'map=/opt/data/carto/geoide-catalogue/1.4/org_38038/'
            .'fr-120066022-orphan-dc361a37-5280-4804-993d-81daf41ed017.intranet.map',
          'wfsOptions'=> ['version'=> '1.0.0','coordOrderInGml'=> 'lngLat'],
          'params'=> [
            'VERSION'=> '1.0.0', 'REQUEST'=> 'GetFeature', 'TYPENAME'=> 'N_ZONE_ALEA_PPRN_19960002_S_048',
            'SRSNAME'=> 'EPSG:4326', 'BBOX'=> '-9.7,42.3,15.7,51.2',
          ],
          'zoom'=> 6,
        ],
        [ 'title'=> "geoide en WFS 1.0.0 PRESCRIPTION_SURF_054 MultiPolygon avec \" dans propriété",
          'wfsUrl'=> 'http://ogc.geo-ide.developpement-durable.gouv.fr/wxs?'
            .'map=/opt/data/carto/geoide-catalogue/1.4/org_38050/2195b276-aee8-47bf-aada-3926dbbc1661.internet.map',
          'wfsOptions'=> ['version'=> '1.0.0','coordOrderInGml'=> 'lngLat'],
          'params'=> [
            'VERSION'=> '1.0.0', 'REQUEST'=> 'GetFeature', 'TYPENAME'=> 'PRESCRIPTION_SURF_054',
            'SRSNAME'=> 'EPSG:4326', 'BBOX'=> '-9.7,42.3,15.7,51.2',
          ],
          'zoom'=> 6,
        ],
        [ 'title'=> "geoide en WFS 1.0.0 N_AC1_GENERATEUR_SUP_L_062 MultiLineString absentes",
          'wfsUrl'=> 'http://ogc.geo-ide.developpement-durable.gouv.fr/wxs?'
            .'map=/opt/data/carto/geoide-catalogue/1.4/org_38066/22c66d57-2e65-40b6-b716-09b911d3725a.internet.map',
          'wfsOptions'=> ['version'=> '1.0.0','coordOrderInGml'=> 'lngLat'],
          'params'=> [
            'VERSION'=> '1.0.0', 'REQUEST'=> 'GetFeature', 'TYPENAME'=> 'N_AC1_GENERATEUR_SUP_L_062',
            'SRSNAME'=> 'EPSG:4326', 'BBOX'=> '-9.7,42.3,15.7,51.2',
          ],
          'zoom'=> 6,
        ],
      ];
    }

    if (!isset($_GET['action'])) {
      echo "<h3>wfs2GeoJson Queries</h3><ul>\n";
      foreach ($queries as $num => $query) {
        $this->_c['wfsUrl'] = $query['wfsUrl'];
        if (isset($query['wfsOptions']))
          $this->_c['wfsOptions'] = $query['wfsOptions'];
        $url = $this->url($query['params']);
        echo "<li>$query[title] ",
          "(<a href='$url'>url</a>, ", // appel de l'URL du WFS
          "<a href='?action=wfs&query=$num'>wfs</a>, ", // appel du WFS et stockage
          "<a href='?action=xml&query=$num'>xml</a>, ", // si en cache affiche
          "<a href='?action=xsl&query=$num'>xsl</a>, ", // affiche la feuille de style
          "<a href='?action=geojson&query=$num&format=simpleXml'>simpleXml</a>, ",
          "<a href='?action=geojson&query=$num&format=verbose'>GeoJSON verbose</a>, ",
          "<a href='?action=geojson&query=$num&format=json'>GeoJSON json</a>)\n"; // transforme en GeoJSON
      }
      echo "</ul>\n";
      //echo "<a href='?action=ex0.txt'>Appel de pseudo2GeoJson() sur le fichier ex0.txt</a><br>\n";
      die();
    }
    
    /*if ($_GET['action']=='ex0.txt') {
      //header('Content-type: application/json');
      header('Content-type: text/plain');
      echo "{\"type\":\"FeatureCollection\",\"features\":[\n";
      $this->pseudo2GeoJson(file_get_contents(__DIR__.$_SERVER['PATH_INFO']."/ex0.txt"), 'json');
      echo "]}\n";
      die();
    }*/

    $query = $queries[$_GET['query']];
    $this->_c['wfsUrl'] = $query['wfsUrl'];
    if (isset($query['wfsOptions']))
      $this->_c['wfsOptions'] = $query['wfsOptions'];
    // le nom du du fichier de cache du résultat de la requête est construit avec le MD5 de la requete
    $md5 = md5($this->url($query['params']));
    $filepath = __DIR__.$_SERVER['PATH_INFO']."/$md5.xml";

    if ($_GET['action']=='xsl') {
      header('Content-type: text/xml');
      $typename = isset($query['params']['TYPENAMES']) ? $query['params']['TYPENAMES'] : $query['params']['TYPENAME'];
      die($this->xslForGeoJson($typename));
    }

    if ($_GET['action']=='wfs') {
      $getrecords = $this->query($query['params']);
      file_put_contents($filepath, $getrecords);
      header('Content-type: text/xml');
      die($getrecords);
    }
    
    if (is_file($filepath))
      $getrecords = file_get_contents($filepath);
    else {
      $getrecords = $this->query($query['params']);
      file_put_contents($filepath, $getrecords);
    }
    if ($_GET['action']=='xml') {
      header('Content-type: text/xml');
      die($getrecords);
    }
    if ($_GET['action']=='geojson') {
      if ($_GET['format']=='json') {
        header('Content-type: application/json');
        echo "{\"type\":\"FeatureCollection\",\"features\":[\n";
      }
      elseif ($_GET['format']=='simpleXml')
        header('Content-type: text/xml');
      else
        header('Content-type: text/plain');
      $typename = isset($query['params']['TYPENAMES']) ? $query['params']['TYPENAMES'] : $query['params']['TYPENAME'];
      $this->wfs2GeoJson(
        $typename,
        $getrecords,
        $_GET['format'],
        explode(',', $query['params']['BBOX']),
        $query['zoom']
      );
      echo "]}\n";
      die();
    }
    echo "action $_GET[action] inconnue\n";
  }
  
  // n'affiche pas le header/tailer GeoJSON
  function getFeatureWoHd(string $typename, array $bbox, int $zoom, string $where, int $count, int $startindex): void {
    if ($this->wfsOptions && isset($this->wfsOptions['version']) && ($this->wfsOptions['version']=='1.0.0')) {
      $request = [
        'VERSION'=> '1.0.0',
        'REQUEST'=> 'GetFeature',
        'TYPENAME'=> $typename,
        'SRSNAME'=> 'EPSG:4326',
        'BBOX'=> implode(',',$bbox),
      ];
    }
    else {
      $request = [
        'VERSION'=> '2.0.0',
        'REQUEST'=> 'GetFeature',
        'TYPENAMES'=> $typename,
        'OUTPUTFORMAT'=> rawurlencode('application/gml+xml; version=3.2'),
        'SRSNAME'=> 'EPSG:4326',
        'COUNT'=> $count,
        'STARTINDEX'=> $startindex,
      ];
      $bbox = [$bbox[1], $bbox[0], $bbox[3], $bbox[2]]; // passage en EPSG:4326
      $request['BBOX'] = implode(',',$bbox);
    }
    //$format = 'gml'; // pour afficher le GML
    //$format = 'pseudo';  // pour afficher le pseudo intermédiaire
    //$format = 'verbose'; // affichage des commentaires de la transfo pseudo en GeoJSON
    $format = 'json'; // affichage GeoJSON
    $this->wfs2GeoJson($typename, $this->query($request), $format, $bbox, $zoom);
  }

  // affiche le résultat de la requête en GeoJSON
  function getFeature(string $typename, array $bbox=[], int $zoom=-1, string $where='', int $count=100, int $startindex=0): string {
    //die($this->query($request)); // affichage du GML
    echo "{ \"type\":\"FeatureCollection\",\n",
      "  \"typename\":\"$typename\",\n",
      "  \"bbox\":",json_encode($bbox),",\n",
      "  \"zoom\":\"$zoom\",\n",
      "  \"where\":\"$where\",\n",
      "  \"count\":\"$count\",\n",
      "  \"startindex\":\"$startindex\",\n",
      "  \"features\":[\n";
    $this->getFeatureWoHd($typename, $bbox, $zoom, $where, $count, $startindex);
    echo "]}\n";
    return '';
  }
  
  function getFeatureTest() {
    header('Content-type: application/json');
    //header('Content-type: application/xml');
    //header('Content-type: text/plain');
    if (0) { // Sextant GML 3.2 
      $this->_c['wfsUrl'] = 'http://www.ifremer.fr/services/wfs/dcsmm';
      $this->getFeature('ms:DCSMM_SRM_TERRITORIALE_201806_L', [-10,41,16,51], 8);
    }
    elseif (0) { // Géo-IDE GML 2 N_VULNERABLE_ZSUP_041 polygones 
      $this->_c['wfsUrl'] = 'http://ogc.geo-ide.developpement-durable.gouv.fr/wxs?'
        .'map=/opt/data/carto/geoide-catalogue/1.4/org_38024/f19f7c24-c605-43f5-b4a0-74676524d00a.internet.map';
      $this->_c['wfsOptions'] = [
        'version'=> '1.0.0',
        'coordOrderInGml'=> 'lngLat',
      ];
      $this->getFeature('N_VULNERABLE_ZSUP_041', [-8.0,42.4,14.0,51.1], 8);
    }
    elseif (1) { // Géo-IDE GML 2 L_MUSEE_CHATEAU_041 point 
      $this->_c['wfsUrl'] = 'http://ogc.geo-ide.developpement-durable.gouv.fr/wxs?'
        .'map=/opt/data/carto/geoide-catalogue/1.4/org_38024/f31dbfdd-1038-451b-a539-668ac27b6526.internet.map';
      $this->_c['wfsOptions'] = [
        'version'=> '1.0.0',
        'coordOrderInGml'=> 'lngLat',
      ];
      $this->getFeature('L_MUSEE_CHATEAU_041', [-7.294921875,42.09822241119,13.3154296875,51.495064730144], 6);
    }
  }
  
  // affiche le résultat de la requête en GeoJSON
  function printAllFeatures(string $typename, array $bbox=[], int $zoom=-1, string $where=''): void {
    $numberMatched = $this->getNumberMatched($typename, $bbox, $where);
    if ($numberMatched <= 100) {
      $this->getFeature($typename, $bbox, $zoom, $where);
      return;
    }
    //$numberMatched = 12; POUR TESTS
    echo '{"type":"FeatureCollection","numberMatched":'.$numberMatched.',"features":[',"\n";
    $startindex = 0;
    $count = 100;
    while ($startindex < $numberMatched) {
      $this->getFeatureWoHd($typename, $bbox, $zoom, $where, $count, $startindex);
      if ($startindex<>0)
        echo ",\n";
      $startindex += $count;
    }
    echo "\n]}\n";
  }
};

if (basename(__FILE__)<>basename($_SERVER['SCRIPT_NAME'])) return;

ini_set('max_execution_time', 300);
ini_set('memory_limit', '512M');

if (!isset($_SERVER['PATH_INFO'])) {
  echo "<h3>Tests unitaires</h3><ul>\n";
  echo "<li><a href='$_SERVER[SCRIPT_NAME]/wfs2GeoJsonTest'>Test de la méthode WfsServerGml::wfs2GeoJson()</a>\n";
  echo "<li><a href='$_SERVER[SCRIPT_NAME]/getFeatureTest'>Test de la méthode WfsServerGml::getFeature()</a>\n";
  echo "</ul>\n";
  die();
}

$testMethod = substr($_SERVER['PATH_INFO'], 1);
$wfsDoc = ['wfsUrl'=>'test'];
$wfsServer = new WfsServerGml($wfsDoc, 'test');
$wfsServer->$testMethod();
