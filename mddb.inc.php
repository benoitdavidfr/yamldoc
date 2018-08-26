<?php
/*PhpDoc:
name: mddb.inc.php
title: mddb.inc.php - base de données de Metadata
functions:
doc: <a href='/yamldoc/?action=version&name=mddb.inc.php'>doc intégrée en Php</a>
*/
{ // doc 
$phpDocs['mddb.inc.php']['file'] = <<<'EOT'
name: mddb.inc.php
title: mddb.inc.php - base de données de Metadata
doc: |
  Une BD de MD est une base YData composé des 3 tables suivantes :
  
  - une table data des MD de données
  - une table services des MD de service
  - une table maps des MD de cartes
journal:
  26/8/2018:
    - création
EOT;
}
require_once __DIR__.'/yamldoc.inc.php';
require_once __DIR__.'/search.inc.php';
require_once __DIR__.'/isometadata.inc.php';

class MetadataDb extends YData {
  static $log = __DIR__.'/mddb.log.yaml'; // nom du fichier de log ou '' pour pas de log
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
    echo "MetadataDb::show($docid, $ypath)<br>\n";
    if (!$ypath || ($ypath == '/')) {
      showDoc($docid, $this->_c);
      return;
    }
    elseif ($ypath == '/listSubjects') {
      foreach ($this->listSubjects($docid) as $cvocid => $cvoc) {
        echo "<h3>$cvocid</h3><ul>\n";
        foreach ($cvoc['labelList'] as $label => $rec)
          echo "<li><a href='?doc=$docid&amp;ypath=/search&amp;subject=",urlencode($label),"'>$label</a> ($rec[nbreOfOccurences])\n";
        echo "</ul>\n";
      }
      //print_r($this->listSubjects($docid));
      return;
    }
    elseif (($ypath == '/search') && isset($_GET['subject'])) {
      echo "<ul>\n";
      foreach($this->searchOnSubject($docid, $_GET['subject'])['results'] as $md) {
        echo "<li><a href='?doc=$docid&amp;ypath=/items/$md[id]'>$md[title]</a>\n";
      }
      echo "</ul>\n";
      echo "<pre>"; print_r($this->searchOnSubject($docid, $_GET['subject']));
      return;
    }
    elseif (preg_match('!^/items/([^/]+)$!', $ypath, $matches)) {
      $mdid = $matches[1];
      parent::show($docid, "/tables/data/data/$mdid");
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
    //echo "MetadataDb::extractByUri($docuri, $ypath)<br>\n";
    if (!$ypath || ($ypath=='/'))
      return $this->_c;
    elseif ($ypath == '/buildOperatedBy') {
      // echo "MetadataDatabase::extractByUri($docuri, $ypath)<br>\n";
      return $this->buildOperatedBy($docuri);
    }
    elseif ($ypath == '/listSubjects') {
      // echo "MetadataDatabase::extractByUri($docuri, $ypath)<br>\n";
      return $this->listSubjects($docuri);
    }
    elseif ($ypath == '/search') {
      if (isset($_GET['text']))
        return FullTextSearch::search('geocats/sigloire/db', '', $_GET['text']);
      elseif (isset($_GET['subject']))
        return $this->searchOnSubject($docuri, $_GET['subject']);
      else
        return "search incompris";
    }
    elseif (preg_match('!^/items/([^/]+)$!', $ypath, $matches)) {
      $mdid = $matches[1];
      return parent::extractByUri($docuri, "/tables/data/data/$mdid");
    }
    else
      return null;
  }
  
  // fabrique la liste des mots-clés organisée par vocabulaire contrôlé
  function listSubjects(string $docuri) {
    //echo "MetadataDb::listSubjects($docuri)<br>\n";
    $subjects = new SubjectList;
    foreach (parent::extractByUri($docuri, '/data')['data'] as $id => $metadata) {
//      print_r($metadata['subject']); echo "<br>\n";
      if (isset($metadata['subject'])) {
        foreach ($metadata['subject'] as $subject) {
          $subjects->add($subject);
        }
      }
    }
    return $subjects->asArray();
  }
  
  function searchOnSubject($docuri, $searchedSubject) {
    //echo "MetadataDb::searchOnSubject($docuri, $searchedSubject)<br>\n";
    $results = [];
    foreach (parent::extractByUri("$docuri/db", '/data')['data'] as $id => $metadata) {
//      print_r($metadata['subject']); echo "<br>\n";
      if (isset($metadata['subject'])) {
        foreach ($metadata['subject'] as $subject) {
          if ($subject['value'] == $searchedSubject) {
            //echo "<b>$metadata[title]</b><br>\n";
            $results[] = ['id'=>$id, 'title'=> $metadata['title']];
            break;
          }
        }
      }
    }
    return [
      'search'=> ['subject'=> $searchedSubject],
      'nbreOfResults'=> count($results),
      'results'=> $results,
    ];
  }
  
  // part de la base de données et complète les fiches de MD de données avec un champ operatedBy 
  function buildOperatedBy(string $docuri) {
    $fileIdentifiers = []; // [ fileIdentifier => id ]
    foreach ($this->tables['data']['data'] as $id => $metadata) {
      $fileIdentifiers[$metadata['fileIdentifier']] = $id;
    }
    foreach ($this->tables['services']['data'] as $serviceId => $metadata) {
      //echo "<b>$metadata[title]</b><br>\n";
      if (isset($metadata['operatesOn'])) {
        //echo "<pre>"; print_r($metadata['operatesOn']); echo "</pre>\n";
        foreach ($metadata['operatesOn'] as $n => $operatesOn) {
          if (isset($operatesOn['uuidref']) && isset($fileIdentifiers[$operatesOn['uuidref']])) {
            //echo "uuidref $n matches fileIdentifier<br>\n";
            $dataId = $fileIdentifiers[$operatesOn['uuidref']];
            $this->_c['tables']['data']['data'][$dataId]['operatedBy'][] = $serviceId;
          }
        }
      }
    }
    //die("buildOperatedBy ok\n");
    return "buildOperatedBy ok\n";
  }
};

// Gestion des vocabulaires contrôlés
class Cvoc {
  private $id;
  private $labelList = []; // [ label => [ 'nbre'=> nbre d''occurences ] ]
    
  function __construct(string $id) { $this->id = $id; }

  // ajoute un mot-clé àà ce cvoc
  function add(array $subject): void {
    if (!isset($this->labelList[$subject['value']]))
      $this->labelList[$subject['value']]['nbreOfOccurences'] = 1;
    else
      $this->labelList[$subject['value']]['nbreOfOccurences']++;
    //echo "Cvoc::add($subject[value])<br>\n"; print_r($this); echo "<br>\n";
  }
  
  // retourne la liste des étiquettes et leur occurence
  function asArray(): array {
    //echo "Cvoc::list()<br>\n"; print_r($this); echo "<br>\n";
    return [
      'nbreOflabels'=> count($this->labelList),
      'labelList'=> $this->labelList,
    ];
  }
};

// contruction d'une liste structurée des mots-clés en vocabulaires contrôlés à partir des mots-clés du Geocat
class SubjectList {
  private $cvocs = []; // [ cvocid => Cvoc ]
  
  function __construct() { }
  
  // ajoute un mot-clé
  function add(array $subject): void {
    $cvocid = isset($subject['cvoc']) ? $subject['cvoc'] : 'none';
    if (!isset($this->cvocs[$cvocid]))
      $this->cvocs[$cvocid] = new Cvoc($cvocid);
    $this->cvocs[$cvocid]->add($subject);
  }
  
  // retourne la liste des vocabulaires contrôlés construits
  function asArray(): array {
    //print_r($this->cvocs);
    $result = [];
    foreach ($this->cvocs as $cvocid => $cvoc)
      $result[$cvocid] = $cvoc->asArray();
    return $result;
  }
};
