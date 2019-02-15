<?php
/*PhpDoc:
name: fdsspecs.inc.php
title: fdsspecs.inc.php - document définissant les spécifications d'une série de données géo constituée d'un ensemble de couches d'objets
functions:
doc: <a href='/yamldoc/?action=version&name=fdsspecs.inc.php'>doc intégrée en Php</a>
*/
{ // doc 
$phpDocs['fdsspecs.inc.php']['file'] = <<<'EOT'
name: fdsspecs.inc.php
title: fdsspecs.inc.php - document définissant les spécifications d'une série de données géo constituée d'un ensemble de couches d'objets
doc: |
journal: |
  9/2/2019:
    - création
EOT;
}
{ // specs des docs 
$phpDocs['fdsspecs.inc.php']['classes']['FDsSpecs'] = <<<'EOT'
title: spécifications d'une série de données géo constituée d'un ensemble de couches d'objets
doc: |

  Un document FeatureDataset est décrit par le [schéma FeatureDataset](ydclasses.php/FDsSpecs.sch.yaml)

EOT;
}

use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Yaml\Exception\ParseException;

class FDsSpecs extends YamlDoc {
  protected $_c; // contient les champs du document
  protected $flattenFc; // dictionnaire des FC mis à plat
  
  // crée un nouveau doc, $yaml est le contenu Yaml externe issu de l'analyseur Yaml
  // $yaml est généralement un array mais peut aussi être du texte
  function __construct($yaml, string $docid) {
    $this->_c = [];
    $this->_id = $docid;
    foreach ($yaml as $prop => $value) {
      $this->_c[$prop] = $value;
    }
    //echo "<pre>"; print_r($this);
    $this->buildFlattenFc($this->featureCollections);
    //echo "<pre>this="; print_r($this); echo "</pre>\n";
  }
  
  // construit $this->flattenFc comme dictionnaire à plat des featureCollections
  function buildFlattenFc(array $featureCollections) {
    foreach ($featureCollections as $fcid => $fc) {
      if (isset($fc['title'])) {
        if (isset($this->flattenFc[$fcid]))
          throw new Exception("Erreur dans FDsSpecs::buildFlattenFc: $fcid définie plus d'une fois");
        $this->flattenFc[$fcid] = &$fc;
      }
      else {
        $this->buildFlattenFc($fc);
      }
    }
  }
  
  // lit les champs
  function __get(string $name) { return isset($this->_c[$name]) ? $this->_c[$name] : null; }
  
  // affiche le sous-élément de l'élément défini par $ypath
  function show(string $ypath=''): void {
    $docid = $this->_id;
    //echo "FeatureDataset::show($docid, $ypath)<br>\n";
    if (preg_match('!^/layers/([^/]+)$!', $ypath, $matches)) {
      $this->showLayer($docid, $matches[1]);
      return;
    }
    elseif (preg_match('!^/layers/([^/]+)/conformsTo$!', $ypath, $matches)) {
      $this->showConformsTo($docid, $matches[1]);
      return;
    }
    elseif (preg_match('!^/layers/([^/]+)/conformsTo/properties/([^/]+)$!', $ypath, $matches)) {
      echo "<h3>Spécifications de ",
           "<a href='?doc=$docid&ypath=/layers/$matches[1]'>$matches[1]</a>.$matches[2]</h3>\n";
      showDoc($docid, $this->extract($ypath));
      return;
    }
    elseif (preg_match('!^/layers/([^/]+)/conformsTo/properties/([^/]+)/enum$!', $ypath, $matches)) {
      echo "<h3>Valeurs possibles pour ",
           "<a href='?doc=$docid&ypath=/layers/$matches[1]'>$matches[1]</a>",
           ".<a href='?doc=$docid&ypath=/layers/$matches[1]/conformsTo/properties/$matches[2]'>$matches[2]</a></h3>\n";
      showDoc($docid, $this->extract($ypath));
      return;
    }
    elseif ($ypath && ($ypath <> '/')) {
      showDoc($docid, $this->extract($ypath));
      return;
    }
    echo "<h1>",$this->title,"</h1>\n";
    $yaml = $this->_c;
    unset($yaml['title']);
    unset($yaml['featureCollections']);
    showDoc($docid, $yaml);
    $this->showFcs($this->featureCollections);
  }
  
  // affichage récursif des thèmes et collections
  function showFcs(array $fcs) {
    foreach ($fcs as $fcid => $fc) {
      if (!isset($fc['title'])) {
        echo "<h2>",str_replace('_',' ',$fcid),"</h2>\n";
        $this->showFcs($fc);
      }
      else {
        $this->showCollection($fcid);
      }
    }
  }
  
  function showCollection(string $fcid) {
    $docid = $this->_id;
    $fc = $this->flattenFcs[$fcid];
    echo "<h3><a href='?doc=$docid&ypath=/collections/$fcid'>$fc[title]</a></h3>\n";
  }
  
  function showLayer(string $docid, string $lyrid) {
    $layer = $this->layers[$lyrid];
    echo "<h3><a href='?doc=$docid&ypath=/layers/$lyrid'>$layer[title]</a></h3>\n";
    unset($layer['title']);
    if (isset($layer['conformsTo']))
      $layer['conformsTo'] = "<html>\n<a href='?doc=$docid&ypath=/layers/$lyrid/conformsTo'>spécifications</a>\n";
    if (isset($layer['style'])) {
      if (is_string($layer['style']))
        $layer['style'] = "<html>\n<pre>$layer[style]</pre>\n";
      elseif (is_array($layer['style']))
        $layer['style'] = "<html>\n<pre>".json_encode($layer['style'])."</pre>\n";
    }
    if (isset($layer['pointToLayer']))
      $layer['pointToLayer'] = "<html>\n<pre>$layer[pointToLayer]</pre>\n";
    showDoc($docid, $layer);
  }
  
  function showConformsTo(string $docid, string $lyrid) {
    $layer = $this->layers[$lyrid];
    echo "<h3>Spécifications de <a href='?doc=$docid&ypath=/layers/$lyrid'>$layer[title]</a></h3>\n";
    $conformsTo = $layer['conformsTo'];
    if (isset($conformsTo['properties']))
      foreach ($conformsTo['properties'] as $propid => $property)
        if (isset($property['enum']))
          $conformsTo['properties'][$propid]['enum'] = "<html>\n<a href='?doc=$docid&ypath=/layers/$lyrid/conformsTo/properties/$propid/enum'>Valeurs possibles</a>\n";
    showDoc($docid, $conformsTo);
  }
  
  // décapsule l'objet et retourne son contenu sous la forme d'un array
  // ce décapsulage ne s'effectue qu'à un seul niveau
  // Permet de maitriser l'ordre des champs
  function asArray() {
    $result = array_merge(['_id'=> $this->_id], $this->_c);
    if ($this->wfsServer)
      $result['wfs'] = $this->wfsServer->asArray();
    return $result;
  }

  // extrait le fragment du document défini par $ypath
  // Renvoie un array ou un objet qui sera ensuite transformé par YamlDoc::replaceYDEltByArray()
  // Utilisé par YamlDoc::yaml() et YamlDoc::json()
  // Evite de construire une structure intermédiaire volumineuse avec asArray()
  function extract(string $ypath) {
    return YamlDoc::sextract($this->_c, $ypath);
  }
};