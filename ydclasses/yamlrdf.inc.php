<?php
/*PhpDoc:
name: yamlrdf.inc.php
title: Stockage/exposition d'un graphe RDF
doc: |
  voir le code
includes:
  - ../../markdown/markdown/PHPMarkdownLib1.8.0/Michelf/MarkdownExtra.inc.php
  - mlstring.inc.php
*/
{ // doc 
$phpDocs['yamlrdf.inc.php']['file'] = <<<EOT
name: yamlrdf.inc.php
title: yamlrdf.inc.php - Stockage/exposition d'un graphe RDF stocké en Yaml selon le schema YamlRdf.sch.yaml
doc: |
  Le graphe RDF est stocké dans un objet de la classe YamlRdf
journal: |
  7/8/2019:
  - correction bugs pour affichage de Turtle
  29/7/2019:
  - mise en oeuvre de l'export JSON-LD
  27/7/2019:
  - l'export JSON est incomplet puisque le contexte n'est pas exporté
  - faire évoluer l'export JSON pour générer du JSON-LD
  26/7/2019:
  - création du schema JSON
  - publi sur georef.eu
  24-25/7/2019:
  - création, gestion de l'affichage turtle, saisie d'un catalogue de quelques jeux de données 
EOT;
}
//require_once __DIR__.'/../vendor/autoload.php';
//require_once __DIR__."/../../markdown/markdown/PHPMarkdownLib1.8.0/Michelf/MarkdownExtra.inc.php";
//require_once __DIR__.'/mlstring.inc.php';

//use Symfony\Component\Yaml\Yaml;
//use Michelf\MarkdownExtra;

{ // doc 
$phpDocs['yamlrdf.inc.php']['classes']['YamlRdf'] = <<<EOT
name: class YamlRdf
title: Stockage/exposition d'un graphe RDF
doc: |
  La classe YamlRdf hérite de la classe abstraite YamlDoc.  
  Un document YamlRdf correspond à un graphe RDF codé en Yaml selon le schema YamlRdf.sch.yaml
  Le document ou un des ressources définies:
    - s'affiche en HTML dans le navigateur YamlDoc
    - s'expose en JSON-LD ou en Turtle
EOT;
}
class YamlRdf extends YamlDoc {
  protected $_c; // contient les champs de la ressource racine
  protected $namespaces;
  protected $classes;
  protected $properties;
  protected $rootId;
  protected $source;
  
  function __construct($yaml, string $docid) {
    $this->_id = $docid;
    if (!is_array($yaml))
      throw new Exception("Erreur dans YamlRdf::__construct() : le paramètre yaml doit être un array");
    unset($yaml['$schema']);
    unset($yaml['abstract']);
    unset($yaml['source']);
    $this->properties = [];
    foreach($yaml['properties'] as $id => $property) {
      $this->properties[$id] = new YamlRdfProperty($property);
    }
    unset($yaml['properties']);
    foreach (['namespaces','classes','rootId'] as $key) {
      $this->$key = $yaml[$key];
      unset($yaml[$key]);
    }
    $this->_c = $yaml;
  }
  
  // un .pser est généré automatiquement à chaque mise à jour du .yaml
  function writePser(): void { YamlDoc::writePserReally(); }
  
  function __get(string $name) { return $this->_c[$name] ?? null; }
  
  // décapsule l'objet et retourne son contenu sous la forme d'un array
  function asArray() {
    return array_merge(
      [ 'namespaces'=> $this->namespaces,
        'classes'=> $this->classes,
        'properties'=> $this->properties,
        'rootId'=> $this->rootId],
      $this->_c);
  }
  
  // retourne le fragment défini par path qui est une chaine
  function extract(string $ypath) {
    return YamlDoc::sextract($this->asArray(), $ypath);
  }
  
  // Fabrique le contexte
  function buildJsonLdContext(): array {
    $context = [];
    foreach ($this->usedPrefixInProp() as $prefix)
      $context[$prefix] = $this->namespaces[$prefix];
    foreach($this->properties as $shortname => $prop) {
      $context[$shortname] = $prop->simplified();
    }
    return ['@context'=> $context];
  }
  
  // si $url est une URL HTTP qui contient un blanc alors retourne la partie avant le blanc sinon retourne le paramètre
  static function removeCommentFromHttpUrl(string $url) {
    if ((substr($url, 0, 7)<>'http://') && (substr($url, 0, 8)<>'https://'))
      return $url;
    $pos = strpos($url, ' ');
    if ($pos)
      return substr($url, 0, $pos);
    else
      return $url;
  }
  
  // fabrique un JSON-LD
  function buildJsonLd(array $res, string $ypath, string $ypath2, int $recursive=0): array {
    $data = [];
    if ($recursive <> 2)
      $data['@context'] = "http://$_SERVER[HTTP_HOST]$_SERVER[SCRIPT_NAME]/".$this->_id.'/@context';
    $data['@id'] = "http://$_SERVER[HTTP_HOST]$_SERVER[SCRIPT_NAME]/".$this->_id.($ypath=='/' ? '' : $ypath);
    foreach ($res as $prop => $objs) {
      if ($prop == 'a')
        $prop = '@type';
      if (is_string($objs))
        $data[$prop] = self::removeCommentFromHttpUrl($objs);
      elseif (is_list($objs)) {
        $data[$prop] = [];
        foreach ($objs as $obj)
          $data[$prop][] = self::removeCommentFromHttpUrl($obj);
      }
      elseif (is_array($objs)) {
        $data[$prop] = [];
        foreach ($objs as $id => $obj) {
          if (!$recursive)
            $data[$prop][] = "http://$_SERVER[HTTP_HOST]$_SERVER[SCRIPT_NAME]/"
              .$this->_id."$ypath2/$prop/".rawurlencode($id);
          else {
            $ypathChild = "$ypath2/$prop/".rawurlencode($id);
            $data[$prop][] = $this->buildJsonLd($obj, $ypathChild, $ypathChild, 2);
          }
        }
      }
    }
    return $data;
  }
  
  // extrait le fragment défini par $ypath, utilisé pour générer un retour à partir d'un URI
  // Retourne la ressource comme JSON-LD
  // L'URI /{rootId} retourne la ressource racine sans ses ressources filles
  // l'URI '/' retourne tout le document
  function extractByUri(string $ypath) {
    if ($ypath == '/@context')
      return $this->buildJsonLdContext();
    elseif ($ypath == '/')
      return $this->buildJsonLd($this->_c, $ypath, '', 1);
    elseif ($ypath == '/'.$this->rootId)
      return $this->buildJsonLd($this->_c, $ypath, '');
    elseif ($subelt = YamlDoc::sextract($this->_c, rawurldecode($ypath)))
      return $this->buildJsonLd($subelt, $ypath, $ypath);
    else
      return null;
  }
  
  // affiche le doc ou le fragment si ypath est non vide
  function show(string $ypath=''): void {
    //echo "<pre>show(ypath=$ypath) data="; print_r($this->data); echo "</pre>\n";
    showDoc($this->_id, YamlDoc::sextract(self::replaceYDEltByArray($this->asArray()), $ypath));
  }
  
  // liste des prefixes utilisés par les propriétés
  function usedPrefixInProp(): array {
    $prefixes = [];
    foreach ($this->properties as $prop) {
      $pos = strpos($prop->_id(), ':');
      $prefix = substr($prop->_id(), 0, $pos);
      if ($pos && !in_array($prefix, $prefixes))
        $prefixes[] = $prefix;
    }
    return $prefixes;
  }
  
  // affiche les namespaces présents dans les propriétés
  function printNamespaces(): void {
    echo "@base <http://id.georef.eu/".$this->_id,"/> .\n";
    //print_r($this);
    //print_r($prefixes);
    foreach ($this->usedPrefixInProp() as $prefix) {
      echo "@prefix $prefix: <",$this->namespaces[$prefix],"> .\n";
    }
    echo "\n";
  }
  
  function showProperty(string $prop): string {
    if (!isset($this->properties[$prop]))
      die("\nErreur propriété $prop non définie\n");
    return '  '.$this->properties[$prop]->_id().' ';
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

class YamlRdfProperty implements YamlDocElement {
  protected $_c; // toujours un array
  
  function __construct($yaml) {
    if (is_string($yaml))
      $this->_c = ['id'=> $yaml];
    elseif (is_array($yaml))
      $this->_c = $yaml;
    else
      throw new Exception("Erreur création YamlRdfProperty");
  }
  
  function _id() { return $this->_c['id']; }
  
  // extrait le sous-élément de l'élément défini par $ypath
  // permet de traverser les objets quand on connait son chemin
  function extract(string $ypath) {}

  // décapsule l'objet et retourne son contenu sous la forme d'un array
  // permet de parcourir tout objet sans savoir a priori ce que l'on cherche
  // est utilisé par YamlDoc::replaceYDEltByArray()
  function asArray() {
    return $this->_c;
  }

  // affichage simplifié pour le contexte
  function simplified() {
    if (count($this->_c) > 1) {
      $simplified = [];
      foreach ($this->_c as $key => $val)
        $simplified['@'.$key] = $val;
      return $simplified;
    }
    else
      return $this->_c['id'];
  }
  
  // affiche un élément en Html
  // est utilisé par showDoc()
  // pas implémenté avec la même signature par tous !!!
  //public function show(string $docuid, string $prefix='');
};