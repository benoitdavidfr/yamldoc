<?php
/*PhpDoc:
name: cswserver.inc.php
title: cswserver.inc.php - document correspondant à un serveur CSW
functions:
doc: <a href='/yamldoc/?action=version&name=cswserver.inc.php'>doc intégrée en Php</a>
*/
{ // phpDocs 
$phpDocs['cswserver.inc.php'] = <<<'EOT'
name: cswserver.inc.php
title: cswserver.inc.php - document correspondant à un serveur CSW
doc: |
  La classe CswServer expose différentes méthodes utilisant un serveur CSW.
  
  Outre les champs de métadonnées, le document doit définir les champs suivants:
  
    - cswUrl: fournissant l'URL du serveur à compléter avec les paramètres,
  
  Il peut aussi définir les champs suivants:
  
    - referer: définissant le referer à transmettre à chaque appel du serveur.
    
  Le document http://localhost/yamldoc/?doc=geocats/sigloirecsw permet de tester cette classe.
  
journal:
  25/8/2018:
    - création
EOT;
}

//require_once __DIR__.'/yd.inc.php';
//require_once __DIR__.'/store.inc.php';
//require_once __DIR__.'/ydclasses.inc.php';

use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Yaml\Exception\ParseException;

// classe simplifiant l'envoi de requêtes WFS
class CswServer extends YamlDoc {
  static $log = __DIR__.'/cswserver.log.yaml'; // nom du fichier de log ou false pour pas de log
  protected $_c; // contient les champs
  
  // crée un nouveau doc, $yaml est le contenu Yaml externe issu de l'analyseur Yaml
  // $yaml est généralement un array mais peut aussi être du texte
  function __construct($yaml, string $docid) { $this->_c = $yaml; $this->_id = $docid; }
  
  // lit les champs
  function __get(string $name) { return isset($this->_c[$name]) ? $this->_c[$name] : null; }

  // affiche le sous-élément de l'élément défini par $ypath
  function show(string $ypath=''): void {
    $docid = $this->_id;
    echo "CswServer::show($docid, $ypath)<br>\n";
    if (!$ypath || ($ypath=='/'))
      showDoc($docid, $this->_c);
    else
      showDoc($docid, $this->extract($ypath));
    //echo "<pre>"; print_r($this->_c); echo "</pre>\n";
  }
  
  // décapsule l'objet et retourne son contenu sous la forme d'un array
  function asArray() { return array_merge($this->_id, $this->_c); }

  // extrait le fragment du document défini par $ypath
  function extract(string $ypath) { return YamlDoc::sextract($this->_c, $ypath); }
  
  static function api(): array {
    return [
      'class'=> get_class(), 
      'title'=> "description de l'API de la classe ".get_class(),
      'abstract'=> "utilisation d'un serveur CSW, retours en XML, moisson dans un répertoire {doc}/harvest",
      'api'=> [
        '/'=> "affiche le document ".get_class(),
        '/api'=> "liste les points d'accès de ".get_class(),
        '/query'=> "effectue sur le serveur CSW une requête définie par les paramètres GET ou POST, les paramètres SERVICE et VERSION sont prédéfinis, renvoi le résultat en XML",
        '/getCapabilities'=> "retourne en XML les capacités du serveur CSW",
        '/numberMatched'=> "retourne le nbre de fiches ISO",
        '/numberMatchedInDc'=> "retourne le nbre de fiches Dublin Core",
        '/getRecords/{startposition}'=> "retourne les fiches en ISO à partir de {startposition}",
        '/getRecordById/{id}'=> "retourne la fiche {id} en ISO",
        '/getRecordsInDc/{startposition}'=> "retourne les fiches DC à partir de {startposition}",
        '/getRecordByIdInDc/{id}'=> "retourne la fiche {id} en DC",
        '/harvest'=> "moisonne toutes les fiches, les pages de fiches sont enregistrées dans {doc}/harvest/{startposition}.xml",
        '/harvest(/{startposition}'=> "moisonne les fiches à partir de {startposition} jusqu'à la fin",
        '/harvest(/{startposition}/{endposition}'=> "moisonne les fiches à partir de {startposition} jusqu'à {endposition}",
       ]
    ];
  }
  
  // extrait le fragment défini par $ypath, utilisé pour générer un retour à partir d'un URI
  function extractByUri(string $ypath) {
    $docuri = $this->_id;
    //echo "CswServer::extractByUri($docuri, $ypath)<br>\n";
    if (!$ypath || ($ypath=='/')) {
      return array_merge(['_id'=> $this->_id], $this->_c);
    }
    elseif ($ypath == '/api') {
      return self::api();
    }
    elseif ($ypath == '/query') {
      $params = isset($_GET) ? $_GET : (isset($_POST) ? $_POST : []);
      if (isset($params['OUTPUTFORMAT']) && ($params['OUTPUTFORMAT']=='application/json'))
        header('Content-type: application/json');
      else
        header('Content-type: application/xml');
      echo $this->query($params);
      die();
    }
    elseif ($ypath == '/getCapabilities') {
      $result = $this->query(['request'=> 'GetCapabilities']);
      header('Content-type: application/xml');
      echo $result;
      $dirpath = __DIR__.'/../'.Store::id().'/'.$docuri;
      if (!is_dir($dirpath))
        mkdir($dirpath);
      file_put_contents("$dirpath/capabilities.xml", $result);
      die();
    }
    elseif ($ypath == '/numberMatched') {
      return ['numberMatched'=> $this->getNumberMatched()];
    }
    elseif ($ypath == '/numberMatchedInDC') {
      return ['numberMatched'=> $this->getNumberMatchedInDC()];
    }
    elseif (preg_match('!^/getRecords/([^/]+)$!', $ypath, $matches)) {
      header('Content-type: application/xml');
      echo $this->getRecords($matches[1]);
      die();
    }
    elseif (preg_match('!^/getRecordById/([^/]+)$!', $ypath, $matches)) {
      header('Content-type: application/xml');
      echo $this->getRecordById($matches[1]);
      die();
    }
    elseif (preg_match('!^/getRecordsInDc/([^/]+)$!', $ypath, $matches)) {
      header('Content-type: application/xml');
      echo $this->getRecordsInDC($matches[1]);
      die();
    }
    elseif (preg_match('!^/getRecordByIdInDc/([^/]+)$!', $ypath, $matches)) {
      header('Content-type: application/xml');
      echo $this->getRecordByIdInDC($matches[1]);
      die();
    }
    elseif (preg_match('!^/harvest(/([^/]+))?(/([^/]+))?$!', $ypath, $matches)) {
      echo "CswServer::extractByUri($docuri, $ypath)<br>\n";
      //echo "count(matches)=",count($matches),"\n"; print_r($matches); die();
      if (count($matches) == 1)
        $this->harvest($docuri);
      elseif (count($matches) == 3)
        $this->harvest($docuri, $matches[2]);
      elseif (count($matches) == 5)
        $this->harvest($docuri, $matches[2], $matches[4]);
      die();
    }
    else
      return null;
  }
  
  // envoi une requête HTTP GET, en cas d'erreur fait 3 essais
  function httpGet(string $url): string {
    $context = null;
    if ($this->referer) {
      $referer = $this->referer;
      $context = stream_context_create(['http'=> ['header'=> "referer: $referer\r\n"]]);
    }
    if (($result = @file_get_contents($url, false, $context)) === false) {
      echo "Attention: 1ère erreur de GET sur $url, 2ème essai<br>\n";
      sleep(1);
      if (($result = @file_get_contents($url, false, $context)) === false) {
        echo "Attention: 2ème erreur de GET sur $url, 3ème essai<br>\n";
        sleep(3);
        if (($result = @file_get_contents($url, false, $context)) === false) {
          var_dump($http_response_header);
          throw new Exception("Erreur dans CswServer::httpGet() : sur url=$url");
        }
      }
    }
    if (self::$log) { // log
      file_put_contents(self::$log, YamlDoc::syaml(['url'=> $url]), FILE_APPEND);
    }
    return $result;
  }
  
  // envoi une requête et récupère la réponse sous la forme d'un texte XML
  function query(array $params): string {
    if (self::$log) { // log
      file_put_contents(
          self::$log,
          YamlDoc::syaml([
            'date'=> date(DateTime::ATOM),
            'appel'=> 'CswServer::query',
            'params'=> $params,
          ]),
          FILE_APPEND
      );
    }
    $url = $this->cswUrl.'?SERVICE=CSW&VERSION=2.0.2';
    foreach($params as $key => $value)
      $url .= "&$key=$value";
    try {
      $result = $this->httpGet($url);
    } catch(Exception $e) {
      throw new Exception($e->getMessage());
    }
    //die($result);
    if (substr($result, 0, 17) == '<ExceptionReport>') {
      if (!preg_match('!^<ExceptionReport><[^>]*>([^<]*)!', $result, $matches))
        throw new Exception("Erreur dans CswServer::query() : message d'erreur non détecté");
      throw new Exception ("Erreur dans CswServer::query() : $matches[1]");
    }
    return $result;
  }
    
  // retourne le nbre d'enregistrements ISO exposés par le serveur
  function getNumberMatched(): int {
    $query = [
      'REQUEST'=> 'GetRecords',
      'TYPENAMES'=> 'gmd:MD_Metadata',
      'RESULTTYPE'=> 'hits',
    ];
    $result = $this->query($query);
    if (!preg_match('! numberOfRecordsMatched="(\d+)" !', $result, $matches)) {
      //echo "result=",$result,"\n";
      throw new Exception("Erreur dans CswServer::getNumberMatchedInIso() : no match on result $result");
    }
    return (int)$matches[1];
  }
    
  // retourne le nbre d'enregistrements DC exposés par le serveur
  function getNumberMatchedInDc(): int {
    $query = [
      'REQUEST'=> 'GetRecords',
      'TYPENAMES'=> 'csw:Record',
      'RESULTTYPE'=> 'hits',
    ];
    $result = $this->query($query);
    if (!preg_match('! numberOfRecordsMatched="(\d+)" !', $result, $matches)) {
      //echo "result=",$result,"\n";
      throw new Exception("Erreur dans CswServer::getNumberMatchedInDc() : no match on result $result");
    }
    return (int)$matches[1];
  }
  
  // retourne en XML le résultat d'une requête GetRecords en format ISO
  function getRecords(int $startposition=1): string {
    $query = [
      'REQUEST'=> 'GetRecords',
      'TYPENAMES'=> 'gmd:MD_Metadata',
      'RESULTTYPE'=> 'results',
      'OUTPUTSCHEMA'=> 'http://www.isotc211.org/2005/gmd',
      'ELEMENTSETNAME'=> 'full',
      'startposition'=> $startposition,
    ];
    return $this->query($query);
  }
  
  // retourne en XML le résultat d'une requête GetRecords en format ISO
  function getRecordById(string $id): string {
    $query = [
      'REQUEST'=> 'GetRecordById',
      'TYPENAMES'=> 'gmd:MD_Metadata',
      'RESULTTYPE'=> 'results',
      'OUTPUTSCHEMA'=> 'http://www.isotc211.org/2005/gmd',
      'ELEMENTSETNAME'=> 'full',
      'id'=> $id,
    ];
    return $this->query($query);
  }
  
  // retourne en XML le résultat d'une requête GetRecords en format DC
  function getRecordsInDc(int $startposition=1): string {
    $query = [
      'REQUEST'=> 'GetRecords',
      'TYPENAMES'=> 'csw:Record',
      'RESULTTYPE'=> 'results',
      'OUTPUTSCHEMA'=> 'http://www.opengis.net/cat/csw/2.0.2',
      'ELEMENTSETNAME'=> 'full',
      'startposition'=> $startposition,
    ];
    return $this->query($query);
  }
  
  // retourne en XML le résultat d'une requête GetRecords en format DC
  function getRecordByIdInDc(string $id): string {
    $query = [
      'REQUEST'=> 'GetRecordById',
      'TYPENAMES'=> 'csw:Record',
      'RESULTTYPE'=> 'results',
      'OUTPUTSCHEMA'=> 'http://www.opengis.net/cat/csw/2.0.2',
      'ELEMENTSETNAME'=> 'full',
      'id'=> $id,
    ];
    return $this->query($query);
  }
  
  // effectue des getRecords à partir de 1 jusqu'à nextRecord > numberOfRecordsMatched
  // Pas optimal: beaucoup de requêtes pour rien
  // Probablement des enregistrements qui ne sont pas compatibles avec le format de sortie ISO demandé
  // ajout possibilité de démarrer à une position quelconque et de s'arrêter avant la fin
  function harvest(string $docuri, int $startposition=1, int $endPosition=-1): void {
    $dirpath = __DIR__.'/../'.Store::storepath().'/'.$docuri;
    if (!is_dir($dirpath))
      mkdir($dirpath);
    $dirpath .= '/harvest';
    if (!is_dir($dirpath))
      mkdir($dirpath);
    $numberOfRecordsMatched = 0;
    // la boucle s'arrête quand $startposition > $numberOfRecordsMatched
    // $numberOfRecordsMatched est calculé à la première itération
    while (true) {
      if (!is_file("$dirpath/$startposition.xml")) {
        $result = $this->getRecords($startposition);
        file_put_contents("$dirpath/$startposition.xml", $result);
      }
      else
        $result = file_get_contents("$dirpath/$startposition.xml");
      if (!preg_match('! nextRecord="(\d+)"!', $result, $matches)) {
        echo "result=",$result,"\n";
        throw new Exception("Erreur dans CswServer::harvest() : no match nextRecord on result $result");
      }
      $nextRecord = $matches[1];
      if (!$numberOfRecordsMatched) {
        if (!preg_match('! numberOfRecordsMatched="(\d+)"!', $result, $matches)) {
          echo "result=",$result,"\n";
          throw new Exception("Erreur dans CswServer::harvest() : no match numberOfRecordsMatched on result $result");
        }
        $numberOfRecordsMatched = $matches[1];
        echo "numberOfRecordsMatched=$numberOfRecordsMatched<br>\n";
      }
      // A value of 0 means all records have been returned.
      if ($nextRecord == 0) {
        echo "nextRecord==0, tous les enregistrements ont été moissonnés<br>\n";
        return;
      }
      echo "nextRecord=$nextRecord / numberOfRecordsMatched=$numberOfRecordsMatched<br>\n";
      if ($nextRecord <= $startposition) {
        echo "Erreur nextRecord=$nextRecord <= startposition=$startposition<br>\n";
        $startposition++;
      }
      else
        $startposition = $nextRecord;
      if ($startposition > $numberOfRecordsMatched) {
        echo "Fin sur startposition=$startposition > numberOfRecordsMatched=$numberOfRecordsMatched<br>\n";
        return;
      }
      if (($endPosition <> -1) && ($startposition > $endPosition)) {
        echo "Fin sur startposition=$startposition > endPosition=$endPosition<br>\n";
        return;
      }
    }
  }
};
