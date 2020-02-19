<?php
/*PhpDoc:
name: dlsot.php
title: dlsot.php - constituition des spatialobjecttypes de inspire-datamodel à partir de docinspire
doc: |
journal: |
  16/7/2018:
    - remplacement du champ definition des attributs par label
    - correction d'un bug
  4-7/7/2018:
    création
includes: [ readcache.inc.php ]
*/
ini_set('memory_limit', '1024M');
ini_set('max_execution_time', 600);

require_once __DIR__.'/../vendor/autoload.php';
require_once __DIR__.'/readcache.inc.php';

use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Yaml\Exception\ParseException;

if (php_sapi_name()<>'cli')
  echo "<!DOCTYPE HTML><html><head><meta charset='UTF-8'><title>dlsot</title></head><body><pre>\n";

function readConcept(string $stag, string $sid, string $prefix, string $attr) {
  $eutext = 'http://uri.docinspire.eu/eutext';
  $urisot = "$eutext/$stag";
  $turtle = readcache('http://docinspire.eu/get.php?fmt=ttl&uri='.urlencode("$urisot/$sid/$prefix$attr"));
  //echo "<pre>",str_replace(['<'],['&lt;'], $turtle),"</pre>\n";
  //die("FIN");
  $pattern = "!skos:definition \"([^\"]*)\"@(..)\.!";
  while (preg_match($pattern, $turtle, $matches)) {
    $turtle = preg_replace($pattern, '', $turtle, 1);
    if (in_array($matches[2], ['fr','en']))
      $concept['label'][$matches[2]] = $matches[1];
  }
  $pattern = "!<$urisot/$sid/$prefix$attr> skos:broader"
      ." <$eutext/(codelist|externaltype|spatialobjecttype|datatype|unknowntype|enum|uniontype)/([^>]*)>\.!";
  if (!preg_match($pattern, $turtle, $matches)) {
    echo "Erreur sur type pour le attr/rel $sid/$prefix$attr<br>\n";
    echo "<pre>",str_replace(['<'],['&lt;'], $turtle),"</pre>\n";
    die("ligne ".__LINE__);
  }
  $turtle = preg_replace($pattern, '', $turtle, 1);
  $concept['type'][$matches[1]] = $matches[2];
            
  $pattern = "!<$urisot/$sid/$prefix$attr> skos:broader"
      ." <http://uri.docinspire.eu/eutextproperty/voidability/voidable>\.!";
  if (preg_match($pattern, $turtle)) {
    $turtle = preg_replace($pattern, '', $turtle, 1);
    $concept['voidability'] = 'voidable';
  }
  else
    $concept['voidability'] = 'notVoidable';
            
  $pattern = "!<$urisot/$sid/$prefix$attr> skos:broader!";
  if (preg_match($pattern, $turtle)) {
    echo "Erreur sur broader sur $sid/$prefix$attr<br>\n";
    echo "<pre>",str_replace(['<'],['&lt;'], $turtle),"</pre>\n";
    die("ligne ".__LINE__);
  }
  return $concept;
}

function readScheme(string $stag, string $sid): array {
  $eutext = 'http://uri.docinspire.eu/eutext';
  $urisot = "$eutext/$stag";
  $scheme = ['type'=> [ $stag ]];
  $turtle = readcache('http://docinspire.eu/get.php?fmt=ttl&uri='.urlencode("$urisot/$sid"));
  $turtle = preg_replace("!<$urisot/$sid> !", '<scheme> ', $turtle);
  //echo str_replace(['<'],['&lt;'], $turtle);
  
  $pattern = "!<scheme> skos:broader <$eutext/(theme|package|model)/([^>]*)>\.!";
  if (!preg_match($pattern, $turtle, $matches)) {
    echo "Erreur sur theme|package|model pour le $stag $sid<br>\n";
    echo str_replace(['<'],['&lt;'], $turtle);
    die("ligne ".__LINE__);
  }
  $turtle = preg_replace($pattern, '', $turtle, 1);
  $scheme['domain'] = ["$matches[1]-$matches[2]"];
  
  // extraction prefLabel|definition contenant éventuelle des \"
  $pattern = '!<scheme> skos:(prefLabel|definition) "([^\\"]*(\\\\"[^\\"]*)*)"@(..)\.!';
  //echo "pattern=$pattern\n";
  while (preg_match($pattern, $turtle, $matches)) {
    //echo "matches="; print_r($matches);
    $turtle = preg_replace($pattern, '', $turtle, 1);
    if (in_array($matches[4], ['fr','en']))
      $scheme[$matches[1]][$matches[4]] = str_replace('\"','"',$matches[2]);
  }
  
  // vérification de l'extraction prefLabel|definition
  if (preg_match('!<scheme> skos:(prefLabel|definition) !', $turtle)) {
    //echo "\n<b>Erreur sur prefLabel|definition pour le $stag $sid</b>\n";
    echo str_replace(['<'],['&lt;'], $turtle);
    die("ligne ".__LINE__);
  }
    
  $pattern = '!<scheme> dc:identifier "([^"]*)"@(..)\.!';
  while (preg_match($pattern, $turtle, $matches)) {
    $turtle = preg_replace($pattern, '', $turtle, 1);
    if (in_array($matches[2], ['fr','en']))
      $scheme['source']['eutext'][$matches[2]] = $matches[1];
  }
  
  //<scheme> skos:broader <http://uri.docinspire.eu/eutext/spatialobjecttype/tn:TransportLinkSequence>.
  $pattern = "!<scheme> skos:broader <$eutext/(spatialobjecttype|externaltype)/([^>]*)>\.!";
  while (preg_match($pattern, $turtle, $matches)) {
    //echo "<pre>"; print_r($matches); echo "</pre>\n";
    $turtle = preg_replace($pattern, '', $turtle, 1);
    $scheme['subtypeOf'][] = $matches[2];
  }
    
  $pattern = "!<scheme> skos:broader <http://uri.docinspire.eu/eutextproperty/typeproperty/"
            ."(abstracttype|associationclass)>\.!";
  if (preg_match($pattern, $turtle, $matches)) {
    $turtle = preg_replace($pattern, '', $turtle, 1);
    $scheme['property'][] = $matches[1];
  }
  
  $pattern = "!skos:broader <$eutext/requirement/[^>]*>\.!";
  while (preg_match($pattern, $turtle)) {
    $turtle = preg_replace($pattern, '', $turtle, 1);
  }
  
  if (preg_match('!<scheme> skos:broader!', $turtle)) {
    echo "Erreur sur broader sur $sotid<br>\n";
    echo "<pre>",str_replace(['<'],['&lt;'], $turtle),"</pre>\n";
    die("ligne ".__LINE__);
  }
  
  foreach (['a'=>'attributes','r'=>'relations'] as $prefix => $field) {
    $pattern = "!skos:hasTopConcept <$urisot/$sid/$prefix([^>]*)>\.!";
    while (preg_match($pattern, $turtle, $matches)) {
      $turtle = preg_replace($pattern, '', $turtle, 1);
      $scheme[$field][$matches[1]] = readConcept($stag, $sid, $prefix, $matches[1]);
    }
  }
  return $scheme;
}
  
// test de l'extraction d'une définition contennant de \"
if (0) {
  //echo Yaml::dump(readScheme('spatialobjecttype', 'act-core:ActivityComplex'), 999, 2);
  echo Yaml::dump(readScheme('spatialobjecttype', 'tn-ro:RoadLinkSequence'), 999, 2);
  die("ligne ".__LINE__);
}
elseif (true || !is_file('sot.pser')) {
  $stag = 'spatialobjecttype';
  $eutext = 'http://uri.docinspire.eu/eutext';
  $urisot = "$eutext/$stag";
  $turtle = readcache('http://docinspire.eu/get.php?fmt=ttl&uri='.urlencode($urisot));
  //echo "<pre>",str_replace(['<'],['&lt;'], $turtle),"</pre>\n";
  $pattern = "!<$urisot> skos:hasTopConcept <$urisot/([^.]*)>\.!";
  $sots = [];
  while (preg_match($pattern, $turtle, $matches)) {
    //echo "<pre>"; print_r($matches); echo "</pre>\n";
    $turtle = preg_replace($pattern, '', $turtle, 1);
    $sots[$matches[1]] = readScheme($stag, $matches[1]);
  }
  //echo "<pre>sots="; print_r($sots); echo "</pre>\n";

  $sots = array_reverse($sots);
  //echo "<pre>spatialobjecttypes="; print_r($spatialobjecttypes); echo "</pre>\n";
  file_put_contents('sot.pser', serialize($sots)); 
}
else
  $sots = unserialize(file_get_contents('sot.pser'));
//echo "<pre>spatialobjecttypes="; print_r($spatialobjecttypes); echo "</pre>\n";

echo Yaml::dump($sots, 999, 2);

