<?php
/*PhpDoc:
name: yaml.php
title: yaml
doc: |
  - manipulation d'un fichier Yaml contenant des listes de tuples
  - représentation de chaque liste de tuples sous la forme d'un tableau
  - possibilité de sélectionner les lignes ayant une valeur donnée pour une colonne donnée
  - tri des lignes selon une colonne
  - enregistrement/lecture d'un fichier en lui affectant un non aléatoire

  L'action à réaliser est définie par le paramètre action, chaque action correspond à des paramètres spécifiques
  Le contenu Yaml est stocké dans $_SESSION['text']
  Lorsqu'il est enregistré dans un fichier, le nom du fichier est enregistré sans $_SESSION['name']
idées:
journal: |
  11/4/2018:
  - edition en mode POST
  - mise en SESSION du contenu Yaml et suppression du fichier temporaire
  - modif de l'IHM
  - enregistrement/lecture d'un fichier en lui affectant un non aléatoire
  9/4/2018:
  - ajout sous-tableaux
  8/4/2018:
  - première version
*/
session_start();
require_once __DIR__.'/../spyc/spyc2.inc.php';

echo "<!DOCTYPE HTML><html><head><meta charset='UTF-8'><title>yaml</title></head><body>\n";

// édition du contenu Yaml
if (isset($_GET['action']) and ($_GET['action']=='edit')) {
  $str = isset($_SESSION['text']) ? $_SESSION['text'] : '';
  echo <<<EOT
<table><form action="?action=store" method="post">
<tr><td><textarea name="text" rows="40" cols="80">
$str
</textarea></td></tr>
<tr><td><input type="submit" value="Envoyer"></td></tr>
</form></table>
EOT;
  die();
}

// enregistrement d'un contenu à la suite d'une édition
// on pourrait renvoyer une édition lorsque le contenu n'est pas conforme Yaml
if (isset($_GET['action']) and ($_GET['action']=='store')) {
  $_SESSION['text'] = $_POST['text'];
  if (!isset($_SESSION['name']))
    $_SESSION['name'] = uniqid();
  file_put_contents("$_SESSION[name].yaml", $_SESSION['text']);
  echo "contenu enregistré dans $_SESSION[name].yaml\n";
}

if (isset($_GET['action']) and ($_GET['action']=='read')) {
  $_SESSION['name'] = $_GET['name'];
  $_SESSION['text'] = file_get_contents("$_SESSION[name].yaml");
}

$data = null;

if (isset($_SESSION['text'])) {
  $data = spycLoadString($_SESSION['text']);
  if (!$data)
    echo "<b>Erreur spycLoad</b>\n";
}

if (isset($_GET['action']) and ($_GET['action']=='dump')) {
  echo "<pre>data = "; print_r($data); echo "</pre>\n";
  die();
}

// accès au sous-tableau défini par path
function subtab(array $data, array $path) {
  if (!$path)
    return $data;
  $name = array_shift($path);
  return subtab($data[$name], $path);
}

if (isset($_GET['action']) and ($_GET['action']=='uniq')) {
  $vals = [];
  $path = explode('/', $_GET['tab']);
  array_shift($path);
  //print_r($path); die();
  $tab = subtab($data, $path);
  foreach ($tab as $line) {
    $val = $line[$_GET['key']];
    if (!in_array($val, $vals))
      $vals[] = $val;
  }
  echo "<h2>$_GET[tab].$_GET[key]</h2>\n";
  foreach ($vals as $val)
    echo "<a href='?action=select&amp;tab=$_GET[tab]&amp;key=$_GET[key]&amp;val=",rawurlencode($val),"'>$val</a><br>\n";
  die();
}


// le paramètre est-il un tuple ?
function is_tuple($tuple) {
  if (!is_array($tuple))
    return false;
  foreach ($tuple as $k => $v)
    if (is_array($v))
      return false;
  return true;
}
  
// le paramètre est-il une liste de tuples ?
function is_listOfTuples($list) {
  //echo "<pre>is_list "; print_r($list); echo "</pre>\n";
  if (!is_array($list))
    return false;
  foreach (array_keys($list) as $k => $v)
    if ($k <> $v)
      return false;
  foreach ($list as $tuple) {
    if (!is_tuple($tuple)) {
      return false;
    }
  }
  return true;
}

// fonction de comparaison utilisée dans le tri d'un tableau
function cmp($a, $b) {
  $key = $_GET['key'];
  $order = $_GET['order'];
  if (!isset($a[$key]) and !isset($b[$key]))
    return 0;
  if (!isset($a[$key]))
    return ($order == '1') ? -1 : 1;
  if (!isset($b[$key]))
    return ($order == '1') ? 1 : -1;
  
  if ($a[$key] == $b[$key]) {
      return 0;
  }
  if ($order == '1')
    return ($a[$key] < $b[$key]) ? -1 : 1;
  else
    return ($a[$key] < $b[$key]) ? 1 : -1;
}

// affiche la table et renvoie la liste des noms de colonnes
// tab est un tableau de tuples qui se ressemblent
function showTable (string $tabname, array $tab) {
  $keys = [];
  //echo "<pre>"; print_r($tab); echo "</pre>\n";
  foreach ($tab as $line) {
    foreach (array_keys($line) as $key) {
      if (!in_array($key, $keys))
        $keys[] = $key;
    }
  }
  //echo "<pre>"; print_r($keys); echo "</pre>\n";
  if (isset($_GET['action']) and ($_GET['action']=='select') and ($_GET['tab']==$tabname))
    echo "<h2>$tabname / $_GET[key]=$_GET[val]</h2>\n";
  else
    echo "<h2>$tabname</h2>\n";
  
  if (isset($_GET['action']) and ($_GET['action']=='sort') and ($_GET['tab']==$tabname)) {
    usort($tab, 'cmp');
  }
  
  echo "<table border=1>\n";
  foreach ($keys as $key)
    echo "<th><a href='?action=uniq&amp;tab=$tabname&amp;key=$key'>$key</a></th>";
  echo "\n";
  foreach ($tab as $line) {
    if (isset($_GET['action']) and ($_GET['action']=='select') and ($_GET['tab']==$tabname)
      and ($line[$_GET['key']] <> $_GET['val']))
        continue;
    
    echo "<tr>";
    foreach ($keys as $key) {
      echo "<td>";
      if (isset($line[$key]))
        echo $line[$key];
      echo "</td>";
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

// extraction recursive des tables stockées dans un Yaml
function extractTables(array $data, string $prefix='') {
  foreach ($data as $name => $tab) {
    if (is_listOfTuples($tab))
      showTable("$prefix/$name", $tab);
    elseif (is_array($tab))
      extractTables($tab, "$prefix/$name");
  } 
}

if ($data) {
  extractTables($data);
}

if (isset($_GET['action']) and ($_GET['action']=='clone')) {
  $_SESSION['name'] = uniqid();
  file_put_contents("$_SESSION[name].yaml", $_SESSION['text']);
  echo "contenu enregistré dans $_SESSION[name].yaml\n";
}

echo "<h2>Menu</h2><ul>\n";
echo "<li><a href='?action=nop'>nop</a>\n";
echo "<li><a href='?action=edit'>edit</a>\n";
echo "<li><a href='?action=dump'>dump</a>\n";
echo "<li><a href='?action=clone'>clone</a>\n";
echo "</ul>\n";

if (isset($_SESSION['name'])) {
  echo "url: <a href='?action=read&name=$_SESSION[name]'>$_SESSION[name].yaml<br>\n";
}

