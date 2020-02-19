<?php
/*PhpDoc:
name: dldomain.php
title: dldomain.php - constituition des domaines de inspire-datamodel à partir de docinspire
doc: |
  génère les fichiers themes.pser, models.pser et packages.pser
  et en sortie un texte Yaml des domaines à include dans inspire-datamodel.yaml
journal: |
  4-5/7/2018:
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
  echo "<!DOCTYPE HTML><html><head><meta charset='UTF-8'><title>dldomain</title></head><body><pre>\n";
  
if (true || !is_file('themes.pser')) { // themes
  $turtle = readcache('http://docinspire.eu/get.php?fmt=ttl&uri='.urlencode('http://uri.docinspire.eu/eutext/theme'));
  //echo "<pre>",str_replace(['<'],['&lt;'], $turtle),"</pre>\n";

  $themes = [];
  $pattern = '!<http://uri.docinspire.eu/eutext/theme> skos:hasTopConcept <http://uri.docinspire.eu/eutext/theme/([^.]*)>\.!';
  while (preg_match($pattern, $turtle, $matches)) {
    //echo "<pre>"; print_r($matches); echo "</pre>\n";
    $themes[$matches[1]] = null;
    $turtle = preg_replace($pattern, '', $turtle, 1);
  }
  //echo "<pre>themes="; print_r($themes); echo "</pre>\n";

  foreach (array_keys($themes) as $themeid) {
    $turtle = readcache('http://docinspire.eu/get.php?fmt=ttl&uri='
                         .urlencode("http://uri.docinspire.eu/eutext/theme/$themeid"));
    $turtle = preg_replace("!<http://uri.docinspire.eu/eutext/theme/$themeid> !", '', $turtle);
    //echo "<pre>",str_replace(['<'],['&lt;'], $turtle),"</pre>\n";
    if (!preg_match('!skos:broader <http://uri.docinspire.eu/eutext/\?CELEX=02010R1089&annex=([^>]*)>\.!', $turtle, $matches)) {
      echo "Erreur sur annex pour le theme $themeid<br>\n";
      echo "<pre>",str_replace(['<'],['&lt;'], $turtle),"</pre>\n";
      die();
    }
    $themes[$themeid]['annex'] = $matches[1];
  
    $pattern = '!skos:prefLabel "([^"]*)"@(fr|en).!';
    while(preg_match($pattern, $turtle, $matches)) {
      $themes[$themeid]['prefLabel'][$matches[2]] = $matches[1];
      $turtle = preg_replace($pattern, '', $turtle, 1);
    }
    //die("FIN");
  }
  //echo "<pre>themes="; print_r($themes); echo "</pre>\n";
  file_put_contents('themes.pser', serialize($themes)); 
}
else
  $themes = unserialize(file_get_contents('themes.pser'));
//echo "<pre>themes="; print_r($themes); echo "</pre>\n";

if (true || !is_file('models.pser')) { // models
  $turtle = readcache('http://docinspire.eu/get.php?fmt=ttl&uri='.urlencode('http://uri.docinspire.eu/eutext/model'));
  //echo "<pre>",str_replace(['<'],['&lt;'], $turtle),"</pre>\n";

  $models = [];
  $pattern = '!<http://uri.docinspire.eu/eutext/model> skos:hasTopConcept <http://uri.docinspire.eu/eutext/model/([^.]*)>\.!';
  while (preg_match($pattern, $turtle, $matches)) {
    //echo "<pre>"; print_r($matches); echo "</pre>\n";
    $models[$matches[1]] = null;
    $turtle = preg_replace($pattern, '', $turtle, 1);
  }
  //echo "<pre>models="; print_r($models); echo "</pre>\n";

  foreach (array_keys($models) as $modelid) {
    $turtle = readcache('http://docinspire.eu/get.php?fmt=ttl&uri='
                         .urlencode("http://uri.docinspire.eu/eutext/model/$modelid"));
    $turtle = preg_replace("!<http://uri.docinspire.eu/eutext/model/$modelid> !", '', $turtle);
    //echo "<pre>",str_replace(['<'],['&lt;'], $turtle),"</pre>\n";
    if (!preg_match('!skos:broader <http://uri.docinspire.eu/eutext/\?CELEX=02010R1089&annex=([^>]*)>\.!', $turtle, $matches)) {
      echo "Erreur sur annex pour le model $modelid<br>\n";
      echo "<pre>",str_replace(['<'],['&lt;'], $turtle),"</pre>\n";
      die();
    }
    $models[$modelid]['annex'] = $matches[1];
  
    $pattern = '!skos:prefLabel "([^"]*)"@(fr|en).!';
    while(preg_match($pattern, $turtle, $matches)) {
      $models[$modelid]['prefLabel'][$matches[2]] = $matches[1];
      $turtle = preg_replace($pattern, '', $turtle, 1);
    }
    //die("FIN");
  }
  //echo "<pre>models="; print_r($models); echo "</pre>\n";
  file_put_contents('models.pser', serialize($models)); 
}
else
  $models = unserialize(file_get_contents('models.pser'));
//echo "<pre>models="; print_r($models); echo "</pre>\n";

if (true || !is_file('packages.pser')) { // packages
  $turtle = readcache('http://docinspire.eu/get.php?fmt=ttl&uri='.urlencode('http://uri.docinspire.eu/eutext/package'));
  //echo "<pre>",str_replace(['<'],['&lt;'], $turtle),"</pre>\n";

  $packages = [];
  $pattern = '!<http://uri.docinspire.eu/eutext/package> skos:hasTopConcept <http://uri.docinspire.eu/eutext/package/([^.]*)>\.!';
  while (preg_match($pattern, $turtle, $matches)) {
    //echo "<pre>"; print_r($matches); echo "</pre>\n";
    $packages[$matches[1]] = null;
    $turtle = preg_replace($pattern, '', $turtle, 1);
  }
  //echo "<pre>packages="; print_r($packages); echo "</pre>\n";

  foreach (array_keys($packages) as $id) {
    $turtle = readcache('http://docinspire.eu/get.php?fmt=ttl&uri='
                         .urlencode("http://uri.docinspire.eu/eutext/package/$id"));
    $turtle = preg_replace("!<http://uri.docinspire.eu/eutext/package/$id> !", '', $turtle);
    //echo "<pre>",str_replace(['<'],['&lt;'], $turtle),"</pre>\n";
    if (!preg_match('!skos:broader <http://uri.docinspire.eu/eutext/(theme|model)/([^>]*)>\.!', $turtle, $matches)) {
      if (!preg_match('!skos:broader <http://uri.docinspire.eu/eutext/\?CELEX=02010R1089&(annex)=([^>]*)>\.!', $turtle, $matches)) {
        echo "Erreur sur theme|model|annex pour le theme $themeid<br>\n";
        echo "<pre>",str_replace(['<'],['&lt;'], $turtle),"</pre>\n";
        die();
      }
    }
    $packages[$id][$matches[1]] = $matches[2];
  
    $pattern = '!skos:prefLabel "([^"]*)"@(fr|en).!';
    while(preg_match($pattern, $turtle, $matches)) {
      $packages[$id]['prefLabel'][$matches[2]] = $matches[1];
      $turtle = preg_replace($pattern, '', $turtle, 1);
    }
  }
  //echo "<pre>packages="; print_r($packages); echo "</pre>\n";
  file_put_contents('packages.pser', serialize($packages)); 
}
else
  $packages = unserialize(file_get_contents('packages.pser'));
//echo "<pre>packages="; print_r($packages); echo "</pre>\n";

$domains = [
  'annexI'=>[
    'prefLabel'=>[
      'fr'=>"Annexe I : TYPES, DÉFINITIONS ET EXIGENCES COMMUNS",
      'en'=>"Annex I : COMMON TYPES, DEFINITIONS AND REQUIREMENTS",
    ],
  ],
  'annexII'=>[
    'prefLabel'=>[
      'fr'=>"Annexe II : EXIGENCES APPLICABLES AUX THÈMES DE DONNÉES GÉOGRAPHIQUES ÉNUMÉRÉS À L'ANNEXE I DE LA DIRECTIVE 2007/2/CE",
      'en'=>"Annex II : REQUIREMENTS FOR SPATIAL DATA THEMES LISTED IN ANNEX I TO DIRECTIVE 2007/2/EC",
    ],
  ],
  'annexIII'=>[
    'prefLabel'=>[
      'fr'=>"Annexe III : EXIGENCES APPLICABLES AUX THÈMES DE DONNÉES GÉOGRAPHIQUES ÉNUMÉRÉS À L'ANNEXE II DE LA DIRECTIVE 2007/2/CE",
      'en'=>"Annex III : REQUIREMENTS FOR SPATIAL DATA THEMES LISTED IN ANNEX II TO DIRECTIVE 2007/2/EC",
    ],
  ],
  'annexIV'=>[
    'prefLabel'=>[
      'fr'=>"Annexe IV : EXIGENCES APPLICABLES AUX THÈMES DE DONNÉES GÉOGRAPHIQUES ÉNUMÉRÉS À L'ANNEXE III DE LA DIRECTIVE 2007/2/CE",
      'en'=>"Annex IV : REQUIREMENTS FOR SPATIAL DATA THEMES LISTED IN ANNEX III TO DIRECTIVE 2007/2/EC",
    ],
  ],
];

foreach (array_reverse($models) as $id=>$model) {
  //echo "<pre>model="; print_r($model); echo "</pre>\n";
  $model['broader'] = ["annex$model[annex]"];
  unset($model['annex']);
  $domains["model-$id"] = $model;
}
foreach (array_reverse($themes) as $id=>$theme) {
  //echo "<pre>theme="; print_r($theme); echo "</pre>\n";
  $domains["theme-$id"] = [
    'prefLabel'=>[
      'fr'=> "Thème ".$theme['prefLabel']['fr'],
      'en'=> "Theme ".$theme['prefLabel']['en'],
    ],
    'broader'=> ["annex$theme[annex]"],
  ];
}
foreach (array_reverse($packages) as $id=>$package) {
  //echo "<pre>model="; print_r($package); echo "</pre>\n";
  if (isset($package['annex'])) {
    $broader = ["annex$package[annex]"];
  }
  elseif (isset($package['theme'])) {
    $broader = ["theme-$package[theme]"];
  }
  elseif (isset($package['model'])) {
    $broader = ["model-$package[model]"];
  }
  else {
    die("erreur sur package $id");
  }
  $domains["package-$id"] = [
    'prefLabel'=>[
      'fr'=> "Paquet ".$package['prefLabel']['fr'],
      'en'=> "Package ".$package['prefLabel']['en'],
    ],
    'broader'=> $broader,
  ];
}

//echo "<pre>domains="; print_r($domains); echo "</pre>\n";
echo Yaml::dump(['domains'=> $domains], 999, 2);
