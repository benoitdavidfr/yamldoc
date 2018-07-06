<?php
// dlcodelist.php - constituition des codelists de inspire-datamodel à partir de docinspire
// 4-5/7/2018

ini_set('memory_limit', '1024M');
ini_set('max_execution_time', 600);

require_once __DIR__.'/../../vendor/autoload.php';
require_once __DIR__.'/readcache.inc.php';

use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Yaml\Exception\ParseException;

echo "<!DOCTYPE HTML><html><head><meta charset='UTF-8'><title>dlcodelist</title></head><body>\n";
$eutext = 'http://uri.docinspire.eu/eutext';
$uricodelist = 'http://uri.docinspire.eu/eutext/codelist';


// lit un turtle concept et retourne un array d'array le décrivant lui et ses descendants
// $cid est l'id long du concept de la forme {clid}:{scid}
// $clid est l'id de la codelist
// $scid est l'id court du concept, valable pour la codelist
function readconcept(string $cid, string $uricodelist): array {
  list($clid, $scid) = explode(':', $cid);
  $concepts = [$cid => ['inScheme' => [$clid]]];
  $turtle = readcache("http://turtle.docinspire.eu/eutext/codelist/$clid/$scid");
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
    $concepts[$cid]['exactMatch'] = $matches[1];
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


if (1 || !is_file('codelist.pser')) {
  $turtle = readcache('http://turtle.docinspire.eu/eutext/codelist');
  //echo "<pre>",str_replace(['<'],['&lt;'], $turtle),"</pre>\n";
  $pattern = "!<$uricodelist> skos:hasTopConcept <$uricodelist/([^.]*)>\.!";
  $codelists = [];
  $concepts = [];
  while (preg_match($pattern, $turtle, $matches)) {
    //echo "<pre>"; print_r($matches); echo "</pre>\n";
    $turtle = preg_replace($pattern, '', $turtle, 1);
    //if ($matches[1] <> 'AnthropogenicGeomorphologicFeatureTypeValue') continue;
    $codelists[$matches[1]] = ['type'=> ['codelist']];
  }
  //echo "<pre>codelists="; print_r($codelists); echo "</pre>\n";

  foreach (array_keys($codelists) as $clid) {
    //echo "<b>$clid</b><br>\n";
    $turtle = readcache("http://turtle.docinspire.eu/eutext/codelist/$clid");
    $turtle = preg_replace("!<$uricodelist/$clid> !", '', $turtle);
    //echo "<pre>",str_replace(['<'],['&lt;'], $turtle),"</pre>\n";
    $pattern = "!skos:broader <$eutext/(theme|package|model)/([^>]*)>\.\n!";
    if (!preg_match($pattern, $turtle, $matches)) {
      echo "Erreur sur theme|package|model pour le spatialobjecttype $sotid<br>\n";
      echo "<pre>",str_replace(['&','<'],['&amp;','&lt;'], $turtle),"</pre>\n";
      die();
    }
    $codelists[$clid]['domain'] = ["$matches[1]-$matches[2]"];
    $turtle = preg_replace($pattern, '', $turtle, 1);
    
    foreach (['prefLabel','definition'] as $tag) {
      $pattern = "!skos:$tag \"([^\"]*)\"@(..)\.\n!";
      while (preg_match($pattern, $turtle, $matches)) {
        //echo "<pre>"; print_r($matches); echo "</pre>\n";
        if (in_array($matches[2], ['fr','en']))
          $codelists[$clid][$tag][$matches[2]] = $matches[1];
        $turtle = preg_replace($pattern, '', $turtle, 1);
      }
    }
    
    $pattern = "!skos:broader <$eutext/codelist/([^>]*)>\.!";
    while (preg_match($pattern, $turtle, $matches)) {
      //echo "<pre>"; print_r($matches); echo "</pre>\n";
      $codelists[$clid]['parent-codelist'][] = $matches[1];
      $turtle = preg_replace($pattern, '', $turtle, 1);
    }
    
    $pattern = '!skos:broader <http://uri.docinspire.eu/eutextproperty/extensibility/([^>]*)>\.!';
    if (preg_match($pattern, $turtle, $matches)) {
      $turtle = preg_replace($pattern, '', $turtle, 1);
      $codelists[$clid]['extensibility'] = $matches[1];
    }
    else
      $codelists[$clid]['extensibility'] = 'undefined';
    
    $pattern = "!skos:broader <$eutext/(requirement|technicalguide|refdoc|docextract)/([^>]*)>\.!";
    while (preg_match($pattern, $turtle, $matches)) {
      $turtle = preg_replace($pattern, '', $turtle, 1);
      $codelists[$clid][$matches[1]] = $matches[2];
    }
    
    if (preg_match('!skos:broader!', $turtle)) {
      echo "Erreur sur broader sur $clid<br>\n";
      echo "<pre>",str_replace(['<'],['&lt;'], $turtle),"</pre>\n";
      die("ligne ".__LINE__);
    }
    
    $pattern = "!skos:hasTopConcept <$uricodelist/$clid/([^>]*)>\.!";
    while (preg_match($pattern, $turtle, $matches)) {
      //echo "<pre>"; print_r($matches); echo "</pre>\n";
      $codelists[$clid]['hasTopConcept'][] = "$clid:$matches[1]";
      $turtle = preg_replace($pattern, '', $turtle, 1);
    }
    echo "<pre>",str_replace(['<'],['&lt;'], $turtle),"</pre>\n";
    //echo "<pre>",Yaml::dump($codelists, 999, 2),"</pre>\n";
    
    if (isset($codelists[$clid]['hasTopConcept'])) {
      $codelists[$clid]['hasTopConcept'] = array_reverse($codelists[$clid]['hasTopConcept']);
      foreach ($codelists[$clid]['hasTopConcept'] as $cid) {
        $concepts = array_merge($concepts, readconcept($cid, $uricodelist));
      }
    }
    //echo "<pre>",Yaml::dump(['schemes'=> array_reverse($codelists), 'concepts'=> array_reverse($concepts)], 999, 2),"</pre>\n";
    //die("ligne ".__LINE__);
  }
  $contents = ['schemes'=> array_reverse($codelists), 'concepts'=> array_reverse($concepts)];
  file_put_contents('codelist.pser', serialize($contents)); 
  echo "<pre>",Yaml::dump($contents, 999, 2),"</pre>\n";
}
else {
  echo "<pre>",Yaml::dump(unserialize(file_get_contents('codelist.pser')), 999, 2),"</pre>\n";
}

