<?php
/*PhpDoc:
name: admin.php
title: admin.php - permet de visualiser l'ensemble des docs en affichant la hiérarchie des catalogues
doc: |
  permet de visualiser l'ensemble des docs en affichant la hiérarchie des catalogues
journal: |
  1-2/7/2018:
    adaptation multi-store
  21/5/2018:
    correction de bugs
  19/5/2018:
    ajout consultation des protections
  12/5/2018:
    refonte
includes: [ store.inc.php, yd.inc.php, ydclasses/inc.php ]
*/
require_once __DIR__.'/yd.inc.php';
require_once __DIR__.'/store.inc.php';
require_once __DIR__.'/ydclasses/inc.php';
use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Yaml\Exception\ParseException;

ini_set('memory_limit', '1024M');
ini_set('max_execution_time', 600);

echo "<!DOCTYPE HTML><html><head><meta charset='UTF-8'><title>admin</title></head><body>\n";

if (!isset($_GET['store'])) {
  // affichage du menu
  // lecture de la liste des stores dans le fichier de configuration
  echo "choix du store :<ul>\n";
  foreach (Store::definition() as $storeid => $store)
    echo "<li><a href='?store=$storeid'>$store[title]\n";
  die("</ul>\n");
}

// [ docid=> [ 'doc'=> objet doc, 'ext'=> ext, 'ssdir'=> ssdir, 'catalogs'?=> [ catalogid ], 'shown'?=> 1 ] ]
$docs = [];

// $docpath est le chemin Unix de la racine des documents
// $ssdir est le chemin relatif d'un répertoire
function scan(string $ssdir='') {
  global $docs;
  $storepath = Store::storepath();
  echo "storepath=$storepath<br>\n";
  $dirpath = $storepath.($ssdir ? '/'.$ssdir : '');
  $wd = opendir($dirpath);
  while (false !== ($entry = readdir($wd))) {
    //echo "$entry a traiter<br>\n";
    if (in_array($entry, ['.','..','.git','.gitignore','.htaccess']))
      continue;
    elseif (is_dir($storepath.($ssdir ? "/$ssdir" : '')."/$entry"))
      scan($ssdir ? "$ssdir/$entry" : $entry);
    elseif (preg_match('!^(.*)\.(yaml|php)$!', $entry, $matches)) {
      $docid = ($ssdir ? $ssdir.'/' : '').$matches[1];
      try {
        $doc = new_doc($docid);
        if (!$doc)
          echo "Erreur new_doc($docid) ligne ",__LINE__,"<br>\n";
        elseif (get_class($doc)=='YamlData')
          $doc->shrink();
        $docs[$docid]['doc'] = $doc;
      }
      catch (ParseException $exception) {
        $docs[$docid]['doc'] = null;
        printf("<b>Analyse YAML erronée sur document %s: %s</b><br>", $docid, $exception->getMessage());
        $docs[$docid]['txt'] = ydread($docid);
      }
      $docs[$docid]['ssdir'] = $ssdir;
      $docs[$docid]['ext'] = $matches[2];
      $docs[$docid]['size'] = filesize($storepath.'/'.($ssdir ? "$ssdir/" : '').$entry);
    }
    elseif (!preg_match('!^(.*)\.(pser)$!', $entry))
      echo "$entry non traite<br>\n";
  }
  closedir($wd);
}

Store::setStoreid($_GET['store']);
scan();

// enregistrement pour chaque document des catalogues dans lesquels il est référencé
foreach ($docs as $docid => $doc) {
  if ($doc['doc'] && in_array(get_class($doc['doc']),['YamlCatalog','YamlHomeCatalog'])) {
    echo "Catalogue $docid<br>\n";
    $ssdir = ($doc['ssdir'] ? $doc['ssdir'].'/' : '');
    if ($doc['doc']->contents)
      foreach ($doc['doc']->contents as $childId => $d) {
        //echo "ssdir=$ssdir, childId=$childId<br>\n";
        if (isset($docs[$ssdir.$childId])) {
          //echo "le document $ssdir$childId existe bien<br>\n";
          if (isset($docs[$ssdir.$childId]['catalogs']))
            $docs[$ssdir.$childId]['catalogs'][] = $docid;
          else
            $docs[$ssdir.$childId]['catalogs'] = [ $docid ];
        }
        //else echo "le document $ssdir$childId n'existe PAS<br>\n";
      }
  }
}
//echo "<pre>docs="; print_r($docs);

// affiche le doc $id
function show(string $id, string $title=null) {
  global $docs;
  //echo "show($id)<br>\n";
  if (!isset($docs[$id])) {
    echo "<li><b>",$title ? "$title ($id)" : $id,"</b>";
    return;
  }
  $doc = $docs[$id];
  if ($doc['doc'] && $doc['doc']->title)
    $title = $doc['doc']->title;
  elseif (isset($doc['doc']->phpDoc['title']))
    $title = $doc['doc']->phpDoc['title'];
  $nbcat = isset($doc['catalogs']) ? count($doc['catalogs']) : 0;
  echo "<li>$title ($id) [$nbcat]\n";
  if (isset($doc['shown'])) {
    echo " <b>/already shown/</b>\n";
    return;
  }
  if ($doc['doc'] && in_array(get_class($doc['doc']),['YamlCatalog','YamlHomeCatalog'])) {
    echo "<ul>";
    //echo " catalogue $id<br>\n";
    //echo "$docid -> ",dirname($docid),"<br>\n";
    $ssdir = '';
    if (($dirname = dirname($id)) <> '.')
      $ssdir = "$dirname/";
    foreach ($doc['doc']->contents as $sid => $sitem) {
      if (isset($sitem['title']))
        show($ssdir.$sid, $sitem['title']);
      else
        show($ssdir.$sid);
    }
    echo "</ul>";
  }
  $docs[$id]['shown'] = 1;
}

// action par défaut: affichage de l'arborescence
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
// affichage de la liste des docs protégés et de leur protection
elseif ($_GET['action']=='prot') {
  echo "<ul>";
  foreach ($docs as $id => $doc) {
    if ($doc['doc']
        && ($doc['doc']->yamlPassword || $doc['doc']->authorizedReaders || $doc['doc']->authorizedWriters)) {
      echo '<li>',$doc['doc']->title ? $doc['doc']->title : '', " ($id) ";
      echo $doc['doc']->yamlPassword ? 'P':'';
      echo $doc['doc']->authorizedReaders ? 'R':'';
      echo $doc['doc']->authorizedWriters ? 'W':'';
    }
  }
  echo "</ul>";
}
// affichage de stats
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
  <li><a href='?'>retour au choix du store</a>
  <li><a href='?store=$_GET[store]&amp;action=prot'>consulter les protections</a>
  <li><a href='?store=$_GET[store]&amp;action=stats'>calculer les stats</a>
</ul>\n";
