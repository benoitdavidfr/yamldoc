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
  La classe Geocat étend CswServer et expose différentes méthodes utilisant un géocatalogue.

  Outre les champs de métadonnées, le document doit définir les champs suivants:

    - urlCsw: fournissant l'URL du serveur à compléter avec les paramètres,

  Il peut aussi définir les champs suivants:

    - referer: définissant le referer à transmettre à chaque appel du serveur.

  Liste des points d'entrée de l'API:

    - /{document} : description du serveur
    - /{document}/harvest : moisonne les enregistrements ISO et les enregistre
      dans les fichiers /{document}/harvest/{startposition}.xml
    - /{document}/buildDb : construit une base de données des métadonnées et l'enregistre
      dans le fichier /{document}/db.yaml
    - /{document}/listSubjects : liste les mots-clés
    - /{document}/search?subject={subject} : recherche dans les MD le mot-clé {subject}
    - /{document}/search?text={text} : recherche plein texte dans les MD le texte {text}
    - /{document}/items/{id} : retourne la MD {id}

  Le document http://localhost/yamldoc/?doc=geocats/sigloire permet de tester cette classe.

journal:
  24-26/8/2018:
    - création
EOT;
}
require_once __DIR__.'/yamldoc.inc.php';
require_once __DIR__.'/search.inc.php';
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
    elseif ($ypath == '/listSubjects') {
      new_doc("$docid/db")->show("$docid/db", '/listSubjects');
      return;
    }
    elseif ($ypath == '/search') {
      new_doc("$docid/db")->show("$docid/db", '/search');
      return;
    }
    elseif (preg_match('!^/items/([^/]+)$!', $ypath, $matches)) {
      new_doc("$docid/db")->show("$docid/db", $ypath);
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
    //echo "Geocat::extractByUri($docuri, $ypath)<br>\n";
    if (!$ypath || ($ypath=='/'))
      return $this->_c;
    elseif ($ypath == '/harvest') {
      $this->harvest($docuri);
      die("harvest ok\n");
    }
    // crée la DB à partir de la moissson et l'enregistre comme fichier db.yaml
    elseif ($ypath == '/buildDb') {
      $db = $this->buildDb($docuri);
      $db->buildOperatedBy($docuri);
      $storepath = Store::storepath();
      $filename = __DIR__."/$storepath/$docuri/db.yaml";
      file_put_contents($filename, YamlDoc::syaml(self::replaceYDEltByArray($db->asArray())));
      return "buildDb ok, fichier $filename créé\n";
    }
    elseif ($ypath == '/buildOperatedBy') {
      return new_doc("$docuri/db")->extractByUri("$docuri/db", '/buildOperatedBy');
    }
    elseif ($ypath == '/listSubjects') {
      return new_doc("$docuri/db")->extractByUri("$docuri/db", '/listSubjects');
    }
    elseif ($ypath == '/search') {
      return new_doc("$docuri/db")->extractByUri("$docuri/db", '/search');
    }
    elseif (preg_match('!^/items/([^/]+)$!', $ypath, $matches)) {
      return new_doc("$docuri/db")->extractByUri("$docuri/db", $ypath);
    }
    else
      return null;
  }

  // Fabrique la base de données des MD, la renoie comme objet MetadataDb
  function buildDb(string $docuri): MetadataDb {
    $database = [
      'title'=> "Métadonnées de $docuri",
      'yamlClass'=> 'MetadataDb',
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
    $dirpath = __DIR__.'/'.Store::id()."/$docuri/harvest";
    $dir = dir($dirpath);
    // lecture des différents fichiers issus du moissonnage
    while (false !== ($entry = $dir->read())) {
      if (in_array($entry, ['.','..']))
        continue;
      //echo $entry."\n";
      $getrecord = file_get_contents("$dirpath/$entry");
      if (!preg_match('!^<\?xml version="1.0" encoding="UTF-8"\?>\s+!', $getrecord, $matches)) {
        echo substr($getrecord, 0, 300),"...\n";
        throw new Exception("Geocat::buildDb: no match en XML header");
      }
      $xmlheader = $matches[0];
      $start = 0;
      $no = 1;
      // extraction des fiches de métadonnées contenues dans le getrecord
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
    return new MetadataDb($database);
  }
  
  // retourne un array Php correspondant à un enregistrement de MD
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
