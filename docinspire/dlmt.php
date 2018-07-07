<?php
// dlmt.php - constituition des types divers de inspire-datamodel à partir de docinspire
// 7/7/2018

ini_set('memory_limit', '1024M');
ini_set('max_execution_time', 600);

require_once __DIR__.'/../../vendor/autoload.php';
require_once __DIR__.'/readcache.inc.php';

use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Yaml\Exception\ParseException;

if (php_sapi_name()<>'cli')
  echo "<!DOCTYPE HTML><html><head><meta charset='UTF-8'><title>dlmt</title></head><body><pre>\n";

function dlmt(string $mttag) {
  $eutext = 'http://uri.docinspire.eu/eutext';
  $urimt = "http://uri.docinspire.eu/eutext/$mttag";

  if (!is_file("$mttag.pser")) {
    $turtle = readcache("http://docinspire.eu/get.php?fmt=ttl&uri=".urlencode($urimt));
    
    //echo "<pre>",str_replace(['<'],['&lt;'], $turtle),"</pre>\n";
    $pattern = "!<$urimt> skos:hasTopConcept <$urimt/([^.]*)>\.!";
    $mts = [];
    while (preg_match($pattern, $turtle, $matches)) {
      //echo "<pre>"; print_r($matches); echo "</pre>\n";
      $mts[$matches[1]] = ['type'=> [$mttag]];
      $turtle = preg_replace($pattern, '', $turtle, 1);
    }
    //echo "<pre>mts="; print_r($mts); echo "</pre>\n";
  
    foreach (array_keys($mts) as $mtid) {
      //echo "<b>$mtid</b><br>\n";
      $turtle = readcache("http://docinspire.eu/get.php?fmt=ttl&uri=".urlencode("$urimt/$mtid"));
      $turtle = preg_replace("!<$urimt/$mtid> !", '', $turtle);
      //echo "<pre>",str_replace(['<'],['&lt;'], $turtle),"</pre>\n";
      if ($mttag<>'unknowntype') {
        $pattern = "!skos:broader <$eutext/(theme|package|model)/([^>]*)>\.!";
        if (!preg_match($pattern, $turtle, $matches)) {
          echo "Erreur sur theme|package|model pour le $mttag $mtid<br>\n";
          echo "<pre>",str_replace(['<'],['&lt;'], $turtle),"</pre>\n";
          die("ligne ".__LINE__);
        }
        $mts[$mtid]['domain'] = ["$matches[1]-$matches[2]"];
        $turtle = preg_replace($pattern, '', $turtle, 1);
      }
    
      foreach (['prefLabel','definition'] as $tag) {
        $pattern = "!skos:$tag \"([^\"]*)\"@(..)\.!";
        while (preg_match($pattern, $turtle, $matches)) {
          //echo "<pre>"; print_r($matches); echo "</pre>\n";
          $turtle = preg_replace($pattern, '', $turtle, 1);
          if (in_array($matches[2], ['fr','en']))
            $mts[$mtid][$tag][$matches[2]] = $matches[1];
        }
      }
    
      $pattern = "!skos:broader <$eutext/(datatype|externaltype)/([^>]*)>\.!";
      while (preg_match($pattern, $turtle, $matches)) {
        //echo "<pre>"; print_r($matches); echo "</pre>\n";
        $mts[$mtid]['super-types'][] = $matches[2];
        $turtle = preg_replace($pattern, '', $turtle, 1);
      }
            
      $pattern = "!skos:broader <http://uri.docinspire.eu/eutextproperty/typeproperty/"
                ."(abstracttype|associationclass)>\.!";
      if (preg_match($pattern, $turtle, $matches)) {
        $turtle = preg_replace($pattern, '', $turtle, 1);
        $mts[$mtid]['typeproperty'][] = $matches[1];
      }
    
      if (preg_match('!skos:broader!', $turtle)) {
        echo "Erreur sur broader sur $mtid<br>\n";
        echo "<pre>",str_replace(['<'],['&lt;'], $turtle),"</pre>\n";
        die("ligne ".__LINE__);
      }
    
      //echo "<pre>",str_replace(['<'],['&lt;'], $turtle),"</pre>\n";
      //die("ligne ".__LINE__);
    
      foreach (['a'=>'attritutes','r'=>'relations'] as $prefix => $field) {
        $pattern = "!skos:hasTopConcept <$urimt/$mtid/$prefix([^>]*)>\.!";
        while (preg_match($pattern, $turtle, $matches)) {
          //echo "<pre>"; print_r($matches); echo "</pre>\n";
          $mts[$mtid][$field][$matches[1]] = null;
          $turtle = preg_replace($pattern, '', $turtle, 1);
        }
      }
    
      foreach (['a'=>'attritutes','r'=>'relations'] as $prefix => $field) {
        if (isset($mts[$mtid][$field]))
          foreach ($mts[$mtid][$field] as $attr=>$value) {
            $turtle = readcache("http://docinspire.eu/get.php?fmt=ttl&uri=".urlencode("$urimt/$mtid/$prefix$attr"));
            //echo "<pre>",str_replace(['<'],['&lt;'], $turtle),"</pre>\n";
            $pattern = "!skos:definition \"([^\"]*)\"@(..)\.!";
            while (preg_match($pattern, $turtle, $matches)) {
              $turtle = preg_replace($pattern, '', $turtle, 1);
              if (in_array($matches[2], ['fr','en']))
                $mts[$mtid][$field][$attr]['definition'][$matches[2]] = $matches[1];
            }
            $pattern = "!<$urimt/$mtid/$prefix$attr> skos:broader"
                ." <$eutext/(codelist|externaltype|spatialobjecttype|datatype|unknowntype|enum|uniontype)/([^>]*)>\.!";
            if (!preg_match($pattern, $turtle, $matches)) {
              echo "Erreur sur type pour le attr/rel $mtid/$prefix$attr<br>\n";
              echo "<pre>",str_replace(['<'],['&lt;'], $turtle),"</pre>\n";
              die("ligne ".__LINE__);
            }
            $turtle = preg_replace($pattern, '', $turtle, 1);
            $mts[$mtid][$field][$attr]['type'][$matches[1]] = $matches[2];
                    
            $pattern = "!<$urimt/$mtid/$prefix$attr> skos:broader"
                ." <http://uri.docinspire.eu/eutextproperty/voidability/voidable>\.!";
            if (preg_match($pattern, $turtle)) {
              $turtle = preg_replace($pattern, '', $turtle, 1);
              $mts[$mtid][$field][$attr]['voidability'] = 'voidable';
            }
            else
              $mts[$mtid][$field][$attr]['voidability'] = 'notVoidable';
            $pattern = "!<$urimt/$mtid/$prefix$attr> skos:broader!";
            if (preg_match($pattern, $turtle)) {
              echo "Erreur sur broader sur $mtid/$prefix$attr<br>\n";
              echo "<pre>",str_replace(['<'],['&lt;'], $turtle),"</pre>\n";
              die("ligne ".__LINE__);
            }
            //echo "<pre>",str_replace(['<'],['&lt;'], $turtle),"</pre>\n";
          }
      }
      //die("ligne ".__LINE__);
    }
    $mts = array_reverse($mts);
    file_put_contents("$mttag.pser", serialize($mts)); 
  }
  else
    $mts = unserialize(file_get_contents("$mttag.pser"));
  return $mts;
}

foreach(['datatype','externaltype','unknowntype','uniontype'] as $mttag) {
  echo Yaml::dump(dlmt($mttag), 999, 2);
}
