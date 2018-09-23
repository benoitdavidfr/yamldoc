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

    - cswUrl: fournissant l'URL du serveur à compléter avec les paramètres,

  Il peut aussi définir les champs suivants:

    - referer: définissant le referer à transmettre à chaque appel du serveur.

  Un objet Geocat peut contenir les différents sous-documents suivants dont l'accès est effectué au travers du Geocat:
    
    - un document MetadataDb, correspondant à l'uri {docid}/db,  contient la base de données des MD
      composée de différentes tables: data, services, maps, ...
    - un objet SubjectList, correspondant à l'uri {docid}/subjects, contient la liste des mot-clés organisée
      par vocabulaire contrôlé

  Les documents geocats/sigloire, geocats/sextant et geocats/geoide permettent de tester cette classe.
  
  A FAIRE:
    - limiter l'indexation en restreignant les champs indexables

journal:
  24-26/8/2018:
    - création
EOT;
}
//require_once __DIR__.'/yamldoc.inc.php';
//require_once __DIR__.'/search.inc.php';
require_once __DIR__.'/../isometadata.inc.php';
require_once __DIR__.'/inc.php';

class Geocat extends CswServer {
  static $log = __DIR__.'/geocat.log.yaml'; // nom du fichier de log ou '' pour pas de log
  
  // crée un nouveau doc, $yaml est le contenu Yaml externe issu de l'analyseur Yaml
  // $yaml est généralement un array mais peut aussi être du texte
  function __construct($yaml, string $docid) {
    $this->_id = $docid;
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
  function show(string $ypath=''): void {
    $docid = $this->_id;
    echo "Geocat::show($docid, $ypath)<br>\n";
    if (!$ypath || ($ypath == '/')) {
      showDoc($docid, $this->_c);
      return;
    }
    elseif ($ypath == '/listSubjects') {
      new_doc("$docid/db")->show('/listSubjects');
      return;
    }
    elseif ($ypath == '/search') {
      new_doc("$docid/db")->show('/search');
      return;
    }
    elseif (preg_match('!^/items/([^/]+)$!', $ypath, $matches)) {
      new_doc("$docid/db")->show($ypath);
    }
    else {
      showDoc($docid, $this->extract($ypath));
      return;
    }
  }
  
  // décapsule l'objet et retourne son contenu sous la forme d'un array
  // ce décapsulage ne s'effectue qu'à un seul niveau
  // Permet de maitriser l'ordre des champs
  function asArray() { return array_merge($this->_id, $this->_c); }

  // extrait le fragment du document défini par $ypath
  // Renvoie un array ou un objet qui sera ensuite transformé par YamlDoc::replaceYDEltByArray()
  // Utilisé par YamlDoc::yaml() et YamlDoc::json()
  // Evite de construire une structure intermédiaire volumineuse avec asArray()
  function extract(string $ypath) { return YamlDoc::sextract($this->_c, $ypath); }
  
  static function api(): array {
    return [
      'class'=> get_class(), 
      'title'=> "description de l'API de la classe",
      'api'=> [
        '/'=> "retourne le contenu du document ".get_class(),
        '/db'=> "retourne le nbre de MD par catégorie",
        '/db/{cmde}'=> MetadataDb::api()['api'],
        '/buildDb'=> "déduit de la moisson la BD des MD et l'enregistre comme document {doc}/db",
        '/subjects'=> "retourne la liste des vocabulaires",
        '/subjects/{cmde}'=> SubjectList::api()['api'],
        '/buildSubjects'=> "déduit de la BDMD la liste des mots-clés et l'enregistre dans le fichier {doc}/subjects.pser",
        '/search'=> " -> /db/search",
        '/items/...'=> " -> /db/items/...",
        '/csw/{cmde}'=> CswServer::api()['api'],
        '/dump'=> "affiche le dump du document",
        '/api'=> "retourne les points d'accès",
      ]
    ];
  }

  // extrait le fragment défini par $ypath, utilisé pour générer un retour à partir d'un URI
  function extractByUri(string $ypath) {
    $docuri = $this->_id;
    //echo "Geocat::extractByUri($docuri, $ypath)<br>\n";
    if (!$ypath || ($ypath=='/')) {
      return array_merge(['_id'=> $this->_id], $this->_c);
    }
    elseif ($ypath == '/api') {
      return self::api();
    }
    elseif ($ypath == '/dump') {
      $this->dump();
      return "dump ok";
    }
    // exécute une requête sur le seveur CSW correspondant
    elseif (preg_match('!^/csw(/.+)?$!', $ypath, $matches)) {
      return parent::extractByUri(isset($matches[1]) ? $matches[1] : '');
    }
    // crée la DB à partir de la moissson et l'enregistre comme fichier db.pser
    elseif ($ypath == '/buildDb') {
      $db = $this->buildDb();
      $db->buildHasFormat();
      $db->writePser();
      return "buildDb ok, document $docuri/db créé en pser";
    }
    elseif ($ypath == '/db') {
      return new_doc("$docuri/db")->extractByUri('');
    }
    elseif ($ypath == '/db/api') {
      return MetadataDb::api();
    }
    elseif (preg_match('!^/db(/.*)$!', $ypath, $matches)) {
      //echo "Geocat::extractByUri($ypath)<br>\n";
      return new_doc("$docuri/db")->extractByUri($matches[1]);
    }
    elseif ($ypath == '/buildSubjects') {
      $subjects = new_doc("$docuri/db")->listSubjects();
      $subjects->writePser();
      return "buildSubjects ok, document $docuri/subjects créé en pser";
    }
    elseif ($ypath == '/subjects') {
      return new_doc("$docuri/subjects")->extractByUri('');
    }
    elseif (preg_match('!^/subjects(/.*)$!', $ypath, $matches)) {
      return new_doc("$docuri/subjects")->extractByUri($matches[1]);
    }
    elseif ($ypath == '/search') {
      return new_doc("$docuri/db")->extractByUri('/search');
    }
    elseif (preg_match('!^/items/(.*)$!', $ypath, $matches)) {
      return new_doc("$docuri/db")->extractByUri($ypath);
    }
    else
      return null;
  }

  // le fileIdentifier est bon comme id s'il n'est composé que de chiffres, lettres et '-' et qu'il est court
  // ex: fr-120066022-jdd-9450de81-bbc9-4175-a4b1-f9726bebf602
  private static function goodIdentifier(string $fileIdentifier): string {
    if (preg_match('!^[0-9a-z-]+$!i', $fileIdentifier) && (strlen($fileIdentifier) <= 600))
      return $fileIdentifier;
    else
      return md5($fileIdentifier);
  }
  // test de goodIdentifier
  static function goodIdentifierTest(): void {
    $testIds = [
      'fr-120066022-jdd-9450de81-bbc9-4175-a4b1-f9726bebf602',
      'a456-B',
      'ssh!hhtp',
    ];
    foreach ($testIds as $id) {
      echo "goodIdentifier($id) -> ",self::goodIdentifier($id),"<br>\n";
      echo "len=",strlen($id),"<br>\n";
    }
  }
    
  // Fabrique la base de données des MD, la renoie comme objet MetadataDb
  function buildDb(): MetadataDb {
    $docuri = $this->_id;
    $dirpath = __DIR__.'/../'.Store::storepath()."/$docuri";
    if (!is_dir($dirpath))
      throw new Exception("Erreur: Le serveur n'a pas été moissonné");
    $logfilename = "$dirpath/build.log.yaml";
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
    $dirpath = __DIR__.'/../'.Store::storepath()."/$docuri/harvest";
    $dir = dir($dirpath);
    // lecture des différents fichiers issus du moissonnage
    while (false !== ($entry = $dir->read())) {
      if (in_array($entry, ['.','..']))
        continue;
      //if ($entry <> '3781.xml') continue;
      //if (!preg_match('!^3!', $entry)) continue;
      echo "$entry<br>\n";
      $getrecord = file_get_contents("$dirpath/$entry");
      try {
        $searchResults = IsoMetadata::simplify($getrecord, $entry, 'ML');
      } catch (Exception $e) {
        echo $e->getMessage(),"<br>\n";
        file_put_contents($logfilename, "  - ".$e->getMessage()."\n", FILE_APPEND);
        continue;
      }
      $no = 0; // no dans le getrecord
      foreach ($searchResults->metadata as $md) {
        $record = IsoMetadata::standardizeMl($md);
        $id = self::goodIdentifier($record['fileIdentifier']);
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
        elseif (in_array($record['type'], ['','Composite Product Record','Product record']))
          $database['tables']['ohers']['data'][$id] = $record;
        elseif ($record['type'] <> 'service') {
          $message = "type '$record[type]' non prévu";
          echo "$message<br>\n";
          file_put_contents($logfilename, "  - $message\n", FILE_APPEND);
          $database['tables']['others']['data'][$id] = $record;
          //throw new Exception("Erreur type $record[type] non accepté");
        }
        elseif (!isset($record['serviceType']))
          $database['tables']['others']['data'][$id] = $record;
        elseif ($record['serviceType'] == 'invoke')
          $database['tables']['maps']['data'][$id] = $record;
        else
          $database['tables']['services']['data'][$id] = $record;
        $no++;
      }
    }
    $dir->close();
    return new MetadataDb($database, "$docuri/db");
  }
  
  // retourne le wfsOptions en fonction du wfsUrl
  function wfsOptions(string $wfsUrl): array {
    if (!$this->wfsOptions)
      return [];
    foreach ($this->wfsOptions as $pattern => $wfsOptions) {
      if (preg_match($pattern, $wfsUrl))
        return $wfsOptions;
    }
    return [];
  }
};


if (basename(__FILE__)<>basename($_SERVER['SCRIPT_NAME'])) return;



if (!isset($_SERVER['PATH_INFO'])) {
  echo "<h3>Tests unitaires</h3><ul>\n";
  echo "<li><a href='$_SERVER[SCRIPT_NAME]/goodIdentifierTest'>Test de la méthode Geocat::goodIdentifier()</a>\n";
  echo "</ul>\n";
  die();
}

$testMethod = substr($_SERVER['PATH_INFO'], 1);
Geocat::$testMethod();
