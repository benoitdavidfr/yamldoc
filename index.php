<?php
/*PhpDoc:
name: index.php
title: index.php - version 2 du visualiseur de documents Yaml
doc: |
  version minimum:
    - pas d'affichage des textes en markdown
    - pas de gestion des utilisateurs ni de gestion du droit de modification d'un document

  Le script utilise les 2 variables de session suivantes:
    - docuids : liste des docuids du chemin des documents sous la forme de 
      - le premier est normalement le catalogue d'accueil
      - le dernier est normalement le document courant
      - les documents intermédiaires sont normalemnt des catalogues intermédiaires
    - homeCatalog : docuid du catalogue d'accueil ou absent
  En mode anonyme, homeCatalogue n'est pas défini. docuids contient qd même l'historique des documents
  
  A REVOIR:
  - il n'est pas possible d'afficher différents docs dans différentes fenêtres
    revoir les variables en session.
    Idée:
      - gérer en session, à la place du fil d'ariane d'un affichage, le graphe du père de chaque doc,
        ou le dernier traversé, ou le père allant à un home catalogue
      - conserver systématiquement en paramètre et pas en session le doc courant
      - dans la cmde nav ajouter un paramètre doc précédent afin de renseigner le graphe
      -> cela permet d'afficher facilement pour chaque document son fil d'ariane
  - gestion des query ?
    - comment gérer les query en Php sans créer une faille de sécurité ?
    - les enregistrer dans un catalogue ?
    -> protéger les query en écriture afin que seul leur propriétaire puisse les modifier
    -> ajouter dans le catalogue un champ donnant l'extension Php
journal: |
  1-8/5/2018:
  - améliorations
  30/4/2018:
  - restructuration
*/
session_start();
require_once __DIR__.'/../spyc/spyc2.inc.php';
require_once __DIR__.'/yd.inc.php';

// Affichage du menu et du fil d'ariane
// Le menu et le fil d'ariane doivent être affichés après avoir géré $_SESSION['docuids']
function show_menu(array $docuids) {
  // affichage du menu
  $ypath = isset($_GET['ypath']) ? $_GET['ypath']: '';
  echo "<table border=1><tr><td><b>Menu :</b></td>\n";
  if ($docuids)
    echo "<td><a href='?action=show",($ypath?'&amp;ypath='.urlencode($ypath):''),"'>show</a></td>\n";
  if ($docuids)
    echo "<td><a href='?action=edit'>edit</a></td>\n";
  if (count($docuids) > 1)
    echo "<td><a href='?action=clone'>clone</a></td>\n";
  echo "<td><a href='?action=dump",($ypath?'&amp;ypath='.urlencode($ypath):''),"'>dump</a></td>\n";
  echo "<td><a href='?action=unset'>unset</a></td>\n";
  echo "</tr></table>\n";

  // affichage du fil d'ariane
  if ($docuids) {
    echo "<form>\n";
    $nbre = count($docuids);
    foreach ($docuids as $i => $docuid) {
      if ($i < $nbre-1)
        echo "<a href='?action=nav&amp;doc=$docuid'>&gt;</a> ";
      else
        echo "<b>*</b>";
    }
    echo "&nbsp;<input type='text' name='ypath' size=80 value='$ypath'></form>\n";
    echo "<br>\n";
  }
}

echo "<!DOCTYPE HTML><html><head><meta charset='UTF-8'><title>yaml</title></head><body>\n";

// action edit - génération du formulaire d'édition du document courant
if (isset($_GET['action']) and ($_GET['action']=='edit')) {
  show_menu(isset($_SESSION['docuids']) ? $_SESSION['docuids'] : []);
  if (!isset($_SESSION['docuids'])) {
    echo "<b>Erreur: aucun document courant</b>\n";
  }
  else {
    $docuid = $_SESSION['docuids'][count($_SESSION['docuids'])-1];
    $text = ydread($docuid);
    echo "<table><form action='?action=store' method='post'>\n",
         "<tr><td><textarea name='text' rows='40' cols='80'>$text</textarea></td></tr>\n",
         "<tr><td><input type='submit' value='Enregistrer'></td></tr>\n",
         "</form></table>\n";
    die();
  }
}

// action store - enregistrement d'un contenu à la suite d'une édition
// on pourrait renvoyer une édition lorsque le contenu n'est pas conforme Yaml
if (isset($_GET['action']) and ($_GET['action']=='store')) {
  show_menu(isset($_SESSION['docuids']) ? $_SESSION['docuids'] : []);
  if (!isset($_SESSION['docuids'])) {
    echo "<b>Erreur: aucun document courant</b><br>\n";
  }
  else {
    $docuid = $_SESSION['docuids'][count($_SESSION['docuids'])-1];
    ydwrite($docuid, $_POST['text']);
    echo "Enregistrement du document $docuid<br>\n";
    $doc = new_yamlDoc($_POST['text']);
    if (!$doc) {
      echo "<b>Erreur: le document $docuid ne correspond pas à un YamlDoc</b>\n";
      echo "<h2>doc $docuid</h2><pre>\n$_POST[text]\n</pre>\n";
    }
    else {
      $ypath = isset($_GET['ypath']) ? $_GET['ypath'] : '';
      $doc->show($ypath);
    }
  }
}

// action nav - navigation vers un nouveau document
// gestion des variables de session et affichage de ce document
if ((!isset($_GET['action']) or ($_GET['action']=='nav')) and isset($_GET['doc'])) {
  if (($text = ydread($_GET['doc'])) === FALSE) {
    show_menu(isset($_SESSION['docuids']) ? $_SESSION['docuids'] : []);
    echo "<b>Erreur: le document $_GET[doc] n'existe pas</b>\n";
  }
  else {
    $doc = new_yamlDoc($text);
    if ($doc and $doc->isHomeCatalog()) {
      $_SESSION['homeCatalog'] = $_GET['doc'];
      $_SESSION['docuids'] = [ $_GET['doc'] ];
    }
    elseif (!isset($_SESSION['docuids']) or !$_SESSION['docuids'])
      $_SESSION['docuids'] = [ $_GET['doc'] ];
    elseif (($k = array_search($_GET['doc'], $_SESSION['docuids']))===FALSE)
      $_SESSION['docuids'][] = $_GET['doc'];
    else
      $_SESSION['docuids'] = array_slice($_SESSION['docuids'], 0, $k+1);
    show_menu(isset($_SESSION['docuids']) ? $_SESSION['docuids'] : []);
    if ($doc) {
      $ypath = isset($_GET['ypath']) ? $_GET['ypath'] : '';
      $doc->show($ypath);
    }
    else {
      echo "<b>Erreur: le document $_GET[doc] ne correspond pas à un YamlDoc</b><br>\n";
      echo "<h2>doc $_GET[doc]</h2><pre>\n$text\n</pre>\n";
    }
  }
}

// action show - lecture et affichage du document courant
if ((!isset($_GET['action']) or ($_GET['action']=='show')) and !isset($_GET['doc'])) {
  show_menu(isset($_SESSION['docuids']) ? $_SESSION['docuids'] : []);
  if (!isset($_SESSION['docuids'])) {
    echo "<b>Erreur: il n'y a pas de document courant</b><br>\n";
  }
  else {
    $docuid = $_SESSION['docuids'][count($_SESSION['docuids'])-1];
    if (($text = ydread($docuid)) === FALSE) {
      echo "<b>Erreur: le document $docuid n'existe pas</b><br>\n";
    }
    elseif (!($doc = new_yamlDoc($text))) {
      echo "<b>Erreur: le document $docuid ne correspond pas à un YamlDoc</b>\n";
      echo "<h2>doc $docuid</h2><pre>\n$text\n</pre>\n";
    }
    else {
      $ypath = isset($_GET['ypath']) ? $_GET['ypath'] : '';
      $doc->show($ypath);
    }
  }
}

// action clone
if (isset($_GET['action']) and ($_GET['action']=='clone')) {
  if (!isset($_SESSION['docuids'])) {
    show_menu(isset($_SESSION['docuids']) ? $_SESSION['docuids'] : []);
    echo "<b>Erreur: il n'y a pas de document courant</b>\n";
  }
  elseif (count($_SESSION['docuids']) <= 1) {
    show_menu(isset($_SESSION['docuids']) ? $_SESSION['docuids'] : []);
    echo "<b>Erreur: il n'y a pas de catalogue parent</b>\n";
  } else {
    $docuid = array_pop($_SESSION['docuids']);
    $catuid = $_SESSION['docuids'][count($_SESSION['docuids'])-1];
    $newdocuid = uniqid();
    $_SESSION['docuids'][] = $newdocuid;
    YamlCatalog::store_in_catalog($newdocuid, $catuid);
    ydwrite($newdocuid, ydread($docuid));
    show_menu(isset($_SESSION['docuids']) ? $_SESSION['docuids'] : []);
    echo "contenu enregistré dans $newdocuid<br>\n";
  }
}

// action dump - affichage des variables de session et du document courant
if (isset($_GET['action']) and ($_GET['action']=='dump')) {
  show_menu(isset($_SESSION['docuids']) ? $_SESSION['docuids'] : []);
  echo "<pre>";
  echo "_SESSION = "; print_r($_SESSION);
  if (isset($_SESSION['docuids'])) {
    $docuid = $_SESSION['docuids'][count($_SESSION['docuids'])-1];
    $ypath = isset($_GET['ypath']) ? $_GET['ypath'] : '';
    $text = ydread($docuid);
    echo "<h2>doc $docuid</h2>\n";
    if (!$ypath)
      echo $text;
    $doc = new_yamlDoc($text);
    if ($ypath) {
      echo "ypath=$ypath\n";
      echo $doc->yaml($ypath);
    }
    echo "<h2>var_dump</h2>\n"; $doc->dump($ypath);
  }
  echo "</pre>\n";
}

// action unset - effacement des variables de session
if (isset($_GET['action']) and ($_GET['action']=='unset')) {
  show_menu(isset($_SESSION['docuids']) ? $_SESSION['docuids'] : []);
  foreach ($_SESSION as $key => $value)
    unset($_SESSION[$key]);
}
