<?php
/*PhpDoc:
name: dlcodelist.php
title: dlcodelist.php - constituition des codelists de inspire-datamodel à partir de docinspire
doc: |
  génère le fichier codelist.pser
  et en sortie un texte Yaml des schemes et des concepts à include dans inspire-datamodel.yaml
  
  chaque codelist est générée sous la forme suivante:
    {id}: // id de la codeliste
      type: [codelist]
      domain: [{domain}] // id du domaine auquel la codelist est rattachée
      prefLabel: // étiquette en français et en anglais ou en neutre (n)
      definition: // définition en français et en anglais
      hasPart?: // les codeliste contenues dans la codeliste
      isPartOf?: // les codelistes auxquelles appartiennent la codeliste
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

  schemes:
    RegionClassificationValue:
      type:
        - codelist
      domain:
        - theme-br
      prefLabel:
        fr: 'Classification des régions'
        en: 'Region Classification'
      definition:
        fr: 'Codes utilisés pour définir les différentes régions biogéographiques.'
        en: 'Codes used to define the various bio-geographical regions.'
      hasPart:
        - NaturalVegetationClassificationValue
        - Natura2000AndEmeraldBio-geographicalRegionClassificationValue
        - MarineStrategyFrameworkDirectiveClassificationValue
        - EnvironmentalStratificationClassificationValue
      extensibility: open

journal: |
  7/7/2018:
    ajout des sous-listes
  4-6/7/2018:
    création
*/

ini_set('memory_limit', '1024M');
ini_set('max_execution_time', 600);

require_once __DIR__.'/../../vendor/autoload.php';
require_once __DIR__.'/readcache.inc.php';

use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Yaml\Exception\ParseException;

if (php_sapi_name()<>'cli')
  echo "<!DOCTYPE HTML><html><head><meta charset='UTF-8'><title>dlcodelist</title></head><body><pre>\n";


// lit un turtle concept et retourne un array d'array le décrivant lui et ses descendants
// $cid est l'id long du concept de la forme {clid}:{scid}
// $clid est l'id de la codelist
// $scid est l'id court du concept, valable pour la codelist
function readconcept(string $cid, string $uricodelist): array {
  list($clid, $scid) = explode(':', $cid);
  $concepts = [$cid => ['inScheme' => [$clid]]];
  $turtle = readcache('http://docinspire.eu/get.php?fmt=ttl&uri='.urlencode("$uricodelist/$clid/$scid"));
  $turtle = preg_replace("!<$uricodelist/$cid> !", '', $turtle);
  
  $pattern = '!skos:topConceptOf <([^>]*)>\.\n!';
  if (preg_match($pattern, $turtle, $matches)) {
    $turtle = preg_replace($pattern, '', $turtle, 1);
    $concepts[$cid]['topConceptOf'] = [$clid];
  }
  
  $pattern = "!skos:broader <$uricodelist/([^/]*)/([^>]*)>\.!";
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
  
  $pattern = "!skos:narrower <$uricodelist/([^/]*)/([^>]*)>\.!";
  while (preg_match($pattern, $turtle, $matches)) {
    $turtle = preg_replace($pattern, '', $turtle, 1);
    $concepts[$cid]['narrower'][] = "$matches[1]:$matches[2]";
  }
  if (isset($concepts[$cid]['narrower'])) {
    foreach ($concepts[$cid]['narrower'] as $narrower) {
      $concepts = array_merge($concepts, readconcept($narrower, $uricodelist));
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

function readscheme(string $stag, string $sid, string $isPartOf=''): array {
  $eutext = 'http://uri.docinspire.eu/eutext';
  $uricodelist = "$eutext/$stag";
  $codelist = ['type'=> [$stag]];
  $turtle = readcache('http://docinspire.eu/get.php?fmt=ttl&uri='.urlencode("$uricodelist/$sid"));
  //if ($isPartOf) echo str_replace(['&','<'],['&amp;','&lt;'], $turtle);
  $turtle = preg_replace("!<$uricodelist/$sid> !", '', $turtle);

  $pattern = "!skos:broader <$eutext/(theme|package|model)/([^>]*)>\.\n!";
  if (preg_match($pattern, $turtle, $matches)) {
    $turtle = preg_replace($pattern, '', $turtle, 1);
    $codelist['domain'] = ["$matches[1]-$matches[2]"];
  }
  elseif (!$isPartOf) {
    echo "Erreur sur theme|package|model pour la codelist $sid<br>\n";
    echo "<pre>",str_replace(['&','<'],['&amp;','&lt;'], $turtle),"</pre>\n";
    die();
  }
  
  foreach (['prefLabel','definition'] as $tag) {
    $pattern = "!skos:$tag \"([^\"]*)\"@(..)\.\n!";
    while (preg_match($pattern, $turtle, $matches)) {
      //echo "<pre>"; print_r($matches); echo "</pre>\n";
      if (in_array($matches[2], ['fr','en']))
        $codelist[$tag][$matches[2]] = $matches[1];
      $turtle = preg_replace($pattern, '', $turtle, 1);
    }
  }
  
  $pattern = "!skos:narrower <$eutext/codelist/([^>]*)>\.!";
  while (preg_match($pattern, $turtle, $matches)) {
    $turtle = preg_replace($pattern, '', $turtle, 1);
    $pid = $matches[1];
    $codelist['hasPart'][] = $pid;
  }
  
  $pattern = "!skos:broader <$eutext/codelist/([^>]*)>\.!";
  while (preg_match($pattern, $turtle, $matches)) {
    $turtle = preg_replace($pattern, '', $turtle, 1);
    $pid = $matches[1];
    $codelist['isPartOf'][] = $pid;
  }
  if ($isPartOf && !in_array($isPartOf, $codelist['isPartOf'])) {
    echo "Erreur sur isPartOf pour la codelist $sid sous-liste de $isPartOf<br>\n";
    echo "<pre>",str_replace(['&','<'],['&amp;','&lt;'], $turtle),"</pre>\n";
    die();
  }
  
  $pattern = '!skos:broader <http://uri.docinspire.eu/eutextproperty/extensibility/([^>]*)>\.!';
  if (preg_match($pattern, $turtle, $matches)) {
    $turtle = preg_replace($pattern, '', $turtle, 1);
    $codelist['extensibility'] = $matches[1];
  }
  else
    $codelist['extensibility'] = 'undefined';
  
  $pattern = "!skos:broader <($eutext/(requirement|technicalguide|refdoc|docextract)/[^>]*)>\.!";
  while (preg_match($pattern, $turtle, $matches)) {
    $turtle = preg_replace($pattern, '', $turtle, 1);
    $codelist[$matches[2]] = $matches[1];
  }
  
  $pattern = "!skos:broader <($eutext/\?[^>]*)>\.!";
  while (preg_match($pattern, $turtle, $matches)) {
    $turtle = preg_replace($pattern, '', $turtle, 1);
    $codelist['eutext'] = $matches[1];
  }
  
  if (preg_match('!skos:broader!', $turtle)) {
    echo "Erreur sur broader sur $sid<br>\n";
    echo "<pre>",str_replace(['<'],['&lt;'], $turtle),"</pre>\n";
    die("ligne ".__LINE__);
  }
  
  $pattern = "!skos:hasTopConcept <$uricodelist/$sid/([^>]*)>\.!";
  while (preg_match($pattern, $turtle, $matches)) {
    $turtle = preg_replace($pattern, '', $turtle, 1);
    $codelist['hasTopConcept'][] = "$sid:$matches[1]";
  }
  //echo "<pre>",str_replace(['<'],['&lt;'], $turtle),"</pre>\n";
  //echo "<pre>",Yaml::dump($codelists, 999, 2),"</pre>\n";
  
  $concepts = [];
  if (isset($codelist['hasTopConcept'])) {
    $codelist['hasTopConcept'] = array_reverse($codelist['hasTopConcept']);
    foreach ($codelist['hasTopConcept'] as $cid) {
      $concepts = array_merge($concepts, readconcept($cid, $uricodelist));
    }
  }
  //echo "<pre>",Yaml::dump(['schemes'=> array_reverse($codelists), 'concepts'=> array_reverse($concepts)], 999, 2),"</pre>\n";
  //die("ligne ".__LINE__);
  $codelist['concepts'] = $concepts;
  return $codelist;
}

if (true || !is_file('codelist.pser')) {
  $eutext = 'http://uri.docinspire.eu/eutext';
  $stag = 'codelist';
  $uricodelist = "$eutext/$stag";
  $turtle = readcache('http://docinspire.eu/get.php?fmt=ttl&uri='.urlencode($uricodelist));
  //echo "<pre>",str_replace(['<'],['&lt;'], $turtle),"</pre>\n";
  $pattern = "!<$uricodelist> skos:hasTopConcept <$uricodelist/([^>]*)>\.!";
  $codelists = [];
  $concepts = [];
  while (preg_match($pattern, $turtle, $matches)) {
    //echo "<pre>"; print_r($matches); echo "</pre>\n";
    $turtle = preg_replace($pattern, '', $turtle, 1);
    //if ($matches[1] <> 'AnthropogenicGeomorphologicFeatureTypeValue') continue;
    $codelists[$matches[1]] = true;
  }
  //echo "<pre>codelists="; print_r($codelists); echo "</pre>\n";

  foreach (array_keys($codelists) as $sid) {
    //echo "<b>$clid</b><br>\n";
    $codelists[$sid] = readscheme($stag, $sid);
    $concepts = array_merge($concepts, $codelists[$sid]['concepts']);
    unset($codelists[$sid]['concepts']);
    if (isset($codelists[$sid]['hasPart']) && $codelists[$sid]['hasPart']) {
      foreach ($codelists[$sid]['hasPart'] as $pid) {
        if (!isset($codelists[$pid])) {
          //echo "Lecture de la sous-liste $pid de $sid\n";
          $codelists[$pid] = readscheme($stag, $pid, $sid);
          $concepts = array_merge($concepts, $codelists[$pid]['concepts']);
          unset($codelists[$pid]['concepts']);
        }
      }
    }
  }
  //   Redéfinition des codelists mal définies dans docinspire
  $suppcodelists = Yaml::parse(@file_get_contents(__DIR__.'/suppcodelist.yaml'));
  $codelists = array_merge($codelists, $suppcodelists['codelists']);
  
  $contents = ['schemes'=> array_reverse($codelists), 'concepts'=> array_reverse($concepts)];
  file_put_contents('codelist.pser', serialize($contents)); 
  echo Yaml::dump($contents, 999, 2);
}
else {
  echo Yaml::dump(unserialize(file_get_contents('codelist.pser')), 999, 2);
}

