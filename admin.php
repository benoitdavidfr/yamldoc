<?php
/*PhpDoc:
name: admin.php
title: admin.php - permet de visualiser l'ensemble des docs en affichant la hiérarchie des catalogues
doc: |
  permet de visualiser l'ensemble des docs en affichant la hiérarchie des catalogues
journal:
  19/5/2018:
    ajout consultation des protections
  12/5/2018:
    refonte
*/
require_once __DIR__.'/yd.inc.php';

echo "<!DOCTYPE HTML><html><head><meta charset='UTF-8'><title>admin</title></head><body>\n";

// [ docid=> [ 'doc'=> objet doc, 'ext'=> ext, 'ssdir'=> ssdir, 'catalogs'?=> [ catalogid ], 'shown'?=> 1 ] ]
$docs = [];

// $docpath est le chemin Unix de la racine des documents
// $ssdir est le chemin relatif d'un répertoire
function scan(string $docpath, string $ssdir='') {
  global $docs;
  $dirpath = $docpath.($ssdir ? '/'.$ssdir : '');
  $wd = opendir($dirpath);
  while (false !== ($entry = readdir($wd))) {
    //echo "$entry a traiter<br>\n";
    if (in_array($entry, ['.','..','.git','.htaccess']))
      continue;
    elseif (is_dir($docpath.'/'.($ssdir ? "$ssdir/$entry" : $entry)))
      scan($docpath, $ssdir ? "$ssdir/$entry" : $entry);
    elseif (preg_match('!^(.*)\.(yaml|php)$!', $entry, $matches)) {
      $docid = ($ssdir ? $ssdir.'/' : '').$matches[1];
      $docs[$docid]['doc'] = new_yamlDoc($docid);
      if (!$docs[$docid]['doc'])
        echo "Erreur new_yamlDoc($docid)<br>\n";
      $docs[$docid]['ssdir'] = $ssdir;
      $docs[$docid]['ext'] = $matches[2];
      $docs[$docid]['size'] = filesize($docpath.'/'.($ssdir ? "$ssdir/" : '').$entry);
    }
    else
      echo "$entry non traite<br>\n";
  }
  closedir($wd);
}
scan('docs');

foreach ($docs as $docid => $doc) {
  if (in_array(get_class($doc['doc']),['YamlCatalog','YamlHomeCatalog'])) {
    $ssdir = ($doc['ssdir'] ? $doc['ssdir'].'/' : '');
    foreach ($doc['doc']->contents as $childId => $d) {
      if (isset($docs[$ssdir.$childId])) {
        if (isset($docs[$ssdir.$childId]['catalogs']))
          $docs[$ssdir.$childId]['catalogs'][] = $docid;
        else
          $docs[$ssdir.$childId]['catalogs'] = [ $docid ];
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
  if ($doc['doc']->title)
    $title = $doc['doc']->title;
  elseif (isset($doc['doc']->phpDoc['title']))
    $title = $doc['doc']->phpDoc['title'];
  $nbcat = isset($doc['catalogs']) ? count($doc['catalogs']) : 0;
  echo "<li>$title ($id) [$nbcat]\n";
  if (isset($doc['shown'])) {
    echo " <b>/already shown/</b>\n";
    return;
  }
  if (in_array(get_class($doc['doc']),['YamlCatalog','YamlHomeCatalog'])) {
    echo "<ul>";
    foreach ($doc['doc']->contents as $sid => $sitem) {
      if (isset($sitem['title']))
        show($sid, $sitem['title']);
      else
        show($sid);
    }
    echo "</ul>";
  }
  $docs[$id]['shown'] = 1;
}

if (!isset($_GET['action'])) {
  echo "<b>Arborescences</b>:<ul>\n";
  foreach ($docs as $id => $doc) {
    if (!isset($doc['catalogs']))
      show($id);
  }
  echo "</ul>\n";

  echo "<b>Notes:</b><ul>
    <li>les titres ou noms en gras ne correspondent à aucun fichier
    <li>le nombre entre crochets est le nombre de fois où le document apparait dans un catalogue
  </ul>";
}
elseif ($_GET['action']=='prot') {
  // affichage de la liste des docs protégés et de leur protection
  echo "<ul>";
  foreach ($docs as $id => $doc) {
    if ($doc['doc']->yamlPassword || $doc['doc']->authorizedReaders || $doc['doc']->authorizedWriters) {
      echo '<li>',$doc['doc']->title ? $doc['doc']->title : '', " ($id) ";
      echo $doc['doc']->yamlPassword ? 'P':'';
      echo $doc['doc']->authorizedReaders ? 'R':'';
      echo $doc['doc']->authorizedWriters ? 'W':'';
    }
  }
  echo "</ul>";
}
elseif ($_GET['action']=='stats') {
  $countPerExt = [];
  $sizePerExt = [];
  foreach ($docs as $id => $doc) {
    if (!isset($countPerExt[$doc['ext']])) {
      $countPerExt[$doc['ext']] = 0;
      $sizePerExt[$doc['ext']] = 0;
    }
    $countPerExt[$doc['ext']]++;
    $sizePerExt[$doc['ext']] += $doc['size'];
  }
  echo count($docs)," documents<br>\n";
  echo "countPerExt = "; print_r($countPerExt); echo "<br>\n";
  echo "sizePerExt = "; print_r($sizePerExt); echo "<br>\n";
}
else {
  echo "Erreur action inconnue<br>\n";
}

echo "<b>Menu:</b><br><ul>
  <li><a href='?'>consulter les arborescences de documents</a>
  <li><a href='?action=prot'>consulter les protections</a>
  <li><a href='?action=stats'>calculer les stats</a>
</ul>\n";
