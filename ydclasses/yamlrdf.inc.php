<?php
/*PhpDoc:
name: yamlrdf.inc.php
title: gestion d'un graphe RDF
doc: |
  voir le code
includes:
  - ../../vendor/autoload.php
  - ../../markdown/markdown/PHPMarkdownLib1.8.0/Michelf/MarkdownExtra.inc.php
  - mlstring.inc.php
*/
{ // doc 
$phpDocs['yamlrdf.inc.php']['file'] = <<<EOT
name: yamlrdf.inc.php
title: yamlrdf.inc.php - gestion d'un graphe RDF
doc: |
  Gestion/affichage d'un graphe RDF codé en Yaml selon les specs décrites dans YamlRdf.sch.yaml
journal: |
  26/7/2019:
  - création du schema JSON
  - publi sur georef.eu
  24-25/7/2019:
  - création, gestion de l'affichage turtle, saisie d'un catalogue de quelques jeux de données 
EOT;
}
require_once __DIR__.'/../../vendor/autoload.php';
require_once __DIR__."/../../markdown/markdown/PHPMarkdownLib1.8.0/Michelf/MarkdownExtra.inc.php";
require_once __DIR__.'/mlstring.inc.php';

use Symfony\Component\Yaml\Yaml;
use Michelf\MarkdownExtra;

{ // doc 
$phpDocs['yamlrdf.inc.php']['classes']['YamlRdf'] = <<<EOT
name: class YamlRdf
title: gestion d'un graphe RDF
doc: |
  La classe YamlRdf hérite de la classe abstraite YamlDoc.  
  Un document Rdf correspond à la ressource principale du graphe, par ex. la ressource Catalog dans Dcat.
  Il contient les différents champs de cet objet principal.
  Il contient en outre:
  
    - un champ namespaces contenant la liste des espaces de noms avec pour chacun le prefix associé comme clé.
    - un champ classes contient un dictionnaire des classes utilisées (à voir)
    - un champ properties contient un dictionnaire des propriétés utilisées donnant la propriété complète

EOT;
}
class YamlRdf extends YamlDoc {
  protected $_c; // contient les champs de la ressource racine
  protected $namespaces;
  protected $classes;
  protected $properties;
  protected $source;
  
  function __construct($yaml, string $docid) {
    $this->_id = $docid;
    if (!is_array($yaml))
      throw new Exception("Erreur dans YamlRdf::__construct() : le paramètre yaml doit être un array");
    unset($yaml['$schema']);
    unset($yaml['abstract']);
    unset($yaml['source']);
    foreach (['namespaces','classes','properties','rootId'] as $key) {
      $this->$key = $yaml[$key];
      unset($yaml[$key]);
    }
    $this->_c = $yaml;
  }
  
  // un .pser est généré automatiquement à chaque mise à jour du .yaml
  function writePser(): void { YamlDoc::writePserReally(); }
  
  function __get(string $name) { return $this->_c[$name] ?? null; }
  
  // décapsule l'objet et retourne son contenu sous la forme d'un array
  function asArray() { return $this->_c; }
  
  // retourne le fragment défini par path qui est une chaine
  function extract(string $ypath) {
    return YamlDoc::sextract($this->_c, $ypath);
  }
  
  // extrait le fragment défini par $ypath, utilisé pour générer un retour à partir d'un URI
  // Retourne la ressource référencée en remplacant ses ressources filles par des URI
  // Ajoute aussi l'URI /{rootId} qui renvoie la ressource racine sans ses ressources filles
  // l'URI '/' retourne tout le document
  function extractByUri(string $ypath) {
    if ($ypath == '/')
      return $this->_c;
    if ($ypath == '/'.$this->rootId) {
      $res = $this->_c;
      $ypath = ''; // supprimer '/catalog' dans l'URL des enfants
    }
    else
      $res = YamlDoc::sextract($this->_c, $ypath);
    $data = [];
    foreach ($res as $prop => $obj) {
      if (is_string($obj))
        $data[$prop] = $obj;
      elseif (is_list($obj))
        $data[$prop] = $obj;
      elseif (is_array($obj)) {
        $data[$prop] = [];
        foreach (array_keys($obj) as $id)
          $data[$prop][] = "http://$_SERVER[HTTP_HOST]$_SERVER[SCRIPT_NAME]/"
            .$this->_id."$ypath/$prop/".rawurlencode($id);
      }
    }
    return $data;
  }
  
  // affiche le doc ou le fragment si ypath est non vide
  function show(string $ypath=''): void {
    //echo "<pre>show(ypath=$ypath) data="; print_r($this->data); echo "</pre>\n";
    showDoc($this->_id, YamlDoc::sextract($this->_c, $ypath));
  }
  
  // affiche les namespaces présents dans les propriétés
  function printNamespaces(): void {
    echo "@base <http://id.georef.eu/".$this->_id,"/> .\n";
    //print_r($this);
    $prefixes = [];
    foreach ($this->properties as $prop) {
      $pos = strpos($prop, ':');
      $prefix = substr($prop, 0, $pos);
      if ($pos && !in_array($prefix, $prefixes))
        $prefixes[] = $prefix;
    }
    //print_r($prefixes);
    foreach ($prefixes as $prefix) {
      echo "@prefix $prefix: <",$this->namespaces[$prefix],"> .\n";
    }
    echo "\n";
  }
  
  function showProperty(string $prop): string {
    if (!isset($this->properties[$prop]))
      die("\nErreur propriété $prop non définie\n");
    return '  '.$this->properties[$prop].' ';
  }
  
  function showObject(string $object): string {
    if ((substr($object, 0, 7)=='http://') || (substr($object, 0, 8)=='https://')) {
      $pos = strpos($object, ' ');
      if ($pos)
        $object = substr($object, 0, $pos);
      return "<$object>";
    }
    elseif (in_array($object, $this->classes))
      return $object;
    else
      return "\"$object\"";
  }
  
  // affiche un ensemble de triplets ayant même sujet
  function printTriples(string $subject, array $propObjects) {
    //print_r($this);
    echo "<$subject>\n";
    foreach ($propObjects as $prop => $objects) {
      if (is_string($objects)) // => un objet soit literal, soit URI
        echo $this->showProperty($prop),$this->showObject($objects)," ;\n";
      elseif (is_list($objects)) { // => une liste d'objets, soit litéraux, soit URI
        foreach ($objects as $object) {
          echo $this->showProperty($prop),$this->showObject($object)," ;\n";
        }
      }
      else { // is_array() && !is_list() => des sous-objets transformés en URI
        foreach (array_keys($objects) as $objuri) {
          echo $this->showProperty($prop),"<$prop/$objuri> ;\n";
        }
      }
    }
    echo ".\n";
  }
  
  function printTriplesRecur(string $subject, array $propObjects) {
    $this->printTriples($subject, $propObjects);
    foreach ($propObjects as $prop => $objects) {
      if (!is_string($objects) && !is_list($objects)) {
        foreach ($objects as $prop2 => $subPropObjects) {
          $this->printTriplesRecur("$prop/$prop2", $subPropObjects);
        }
      }
    }
  }
  
  // Affiche une sortie turtle d'un sujet ou de l'ensemble du graphe RDF décrit
  function printTtl(string $ypath): void {
    $this->printNamespaces();
    if (in_array($ypath,['','/'])) {
      $this->printTriplesRecur('catalog', $this->_c);
    }
    elseif ($ypath == '/'.$this->rootId) {
      $this->printTriples('catalog', $this->_c);
    }
    else { // ypath n'est pas root
      $this->printTriples(substr($ypath,1), $this->extract($ypath));
    }
  }
};