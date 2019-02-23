<?php
/*PhpDoc:
name: strucdata.inc.php
title: strucdata.inc.php - sous-classe de documents pour des données structurées selon un schema
functions:
doc: <a href='/yamldoc/?action=version&name=strucdata.inc.php'>doc intégrée en Php</a>
*/
{ //doc 
$phpDocs['strucdata.inc.php']['file'] = <<<'EOT'
name: strucdata.inc.php
title: strucdata.inc.php - sous-classe de documents pour des données structurées selon un schema
doc: |  
journal:
  18/2/2019:
    - création
EOT;
}

use Symfony\Component\Yaml\Yaml;

{ // doc
$phpDocs['strucdata.inc.php']['classes']['StrucData'] = <<<'EOT'
title: données structurées selon un schema
doc: |
Document contenant son schéma dans le champ schéma
EOT;
}

class StrucData extends YamlDoc {
  protected $_c; // contient les champs
  
  // crée un nouveau doc, $yaml est le contenu Yaml externe issu de l'analyseur Yaml
  // $yaml est généralement un array mais peut aussi être du texte
  function __construct($yaml, string $docid) {
    $this->_id = $docid;
    $this->_c = $yaml;
  }
  
  // lit les champs
  function __get(string $name) { return isset($this->_c[$name]) ? $this->_c[$name] : null; }

  // affiche le sous-élément de l'élément défini par $ypath
  function show(string $ypath=''): void {
    $docid = $this->_id;
    echo "StrucData::show($docid, $ypath)<br>\n";
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
  function extract(string $ypath) { return YamlDoc::sextract($this->_c, $ypath); }
  
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
    $docuri = $this->_id;
    if (!$ypath || ($ypath=='/')) {
      return array_merge(['_id'=> $this->_id], $this->_c);
    }
    elseif ($ypath == '/api') {
      return self::api();
    }
    else {
      $fragment = $this->extract($ypath);
      $fragment = self::replaceYDEltByArray($fragment);
      return $fragment;
    }
  }
  
  function checkSchemaConformity(string $ypath): void {
    echo "StrucData::checkSchemaConformity(ypath=$ypath)<br>\n";
    if (!$this->schema) {
      echo "Erreur: schema absent<br>\n";
      return;
    }
    $metaschema = new JsonSchema('http://json-schema.org/draft-07/schema#');
    $metaschema->check($this->schema, [
      'showWarnings'=> "ok schéma conforme au méta-schéma<br>\n",
      'showErrors'=> "<b>KO schéma NON conforme au méta-schéma</b><br>\n",
    ]);

    $schema = new JsonSchema($this->schema);
    $schema->check($this->_c, [
      'showWarnings'=> "ok doc conforme au schéma du document<br>\n",
      'showErrors'=> "<b>KO doc NON conforme au schéma du document</b><br>\n",
    ]);
  }
};
