<?php
/*PhpDoc:
name: geocat.inc.php
title: geocat.inc.php - document définissant un géocatalogue
functions:
doc: <a href='/yamldoc/?action=version&name=geocat.inc.php'>doc intégrée en Php</a>
*/
{ // doc 
$phpDocs['geocat.inc.php']['file'] = <<<'EOT'
name: geocat.inc.php
title: geocat.inc.php - document définissant un géocatalogue
doc: |
  
journal:
  24-25/8/2018:
    - création
EOT;
}
require_once __DIR__.'/yamldoc.inc.php';
require_once __DIR__.'/isometadata.inc.php';

class Geocat extends CswServer {
  static $log = __DIR__.'/geocat.log.yaml'; // nom du fichier de log ou '' pour pas de log
  protected $_c; // contient les champs
  
  // crée un nouveau doc, $yaml est le contenu Yaml externe issu de l'analyseur Yaml
  // $yaml est généralement un array mais peut aussi être du texte
  function __construct(&$yaml) {
    $this->_c = $yaml;
    //echo "<pre>"; print_r($this);
    if (self::$log) {
      if (php_sapi_name() <> 'cli') {
        $uri = substr($_SERVER['REQUEST_URI'], strlen($_SERVER['SCRIPT_NAME']));
        if (isset($_SERVER['QUERY_STRING']) && $_SERVER['QUERY_STRING'])
          $uri = substr($uri, 0, strlen($uri)-strlen($_SERVER['QUERY_STRING'])-1);
        $log = [ 'uri'=> $uri ];
        $log['_SERVER'] = $_SERVER;
      }
      else {
        $log = [ 'argv'=> $_SERVER['argv'] ];
      }
      $log['date'] = date(DateTime::ATOM);
      if (isset($_GET) && $_GET)
        $log['_GET'] = $_GET;
      if (isset($_POST) && $_POST)
        $log['_POST'] = $_POST;
      file_put_contents(self::$log, YamlDoc::syaml($log));
    }
  }
  
  // lit les champs
  function __get(string $name) { return isset($this->_c[$name]) ? $this->_c[$name] : null; }

  // affiche le sous-élément de l'élément défini par $ypath
  function show(string $docid, string $ypath): void {
    echo "Geocat::show($docid, $ypath)<br>\n";
    if (!$ypath || ($ypath == '/')) {
      showDoc($docid, $this->_c);
      return;
    }
    elseif (preg_match('!^/layers/([^/]+)$!', $ypath, $matches)) {
      $this->showLayer($docid, $matches[1]);
      return;
    }
    else {
      showDoc($docid, $this->extract($ypath));
      return;
    }
  }
  
  // décapsule l'objet et retourne son contenu sous la forme d'un array
  // ce décapsulage ne s'effectue qu'à un seul niveau
  // Permet de maitriser l'ordre des champs
  function asArray() { return $this->_c; }

  // extrait le fragment du document défini par $ypath
  // Renvoie un array ou un objet qui sera ensuite transformé par YamlDoc::replaceYDEltByArray()
  // Utilisé par YamlDoc::yaml() et YamlDoc::json()
  // Evite de construire une structure intermédiaire volumineuse avec asArray()
  function extract(string $ypath) { return YamlDoc::sextract($this->_c, $ypath); }

  // extrait le fragment défini par $ypath, utilisé pour générer un retour à partir d'un URI
  function extractByUri(string $docuri, string $ypath) {
    if (!$ypath || ($ypath=='/'))
      return $this->_c;
    elseif ($ypath == '/harvest') {
      $this->harvest($docuri);
      die;
    }
    elseif ($ypath == '/build') {
      return $this->build($docuri);
    }
    else
      return null;
  }

  // Fabrique une base de données
  function build(string $docuri): array {
    $database = [
      'title'=> "Métadonnées de $docuri",
      'tables'=> [
        'data'=> [
          'title'=> "Métadonnées des données",
          'data'=> [],
        ],
        'services'=> [
          'title'=> "Métadonnées des services",
          'data'=> [],
        ],
        'maps'=> [
          'title'=> "Métadonnées des cartes",
          'data'=> [],
        ],
      ],
    ];
    //$this->harvest($docuri);
    $dirpath = __DIR__.'/'.Store::id().'/'.$docuri;
    $dir = dir($dirpath);
    while (false !== ($entry = $dir->read())) {
      if (in_array($entry, ['.','..']))
        continue;
      //echo $entry."\n";
      $getrecord = file_get_contents("$dirpath/$entry");
      if (!preg_match('!^<\?xml version="1.0" encoding="UTF-8"\?>\s+!', $getrecord, $matches))
        throw new Exception("Geocat::build: no match en XML header");
      $xmlheader = $matches[0];
      //die($xmlheader);
      //header('Content-type: application/xml');
      //die($getrecord);
      //die("Fin ligne ".__LINE__."\n");
      $start = 0;
      $no = 1;
      while (true) {
        $start = strpos($getrecord, '<gmd:MD_Metadata', $start);
        if ($start === FALSE) {
          //die("Fin ligne ".__LINE__."\n");
          break;
        }
        $end = strpos($getrecord, '</gmd:MD_Metadata', $start+1);
        //die("start=$start, end=$end");
        
        //header('Content-type: application/xml');
        $record = substr($getrecord, $start, $end-$start+18);
        $record = str_replace(
            '<gmd:MD_Metadata ',
            '<gmd:MD_Metadata '.'xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" ',
            $record);
        //die("$xmlheader$record\n");
        $record = $this->buildOne($entry, $no++, $xmlheader, $record);
        $id = md5($record['fileIdentifier']);
        //echo "type=$record[type]<br>\n";
        if (in_array($record['type'], ['dataset','series']))
          $database['tables']['data']['data'][$id] = $record;
        elseif ($record['type'] <> 'service')
          throw new Exception("Erreur type $record[type] non accepté");
        elseif ($record['serviceType'] == 'invoke')
          $database['tables']['maps']['data'][$id] = $record;
        else
          $database['tables']['services']['data'][$id] = $record;
        $start = $end + 18;
      }
    }
    $dir->close();
    return $database;
  }
  
  // retourne u array Php correspondant à un enregistrement de MD
  function buildOne(string $entry, int $no, string $xmlheader, string $record): array {
    //echo "Geocat::buildOne($entry-$no)<br>\n";
    $record = IsoMetadata::simplify($record);
    //echo "<pre>"; var_dump($record); echo "</pre>\n";
    $record = IsoMetadata::standardize($record->metadata);
    //echo '<pre>',YamlDoc::syaml($record),"\n\n";
    //die("Fin ligne ".__LINE__."\n");
    return $record;
  }
};
