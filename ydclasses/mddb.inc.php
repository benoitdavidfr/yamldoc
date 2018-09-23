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
require_once __DIR__.'/../search.inc.php';

class MetadataDb extends YData {
  static $log = __DIR__.'/mddb.log.yaml'; // nom du fichier de log ou '' pour pas de log
  protected $_c; // contient les champs
  
  // crée un nouveau doc, $yaml est le contenu Yaml externe issu de l'analyseur Yaml
  // $yaml est généralement un array mais peut aussi être du texte
  function __construct($yaml, string $docid) {
    $this->_c = $yaml;
    $this->_id = $docid;
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
      foreach($this->searchOnSubject($_GET['subject'])['results'] as $id => $md) {
        echo "<li><a href='?doc=$docid&amp;ypath=/items/$id'>$md[title]</a>\n";
      }
      echo "</ul>\n";
      echo "<pre>"; print_r($this->searchOnSubject($_GET['subject']));
      return;
    }
    elseif (preg_match('!^/items/([^/]+)$!', $ypath, $matches)) {
      $mdid = $matches[1];
      foreach(['data','services','maps','nonGeographicDataset','others'] as $table) {
        $hasFormats = null;
        if ($md = parent::extractByUri("/tables/$table/data/$mdid")) {
          if (isset($md['hasFormat'])) {
            $hasFormats = $md['hasFormat'];
            unset($md['hasFormat']);
          }
          //parent::show($docid, "/tables/$table/data/$mdid");
          showDoc($docid, $md);
          if ($hasFormats) {
            showDoc($docid, $hasFormats);
            echo "<h3>Liens</h3><ul>\n";
            foreach ($hasFormats as $hasFormat) {
              if (isset($hasFormat['protocol']) && ($hasFormat['protocol'] == 'http:link')) {
                echo "<li><a href='$hasFormat[url]'>Téléchargement</a></li>\n";
              }
              elseif (isset($hasFormat['protocol']) && ($hasFormat['protocol'] == 'OGC:WMS')) {
                echo "<li><a href='$hasFormat[url]'>Lien WMS</a></li>\n";
              }
              elseif (isset($hasFormat['protocol']) && ($hasFormat['protocol'] == 'OGC:WFS')) {
                //echo "<li><a href='$hasFormat[url]'>Lien WFS</a></li>\n";
                $fileId = $md['fileIdentifier'];
                //echo "fileId=$fileId<br>\n";
                echo "<li><a href='id.php/geocats/geoide/items/$fileId/directDwnld/map/display'>",
                  "Affichage de la carte des objets</a></li>\n";
              }
            }
            echo "</url>\n";
          }
        }
      }
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
  
  static function api(): array {
    return [
      'class'=> get_class(), 
      'title'=> "description de l'API de la classe ".get_class(),
      'abstract'=> "exploitation de la base des MD issue d'une moisson d'un serveur CSW",
      'api'=> [
        '/'=> "retourne le nbre de MD par catégorie",
        '/api'=> "retourne les points d'accès de ".get_class()  ,
        '/(data|services|maps|nonGeographicDataset|others)'=> "retourne les fiches de MD de la catégorie avec leur titre",
        '/fds'=> "retourne le catalogue comme FeatureDataset",
        '/fds/{params}'=> FeatureDataset::api()['api'],
        '/buildHasFormat'=> "déduit pour chaque MDD un champ hasFormat et réenregistre la BDMD",
        '/proj/{fields}'=> "retourne les champs {fields} des MDD",
        '/wfs'=> "test",
        '/wfs2'=> "test",
        '/wfsGeoide'=> "test",
        '/search?text={text}'=> "recherche plein texte le paramètre GET text,"
          ." retourne un array ['search'=> paramètre de recherche, 'nbreOfResults'=> nbreOfResults, 'results'=> [['title'=> title]]] ",
        '/search?subject={subject}'=> "recherche le mot-clé défini par le paramètre GET subject,"
          ." retourne un array ['search'=> paramètre de recherche, 'nbreOfResults'=> nbreOfResults, 'results'=> [['title'=> title]]] ",
        '/items'=> "retourne toutes les fiches de MD",
        '/items/{id}'=> "retourne la fiche de MD identifiée par {id}",
        '/items/{id}/directDwnld'=> "retourne le contenu de la SD (sous la forme d'un FeatureDataset) correspondant à la MDD définie par {id}",
        '/items/{id}/directDwnld/{params}'=> FeatureDataset::api()['api'],
      ]
    ];
  }

  // extrait le fragment défini par $ypath, utilisé pour générer un retour à partir d'un URI
  function extractByUri(string $ypath) {
    $docuri = $this->_id;
    //echo "MetadataDb::extractByUri($docuri, $ypath)<br>\n";
    if (!$ypath || ($ypath=='/')) {
      $result = [
        '_id'=> $this->_id,
        'title'=> $this->title,
        'yamlClass'=> $this->yamlClass,
      ];
      foreach(['data','services','maps','nonGeographicDataset','others'] as $table) {
        if ($this->tables[$table]['data']) {
          $result[$table] = [
            'title'=> $this->tables[$table]['title'],
            'nbre'=> count($this->tables[$table]['data']),
          ];
        }
      }
      return $result;
    }
    elseif ($ypath == '/api') {
      return self::api();
    }
    elseif (preg_match('!^/(data|services|maps|nonGeographicDataset|others)$!', $ypath, $matches)) {
      $table = $matches[1];
      $result = [
        '_id'=> ($this->_id).$ypath,
        'title'=> $this->tables[$table]['title'],
        'nbre'=> count($this->tables[$table]['data']),
        'data'=> [],
      ];
      if ($this->tables[$table]['data']) {
        foreach ($this->tables[$table]['data'] as $id => $file)
          $result['data'][$id]['title'] = $file['title'];
      }
      return $result;
    }
    elseif ($ypath == '/vds') {
      return $this->vds()->asArray();
    }
    elseif (preg_match('!^/vds/(.*)$!', $ypath, $matches)) {
      //echo "MetadataDb::extractByUri($docuri, $ypath)<br>\n";
      $params = array_merge($_GET, $_POST);
      if (!isset($params['bbox'])) {
        $vds = $this->vds();
        return $vds->extractByUri("/$matches[1]");
      }
      else {
        header('Content-type: application/json');
        $bbox = explode(',', $params['bbox']);
        $no = 0;
        echo "{\"type\":\"FeatureCollection\",\"features\":[\n";
        foreach ($this->tables['data']['data'] as $id => $mdd) {
          $pt = null;
          if (isset($mdd['spatial'])) {
            foreach ($mdd['spatial'] as $i => $sp) {
              if ($sp['westlimit'] && $sp['eastlimit'] && $sp['southlimit'] && $sp['northlimit']) {
                $pt = [($sp['westlimit'] + $sp['eastlimit'])/2, ($sp['southlimit'] + $sp['northlimit'])/2];
                break;
              }
            }
          }
          if ($pt && ($pt[0] >= $bbox[0]) && ($pt[1] >= $bbox[1]) && ($pt[0] <= $bbox[2]) && ($pt[1] <= $bbox[3])) {
            echo $no ? ",\n":'',
              "  { \"type\":\"Feature\",\n",
              "    \"properties\":{\n",
              "      \"title\": \"",str_replace(['"',"\t"], ['\"','\t'], $mdd['title']),"\"\n",
              "    },\n",
              "    \"geometry\":{\"type\":\"Point\", \"coordinates\": [$pt[0], $pt[1]]}\n",
              "  }";
            $no++;
          }
        }
        echo " ]}\n";
        die();
      }
    }
    elseif ($ypath == '/buildHasFormat') {
      // echo "MetadataDatabase::extractByUri($docuri, $ypath)<br>\n";
      $this->buildHasFormat($docuri);
      $this->writePser();
      return "buildHasFormat ok, document $docuri/db enregistré en pser\n";
    }
    // projection sur certains champs
    elseif (preg_match('!^/proj/(.*)$!', $ypath, $matches)) {
      //echo "MetadataDb::extractByUri($ypath)<br>\n";
      $fields = explode(',', $matches[1]);
      //print_r($fields);
      if (!is_array($fields))
        $fields = [$fields];
      return $this->proj($fields);
    }
    // retourne les MDD ayant une relation avec protocole WFS
    elseif (preg_match('!^/wfs$!', $ypath, $matches)) {
      $result = [];
      foreach (parent::extractByUri('/data')['data'] as $id => $metadata) {
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
      foreach (parent::extractByUri('/data')['data'] as $id => $metadata) {
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
        return $this->searchOnSubject($_GET['subject']);
      else
        return "search incompris";
    }
    // accès à une MD par son id
    elseif ($ypath == '/items') {
      //echo "MetadataDb::extractByUri($docuri, $ypath)<br>\n";
      $result = [
        '_id'=> ($this->_id).$ypath,
        'title'=> $this->title,
      ];
      foreach(['data','services','maps','nonGeographicDataset','others'] as $table) {
        if ($this->tables[$table]['data']) {
          foreach ($this->tables[$table]['data'] as $id => $file)
            $result[$table][$id] = $file;
        }
      }
      return $result;
    }
    elseif (preg_match('!^/items/([^/]+)$!', $ypath, $matches)) {
      //echo "MetadataDb::extractByUri($docuri, $ypath)<br>\n";
      $mdid = $matches[1];
      foreach(['data','services','maps','nonGeographicDataset','others'] as $table) {
        if (isset($this->tables[$table]['data'][$mdid]))
          return $this->tables[$table]['data'][$mdid];
      }
      return null;
    }
    // renvoie le FeatureDataset correspondant au WFS
    elseif (preg_match('!^/items/([^/]+)/directDwnld$!', $ypath, $matches)) {
      //echo "MetadataDb::extractByUri($docuri, $ypath)<br>\n";
      return $this->directDwnld($docuri, $matches[1])->asArray();
    }
    elseif (preg_match('!^/items/([^/]+)/directDwnld/(.*)$!', $ypath, $matches)) {
      //echo "MetadataDb::extractByUri($docuri, $ypath)<br>\n";
      $vds = $this->directDwnld($docuri, $matches[1]);
      return $vds->extractByUri("/$matches[2]");
    }
    else
      return null;
  }
  
  // création d'un FeatureDataset
  function vds() {
    $dataset = [
      'yamlClass'=> 'FeatureDataset',
      'wfsUrl'=> 'xx',
      'layers'=> [
        'data'=> ['title'=> "MD de données", 'typename'=> 'data'],
      ],
    ];
    $dbid = $this->_id;
    return new FeatureDataset($dataset, "$dbid/vds");
  }
  
  // fabrique la liste des mots-clés organisée par vocabulaire contrôlé
  function listSubjects(): SubjectList {
    $docid = $this->_id;
    $geocatid = dirname($docid);
    $subjects = new SubjectList([], "$geocatid/subjects");
    foreach ($this->tables['data']['data'] as $id => $metadata) {
      //echo "subjects = "; print_r($metadata['subject']); echo "<br>\n";
      //echo "metadata = "; print_r($metadata); echo "<br>\n";
      $mainMdLanguage = null;
      if (!isset($metadata['mdLanguage']))
        $mainMdLanguage = 'fre';
      elseif (is_string($metadata['mdLanguage']))
        $mainMdLanguage = $metadata['mdLanguage'];
      elseif (is_array($metadata['mdLanguage'])) {
        foreach ($metadata['mdLanguage'] as $mdLang) {
          if ($mdLang) {
            $mainMdLanguage = $mdLang;
            break;
          }
        }
      }
      if (!$mainMdLanguage)
        $mainMdLanguage = 'fre';
      if (isset($metadata['subject'])) {
        foreach ($metadata['subject'] as $subject) {
          //echo "subject = "; print_r($subject); echo "<br>\nmainMdLanguage=$mainMdLanguage<br>\n";
          $subjects->add($subject, $mainMdLanguage);
        }
      }
    }
    $subjects->sortVocs();
    return $subjects;
  }
  
  function proj(array $fields) {
    $result = [];
    foreach ($this->tables['data']['data'] as $id => $metadata) {
      $proj = [];
      foreach ($fields as $field) {
        if ($field=='_id')
          $proj[$field] = $id;
        elseif (isset($metadata[$field]))
          $proj[$field] = $metadata[$field];
      }
      if ($proj)
        $result[$id] = $proj;
    }
    return $result;
  }
  
  function searchOnSubject(string $searchedSubject) {
    //echo "MetadataDb::searchOnSubject($docuri, $searchedSubject)<br>\n";
    $results = [];
    foreach ($this->tables['data']['data'] as $id => $metadata) {
//      print_r($metadata['subject']); echo "<br>\n";
      if (isset($metadata['subject'])) {
        foreach ($metadata['subject'] as $subject) {
          if ($subject['value'] == $searchedSubject) {
            //echo "<b>$metadata[title]</b><br>\n";
            $results[$id] = ['title'=> $metadata['title']];
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
  function buildHasFormat(): void {
    if ($this->_id == 'geocats/geoide/db') {
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
  
  // renvoie la FeatureDataset correspondant à la fiche de MDD $dsid ou génère une exception si cela n'est pas possible
  function directDwnld(string $dbid, string $dsid): FeatureDataset {
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
    $geocatid = new_doc(dirname($dbid));
    $wfsOptions = $geocatid->wfsOptions($wfsUrl);
    $wfsParams = ['wfsUrl'=> $wfsUrl, 'wfsOptions'=> $wfsOptions];
    $wfsServer = WfsServer::new_WfsServer($wfsParams, "$dbid/items/$dsid/wfs");
    $featureTypeList = $wfsServer->featureTypeList($dataset['identifier'][0]['code']);
    // Si aucun featureType n'est trouvé alors le filtre est supprimé
    if (!$featureTypeList)
      $featureTypeList = $wfsServer->featureTypeList();
    //echo '<pre>$featureTypeList = '; print_r($featureTypeList); echo "</pre>\n";
    $dataset = ['yamlClass'=> 'FeatureDataset', 'wfsUrl'=> $wfsUrl, 'wfsOptions'=> $wfsOptions];
    foreach ($featureTypeList as $typename => $featureType) {
      $dataset['layers'][$typename] = [ 'title'=> $featureType['Title'], 'typename'=> $typename ];
    }
    //die("FIN ligne ".__LINE__."\n");
    return new FeatureDataset($dataset, "$dbid/items/$dsid/directDwnld");
  }
};
