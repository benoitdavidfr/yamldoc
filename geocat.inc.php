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
    - /{document}/csw/xxx : appelle /xxx sur le serveur Csw sous-jacent
    - /{document}/buildDb : construit une base de données des métadonnées et l'enregistre
      dans le fichier /{document}/db.yaml
    - /{document}/buildSubjects : construit le sous-objet de la liste les mots-clés
    - /{document}/search?subject={subject} : recherche dans les MD le mot-clé {subject}
    - /{document}/search?text={text} : recherche plein texte dans les MD le texte {text}
    - /{document}/items/{id} : retourne la MD {id}

  Un objet Geocat peut contenir les différents sous-objets suivants dont l'accès est effectué au travers du Geocat:
    
    - un objet MetadataDb, correspondant à l'uri {docid}/db, qui contient une base de données des MD
      composée de différentes tables: data, services, maps, ...
    - un objet Subjects, correspondant à l'uri {docid}/subjects, qui contient la liste des mot-clés organisée
      par vocabulaire contrôlé

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
    // exécute une requête sur le seveur CSW correspondant
    elseif (preg_match('!^/csw(/.+)$!', $ypath, $matches)) {
      return parent::extractByUri($docuri, $matches[1]);
    }
    // crée la DB à partir de la moissson et l'enregistre comme fichier db.yaml
    elseif ($ypath == '/buildDb') {
      $db = $this->buildDb($docuri);
      $db->buildOperatedBy($docuri);
      $storepath = Store::storepath();
      //$filename = __DIR__."/$storepath/$docuri/db.yaml";
      //file_put_contents($filename, YamlDoc::syaml(self::replaceYDEltByArray($db->asArray())));
      $db->writePser("$docuri/db");
      return "buildDb ok, document $docuri/db créé en pser\n";
    }
    elseif ($ypath == '/buildOperatedBy') {
      return new_doc("$docuri/db")->extractByUri("$docuri/db", '/buildOperatedBy');
    }
    elseif ($ypath == '/db') {
      return new_doc("$docuri/db")->extractByUri("$docuri/db", '');
    }
    elseif ($ypath == '/buildSubjects') {
      $subjects = new_doc("$docuri/db")->listSubjects("$docuri/db");
      $subjects->writePser("$docuri/subjects");
      return "buildSubjects ok, document $docuri/subjects créé en pser\n";
    }
    elseif ($ypath == '/subjects') {
      return new_doc("$docuri/subjects")->extractByUri("$docuri/subjects", '');
    }
    elseif (preg_match('!^/subjects(/.*)$!', $ypath, $matches)) {
      return new_doc("$docuri/subjects")->extractByUri("$docuri/subjects", $matches[1]);
    }
    elseif ($ypath == '/search') {
      return new_doc("$docuri/db")->extractByUri("$docuri/db", '/search');
    }
    elseif (preg_match('!^/items/([^/]+)$!', $ypath, $matches)) {
      return new_doc("$docuri/db")->extractByUri("$docuri/db", $ypath);
    }
    elseif (preg_match('!^/items/([^/]+)/download$!', $ypath, $matches)) {
      return new_doc("$docuri/db")->extractByUri("$docuri/db", $ypath);
    }
    else
      return null;
  }

  // Fabrique la base de données des MD, la renoie comme objet MetadataDb
  function buildDb(string $docuri): MetadataDb {
    $logfilename = __DIR__.'/'.Store::id()."/$docuri/build.log.yaml";
    file_put_contents(
        $logfilename,
        YamlDoc::syaml([
          'date'=> date(DateTime::ATOM),
          'appel'=> 'Geocat::buildDb',
          'docuri'=> $docuri,
        ]),
        FILE_APPEND
    );
    file_put_contents($logfilename, "logs:\n", FILE_APPEND);
    
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
        'nonGeographicDataset'=> [
          'title'=> "Métadonnées de données non géographiques (nonGeographicDataset)",
          'data'=> [],
        ],
        'others'=> [
          'title'=> "Autres métadonnées",
          'data'=> [],
        ],
      ],
    ];
    $dirpath = __DIR__.'/'.Store::id()."/$docuri/harvest";
    $dir = dir($dirpath);
    // lecture des différents fichiers issus du moissonnage
    while (false !== ($entry = $dir->read())) {
      if (in_array($entry, ['.','..']))
        continue;
      echo $entry."\n";
      $getrecord = file_get_contents("$dirpath/$entry");
      try {
        $searchResults = IsoMetadata::simplify($getrecord, $entry);
      } catch (Exception $e) {
        echo $e->getMessage(),"<br>\n";
        file_put_contents($logfilename, "  - ".$e->getMessage()."\n", FILE_APPEND);
        continue;
      }
      $no = 0; // no dans le getrecord
      foreach ($searchResults->metadata as $md) {
        $record = IsoMetadata::standardize($md);
        $id = md5($record['fileIdentifier']);
        //echo "type=$record[type]<br>\n";
        if (!isset($record['type'])) {
          $message = "erreur champ type absent dans l'enregistrement $entry $no";
          echo "$message<br>\n";
          file_put_contents($logfilename, "  - $message\n", FILE_APPEND);
          $database['tables']['others']['data'][$id] = $record;
          //throw new Exception("erreur champ type absent dans l'enregistrement");
        }
        elseif (in_array($record['type'], ['dataset','series']))
          $database['tables']['data']['data'][$id] = $record;
        elseif (in_array($record['type'], ['map']))
          $database['tables']['maps']['data'][$id] = $record;
        elseif (in_array($record['type'], ['nonGeographicDataset']))
          $database['tables']['nonGeographicDataset']['data'][$id] = $record;
        elseif ($record['type'] <> 'service') {
          $message = "type '$record[type]' non prévu";
          echo "$message<br>\n";
          file_put_contents($logfilename, "  - $message\n", FILE_APPEND);
          $database['tables']['others']['data'][$id] = $record;
          //throw new Exception("Erreur type $record[type] non accepté");
        }
        elseif ($record['serviceType'] == 'invoke')
          $database['tables']['maps']['data'][$id] = $record;
        else
          $database['tables']['services']['data'][$id] = $record;
        $no++;
      }
    }
    $dir->close();
    return new MetadataDb($database);
  }
  
  // version périmée, la standardisation fonctionne sur un getrecord !
  // Fabrique la base de données des MD, la renoie comme objet MetadataDb
  function buildDbOld(string $docuri): MetadataDb {
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
        $record = $this->buildOne($entry, $no, $xmlheader, $record);
        $id = md5($record['fileIdentifier']);
        //echo "type=$record[type]<br>\n";
        if (!isset($record['type'])) {
          echo "erreur champ type absent dans l'enregistrement $entry $no:<br>\n";
          echo "Enregistrement non traité<br>\n";
          //echo "<pre>"; print_r($record); echo "</pre>\n";
          //throw new Exception("erreur champ type absent dans l'enregistrement");
        }
        elseif (in_array($record['type'], ['dataset','series']))
          $database['tables']['data']['data'][$id] = $record;
        elseif ($record['type'] <> 'service') {
          echo "type '$record[type]' non prévu, enregistrement non traita<br>\n";
          //throw new Exception("Erreur type $record[type] non accepté");
        }
        elseif ($record['serviceType'] == 'invoke')
          $database['tables']['maps']['data'][$id] = $record;
        else
          $database['tables']['services']['data'][$id] = $record;
        $start = $end + 18;
        $no++;
      }
    }
    $dir->close();
    return new MetadataDb($database);
  }
  
  // retourne un array Php correspondant à un enregistrement de MD
  function buildOneOld(string $entry, int $no, string $xmlheader, string $record): array {
    //echo "Geocat::buildOne($entry-$no)<br>\n";
    $record = IsoMetadata::simplify($record, "$entry-$no");
    //echo "<pre>"; var_dump($record); echo "</pre>\n";
    $record = IsoMetadata::standardize($record->metadata);
    //echo '<pre>',YamlDoc::syaml($record),"\n\n";
    //die("Fin ligne ".__LINE__."\n");
    return $record;
  }
};
