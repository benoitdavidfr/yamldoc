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
  Une BD de MD est une base YData composé des 5 tables suivantes :
  
  - une table data des MD de données
  - une table services des MD de service
  - une table maps des MD de cartes
  - une table nonGeographicDataset des MD de SD non géographiques (type=nonGeographicDataset)
  - une table others des autres MD
journal:
  26/8/2018:
    - création
EOT;
}
//require_once __DIR__.'/yamldoc.inc.php';
//require_once __DIR__.'/search.inc.php';
//require_once __DIR__.'/isometadata.inc.php';

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
    elseif ($ypath == '/buildHasFormat') {
      // echo "MetadataDatabase::extractByUri($docuri, $ypath)<br>\n";
      $this->buildHasFormat($docuri);
      $this->writePser($docuri);
      return "buildHasFormat ok, document $docuri/db enregistré en pser\n";
    }
    // projection sur certains champs
    elseif (preg_match('!^/proj/(.*)$!', $ypath, $matches)) {
      $fields = explode(',', $matches[1]);
      return $this->proj($docuri, $fields);
    }
    // retourne les MDD ayant une relation avec protocole WFS
    elseif (preg_match('!^/wfs$!', $ypath, $matches)) {
      $result = [];
      foreach (parent::extractByUri($docuri, '/data')['data'] as $id => $metadata) {
        if (isset($metadata['relation'])) {
          foreach ($metadata['relation'] as $relation) {
            //print_r($relation);
            if (isset($relation['protocol'])
                && is_string($relation['protocol'])
                && preg_match('!WFS!', $relation['protocol'])) {
              $result[] = [
                'id'=> $id,
                'title'=> $metadata['title'],
                'relation'=> $metadata['relation'],
              ];
              break;
            }
          }
        }
      }
      return $result;
    }
    // retourne les relation ayant un protocole WFS
    elseif (preg_match('!^/wfs2$!', $ypath, $matches)) {
      $result = [];
      foreach (parent::extractByUri($docuri, '/data')['data'] as $id => $metadata) {
        if (isset($metadata['relation'])) {
          foreach ($metadata['relation'] as $relation) {
            //print_r($relation);
            if (isset($relation['protocol'])
                && is_string($relation['protocol'])
                && preg_match('!WFS!', $relation['protocol'])) {
              $result[] = $relation;
            }
          }
        }
      }
      $wfsServers = []; // url => ['protocol'=>protocol, 'names'=> [name]
      foreach ($result as $relation) {
        if (!isset($relation['url']) || !$relation['url']) continue;
        if (!isset($wfsServers[$relation['url']]))
          $wfsServers[$relation['url']] = ['protocol'=> $relation['protocol'], 'names'=> [$relation['name']]];
        else
          $wfsServers[$relation['url']]['names'][] = $relation['name'];
      }
      ksort($wfsServers);
      return [[
        'title'=> "liste des serveurs WFS recensés dans les MDD Sextant",
        'wfsservers'=> $wfsServers,
      ]];
    }
    // recherche des serveurs WFS dans GeoIDE: AUCUN
    elseif (preg_match('!^/wfsGeoide$!', $ypath, $matches)) {
      //echo "MetadataDb::extractByUri($docuri, $ypath)<br>\n";
      $result = [];
      if (0) { // serviceType<>view -> Aucun 
        foreach ($this->tables['services']['data'] as $id => $srvmd) {
          if (1 && ($srvmd['serviceType']<>'view'))
            $result[$id] = $srvmd;
        }
      }
      elseif (0) { // afficher les relation.protocol: OGC:WFS 
        foreach ($this->tables['data']['data'] as $id => $datamd) {
          if (isset($datamd['relation'])) {
            foreach ($datamd['relation'] as $relation) {
              if (isset($relation['protocol'])) {
                if (!in_array($relation['protocol'], $result)) {
                  $result[] = $relation['protocol'];
                }
              }
            }
          }
        }
      }
      elseif (0) { // URL de service WFS, aucun geo-ide sur internet 
        foreach ($this->tables['data']['data'] as $id => $datamd) {
          if (isset($datamd['relation'])) {
            foreach ($datamd['relation'] as $relation) {
              if (isset($relation['protocol']) && ($relation['protocol']=='OGC:WFS')) {
                $result[] = ['id'=> $id, 'url'=>$relation['url']];
              }
            }
          }
        }
      }
      // "http://data.geo-ide.application.i2/WFS/709/document_urbanisme?&REQUEST=describefeaturetype&typename=PPRM_arrondissement_montdidier_Zr"
      elseif (0) {
        foreach ($this->tables['data']['data'] as $id => $datamd) {
        }
      }
      return $result;
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
    // renvoie le VectorDataset correspondant au WFS
    elseif (preg_match('!^/items/([^/]+)/wfs$!', $ypath, $matches)) {
      return $this->wfs($docuri, $matches[1])->asArray();
    }
    elseif (preg_match('!^/items/([^/]+)/wfs/(.*)$!', $ypath, $matches)) {
      $vds = $this->wfs($docuri, $matches[1]);
      return $vds->extractByUri("$docuri/items/$matches[1]/wfs", "/$matches[2]");
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
      foreach ($fields as $field) {
        if ($field=='id')
          $eltproj[$field] = $id;
        elseif (isset($metadata[$field]))
          $eltproj[$field] = $metadata[$field];
      }
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
  
  /* part de la base de données et complète les fiches de MD de données avec un champ hasFormat de la forme:
    s'il existe des MD de service [[ 'serviceType'=> serviceType, 'serviceId'=> serviceId ]]
    sinon [ 'url'=> url, 'protocol'=> protocol ] / protocol in ('OGC:WMS', 'OGC:WFS', 'IETF:ATOM', 'http:link')
  */
  function buildHasFormat(string $docuri): void {
    if ($docuri == 'geocats/geoide/db') {
      $this->buildHasFormatForGeoide();
      return;
    }
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
            $hasFormat = [ 'serviceType'=> $serviceType, 'serviceId'=> $serviceId ];
            if (!isset($this->_c['tables']['data']['data'][$dataId]['hasFormat']))
              $this->_c['tables']['data']['data'][$dataId]['hasFormat'] = [$hasFormat];
            elseif (!in_array($hasFormat, $this->_c['tables']['data']['data'][$dataId]['hasFormat']))
              $this->_c['tables']['data']['data'][$dataId]['hasFormat'][] = $hasFormat;
          }
        }
      }
    }
    //die("buildHasFormat ok\n");
    return;
  }
  
  // buildHasFormat spécifique pour Geoide
  function buildHasFormatForGeoide(): void {
    foreach ($this->tables['data']['data'] as $id => $mdd) {
      if (isset($mdd['relation'])) {
        $hasFormat = [];
        foreach ($mdd['relation'] as $relation) {
          if (!isset($relation['url']))
            continue;
          if (preg_match('!^http://atom.geo-ide.developpement-durable.gouv.fr/atomArchive/GetResource!', $relation['url'])) {
            $hasFormat[] = [ 'url'=> $relation['url'], 'protocol'=> 'http:link' ];
          }
          if (preg_match('!^http://ogc.geo-ide.developpement-durable.gouv.fr/wxs!', $relation['url'])) {
            $hasFormat[] = [ 'url'=> $relation['url'], 'protocol'=> 'OGC:WMS' ];
            $hasFormat[] = [ 'url'=> $relation['url'], 'protocol'=> 'OGC:WFS' ];
          }
        }
        if ($hasFormat) {
          $this->_c['tables']['data']['data'][$id]['hasFormat'] = $hasFormat;
          echo "MDD $id modifiée<br>\n";
        }
      }
    }
  }
  
  // nettoyage de l'URL WFS qui contient service=WFS et request=GetCapabilities
  private static function cleanWfsUrl(string $wfsUrl): string {
    $wfsUrl = str_replace('&amp;', '&', $wfsUrl);
    //echo "wfsUrl=$wfsUrl<br>\n";
    if (!preg_match('!^([^?]+)\?(service=WFS&?|version=.\..\..&?|request=GetCapabilities&?)*$!i', $wfsUrl, $matches))
      throw new Exception("Impossible d'interpréter l'URL du service WFS : $wfsUrl");
    //print_r($matches);
    return ($matches[1]);
  }
  
  // renvoie la VectorDataset correspondant à la fiche de MDD $dsid ou génère une exception si cela n'est pas possible
  function wfs(string $docuri, string $dsid): VectorDataset {
    if (!isset($this->tables['data']['data'][$dsid]))
      throw new Exception("Erreur: MDD inexistante");
    $dataset = $this->tables['data']['data'][$dsid];
    //echo "<pre> dataset = "; print_r($dataset);
    if (!isset($dataset['hasFormat']))
      throw new Exception("Erreur: aucun service download");
    $wfsUrl = null;
    foreach ($dataset['hasFormat'] as $hasFormat) {
      if (isset($hasFormat['serviceType']) && ($hasFormat['serviceType']=='download')) {
        $service = $this->tables['services']['data'][$hasFormat['serviceId']];
        if (!isset($service['relation']))
          continue;
        foreach ($service['relation'] as $relation) {
          if (isset($relation['protocol']) && preg_match('!^OGC:WFS!', $relation['protocol'])) {
            $wfsUrl = self::cleanWfsUrl($relation['url']);
            break 2;
          }
        }
      }
      elseif (isset($hasFormat['protocol']) && ($hasFormat['protocol']=='OGC:WFS')) {
        $wfsUrl = $hasFormat['url'];
      }
    }
    if (!$wfsUrl)
      throw new Exception("Aucun service WFS");
    //echo "wfsUrl=$wfsUrl<br>\n";
    $geocat = new_doc(dirname($docuri));
    $wfsOptions = $geocat->wfsOptions($wfsUrl);
    $wfsParams = ['wfsUrl'=> $wfsUrl, 'wfsOptions'=> $wfsOptions];
    $wfsServer = WfsServer::new_WfsServer($wfsParams);
    $featureTypeList = $wfsServer->featureTypeList($dataset['identifier'][0]['code']);
    // Si aucun featureType n'est trouvé alors le filtre est supprimé
    if (!$featureTypeList)
      $featureTypeList = $wfsServer->featureTypeList();
    //echo '<pre>$featureTypeList = '; print_r($featureTypeList); echo "</pre>\n";
    $dataset = ['yamlClass'=> 'VectorDataset', 'wfsUrl'=> $wfsUrl, 'wfsOptions'=> $wfsOptions];
    foreach ($featureTypeList as $typename => $featureType) {
      $dataset['layers'][$typename] = [ 'title'=> $featureType['Title'], 'typename'=> $typename ];
    }
    //die("FIN ligne ".__LINE__."\n");
    return new VectorDataset($dataset);
  }
};
