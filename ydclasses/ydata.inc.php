<?php
/*PhpDoc:
name: ydata.inc.php
title: ydata.inc.php - sous-classe de documents pour la gestion des données
functions:
doc: <a href='/yamldoc/?action=version&name=ydata.inc.php'>doc intégrée en Php</a>
*/
{
$phpDocs['ydata.inc.php'] = <<<'EOT'
name: ydata.inc.php
title: ydata.inc.php - sous-classes YData et YDataTable pour la gestion des données
doc: |
  objectifs:
  
    - abandon du YamlSchema au profit de json-schema, spécification mieux définie des données et plus standard
    - réécriture à la suite de la restructuration des classes YamlDoc et BasicYamlDoc.
      La nouvelle classe hérite de YamlDoc et non de BasicYamlDoc.'
    - Simplification en se limitant à une seule clé que j'appelle _id comme dans MongoDB.
      Les données historisées seront traitées avec HistoData.'

  Un document YData doit définir à la racine un champ yamlClass avec la valeur YData.
  Il peut alors:
  
    - soit contenir une seule table stockée en Yaml dans le champ data
    - soit contenir une liste de tables stockée dans une structure Yaml
      tables:
        {nomtable}:
          title: titre de la table
          data: enregistrements contenus dans la table
  Une version serialisée du doc est enregistrée pour accélérer la lecture des gros documents.
  
  Implémente pour URI un ypath réduit /{table}/{tupleid}/... ou /{tupleid}/...
  
journal: |
  3/1/2019:
  - correction affichage
  - ajout test de conformité d'une table à son schéma
  29/7/2018:
  - mécanismes d'accès de base
  - manque projection, sélection
  - manque json-schema
EOT;
}
require_once __DIR__.'/../../schema/jsonschema.inc.php';

use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Yaml\Exception\ParseException;

class YData extends YamlDoc {
  protected $_c; // contient les champs
  
  // crée un nouveau doc, $yaml est le contenu Yaml externe issu de l'analyseur Yaml
  // $yaml est généralement un array mais peut aussi être du texte
  function __construct($yaml, string $docid) {
    $this->_c = [];
    $this->_id = $docid;
    foreach ($yaml as $prop => $value) {
      if ($prop == 'data')
        $this->_c['data'] = new YDataTable($value);
      elseif ($prop == 'tables') {
        $tables = $value;
        $this->_c['tables'] = [];
        foreach ($tables as $tabid => $table) {
          foreach ($table as $prop => $value) {
            if ($prop == 'data')
              $this->_c['tables'][$tabid]['data'] = new YDataTable($value);
            else
              $this->_c['tables'][$tabid][$prop] = $value;
          }
        }
      }
      else
        $this->_c[$prop] = $value;
    }
  }
  
  // lit un champ
  function __get(string $name) { return isset($this->_c[$name]) ? $this->_c[$name] : null; }

  // affiche le sous-élément de l'élément défini par $ypath
  function show(string $ypath=''): void {
    $docid = $this->_id;
    //echo "YData::show($docid, $ypath)<br>\n";
    if (!$ypath || ($ypath=='/'))
      showDoc($docid, $this->_c);
    else
      showDoc($docid, $this->extract($ypath));
    //echo "<pre>"; print_r($this->_c); echo "</pre>\n";
  }
  
  // fonction dump par défaut, dump le document et non le fragment
  function dump(string $ypath=''): void { var_dump($this->_c); }
  
  // décapsule l'objet et retourne son contenu sous la forme d'un array
  // ce décapsulage ne s'effectue qu'à un seul niveau
  // Permet de maitriser l'ordre des champs
  function asArray() { return $this->_c; }

  // extrait le fragment du document défini par $ypath
  // Renvoie un array ou un objet qui sera ensuite transformé par YamlDoc::replaceYDEltByArray()
  // Utilisé par YamlDoc::yaml() et YamlDoc::json()
  // Evite de construire une structure intermédiaire volumineuse avec asArray()
  function extract(string $ypath) {
    if (!$ypath || ($ypath=='/'))
      return $this;
    elseif (preg_match('!^/([^/]*)$!', $ypath, $matches))
      return $this->{$matches[1]};
    elseif (preg_match('!^/data(/.*)$!', $ypath, $matches))
      return $this->data->extract($matches[1]);
    elseif (preg_match('!^/tables$!', $ypath))
      return $this->tables;
    elseif (preg_match('!^/tables/([^/]*)$!', $ypath, $matches))
      return isset($this->tables[$matches[1]]) ? $this->tables[$matches[1]] : null;
    elseif (preg_match('!^/tables/([^/]*)/([^/]*)$!', $ypath, $matches))
      return isset($this->tables[$matches[1]][$matches[2]]) ? $this->tables[$matches[1]][$matches[2]] : null;
    elseif (preg_match('!^/tables/([^/]*)/data(/.*)$!', $ypath, $matches))
      return $this->tables[$matches[1]]['data']->extract($matches[2]);
    else
      return null;
  }
    
  // extrait le fragment défini par $ypath, utilisé pour générer un retour à partir d'un URI
  // implémnte un ypath réduit /{table}/{tupleid}/... ou /{tupleid}/...
  function extractByUri(string $ypath) {
    $docuri = $this->_id;
    //echo "YData::extractByUri($docuri, $ypath)<br>\n";
    $fragment = $this->extract($ypath);
    $fragment = self::replaceYDEltByArray($fragment);
    if ($fragment)
      return $fragment;
    //echo "fragment vide  test version ypath réduit\n"; // sinon test version ypath réduit
    // /{table}
    if ($this->tables && preg_match('!^/([^/]+)$!', $ypath, $matches) && isset($this->tables[$matches[1]])) {
      //echo "table $matches[1] ok\n";
      $table = $this->tables[$matches[1]];
      return self::replaceYDEltByArray($table);;
    }
    // /{table}/{tupleid}
    if ($this->tables && preg_match('!^/([^/]+)(/.*)$!', $ypath, $matches) && isset($this->tables[$matches[1]])) {
      //echo "table $matches[1] ok ypath $matches[2]\n";
      $table = $this->tables[$matches[1]];
      return $table['data']->extract($matches[2]);
    }
    // /{tupleid}
    if ($this->data)
      return $this->data->extract($ypath);
    return null;
  }
  
  // un .pser est généré automatiquement à chaque mise à jour du .yaml
  function writePser(): void { YamlDoc::writePserReally(); }
  
  function checkSchemaConformity(string $ypath): void {
    //echo "YData::checkSchemaConformity(ypath=$ypath)<br>\n";
    // si le path pointe directement dans les données, je remonte dans le document de la table
    if (substr($ypath, -5)=='/data')
      $ypath = substr($ypath, 0, strlen($ypath)-5);
    $subdoc = $this->extractByUri($ypath);
    //echo '<pre>',Yaml::dump($subdoc, 999),"</pre>\n";
    if (!isset($subdoc['jSchema']) || !isset($subdoc['data'])) {
      echo "Erreur: jSchema ou data absent du sous-document<br>\n";
      return;
    }
    $schema = new JsonSchema($subdoc['jSchema']);
    if (isset($subdoc['data'])) {
      if ($schema->check($subdoc['data'])) {
        $schema->showWarnings();
        echo "ok data conforme au schéma<br>\n";
      }
      else
        $schema->showErrors();
    }
  }
};

// contenu d'une table
// objet se retouvant à l'intérieur d'un doc
// est créé par YData 
class YDataTable implements YamlDocElement, IteratorAggregate {
  protected $attrs; // liste des attributs détectés dans la table
  protected $data; // contenu de data sous forme d'un array Php
  
  // prend en entrée le contenu de la table sous la forme d'un array [ _id => array ]
  function __construct(array $data) {
    $this->data = $data;
    $this->attrs = [ '_id' ];
    foreach ($data as $_id => $tuple) {
      foreach ($tuple as $attr => $value) {
        if (!in_array($attr, $this->attrs))
          $this->attrs[] = $attr;
      }
    }
  }
  
  // fournit les tuples de la table sous la forme d'une liste en ajoutant un attribut _id avec la liste des clés
  function tuples() {
    $tuples = [];
    foreach ($this->data as $_id => $tuple) {
      $tuples[] = array_merge(['_id'=> $_id], $tuple);
    }
    return $tuples;
  }
  
  // extrait le sous-élément de l'élément défini par $ypath
  // permet de traverser les objets quand on connait son chemin
  function extract(string $ypath) {
    if (!$ypath || ($ypath=='/'))
      return $this->data;
    else
      return YamlDoc::sextract($this->data, $ypath);
  }
  
  // décapsule l'objet et retourne son contenu sous la forme d'un array
  // permet de parcourir tout objet sans savoir a priori ce que l'on cherche
  // est utilisé par YamlDoc::replaceYDEltByArray()
  function asArray() { return $this->data; }
  
  // affiche un élément en Html
  // est utilisé par showDoc()
  // pas implémenté avec la même signature par tous !!!
  function show(string $docid, string $prefix='') {
    //echo "YDataTable::show()";
    echo "<table border=1>\n";
    echo "<th>",implode('</td><td>', $this->attrs),"</th>";
    foreach($this as $tuple) {
      echo "<tr>";
      foreach ($this->attrs as $attr) {
        echo "<td>";
        if (isset($tuple[$attr]))
          showDoc($docid, $tuple[$attr]);
        echo "</td>";
      }
      echo "</tr>\n";
    }
    echo "</table>\n";
  }
  
  function showPart(string $docid, string $ypath) {
    showDoc($docid, $this->extract($ypath));
  }
  
  // interface IteratorAggregate
  function getIterator() { return new ArrayIterator($this->tuples()); }
};
