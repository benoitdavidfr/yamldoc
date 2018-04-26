<?php
/*PhpDoc:
name: yd.inc.php
title: yd.inc.php - fonctions générales pour yamldoc
doc: |
journal: |
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

/*
// le paramètre est-il un tuple de valeurs élémentaires ou non ?
function is_tuple($tuple) {
  if (!is_array($tuple))
    return false;
  foreach ($tuple as $k => $v)
    if (is_array($v))
      return false;
  return true;
}
*/
  
// le paramètre est-il une liste de tuples ?
function is_listOfTuples($list) {
  //echo "<pre>is_list "; print_r($list); echo "</pre>\n";
  //echo "<pre>array_keys = "; print_r(array_keys($list)); echo "</pre>\n";
  $ret = is_listOfTuples_i($list);
  //echo "is_list => $ret<br>\n";
  return $ret;
}
function is_listOfTuples_i($list) {
  if (!is_array($list))
    return false;
  foreach (array_keys($list) as $k => $v)
    if ($k !== $v)
      return false;
  foreach ($list as $tuple) {
    if (!is_array($tuple)) {
      return false;
    }
  }
  return true;
}

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


if (basename(__FILE__)<>basename($_SERVER['PHP_SELF'])) return;


