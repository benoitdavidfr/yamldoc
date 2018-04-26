<?php
/*PhpDoc:
name: ltuples.inc.php
title: ltuples.inc.php - gestionnaire de documents Yaml vus comme ensemble de listes de tuples
doc: |
  Défini la fonction showListsOfTuples() qui affiche les listes de tuples stockées dans un document
journal: |
  22/4/2018:
    création par split de index.php
*/

// extrait recursivement les liste de tuples stockées dans un Yaml et les affiche
function showListsOfTuples(array $data, string $prefix='') {
  foreach ($data as $name => $tab) {
    if (is_listOfTuples($tab)) {
      $tabname = "$prefix/$name";
      // affichage du nom de la table en titre
      if (isset($_GET['action']) and ($_GET['action']=='select') and ($_GET['tab']==$tabname))
        echo "<h2>$tabname / $_GET[key]=$_GET[val]</h2>\n";
      else
        echo "<h2>$tabname</h2>\n";
      showOneListOfTuples($tabname, $tab);
    }
    elseif (is_array($tab))
      showListsOfTuples($tab, "$prefix/$name");
  } 
}

// fonction de comparaison utilisée dans le tri d'un tableau
// $_GET['key'] indique la clé de tri pour le tableau
// $_GET['order'] vaut '1' ssi tri ascendant
function cmp(array $a, array $b) {
  $key = $_GET['key'];
  $asc = ($_GET['order']=='1');
  if (!isset($a[$key]) and !isset($b[$key]))
    return 0;
  // une valeur indéfinie est inférieure à une valeur définie
  if (!isset($a[$key]))
    return $asc ? -1 : 1;
  if (!isset($b[$key]))
    return $asc ? 1 : -1;
  
  if ($a[$key] == $b[$key])
    return 0;
  if ($asc)
    return ($a[$key] < $b[$key]) ? -1 : 1;
  else
    return ($a[$key] < $b[$key]) ? 1 : -1;
}

// affiche le tableau $tab de tuples se ressemblant
// utilisation de $_GET['action'] et $_GET['tab']
// le tableau est trié ou seuls certains tuples sont affichés si c'est demandé
// 
function showOneListOfTuples (string $tabname, array $tab) {
  $keys = []; // liste des clés d'au moins un tuple
  //echo "<pre>"; print_r($tab); echo "</pre>\n";
  foreach ($tab as $line) {
    foreach (array_keys($line) as $key) {
      if (!in_array($key, $keys))
        $keys[] = $key;
    }
  }
  //echo "<pre>"; print_r($keys); echo "</pre>\n";
  
  if (isset($_GET['action']) and ($_GET['action']=='sort') and ($_GET['tab']==$tabname)) {
    usort($tab, 'cmp');
  }
  
  echo "<table border=1>\n";
  foreach ($keys as $key)
    echo "<th><a href='?action=uniq&amp;tab=$tabname&amp;key=$key'>$key</a></th>";
  echo "\n";
  foreach ($tab as $line) {
    if (isset($_GET['action']) and ($_GET['action']=='select') and ($_GET['tab']==$tabname)) {
      if (isset($line[$_GET['key']]) and ($line[$_GET['key']] <> $_GET['val']))
        continue;
      elseif (!isset($line[$_GET['key']]) and ($_GET['val'] <> ''))
        continue;
    }
    
    echo "<tr>";
    foreach ($keys as $key) {
      if (!isset($line[$key]))
        echo "<td></td>";
      elseif (is_numeric($line[$key]))
        echo "<td align='right'>",$line[$key],"</td>";
      elseif (is_listOfTuples($line[$key])) {
        echo "<td>";
        showOneListOfTuples("$tabname/$key", $line[$key]);
        echo "</td>";
      }
      elseif (is_array($line[$key])) {
        echo "<td>";
        showDocAsTable($line[$key], "$tabname/$key");
        echo "</td>";
      }
      else
        echo "<td>",$line[$key],"</td>";
    }
    echo "</tr>\n";
  }
  echo "<tr>";
  foreach ($keys as $key) {
    echo "<td>",
         "<a href='?action=sort&amp;tab=$tabname&amp;key=$key&amp;order=1'>+</a>",
         " <a href='?action=sort&amp;tab=$tabname&amp;key=$key&amp;order=-'>-</a>",
         "</td>";
  }
  echo "</tr>\n";
  
  echo "</table>\n";
}

