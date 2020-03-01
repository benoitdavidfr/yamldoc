<?php
/*PhpDoc:
name: autodescr.inc.php
title: autodescr.inc.php - sous-classe de documents pour des données structurées selon un schema
functions:
doc: <a href='/yamldoc/?action=version&name=autodescr.inc.php'>doc intégrée en Php</a>
*/
{ //doc 
$phpDocs['autodescr.inc.php']['file'] = <<<'EOT'
name: autodescr.inc.php
title: autodescr.inc.php - documents autodécrits
doc: |
journal:
  20/2/2020:
    - modification de la classe pour gérer le registre des organisations
      (http://localhost/yamldoc/id.php/organizations)
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

{ // doc
$phpDocs['autodescr.inc.php']['classes']['AutoDescribed'] = <<<'EOT'
title: document autodécrit par un schema et avec un comportement paramétré 
doc: |
  Document auto-décrit par un schéma JSON défini dans le champ $schema.
  Le champ ydADscrBhv permet de paramétrer:
    - l'enregistrement du document en pser
    - l'extract/extractByUri
  Peut être utilisé pour gérer un registre hiérarchique
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
    //echo "AutoDescribed::show($docid, $ypath)<br>\n";
    if (!$ypath || ($ypath=='/'))
      showDoc($docid, $this->_c);
    else
      showDoc($docid, $this->extract($ypath));
    //echo "<pre>"; print_r($this->_c); echo "</pre>\n";
  }
  
  // décapsule l'objet et retourne son contenu sous la forme d'un array
  // ce décapsulage ne s'effectue qu'à un seul niveau
  // Permet de maitriser l'ordre des champs
  function asArray() { return $this->_c; }

  // extrait le fragment du document défini par $ypath
  // Renvoie un array ou un objet qui sera ensuite transformé par YamlDoc::replaceYDEltByArray()
  // Utilisé par YamlDoc::yaml() et YamlDoc::json()
  // Evite de construire une structure intermédiaire volumineuse avec asArray()
  // Si le champ ydADscrBhv/extract est défini alors utilise les 2 clés pour descendre dans la hiérarchie
  function extract(string $ypath) {
    //echo "Appel de AutoDescribed::extract($ypath)<br>\n";
    if ($this->ydADscrBhv && isset($this->ydADscrBhv['extract'])) {
      $extractFields = $this->ydADscrBhv['extract'];
      //echo "<pre>extractFields="; var_dump($extractFields); echo "</pre>\n";
      $mainKey = $extractFields [0];
      //echo "mainKey=$mainKey<br>\n";
      $secondKey = $extractFields[1];
      //echo "secondKey=$secondKey<br>\n";
      $keys = explode('/', $ypath);
      array_shift($keys);
      //echo "<pre>keys="; var_dump($keys); echo "</pre>\n";
      foreach ($keys as $ikey => $key) {
        //echo "Traverse key $key<br>\n";
        if ($ikey == 0) {
          if (isset($this->$mainKey[$key]))
            $item = $this->$mainKey[$key];
          elseif ($this->$key)
            $item = $this->$key;
          else
            return null;
        }
        elseif (isset($item[$secondKey][$key])) {
          $item = $item[$secondKey][$key];
        }
        elseif (isset($item[$key])) {
          $item = $item[$key];
        }
        else
          return null;
        //echo "<pre>item="; print_r($item); echo "</pre>\n";
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
    if (!$ypath || ($ypath=='/')) {
      $id = "http://$_SERVER[HTTP_HOST]$_SERVER[SCRIPT_NAME]/".$this->_id.($ypath=='/' ? '' : $ypath);
      return array_merge(['@id'=> $id], $this->_c);
    }
    elseif ($ypath == '/api') {
      return self::api();
    }
    else {
      $id = "http://$_SERVER[HTTP_HOST]$_SERVER[SCRIPT_NAME]/".$this->_id.($ypath=='/' ? '' : $ypath);
      $fragment = $this->extract($ypath);
      $fragment = self::replaceYDEltByArray($fragment);
      return array_merge(['@id'=> $id], $fragment);
    }
  }
  
  function checkSchemaConformity(string $ypath): void {
    echo "AutoDescribed::checkSchemaConformity(ypath=$ypath)<br>\n";
    if (!($schema = isset($this->_c['$schema']) ? $this->_c['$schema'] : null)) {
      echo "Erreur: schema absent<br>\n";
      return;
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
