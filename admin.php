<?php
/*PhpDoc:
name: admin.php
title: admin.php - permet de visualiser l'ensemble des docs en affichant la hiérarchie des catalogues
doc: |
  permet de visualiser l'ensemble des docs en affichant la hiérarchie des catalogues
*/
require_once __DIR__.'/../vendor/autoload.php';
use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Yaml\Exception\ParseException;

echo "<!DOCTYPE HTML><html><head><meta charset='UTF-8'><title>admin</title></head><body>\n";

$docs = [];
$wd = opendir('docs');
while (false !== ($entry = readdir($wd))) {
  if (!preg_match('!^(.*)\.yaml$!', $entry, $matches))
    continue;
  //echo "$entry<br>\n";
  $name = $matches[1];
  $doc = file_get_contents("docs/$entry");

  try {
    $yaml = Yaml::parse($doc);
    $docs[$name] = ['yaml'=> $yaml];
  } catch (ParseException $exception) {
    printf("%s n'est pas un fichier Yaml: %s<br>\n", $name, $exception->getMessage());
    $docs[$name] = ['text'=> $doc];
  }
}
closedir($wd);

foreach ($docs as $pname => $doc) {
  if (isset($doc['yaml']['yamlClass']) and in_array($doc['yaml']['yamlClass'], ['YamlCatalog','YamlHomeCatalog'])) {
    foreach ($doc['yaml']['contents'] as $cname => $d) {
      if (isset($docs[$cname])) {
        if (isset($docs[$cname]['catalogs']))
          $docs[$cname]['catalogs'][] = $pname;
        else
          $docs[$cname]['catalogs'] = [ $pname ];
      }
    }
  }
}
//echo "<pre>docs="; print_r($docs);

function show(array $docs, string $key, string $title=null) {
  if (!isset($docs[$key])) {
    
    echo "<li><b>",$title ? "$title ($key)" : $key,"</b>";
    return;
  }
  $doc = $docs[$key];
  if (isset($doc['yaml']['title']))
    $title = $doc['yaml']['title'];
  $nbcat = isset($doc['catalogs']) ? count($doc['catalogs']) : 0;
  $istext = isset($doc['text']);
  echo "<li>",$istext?'<i>':'',
       "<a href='index.php?doc=$key'>",$title ? $title : $key,"</a>",
       $istext?'</i>':'',
       " ($nbcat)\n";
  if (isset($doc['yaml']['type']) and ($doc['yaml']['type']=='catalog')) {
    echo "<ul>";
    foreach ($doc['yaml']['contents'] as $skey => $sdoc) {
      if (isset($sdoc['title']))
        show($docs, $skey, $sdoc['title']);
      else
        show($docs, $skey);
    }
    echo "</ul>";
  }
}

foreach ($docs as $key => $doc) {
  echo "<ul>";
  if (!isset($doc['catalogs']))
    show($docs, $key);
  echo "</ul>";
}

echo "<b>Notes:</b><ul>
  <li>les titres ou noms en italique correspondent à des fichiers qui ne sont pas au format Yaml
  <li>les titres ou noms en gras ne correspondent à aucun fichier
  <li>le nombre entre parenthèse est le nombre de fois où le document apparait dans un catalogue
</ul>";