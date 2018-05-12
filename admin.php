<?php
/*PhpDoc:
name: admin.php
title: admin.php - permet de visualiser l'ensemble des docs en affichant la hiérarchie des catalogues
doc: |
  permet de visualiser l'ensemble des docs en affichant la hiérarchie des catalogues
journal:
  12/5/2018:
    refonte
*/
require_once __DIR__.'/yd.inc.php';

echo "<!DOCTYPE HTML><html><head><meta charset='UTF-8'><title>admin</title></head><body>\n";

$docs = [];
$wd = opendir('docs');
while (false !== ($entry = readdir($wd))) {
  if (!preg_match('!^(.*)\.(yaml|php)$!', $entry, $matches))
    continue;
  //echo "$entry<br>\n";
  $docid = $matches[1];
  $docs[$docid]['doc'] = new_yamlDoc($docid);
}
closedir($wd);

foreach ($docs as $docid => $doc) {
  if (in_array(get_class($doc['doc']),['YamlCatalog','YamlHomeCatalog'])) {
    foreach ($doc['doc']->contents() as $childId => $d) {
      if (isset($docs[$childId])) {
        if (isset($docs[$childId]['catalogs']))
          $docs[$childId]['catalogs'][] = $docid;
        else
          $docs[$childId]['catalogs'] = [ $docid ];
      }
    }
  }
}
//echo "<pre>docs="; print_r($docs);

// affiche le doc $id
function show(string $id, string $title=null) {
  global $docs;
  if (!isset($docs[$id])) {
    echo "<li><b>",$title ? "$title ($id)" : $id,"</b>";
    return;
  }
  $doc = $docs[$id];
  if ($doc['doc']->title())
    $title = $doc['doc']->title();
  $nbcat = isset($doc['catalogs']) ? count($doc['catalogs']) : 0;
  echo "<li>$title ($id) [$nbcat]\n";
  if (isset($doc['shown'])) {
    echo " <b>/already shown/</b>\n";
    return;
  }
  if (in_array(get_class($doc['doc']),['YamlCatalog','YamlHomeCatalog'])) {
    echo "<ul>";
    foreach ($doc['doc']->contents() as $sid => $sitem) {
      if (isset($sitem['title']))
        show($sid, $sitem['title']);
      else
        show($sid);
    }
    echo "</ul>";
  }
  $docs[$id]['shown'] = 1;
}

foreach ($docs as $id => $doc) {
  echo "<ul>";
  if (!isset($doc['catalogs']))
    show($id);
  echo "</ul>";
}

echo "<b>Notes:</b><ul>
  <li>les titres ou noms en gras ne correspondent à aucun fichier
  <li>le nombre entre crochets est le nombre de fois où le document apparait dans un catalogue
</ul>";