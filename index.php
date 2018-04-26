<?php
/*PhpDoc:
name: index.php
title: index.php - gestionnaire de documents Yaml
doc: |
  - manipulation d'un document contenant des listes de tuples
    - représentation de chaque liste de tuples sous la forme d'un tableau
    - possibilité de sélectionner les lignes ayant une valeur donnée pour une colonne donnée
    - tri des lignes selon une colonne
    - enregistrement/lecture d'un fichier en lui affectant un non aléatoire
  - gestion de catalogues de documents comme un document

  Le paramètre action définit l'action à réaliser qui correspond à des paramètres spécifiques
  Le contenu Yaml est stocké dans $_SESSION['text'].
  Le nom du fichier est enregistré sans $_SESSION['name']
  Le chemin des catalogues est enregistré dans $_SESSION['catalogs'], la racine en 0
  idées:
journal: |
  22/4/2018:
    split de l'afficha de listes de tuples dans ltuples.inc.php
  18/4/2018:
    utilisation de ydread() et ydwrite()
  17/4/2018:
  - test d'encryptage des docs
  15/4/2018:
  - transfert des docs dans docs
  14/4/2018:
  - gestion des catalogues
  - gestion des droits de modification
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
require_once __DIR__.'/yd.inc.php';
require_once __DIR__.'/catalog.inc.php';
require_once __DIR__.'/ltuples.inc.php';
require_once __DIR__.'/onetable.inc.php';

// indique si l'utilisateur courant est autorisé à modifier le document passé en paramètre
function authorizedWriter(array $data) {
  $verbose = false;
  if ($verbose)
    echo "authorizedWriter ";
  if (!$data) {
    if ($verbose)
      echo "no data<br>\n";
    return true;
  }
  if (!isset($data['authorizedWriters'])) {
    if ($verbose)
      echo "no authorizedWriters<br>\n";
    return true;
  }
  $userId = md5($_SESSION['catalogs'][0]);
  if ($verbose)
    echo in_array($userId, $data['authorizedWriters']) ? "in" : "not in", "<br>\n";
  return in_array($userId, $data['authorizedWriters']);
}
 
echo "<!DOCTYPE HTML><html><head><meta charset='UTF-8'><title>yaml</title></head><body>\n";

// reinitialisation des variables de session puis lecture éventuelle d'un document dans un fichier
if (isset($_GET['action']) and ($_GET['action']=='init')) {
  unset($_SESSION['text']);
  unset($_SESSION['name']);
  unset($_SESSION['catalogs']);
  // lecture d'un document dans un fichier
  if (isset($_GET['name'])) {
    $_SESSION['name'] = $_GET['name'];
    if (($text = ydread($_SESSION['name'])) === FALSE) {
      echo "<b>Erreur le fichier $_SESSION[name].yaml n'existe pas</b>\n";
    }
    else
      $_SESSION['text'] = $text;
  }
}

// édition du contenu Yaml
if (isset($_GET['action']) and ($_GET['action']=='edit')) {
  $str = isset($_SESSION['text']) ? $_SESSION['text'] : '';
  if ($str) {
    $data = spycLoadString($str);
    if (!authorizedWriter($data))
      die("Erreur: édition interdite<br>\n");
  }
  $userId = isset($_SESSION['catalogs']) ? "userId=".md5($_SESSION['catalogs'][0])."<br>\n" : '';
  echo "<table><form action='?action=store' method='post'>\n",
       "<tr><td><textarea name='text' rows='40' cols='80'>$str</textarea></td></tr>\n",
       "<tr><td><input type='submit' value='Envoyer'></td></tr>\n",
       "</form></table>\n",
       "$userId\n";
   die();
}

// enregistrement d'un contenu à la suite d'une édition
// on pourrait renvoyer une édition lorsque le contenu n'est pas conforme Yaml
if (isset($_GET['action']) and ($_GET['action']=='store')) {
  $_SESSION['text'] = $_POST['text'];
  if (!isset($_SESSION['name'])) {
    $_SESSION['name'] = uniqid();
    if (!isset($_SESSION['catalogs'])) {
      $_SESSION['catalogs'] = [ create_catalog() ];
    }
    store_in_catalog($_SESSION['name'], $_SESSION['catalogs'][count($_SESSION['catalogs'])-1]);
  }
  ydwrite($_SESSION['name'], $_SESSION['text']);
  echo "Enregistrement du document $_SESSION[name]<br>\n";
}

// lecture d'un document dans un fichier sans réinitialiser $_SESSION['catalogs']
if (isset($_GET['action']) and ($_GET['action']=='read')) {
  $_SESSION['name'] = $_GET['name'];
  if (($text = ydread($_SESSION['name'])) === FALSE) {
    echo "<b>Erreur le fichier $_SESSION[name].yaml n'existe pas</b>\n";
    unset($_SESSION['text']);
  }
  else
    $_SESSION['text'] = $text;
}

$data = null;

// analyse du texte pour en faire un Yaml dans $data
if (isset($_SESSION['text'])) {
  $data = spycLoadString($_SESSION['text']);
  if (!$data) {
    echo "<b>Erreur spycLoad</b>\n";
    echo "<pre>$_SESSION[text]</pre>\n";
  }
}

// action dump de $data
if (isset($_GET['action']) and ($_GET['action']=='dump')) {
  //echo "<pre>data = "; print_r($data); echo "</pre>\n";
  echo "<pre>data = "; var_dump($data); echo "</pre>\n";
  echo "<a href='?action=nop'>retour</a>\n";
  die();
}

// affichage de la liste de valeurs uniques d'une colonne définie par $_GET['tab'] qui indique le ypath
if (isset($_GET['action']) and ($_GET['action']=='uniq')) {
  $vals = [];
  $key = $_GET['key'];
  $tab = ypath($data, $_GET['tab']);
  foreach ($tab as $line) {
    $val = (isset($line[$key]) ? $line[$key] : '');
    if (!in_array($val, $vals))
      $vals[] = $val;
  }
  echo "<h2>uniq $_GET[tab].$_GET[key]</h2>\n";
  foreach ($vals as $val)
    echo "<a href='?action=select&amp;tab=$_GET[tab]&amp;key=$key&amp;val=",
          rawurlencode($val),"'>",($val ? $val : 'vide ou non définie'),"</a><br>\n";
  die();
}


if ($data) {
  // affichage selon le type
  $display = isset($data['display']) ? $data['display'] : (isset($data['type']) ? $data['type'] : null);
  switch ($display) {
    case 'catalog':
      show_catalog($data);
      if (!isset($_SESSION['catalogs']) or !$_SESSION['catalogs'])
        $_SESSION['catalogs'] = [ $_SESSION['name'] ];
      elseif (($k = array_search( $_SESSION['name'], $_SESSION['catalogs']))===FALSE)
        $_SESSION['catalogs'][] = $_SESSION['name'];
      else
        $_SESSION['catalogs'] = array_slice($_SESSION['catalogs'], 0, $k+1);  
      break;
    case 'tables':
      showListsOfTuples($data);
      break;
    case 'oneTable':
      showDocAsTable($data);
      break;
    default:
      showDocAsTable($data);
      break;
  }
  
  // action de clonage
  if (isset($_GET['action']) and ($_GET['action']=='clone')) {
    $_SESSION['name'] = uniqid();
    if (!isset($_SESSION['catalogs'])) {
      $_SESSION['catalogs'] = [ create_catalog() ];
    }
    store_in_catalog($_SESSION['name'], $_SESSION['catalogs'][count($_SESSION['catalogs'])-1]);
    ydwrite($_SESSION['name'], $_SESSION['text']);
    echo "contenu enregistré dans $_SESSION[name].yaml<br>\n";
  }
}

echo "<h2>Menu</h2><ul>\n";
echo "<li><a href='?action=nop'>nop</a>\n";
if (authorizedWriter($data))
  echo "<li><a href='?action=edit'>edit</a>\n";
//else
  //echo "<li>édition du document interdite\n";
echo "<li><a href='?action=dump'>dump</a>\n";
if ($data) {
  echo "<li><a href='?action=clone'>clone</a>\n";
}
//echo "<li><a href='?action=init'>init</a>\n";
echo "</ul>\n";

// bas de page
if (isset($_SESSION['catalogs'])) {
  foreach ($_SESSION['catalogs'] as $i => $catalog) {
    echo "<a href='?action=",($i==0?'init':'read'),"&amp;name=$catalog'>&gt;</a> ";
  }
}
if (isset($_SESSION['name']) and !in_array($_SESSION['name'], $_SESSION['catalogs'])) {
  echo "<a href='?action=read&name=$_SESSION[name]'>doc</a>\n";
}
echo "<br>\n";
//echo "<pre>_SESSION="; print_r($_SESSION);
