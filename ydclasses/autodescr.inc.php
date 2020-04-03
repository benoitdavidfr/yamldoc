<?php
/*PhpDoc:
name: autodescr.inc.php
title: autodescr.inc.php - sous-classe de documents pour des données structurées selon un schema
functions:
doc: <a href='/yamldoc/?action=version&name=autodescr.inc.php'>doc intégrée en Php</a>
*/
{ // file's doc 
$phpDocs['autodescr.inc.php']['file'] = <<<'EOT'
name: autodescr.inc.php
title: autodescr.inc.php - document autodécrit contenant un registre hiérarchique d'objets JSON-LD
doc: |
  Testé sur:
    - http://localhost/yamldoc/id.php/organizations ou http://id.georef.eu/organizations
    - http://127.0.0.1/yamldoc/id.php/contactspro ou http://bdavid.alwaysdata.net/yamldoc/id.php/contactspro
    
  Problèmes rencontrés:
    - http://localhost/yamldoc/id.php/organizations/fr.gouv/CGDD renvoie bien du JSON mais pas du JSON-LD
    - http://localhost/yamldoc/id.php/organizations/fr.gouv/CGDD/Orléans est un IRI incorrect
    - comment structurer http://localhost/yamldoc/id.php/organizations en JSON-LD ?
      - quel est son type ?
      - quelle définition donner à contents ?
    - un document AutoDescribed doit-il obligatoirement comporter un champ contents ?
  
journal:
  31/3/2020:
    - ajout possibilité que le schéma soit défini par une URL renvoyant un JSON
  14-15/3/2020:
    - restructuration du champ ydADscrBhv et de son exploitation
    - J'obtiens bien du HTML et du JSON objet par objet mais le JSON n'est pas du JSON-LD
  20/2/2020:
    - modification de la classe pour gérer le registre des organisations
      (http://localhost/yamldoc/id.php/organizations ou http://id.georef.eu/organizations)
    - ajout de la possibilité de paramétrer le comportement de la classe
    - manque la sortie en JSON-LD
  23/2/2019:
    - changement de nom
    - utilisation du champ $schema
    - détection dans new_doc
  18/2/2019:
    - création
EOT;
}

use Symfony\Component\Yaml\Yaml;

{ // class's doc
$phpDocs['autodescr.inc.php']['classes']['AutoDescribed'] = <<<'EOT'
title: document autodécrit par un schema et avec un comportement paramétré 
doc: |
  Document auto-décrit par un schéma JSON défini dans le champ $schema.
  Le champ ydADscrBhv permet de paramétrer:
    - l'enregistrement du document en pser
    - l'extract/extractByUri
  Conçu pour gérer un registre hiérarchique d'objets JSON-LD
EOT;
}

class AutoDescribed extends YamlDoc {
  protected $_c; // contient les champs
  
  // crée un nouveau doc, $yaml est le contenu Yaml externe issu de l'analyseur Yaml
  // $yaml est généralement un array mais peut aussi être du texte
  function __construct($yaml, string $docid) {
    $this->_id = $docid;
    $this->_c = $yaml;
  }
  
  // lit les champs
  function __get(string $name) { return isset($this->_c[$name]) ? $this->_c[$name] : null; }

  // un .pser est généré ssi le champ ydAutoDescribedBehaviour/writePserReally du document est défini
  public function writePser(): void {
    if ($this->ydAutoDescribedBehaviour && isset($this->ydAutoDescribedBehaviour['writePserReally'])) {
      $this->writePserReally();
    }
  }

  // affiche le sous-élément de l'élément défini par $ypath
  function show(string $ypath=''): void {
    $docid = $this->_id;
    //echo "AutoDescribed::show(docid='$docid', ypath='$ypath')<br>\n";
    if (!$ypath || ($ypath=='/')) {
      $doc = $this->_c;
      if (is_array($doc['$schema']))
        $doc['$schema'] = $this->path('/$schema');
      if ($this->ydADscrBhv)
        $doc['ydADscrBhv'] = $this->path('/ydADscrBhv');
      unset($doc['eof']);
      if ($this->contents) {
        $doc['contents'] = [];
        foreach ($this->contents as $skey => $sitem) {
          $name = $this->buildName(
              $sitem,
              $this->ydADscrBhv['firstLevelType'],
              $skey);
          $doc['contents'][$name] = $this->path("$ypath/$skey");
        }
      }
      //echo "<pre>doc="; print_r($doc); echo "</pre>\n";
      showDoc($docid, $doc);
    }
    else
      showDoc($docid, $this->extract($ypath));
    //echo "<pre>"; print_r($this->_c); echo "</pre>\n";
  }
  
  // décapsule l'objet et retourne son contenu sous la forme d'un array
  // ce décapsulage ne s'effectue qu'à un seul niveau
  // Permet de maitriser l'ordre des champs
  function asArray() { return $this->_c; }

  // renvoie l'URL pour ypath
  private function path(string $ypath) {
    //echo "<pre>_SERVER="; print_r($_SERVER); echo "</pre>\n";
    if (isset($_GET['doc']))
      return "http://$_SERVER[SERVER_NAME]".dirname($_SERVER['SCRIPT_NAME'])."/?doc=$_GET[doc]&ypath=$ypath";
    else
      return "http://$_SERVER[SERVER_NAME]$_SERVER[SCRIPT_NAME]/".$this->_id.$ypath;
  }
  
  // construit l'étiquette à afficher de l'objet en fonction de l'info de ydADscrBhv/buildName
  // $skey est la clé qui référencait l'objet
  private function buildName(array $item, string $objectType, string $skey): string {
    static $buildName = null;
    
    if (!$buildName) {
      $buildName = $this->ydADscrBhv['buildName'];
      //echo "<pre>buildName="; print_r($buildName); echo "</pre>\n";
    }
    
    if (isset($buildName[$objectType]))
      return eval($buildName[$objectType]);
    else
      return "buildName() non défini pour $objectType";
  }
  
  // extrait le fragment du document défini par $ypath
  // Renvoie un array ou un objet qui sera ensuite transformé par YamlDoc::replaceYDEltByArray()
  // Utilisé par YamlDoc::yaml() et YamlDoc::json()
  // Evite de construire une structure intermédiaire volumineuse avec asArray()
  // Si le champ ydADscrBhv/extractProperties est défini alors l'utilise pour descendre dans la hiérarchie
  function extract(string $ypath) {
    //echo "Appel de AutoDescribed::extract($ypath)<br>\n";
    
    if ($this->ydADscrBhv && isset($this->ydADscrBhv['extractProperties'])) {
      $extractProperties = $this->ydADscrBhv['extractProperties'];
      //echo "<pre>extractProperties="; var_dump($extractProperties); echo "</pre>\n";
      $keys = explode('/', $ypath);
      array_shift($keys);
      //echo "<pre>keys="; var_dump($keys); echo "</pre>\n";
      $parent = null;
      foreach ($keys as $ikey => $key) {
        if ($ikey == 0) {
          if (isset($this->contents[$key])) { // je traverse contents
            $item = $this->contents[$key];
            $type = $this->ydADscrBhv['firstLevelType'];
            //echo "type=$type<br>\n";
          }
          elseif ($this->$key) { // sinon je teste si key est une propriété
            $item = $this->$key;
            $type = null;
          }
          else
            return null;
        }
        else {
          $done = false;
          // j'essaie de traverser une des extractProperties correspondant au type courant
          if (isset($extractProperties[$type])) {
            foreach ($extractProperties[$type] as $extractPropertyKey => $extractPropertyValue) {
              if (isset($item[$extractPropertyKey][$key])) {
                //echo "extractProperty $extractPropertyKey et clé $key traversée<br>\n";
                $item = $item[$extractPropertyKey][$key];
                $type = $extractPropertyValue['objectType'];
                $parent = [
                  'keys' => implode('/', array_slice($keys, 0, $ikey)), // implode des clés du parent
                  'inverse' => $extractPropertyValue['inverse'] ?? null,
                ];
                $done = true;
                break;
              }
            }
          }
          if (!$done) { // aucune extractProperties traversée
            if (isset($item[$key])) {
              //echo "aucune extractProperty traversée mais clé $key ok<br>\n";
              $item = $item[$key];
              $type = null;
              $parent = null;
            }
            else {
              //echo "aucune extractProperty traversée et aucune clé pour $key<br>\n";
              return null;
            }
          }
        }
        //echo "<pre>item="; print_r($item); echo "</pre>\n";
      }
      //echo "parent = "; print_r($parent); echo "<br>\n";
      //echo "<pre>SERVER="; print_r($_SERVER); echo "</pre>\n";
      if ($parent && $parent['inverse']) {
        $item = array_merge([$parent['inverse'] => $this->path("/$parent[keys]")], $item);
      }
      //echo "type=$type<br>\n";
      if ($type) { // Si j'ai identifié le type du résultat dans l'extract
        $item = array_merge(['@type' => $type], $item);
      }
      if ($type && isset($extractProperties[$type])) {
        foreach ($extractProperties[$type] as $extractPropertyKey => $extractPropertyValue) {
          if (isset($item[$extractPropertyKey])) {
            $newitem = [];
            foreach ($item[$extractPropertyKey] as $skey => $sitem) {
              $name = $this->buildName(
                  $item[$extractPropertyKey][$skey],
                  $extractPropertyValue['objectType'],
                  $skey);
              $newitem[$name] = $this->path("$ypath/$skey");
            }
            $item[$extractPropertyKey] = $newitem;
          }
        }
      }
      return $item;
    }
    else
      return YamlDoc::sextract($this->_c, $ypath);
  }
  
  static function api(): array {
    return [
      'class'=> get_class(), 
      'title'=> "description de l'API de la classe ".get_class(),
      'abstract'=> "documents pour l'affichage d'une carte Leaflet",
      'api'=> [
        '/'=> "retourne le contenu du document ".get_class(),
        '/api'=> "retourne les points d'accès de ".get_class(),
      ]
    ];
  }

  // extrait le fragment défini par $ypath, utilisé pour générer un retour à partir d'un URI
  function extractByUri(string $ypath) {
    //echo "AutoDescribed::extractByUri(ypath='$ypath')<br>\n";
    if (!$ypath || ($ypath=='/')) {
      $id = "http://$_SERVER[HTTP_HOST]$_SERVER[SCRIPT_NAME]/".$this->_id.($ypath=='/' ? '' : $ypath);
      $doc = $this->_c;
      $doc['$schema'] = $this->path('/$schema');
      if ($this->ydADscrBhv)
        $doc['ydADscrBhv'] = $this->path('/ydADscrBhv');
      unset($doc['eof']);
      if ($this->contents) {
        $doc['contents'] = [];
        foreach ($this->contents as $skey => $sitem) {
          $name = $this->buildName(
              $sitem,
              $this->ydADscrBhv['firstLevelType'],
              $skey);
          $doc['contents'][$name] = $this->path("/$skey");
        }
      }
      //echo "<pre>doc="; print_r($doc); echo "</pre>\n";
      return array_merge(['@id'=> $id], $doc);
    }
    elseif ($ypath == '/api') {
      return self::api();
    }
    else {
      $id = "http://$_SERVER[HTTP_HOST]$_SERVER[SCRIPT_NAME]/".$this->_id.($ypath=='/' ? '' : $ypath);
      $fragment = $this->extract($ypath);
      if (!$fragment) {
        return null;
      }
      elseif (isset($fragment['@type'])) {
        return array_merge(
          [
            '@context'=> 'http://schema.org/',
            '@type'=> $fragment['@type'],
            '@id'=> $id
          ],
          $fragment
        );
      }
      else {
        return array_merge(
          [
            '@id'=> $id
          ],
          $fragment
        );
      }
    }
  }
  
  function checkSchemaConformity(string $ypath): void {
    echo "AutoDescribed::checkSchemaConformity(ypath=$ypath)<br>\n";
    $schema = $this->_c['$schema'] ?? null;
    if (!$schema) {
      echo "Erreur: schema absent<br>\n";
      return;
    }
    if (is_string($schema)) {
      //echo "AutoDescribed::checkSchemaConformity: schema string $schema<br>\n";
      if (preg_match('!^http://(id|docs)\.georef\.eu/!', $schema)) {
        $schema = getFragmentFromUri($schema);
      }
      else {
        if (($schcontents = @file_get_contents($schema)) === FALSE) {
          echo "Erreur de lecture de $schema<br>\n";
          return;
        }
        if (($schcontents = json_decode($schcontents, true)) === NULL) {
          echo "Erreur de décodage JSON de $schema<br>\n";
          return;
        }
        $schema = $schcontents;
      }
    }
    $metaschema = new JsonSchema('http://json-schema.org/draft-07/schema#');
    $metaschema->check($schema, [
      'showWarnings'=> "ok schéma conforme au méta-schéma<br>\n",
      'showErrors'=> "<b>KO schéma NON conforme au méta-schéma</b><br>\n",
    ]);

    $schema = new JsonSchema($schema);
    $schema->check($this->_c, [
      'showWarnings'=> "ok doc conforme au schéma du document<br>\n",
      'showErrors'=> "<b>KO doc NON conforme au schéma du document</b><br>\n",
    ]);
  }
};
