<?php
// dlsot.php - constituition des spatialobjecttypes de inspire-datamodel à partir de docinspire
// 4-5/7/2018

ini_set('memory_limit', '1024M');
ini_set('max_execution_time', 600);

require_once __DIR__.'/../../vendor/autoload.php';
require_once __DIR__.'/readcache.inc.php';

use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Yaml\Exception\ParseException;

echo "<!DOCTYPE HTML><html><head><meta charset='UTF-8'><title>dlsot</title></head><body>\n";
$eutext = 'http://uri.docinspire.eu/eutext';
$sot = 'http://uri.docinspire.eu/eutext/spatialobjecttype';

if (!is_file('sot.pser')) {
  $turtle = readcache('http://turtle.docinspire.eu/eutext/spatialobjecttype');
  //echo "<pre>",str_replace(['<'],['&lt;'], $turtle),"</pre>\n";
  $pattern = "!<$sot> skos:hasTopConcept <$sot/([^.]*)>\.!";
  $spatialobjecttypes = [];
  while (preg_match($pattern, $turtle, $matches)) {
    //echo "<pre>"; print_r($matches); echo "</pre>\n";
    $spatialobjecttypes[$matches[1]] = ['type'=> ['spatialobjecttype']];
    $turtle = preg_replace($pattern, '', $turtle, 1);
  }
  //echo "<pre>spatialobjecttypes="; print_r($spatialobjecttypes); echo "</pre>\n";

  foreach (array_keys($spatialobjecttypes) as $sotid) {
    $turtle = readcache('http://turtle.docinspire.eu/eutext/spatialobjecttype'."/$sotid");
    $turtle = preg_replace("!<$sot/$sotid> !", '', $turtle);
    //echo "<pre>",str_replace(['<'],['&lt;'], $turtle),"</pre>\n";
    $pattern = "!skos:broader <$eutext/(theme|package|model)/([^>]*)>\.!";
    if (!preg_match($pattern, $turtle, $matches)) {
      echo "Erreur sur theme|package|model pour le spatialobjecttype $sotid<br>\n";
      echo "<pre>",str_replace(['<'],['&lt;'], $turtle),"</pre>\n";
      die();
    }
    //$spatialobjecttypes[$sotid][$matches[1]] = $matches[2];
    $spatialobjecttypes[$sotid]['domain'] = ["$matches[1]-$matches[2]"];
    $turtle = preg_replace($pattern, '', $turtle, 1);
    
    foreach (['prefLabel','definition'] as $tag) {
      $pattern = "!skos:$tag \"([^\"]*)\"@(fr|en)\.!";
      while (preg_match($pattern, $turtle, $matches)) {
        //echo "<pre>"; print_r($matches); echo "</pre>\n";
        $spatialobjecttypes[$sotid][$tag][$matches[2]] = $matches[1];
        $turtle = preg_replace($pattern, '', $turtle, 1);
      }
    }
    
    $pattern = "!skos:broader <$eutext/(spatialobjecttype|externaltype)/([^>]*)>\.!";
    while (preg_match($pattern, $turtle, $matches)) {
      //echo "<pre>"; print_r($matches); echo "</pre>\n";
      $spatialobjecttypes[$sotid]['super-classes'][] = $matches[2];
      $turtle = preg_replace($pattern, '', $turtle, 1);
    }
    
    $pattern = '!skos:broader <http://uri.docinspire.eu/eutextproperty/typeproperty/abstracttype>\.!';
    if (preg_match($pattern, $turtle)) {
      $turtle = preg_replace($pattern, '', $turtle, 1);
      $spatialobjecttypes[$sotid]['abstracttype'] = 'abstracttype';
    }
    else
      $spatialobjecttypes[$sotid]['abstracttype'] = 'notAbstracttype';
    
    $pattern = "!skos:broader <$eutext/requirement/[^>]*>\.!";
    while (preg_match($pattern, $turtle)) {
      $turtle = preg_replace($pattern, '', $turtle, 1);
    }
    
    if (preg_match('!skos:broader!', $turtle)) {
      echo "Erreur sur broader sur $sotid<br>\n";
      echo "<pre>",str_replace(['<'],['&lt;'], $turtle),"</pre>\n";
      die("ligne ".__LINE__);
    }
    
    foreach (['a'=>'attritutes','r'=>'relations'] as $prefix => $field) {
      $pattern = "!skos:hasTopConcept <$sot/$sotid/$prefix([^>]*)>\.!";
      while (preg_match($pattern, $turtle, $matches)) {
        //echo "<pre>"; print_r($matches); echo "</pre>\n";
        $spatialobjecttypes[$sotid][$field][$matches[1]] = null;
        $turtle = preg_replace($pattern, '', $turtle, 1);
      }
    }
    
    foreach (['a'=>'attritutes','r'=>'relations'] as $prefix => $field) {
      if (isset($spatialobjecttypes[$sotid][$field]))
        foreach ($spatialobjecttypes[$sotid][$field] as $attr=>$value) {
          $turtle = readcache("http://turtle.docinspire.eu/eutext/spatialobjecttype/$sotid/$prefix$attr");
          //echo "<pre>",str_replace(['<'],['&lt;'], $turtle),"</pre>\n";
          //die("FIN");
          $pattern = "!skos:definition \"([^\"]*)\"@(fr|en)\.!";
          while (preg_match($pattern, $turtle, $matches)) {
            $spatialobjecttypes[$sotid][$field][$attr]['definition'][$matches[2]] = $matches[1];
            $turtle = preg_replace($pattern, '', $turtle, 1);
          }
          $pattern = "!<$sot/$sotid/$prefix$attr> skos:broader"
              ." <$eutext/(codelist|externaltype|spatialobjecttype|datatype|unknowntype|enum|uniontype)/([^>]*)>\.!";
          if (!preg_match($pattern, $turtle, $matches)) {
            echo "Erreur sur type pour le attr/rel $sotid/$prefix$attr<br>\n";
            echo "<pre>",str_replace(['<'],['&lt;'], $turtle),"</pre>\n";
            die("ligne ".__LINE__);
          }
          $turtle = preg_replace($pattern, '', $turtle, 1);
          $spatialobjecttypes[$sotid][$field][$attr]['type'][$matches[1]] = $matches[2];
                    
          $pattern = "!<$sot/$sotid/$prefix$attr> skos:broader"
              ." <http://uri.docinspire.eu/eutextproperty/voidability/voidable>\.!";
          if (preg_match($pattern, $turtle)) {
            $turtle = preg_replace($pattern, '', $turtle, 1);
            $spatialobjecttypes[$sotid][$field][$attr]['voidability'] = 'voidable';
          }
          else
            $spatialobjecttypes[$sotid][$field][$attr]['voidability'] = 'notVoidable';
                    
          $pattern = "!<$sot/$sotid/$prefix$attr> skos:broader!";
          if (preg_match($pattern, $turtle)) {
            echo "Erreur sur broader sur $sotid/$prefix$attr<br>\n";
            echo "<pre>",str_replace(['<'],['&lt;'], $turtle),"</pre>\n";
            die("ligne ".__LINE__);
          }
        }
    }
  }
  $spatialobjecttypes = array_reverse($spatialobjecttypes);
  //echo "<pre>spatialobjecttypes="; print_r($spatialobjecttypes); echo "</pre>\n";
  file_put_contents('sot.pser', serialize($spatialobjecttypes)); 
}
else
  $spatialobjecttypes = unserialize(file_get_contents('sot.pser'));
//echo "<pre>spatialobjecttypes="; print_r($spatialobjecttypes); echo "</pre>\n";

echo "<pre>",Yaml::dump($spatialobjecttypes, 999, 2),"</pre>\n";

