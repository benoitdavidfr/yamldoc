<?php
/*PhpDoc:
name: cswserver.inc.php
title: cswserver.inc.php - document correspondant à un serveur CSW
functions:
doc: <a href='/yamldoc/?action=version&name=cswserver.inc.php'>doc intégrée en Php</a>
*/
{
$phpDocs['cswserver.inc.php'] = <<<'EOT'
name: cswserver.inc.php
title: cswserver.inc.php - document correspondant à un serveur CSW
doc: |
  La classe CswServer expose différentes méthodes utilisant un serveur CSW.
  
  Outre les champs de métadonnées, le document doit définir les champs suivants:
  
    - urlCsw: fournissant l'URL du serveur à compléter avec les paramètres,
  
  Il peut aussi définir les champs suivants:
  
    - referer: définissant le referer à transmettre à chaque appel du serveur.

  Liste des points d'entrée de l'API:
  
    - /{document} : description du serveur
    - /{document}/query?{params} : envoi d'une requête, les champs SERVICE et VERSION sont prédéfinis, retour XML
    - /{document}/getCapabilities : lecture des capacités du serveur, les renvoi en XML et les enregistre
      dans le fichier /{document}/capabilities.xml
    - /{document}/numberMatched : renvoi le nbre d'enregistrements
    - /{document}/GetRecordsInIso/{startposition} : affiche en XML les enregistrements ISO à parir de {startposition}
    - /{document}/GetRecordsInDC/{startposition} : affiche en XML les enregistrements DC à parir de {startposition}
    - /{document}/harvest : moisonne les enregistrements ISO et les enregistre
      dans les fichiers /{document}/harvest/{startposition}.xml
    
  Le document http://localhost/yamldoc/?doc=geocats/sigloirecsw permet de tester cette classe.
  
journal:
  25/8/2018:
    - création
EOT;
}

require_once __DIR__.'/yd.inc.php';
require_once __DIR__.'/store.inc.php';
require_once __DIR__.'/ydclasses.inc.php';

use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Yaml\Exception\ParseException;

// classe simplifiant l'envoi de requêtes WFS
class CswServer extends YamlDoc {
  static $log = __DIR__.'/cswserver.log.yaml'; // nom du fichier de log ou false pour pas de log
  protected $_c; // contient les champs
  
  // crée un nouveau doc, $yaml est le contenu Yaml externe issu de l'analyseur Yaml
  // $yaml est généralement un array mais peut aussi être du texte
  function __construct(&$yaml) { $this->_c = $yaml; }
  
  // lit les champs
  function __get(string $name) { return isset($this->_c[$name]) ? $this->_c[$name] : null; }

  // affiche le sous-élément de l'élément défini par $ypath
  function show(string $docid, string $ypath): void {
    echo "CswServer::show($docid, $ypath)<br>\n";
    if (!$ypath || ($ypath=='/'))
      showDoc($docid, $this->_c);
    else
      showDoc($docid, $this->extract($ypath));
    //echo "<pre>"; print_r($this->_c); echo "</pre>\n";
  }
  
  // décapsule l'objet et retourne son contenu sous la forme d'un array
  function asArray() { return $this->_c; }

  // extrait le fragment du document défini par $ypath
  function extract(string $ypath) { return YamlDoc::sextract($this->_c, $ypath); }
  
  // extrait le fragment défini par $ypath, utilisé pour générer un retour à partir d'un URI
  function extractByUri(string $docuri, string $ypath) {
    //echo "CswServer::extractByUri($docuri, $ypath)<br>\n";
    if (!$ypath || ($ypath=='/'))
      return $this->_c;
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
      $dirpath = __DIR__.'/'.Store::id().'/'.$docuri;
      if (!is_dir($dirpath))
        mkdir($dirpath);
      file_put_contents("$dirpath/capabilities.xml", $result);
      die();
    }
    elseif ($ypath == '/numberMatched') {
      return ['numberMatched'=> $this->getNumberMatched()];
    }
    elseif (preg_match('!^/GetRecordsInIso/([^/]+)$!', $ypath, $matches)) {
      header('Content-type: application/xml');
      echo $this->GetRecordsInIso($matches[1]);
      die();
    }
    elseif (preg_match('!^/GetRecordsInDC/([^/]+)$!', $ypath, $matches)) {
      header('Content-type: application/xml');
      echo $this->GetRecordsInDC($matches[1]);
      die();
    }
    elseif ($ypath == '/harvest') {
      //echo "CswServer::extractByUri($docuri, $ypath)<br>\n";
      $this->harvest($docuri);
      die();
    }
    else
      return null;
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
    $url = $this->urlCsw.'?SERVICE=CSW&VERSION=2.0.2';
    foreach($params as $key => $value)
      $url .= "&$key=$value";
    if ($this->referer) {
      $referer = $this->referer;
      $context = stream_context_create(['http'=> ['header'=> "referer: $referer\r\n"]]);
    }
    else
      $context = null;
    //echo "referer=$referer\n";
    if (($result = file_get_contents($url, false, $context)) === false) {
      var_dump($http_response_header);
      throw new Exception("Erreur dans CswServer::query() : sur url=$url");
    }
    if (self::$log) { // log
      file_put_contents(self::$log, YamlDoc::syaml(['url'=> $url]), FILE_APPEND);
    }
    //die($result);
    if (substr($result, 0, 17) == '<ExceptionReport>') {
      if (!preg_match('!^<ExceptionReport><[^>]*>([^<]*)!', $result, $matches))
        throw new Exception("Erreur dans CswServer::query() : message d'erreur non détecté");
      throw new Exception ("Erreur dans CswServer::query() : $matches[1]");
    }
    return $result;
  }
    
  // retourne le nbre d'objets correspondant au résultat de la requête
  function getNumberMatched(): int {
    $query = [
      'REQUEST'=> 'GetRecords',
      'TYPENAMES'=> 'csw:Record',
      'RESULTTYPE'=> 'hits',
    ];
    $result = $this->query($query);
    if (!preg_match('! numberOfRecordsMatched="(\d+)" !', $result, $matches)) {
      //echo "result=",$result,"\n";
      throw new Exception("Erreur dans CswServer::getNumberMatched() : no match on result $result");
    }
    return (int)$matches[1];
  }
  
  // retourne en XML le résultat d'une requête GetRecords en format ISO
  function GetRecordsInIso(int $startposition=1): string {
    $query = [
      'REQUEST'=> 'GetRecords',
      'TYPENAMES'=> 'gmd:MD_Metadata',
      'RESULTTYPE'=> 'results',
      //'OUTPUTSCHEMA'=> urlencode('http://www.isotc211.org/2005/gmd'), // semble faux
      'OUTPUTSCHEMA'=> 'http://www.isotc211.org/2005/gmd',
      'ELEMENTSETNAME'=> 'full',
      'startposition'=> $startposition,
    ];
    return $this->query($query);
  }
  
  // retourne en XML le résultat d'une requête GetRecords en format DC
  function GetRecordsInDC(int $startposition=1): string {
    $query = [
      'REQUEST'=> 'GetRecords',
      'TYPENAMES'=> 'csw:Record',
      'RESULTTYPE'=> 'results',
      //'OUTPUTSCHEMA'=> urlencode('http://www.opengis.net/cat/csw/2.0.2'),
      'OUTPUTSCHEMA'=> 'http://www.opengis.net/cat/csw/2.0.2',
      'ELEMENTSETNAME'=> 'full',
      'startposition'=> $startposition,
    ];
    return $this->query($query);
  }
  
  // effectue des GetRecordsInIso à partir de 1 jusqu'à nextRecord > numberOfRecordsMatched
  // Pas optimal: beaucoup de requêtes pour rien
  // Probablement des enregistrements qui ne sont pas compatibles avec le format de sortie ISO demandé
  function harvest(string $docuri): void {
    $dirpath = __DIR__.'/'.Store::id().'/'.$docuri;
    if (!is_dir($dirpath))
      mkdir($dirpath);
    $dirpath .= '/harvest';
    if (!is_dir($dirpath))
      mkdir($dirpath);
    $numberOfRecordsMatched = 0;
    $startposition = 1;
    // la boucle s'arrête quand $startposition > $numberOfRecordsMatched
    // $numberOfRecordsMatched est calculé à la première itération
    while (true) {
      if (!is_file("$dirpath/$startposition.xml")) {
        $result = $this->GetRecordsInIso($startposition);
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
      if ($startposition > $numberOfRecordsMatched)
        return;
    }
  }
};
