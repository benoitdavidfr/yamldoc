<?php
/*PhpDoc:
name: index.php
title: index.php - version 2 du visualiseur de documents Yaml
doc: |
  version minimum:
    - pas d'affichage d'un fragment
    - pas de gestion des utilisateurs ni de gestion du droit de modification d'un document
journal: |
  30/4/2018:
  - restructuration
*/
session_start();
require_once __DIR__.'/../spyc/spyc2.inc.php';
require_once __DIR__.'/yd.inc.php';

echo "<!DOCTYPE HTML><html><head><meta charset='UTF-8'><title>yaml</title></head><body>\n";

// action edit - génération du formulaire d'édition du document courant
if (isset($_GET['action']) and ($_GET['action']=='edit')) {
  if (!isset($_SESSION['docuids'])) {
    echo "<b>Erreur: aucun document courant</b>\n";
  }
  else {
    $docuid = $_SESSION['docuids'][count($_SESSION['docuids'])-1];
    $text = ydread($docuid);
    echo "<table><form action='?action=store' method='post'>\n",
         "<tr><td><textarea name='text' rows='40' cols='80'>$text</textarea></td></tr>\n",
         "<tr><td><input type='submit' value='Envoyer'></td></tr>\n",
         "</form></table>\n";
    die();
  }
}

// action store - enregistrement d'un contenu à la suite d'une édition
// on pourrait renvoyer une édition lorsque le contenu n'est pas conforme Yaml
if (isset($_GET['action']) and ($_GET['action']=='store')) {
  if (!isset($_SESSION['docuids'])) {
    echo "<b>Erreur: aucun document courant</b>\n";
  }
  else {
    $docuid = $_SESSION['docuids'][count($_SESSION['docuids'])-1];
    ydwrite($docuid, $_POST['text']);
    echo "Enregistrement du document $docuid<br>\n";
    $doc = new_yamlDoc($docuid, $_POST['text']);
    $ypath = isset($_GET['ypath']) ? $_GET['ypath'] : null;
    $doc->show($ypath);
  }
}

// action nav - navigation vers un nouveau document
// gestion des variables de session et affichage de ce document
if ((!isset($_GET['action']) or ($_GET['action']=='nav')) and isset($_GET['doc'])) {
  if (($text = ydread($_GET['doc'])) === FALSE) {
    echo "<b>Erreur le fichier $_GET[doc] n'existe pas</b>\n";
  }
  else {
    $doc = new_yamlDoc($_GET['doc'], $text);
    if ($homeCatalog = $doc->isHomeCatalog()) {
      $_SESSION['homeCatalog'] = $homeCatalog;
      $_SESSION['docuids'] = [ $_GET['doc'] ];
    }
    elseif (!isset($_SESSION['docuids']) or !$_SESSION['docuids'])
      $_SESSION['docuids'] = [ $_GET['doc'] ];
    elseif (($k = array_search($_GET['doc'], $_SESSION['docuids']))===FALSE)
      $_SESSION['docuids'][] = $_GET['doc'];
    else
      $_SESSION['docuids'] = array_slice($_SESSION['docuids'], 0, $k+1);
    $ypath = isset($_GET['ypath']) ? $_GET['ypath'] : null;
    $doc->show($ypath);
  }
}

// action show - lecture et affichage du document courant
if ((!isset($_GET['action']) or ($_GET['action']=='show')) and !isset($_GET['doc'])) {
  if (!isset($_SESSION['docuids'])) {
    echo "<b>Erreur: il n'y a pas de document courant</b>\n";
  }
  else {
    $docuid = $_SESSION['docuids'][count($_SESSION['docuids'])-1];
    if (($text = ydread($docuid)) === FALSE) {
      echo "<b>Erreur: le fichier $docuid n'existe pas</b>\n";
    }
    else {
      $doc = new_yamlDoc($docuid, $text);
      $ypath = isset($_GET['ypath']) ? $_GET['ypath'] : null;
      $doc->show($ypath);
    }
  }
}

// action dump - affichage des variables de session et du document courant
if (isset($_GET['action']) and ($_GET['action']=='dump')) {
  echo "<pre>";
  echo "_SESSION = "; print_r($_SESSION);
  if (isset($_SESSION['docuids'])) {
    $docuid = $_SESSION['docuids'][count($_SESSION['docuids'])-1];
    echo "<h2>doc $docuid</h2>\n",ydread($docuid);
  }
  echo "</pre>\n";
}

// action unset - effacement des variables de session
if (isset($_GET['action']) and ($_GET['action']=='unset')) {
  foreach ($_SESSION as $key => $value)
    unset($_SESSION[$key]);
}

// Affichage du fil d'ariane
if (isset($_SESSION['docuids'])) {
  $nbre = count($_SESSION['docuids']);
  foreach ($_SESSION['docuids'] as $i => $docuid) {
    if ($i < $nbre-1)
      echo "<a href='?action=nav&amp;doc=$docuid'>&gt;</a> ";
    else
      echo "<b>*</b>";
  }
}

// Affichage du menu
if (1) {
  echo "<h2>Menu</h2><ul>\n";
  echo "<li><a href='?action=show'>show</a>\n";
  echo "<li><a href='?action=edit'>edit</a>\n";
  echo "<li><a href='?action=dump'>dump</a>\n";
  echo "<li><a href='?action=unset'>unset</a>\n";
  echo "</ul>\n";
}
