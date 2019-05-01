<?php
/*PhpDoc:
name: dlenum.php
title: dlenum.php - constituition des enums de inspire-datamodel à partir de docinspire
doc: |
  génère le fichier enum.pser
  et en sortie un texte Yaml des schemes et des concepts à include dans inspire-datamodel.yaml
  
  chaque codelist est générée sous la forme suivante:
    {id}: // id de la codeliste
      type: [codelist]
      domain: [{domain}] // id du domaine auquel la codelist est rattachée
      prefLabel: // étiquette en français et en anglais ou en neutre (n)
      definition: // définition en français et en anglais
      parent-codelist?: // une codeliste de plus haut niveau
      extensibility: (none|any|narrower|open)
      (requirement|technicalguide|refdoc|docextract): // à voir
      hasTopConcept: // liste des id des concepts de plus haut niveau
  Chaque concept est généré sous la forme suivante:
    {id}: // id du concept sous la forme {codelistId}:{id}
      type: [codelist]
      inScheme: {codelistId}
      topConceptOf: {codelistId}
      prefLabel: // étiquette en français et en anglais ou en neutre (n)
      definition: // définition en français et en anglais
      exactMatch: // liste d'URI, notamment dans le registre Inspire
      narrower: // liste d'id des concepts spécifiques
      broader: // liste d'id des concepts génériques
    
journal: |
  6/7/2018:
    création
includes: [ ../../vendor/autoload.php, readcache.inc.php ]
*/

ini_set('memory_limit', '1024M');
ini_set('max_execution_time', 600);

require_once __DIR__.'/../../vendor/autoload.php';
require_once __DIR__.'/readcache.inc.php';

use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Yaml\Exception\ParseException;

if (php_sapi_name()<>'cli')
  echo "<!DOCTYPE HTML><html><head><meta charset='UTF-8'><title>dlcodelist</title></head><body><pre>\n";

$eutext = 'http://uri.docinspire.eu/eutext';
$urienum = 'http://uri.docinspire.eu/eutext/enum';

// lit un turtle concept et retourne un array d'array le décrivant lui et ses descendants
// $cid est l'id long du concept de la forme {clid}:{scid}
// $clid est l'id de la codelist
// $scid est l'id court du concept, valable pour la codelist
function readconcept(string $cid, string $urienum): array {
  list($eid, $scid) = explode(':', $cid);
  $concepts = [$cid => ['inScheme' => [$eid]]];
  $turtle = readcache('http://docinspire.eu/get.php?fmt=ttl&uri='
                       .urlencode("http://uri.docinspire.eu/eutext/enum/$eid/$scid"));
  $turtle = preg_replace("!<$urienum/$cid> !", '', $turtle);
  
  $pattern = '!skos:topConceptOf <([^>]*)>\.\n!';
  if (preg_match($pattern, $turtle, $matches)) {
    $turtle = preg_replace($pattern, '', $turtle, 1);
    $concepts[$cid]['topConceptOf'] = [$eid];
  }
  
  $pattern = "!skos:broader <$urienum/([^/]*)/([^>]*)>\.!";
  while (preg_match($pattern, $turtle, $matches)) {
    $turtle = preg_replace($pattern, '', $turtle, 1);
    $concepts[$cid]['broader'][] = "$matches[1]:$matches[2]";
  }
  
  foreach(['prefLabel','definition'] as $tag) {
    $pattern = "!skos:$tag \"([^\"]*)\"(@(..))?\.\n!";
    while (preg_match($pattern, $turtle, $matches)) {
      if (isset($matches[3])) {
        if (in_array($matches[3], ['fr','en']))
          $concepts[$cid][$tag][$matches[3]] = $matches[1];
      }
      else
        $concepts[$cid][$tag]['n'] = $matches[1];
      $turtle = preg_replace($pattern, '', $turtle, 1);
    }
  }
  
  $pattern = '!skos:exactMatch <([^>]*)>\.\n!';
  if (preg_match($pattern, $turtle, $matches)) {
    $turtle = preg_replace($pattern, '', $turtle, 1);
    $concepts[$cid]['exactMatch'] = [$matches[1]];
  }
  
  $pattern = "!skos:narrower <$urienum/([^/]*)/([^>]*)>\.!";
  while (preg_match($pattern, $turtle, $matches)) {
    $turtle = preg_replace($pattern, '', $turtle, 1);
    $concepts[$cid]['narrower'][] = "$matches[1]:$matches[2]";
  }
  if (isset($concepts[$cid]['narrower'])) {
    foreach ($concepts[$cid]['narrower'] as $narrower) {
      $concepts = array_merge($concepts, readconcept($narrower, $urienum));
    }
  }
  
  $patterns = [
    '!^(# [^\n\r]*[\r\n]+)*!',
    '!^(@prefix[^\n\r]*[\n\r]+)*!',
    '!^skos:inScheme <[^>]*>\.[\n\r]+!',
    '!^rdf:type <[^>]*>\.[\n\r]+!',
  ];
  foreach($patterns as $pattern) {
    $turtle = preg_replace($pattern, '', $turtle);
  }
    
  //echo "<pre>",str_replace(['&','<'],['&amp;','&lt;'], $turtle),"</pre>\n";
  //echo "<pre>concepts=",Yaml::dump($concepts, 999, 2),"</pre>\n";
  return $concepts;
}


if (true || !is_file('enum.pser')) {
  $turtle = readcache('http://docinspire.eu/get.php?fmt=ttl&uri='.urlencode('http://uri.docinspire.eu/eutext/enum'));
  //echo "<pre>",str_replace(['<'],['&lt;'], $turtle),"</pre>\n";
  $pattern = "!<$urienum> skos:hasTopConcept <$urienum/([^>]*)>\.!";
  $enums = [];
  $concepts = [];
  while (preg_match($pattern, $turtle, $matches)) {
    //echo "<pre>"; print_r($matches); echo "</pre>\n";
    $turtle = preg_replace($pattern, '', $turtle, 1);
    //if ($matches[1] <> 'AnthropogenicGeomorphologicFeatureTypeValue') continue;
    $enums[$matches[1]] = ['type'=> ['enumeration']];
  }
  //echo "<pre>enums="; print_r($enums); echo "</pre>\n"; die("ligne ".__LINE__);

  foreach (array_keys($enums) as $eid) {
    //echo "<b>$clid</b><br>\n";
    $turtle = readcache('http://docinspire.eu/get.php?fmt=ttl&uri='
                         .urlencode("http://uri.docinspire.eu/eutext/enum/$eid"));
    $turtle = preg_replace("!<$urienum/$eid> !", '', $turtle);
    //echo "<pre>",str_replace(['<'],['&lt;'], $turtle),"</pre>\n";
    $pattern = "!skos:broader <$eutext/(theme|package|model)/([^>]*)>\.\n!";
    if (!preg_match($pattern, $turtle, $matches)) {
      echo "Erreur sur theme|package|model pour le spatialobjecttype $sotid<br>\n";
      echo "<pre>",str_replace(['&','<'],['&amp;','&lt;'], $turtle),"</pre>\n";
      die();
    }
    $enums[$eid]['domain'] = ["$matches[1]-$matches[2]"];
    $turtle = preg_replace($pattern, '', $turtle, 1);
    
    foreach (['prefLabel','definition'] as $tag) {
      $pattern = "!skos:$tag \"([^\"]*)\"@(..)\.\n!";
      while (preg_match($pattern, $turtle, $matches)) {
        //echo "<pre>"; print_r($matches); echo "</pre>\n";
        if (in_array($matches[2], ['fr','en']))
          $enums[$eid][$tag][$matches[2]] = $matches[1];
        $turtle = preg_replace($pattern, '', $turtle, 1);
      }
    }
        
    if (preg_match('!skos:broader!', $turtle)) {
      echo "Erreur sur broader sur $clid<br>\n";
      echo "<pre>",str_replace(['<'],['&lt;'], $turtle),"</pre>\n";
      die("ligne ".__LINE__);
    }
    
    $pattern = "!skos:hasTopConcept <$urienum/$eid/([^>]*)>\.!";
    while (preg_match($pattern, $turtle, $matches)) {
      //echo "<pre>"; print_r($matches); echo "</pre>\n";
      $enums[$eid]['hasTopConcept'][] = "$eid:$matches[1]";
      $turtle = preg_replace($pattern, '', $turtle, 1);
    }
    //echo "<pre>",str_replace(['<'],['&lt;'], $turtle),"</pre>\n";
    //echo "<pre>",Yaml::dump($enums, 999, 2),"</pre>\n"; die("ligne ".__LINE__);
    
    if (isset($enums[$eid]['hasTopConcept'])) {
      $enums[$eid]['hasTopConcept'] = array_reverse($enums[$eid]['hasTopConcept']);
      foreach ($enums[$eid]['hasTopConcept'] as $cid) {
        $concepts = array_merge($concepts, readconcept($cid, $urienum));
      }
    }
    //echo "<pre>",Yaml::dump(['schemes'=> array_reverse($codelists), 'concepts'=> array_reverse($concepts)], 999, 2),"</pre>\n";
    //die("ligne ".__LINE__);
  }
  $contents = ['schemes'=> array_reverse($enums), 'concepts'=> array_reverse($concepts)];
  file_put_contents('enum.pser', serialize($contents)); 
  echo Yaml::dump($contents, 999, 2);
}
else {
  echo Yaml::dump(unserialize(file_get_contents('enum.pser')), 999, 2);
}
