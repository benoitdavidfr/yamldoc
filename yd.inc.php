<?php
/*PhpDoc:
name: yd.inc.php
title: yd.inc.php - fonctions générales pour yamldoc
doc: |
journal: |
  1/5/2018:
  - refonte pour index.php v2
  19/4/2018:
  - suppression du cryptage
  18/4/2018:
  - première version
*/

// écriture d'un document, prend l'uid et le texte
function ydwrite(string $uid, string $doc) {
  return file_put_contents("docs/$uid.yaml", $doc);
}

// lecture d'un document, prend l'uid et retourne le texte
function ydread(string $uid) {
  //echo "ydread($uid)<br>\n";
  return @file_get_contents("docs/$uid.yaml");
}

// le paramètre est-il une liste d'atomes ?
function is_listOfAtoms($list) {
  // ce doit être un array
  if (!is_array($list))
    return false;
  // les clés doivent être la liste des entiers à partir de 0
  foreach (array_keys($list) as $k => $v)
    if ($k !== $v)
      return false;
  // aucun des atom ne doit être un array
  foreach ($list as $atom)
    if (is_array($atom))
      return false;
  return true;
}

// le paramètre est-il une liste de tuples ?
function is_listOfTuples($list) {
  //echo "<pre>is_list "; print_r($list); echo "</pre>\n";
  //echo "<pre>array_keys = "; print_r(array_keys($list)); echo "</pre>\n";
  $ret = is_listOfTuples_i($list);
  //echo "is_list => $ret<br>\n";
  return $ret;
}
function is_listOfTuples_i($list) {
  // ce doit être un array
  if (!is_array($list))
    return false;
  // les clés doivent être la liste des entiers à partir de 0
  foreach (array_keys($list) as $k => $v)
    if ($k !== $v)
      return false;
  // chaque tuple doit être un array
  foreach ($list as $tuple)
    if (!is_array($tuple))
      return false;
  return true;
}

// affichage d'une liste d'atomes comme <ul><li>
function showListOfAtoms(array $list, string $prefix='') {
  echo "<ul>";
  foreach ($list as $atom)
    echo "<li>$atom\n";
  echo "</ul>\n";
}

// affichage d'une liste de tuples comme table Html
function showListOfTuplesAsTable(array $table, string $prefix='') {
  $keys = []; // liste des clés d'au moins un tuple
  //echo "<pre>"; print_r($tab); echo "</pre>\n";
  foreach ($table as $tuple) {
    foreach (array_keys($tuple) as $key) {
      if (!in_array($key, $keys))
        $keys[] = $key;
    }
  }
  echo "<pre>"; print_r($keys); echo "</pre>\n";

  echo "<table border=1>\n";
  foreach ($keys as $key)
    echo "<th>$key</th>";
  echo "\n";
  foreach ($table as $tuple) {
    echo "<tr>";
    foreach ($keys as $key) {
      if (!isset($tuple[$key]))
        echo "<td></td>";
      elseif (is_numeric($tuple[$key]))
        echo "<td align='right'>",$tuple[$key],"</td>";
      elseif (is_listOfAtoms($tuple[$key])) {
        echo "<td>";
        showListOfAtoms($tuple[$key], "$prefix/$key");
        echo "</td>";
      }
      elseif (is_listOfTuples($tuple[$key])) {
        echo "<td>";
        showListOfTuplesAsTable($tuple[$key], "$prefix/$key");
        echo "</td>";
      }
      elseif (is_array($tuple[$key])) {
        echo "<td>";
        showArrayAsTable($tuple[$key], "$prefix/$key");
        echo "</td>";
      }
      else
        echo "<td>",$tuple[$key],"</td>";
    }
    echo "</tr>\n";
  }
  echo "</table>\n";
}

// affichage d'un array comme table Html
function showArrayAsTable(array $data, string $prefix='') {
  echo "<table border=1>\n";
  foreach ($data as $key => $value) {
    echo "<tr><td>$key</td><td>\n";
    if (is_listOfAtoms($value))
      showListOfAtoms($value, "$prefix/$key");
    elseif (is_listOfTuples($value))
      showListOfTuplesAsTable($value, "$prefix/$key");
    elseif (is_array($value))
      showArrayAsTable($value, "$prefix/$key");
    elseif (is_string($value) and (strpos($value, "\n")!==FALSE))
      echo "<pre>$value</pre>";
    else
      echo $value;
    echo "</td></tr>\n";
  }
  echo "</table>\n";
}

// crée un YamlDoc à partir d'un texte
// détermine sa classe en fonction du champ yamlClass
// retourne null si le texte n'est pas du Yaml
function new_yamlDoc(string $docuid, string $text) {
  if (!($data = spycLoadString($text)))
    return null;
  if (isset($data['yamlClass'])) {
    $yamlClass = $data['yamlClass'];
    if (class_exists($yamlClass))
      return new $yamlClass ($docuid, $data);
    else
      echo "<b>Erreur: la classe $yamlClass n'est pas définie</b>\n";
  }
  return new YamlDoc($docuid, $data);
}

// classe YamlDoc de base
class YamlDoc {
  protected $docuid; // uid du document
  protected $data; // contenu du doc sous forme d'un arrray Php
  
  function __construct(string $docuid, array $data) { $this->docuid = $docuid;  $this->data = $data; }
  function isHomeCatalog() { return null; }
  
  function show(?string $ypath) {
    //echo "<pre>"; print_r($this->data); echo "</pre>\n";
    showArrayAsTable($this->data);
  }
};

// class des catalogues
class YamlCatalog extends YamlDoc {
  function show(?string $ypath) {
    //echo "<pre>"; print_r($this->data); echo "</pre>\n";
    echo "<h1>",$this->data['title'],"</h1><ul>\n";
    foreach($this->data['contents'] as $duid => $item) {
      $title = isset($item['title']) ? $item['title'] : $duid;
      echo "<li><a href='?doc=$duid'>$title</a>\n";
    }
    echo "</ul>\n";
  }
};

// classe des catalogues d'accueil
class YamlHomeCatalog extends YamlCatalog {
  function isHomeCatalog() { return $this->docuid; }
};

/*
// retourne le sous-document défini par path qui est une chaine ou un array de clés
function ypath(array $data, $path) {
  if (!$path)
    return $data;
  if (is_string($path)) {
    $path = explode('/', $path);
    if (!$path[0])
      array_shift($path);
  }
  $key = array_shift($path);
  return ypath($data[$key], $path);
}
*/

if (basename(__FILE__)<>basename($_SERVER['PHP_SELF'])) return;


