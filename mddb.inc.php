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
  Une BD de MD est une base YData composé des 4 tables suivantes :
  
  - une table data des MD de données
  - une table services des MD de service
  - une table maps des MD de cartes
  - une table nonGeographicDataset des MD de SD non géographiques (nonGeographicDataset)
  - une table others des autres MD
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
    // projection sur certains champs
    elseif (preg_match('!^/proj/(.*)$!', $ypath, $matches)) {
      $fields = explode(',', $matches[1]);
      return $this->proj($docuri, $fields);
    }
    // recherche full text ou par mot-clé
    elseif ($ypath == '/search') {
      if (isset($_GET['text']))
        return FullTextSearch::search($docuri, '', $_GET['text']);
      elseif (isset($_GET['subject']))
        return $this->searchOnSubject($docuri, $_GET['subject']);
      else
        return "search incompris";
    }
    // accès à une MD par son id
    elseif (preg_match('!^/items/([^/]+)$!', $ypath, $matches)) {
      $mdid = $matches[1];
      foreach(['data','services','maps','nonGeographicDataset','others'] as $table) {
        if ($md = parent::extractByUri($docuri, "/tables/$table/data/$mdid"))
          return $md;
      }
      return null;
    }
    elseif (preg_match('!^/items/([^/]+)/download$!', $ypath, $matches)) {
      return $this->download($docuri, $matches[1])->asArray();
    }
    else
      return null;
  }
  
  // fabrique la liste des mots-clés organisée par vocabulaire contrôlé
  function listSubjects(string $docuri): SubjectList {
    $yaml = [];
    $subjects = new SubjectList($yaml);
    foreach (parent::extractByUri($docuri, '/data')['data'] as $id => $metadata) {
      //echo "subjects = "; print_r($metadata['subject']); echo "<br>\n";
      $mainMdLanguage = (is_string($metadata['mdLanguage']) ? $metadata['mdLanguage'] : $metadata['mdLanguage'][0]);
      if (isset($metadata['subject'])) {
        foreach ($metadata['subject'] as $subject) {
          //echo "subject = "; print_r($subject); echo "<br>\n";
          $subjects->add($subject, $mainMdLanguage);
        }
      }
    }
    $subjects->sortVocs();
    return $subjects;
  }
  
  function proj(string $docuri, array $fields) {
    $proj = [];
    foreach (parent::extractByUri($docuri, '/data')['data'] as $id => $metadata) {
      $eltproj = [];
      foreach ($fields as $field)
        if (isset($metadata[$field]))
          $eltproj[$field] = $metadata[$field];
      $proj[] = $eltproj;
    }
    return $proj;
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
            $results[] = ['id'=> $id, 'title'=> $metadata['title']];
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
    // fabrication d'une table temporaire $fileIdentifiers pour accéder efficament aux datasets par leur fileIdentifier
    foreach ($this->tables['data']['data'] as $id => $metadata) {
      $fileIdentifiers[$metadata['fileIdentifier']] = $id;
    }
    // Parcours de la table fes services pour fabriquer le lien inverse de operatesOn
    foreach ($this->tables['services']['data'] as $serviceId => $metadata) {
      //echo "<b>$metadata[title]</b><br>\n";
      $serviceType = isset($metadata['serviceType']) ? $metadata['serviceType'] : 'noServiceType';
      if (isset($metadata['operatesOn'])) {
        echo "<pre>"; print_r($metadata['operatesOn']); echo "</pre>\n";
        foreach ($metadata['operatesOn'] as $n => $operatesOn) {
          if (isset($operatesOn['uuidref']) && isset($fileIdentifiers[$operatesOn['uuidref']])) {
            echo "uuidref $n matches fileIdentifier<br>\n";
            $dataId = $fileIdentifiers[$operatesOn['uuidref']];
            if (!isset($this->_c['tables']['data']['data'][$dataId]['operatedBy'][$serviceType]))
              $this->_c['tables']['data']['data'][$dataId]['operatedBy'][$serviceType] = [$serviceId];
            elseif (!in_array($serviceId, $this->_c['tables']['data']['data'][$dataId]['operatedBy'][$serviceType]))
              $this->_c['tables']['data']['data'][$dataId]['operatedBy'][$serviceType][] = $serviceId;
          }
        }
      }
    }
    //die("buildOperatedBy ok\n");
    return "buildOperatedBy ok\n";
  }
  
  // renvoie la VectorDataset correspondant à la fiche de MDD $$dsid ou génère une exception si cela n'est pas possible
  function download(string $docuri, string $dsid): VectorDataset {
    $dataset = parent::extractByUri($docuri, "/tables/data/data/$dsid");
    //echo "<pre> dataset = "; print_r($dataset);
    if (!isset($dataset['operatedBy']['download']))
      throw new Exception("Aucun service download");
    foreach ($dataset['operatedBy']['download'] as $serviceId) {
      //echo "serviceId=$serviceId<br>\n";
      $service = parent::extractByUri($docuri, "/tables/services/data/$serviceId");
      //echo "<pre>"; print_r($service);
      if (!isset($service['relation']))
        continue;
      $urlWfs = null;
      foreach ($service['relation'] as $relation) {
        if (isset($relation['protocol'])
             && preg_match('!^OGC:WFS-1.0.0-http-get-capabilities$!', $relation['protocol'])) {
          $urlWfs = $relation['url'];
          break 2;
        }
      }
    }
    if (!$urlWfs)
      throw new Exception("Aucun service WFS");
    $urlWfs = str_replace('&amp;', '&', $urlWfs);
    //echo "urlWfs=$urlWfs<br>\n";
    if (!preg_match('!^([^?]+)\?(service=WFS&?|version=.\..\..&?|request=GetCapabilities&?)*$!i', $urlWfs, $matches))
      throw new Exception("Impossible d'interpréter l'URL du service WFS : $urlWfs");
    //print_r($matches);
    $urlWfs = $matches[1];
    //echo "urlWfs=$urlWfs<br>\n";
    $params = ['urlWfs'=> $urlWfs];
    $wfsServer = new WfsServer($params);
    $featureTypeList = $wfsServer->featureTypeList($dataset['identifier'][0]['code']);
    // Si aucun featureType n'est trouvé alors le filtre est supprimé
    if (!$featureTypeList)
      $featureTypeList = $wfsServer->featureTypeList();
    //echo '<pre>$featureTypeList = '; print_r($featureTypeList); echo "</pre>\n";
    $dataset['yamlClass'] = 'VectorDataset';
    $dataset['urlWfs'] = $urlWfs;
    foreach ($featureTypeList as $typename => $featureType) {
      $dataset['layers'][$typename] = [
        'title'=> $featureType['Title'],
        'typename'=> $typename,
      ];
    }
    //die("FIN ligne ".__LINE__."\n");
    return new VectorDataset($dataset);
  }
};
