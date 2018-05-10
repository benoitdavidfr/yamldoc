<?php
/*PhpDoc:
name: index.php
title: index.php - version 2 du visualiseur de documents Yaml
doc: |
  version limitée:
    - pas d'affichage des textes en markdown
    - gestion des utilisateurs minimum et pas de gestion du droit de modification d'un document

  Le script utilise les 2 variables de session suivantes:
    - homeCatalog : uid du catalogue d'accueil ou absent
    - parents : graphe de navigation sous la forme d'un tableau docuid enfant vers docuid parent
  En mode anonyme, homeCatalogue n'est pas défini.
  
  A REVOIR:
  - gestion des query ?
    - comment gérer les query en Php sans créer une faille de sécurité ?
    - les enregistrer dans un catalogue ?
    -> protéger les query en écriture afin que seul leur propriétaire puisse les modifier
    -> ajouter dans le catalogue un champ donnant l'extension Php
journal: |
  9-10/5/2018:
  - modification de la gestion du fil d'ariane pour afficher différents docs dans différentes fenêtres
    - le doc courant est systématiquement en paramètre
    - le referer est utilisé pour analyser le graphe de navigation et l'enregistrer en session
    - le fil d'Ariane est déduit de ce graphe
  - ajout possibilité de supprimer un document et de le supprimer d'un catalogue
  - ajout accès par défault
  1-8/5/2018:
  - améliorations
  30/4/2018:
  - restructuration
*/
session_start();
require_once __DIR__.'/../spyc/spyc2.inc.php';
require_once __DIR__.'/yd.inc.php';

// Affichage du menu et du fil d'ariane comme array de docid
function show_menu(array $breadcrumb) {
  // affichage du menu
  $ypath = isset($_GET['ypath']) ? $_GET['ypath']: '';
  $docuid = ($breadcrumb ? $breadcrumb[count($breadcrumb)-1] : null);
  echo "<table border=1><tr><td><b>Menu :</b></td>\n";
  if ($docuid)
    echo "<td><a href='?doc=$docuid",($ypath?'&amp;ypath='.urlencode($ypath):''),"'>show</a></td>\n";
  if ($docuid)
    echo "<td><a href='?action=edit&amp;doc=$docuid'>edit</a></td>\n";
  if ($docuid and ($catuid = CallingGraph::parent($docuid)))
    echo "<td><a href='?clone=$docuid&amp;doc=$catuid'>clone</a></td>\n";
  echo "<td><a href='?action=dump",
       ($docuid ? "&amp;doc=$docuid" : ''),
       ($ypath ? '&amp;ypath='.urlencode($ypath) : ''),
       "'>dump</a></td>\n";
  echo "<td><a href='?action=unset",
       ($docuid ? "&amp;doc=$docuid" : ''),
       "'>unset</a></td>\n";
  echo "</tr></table>\n";

  // affichage du fil d'ariane et du ypath
  if ($breadcrumb) {
    $doc = array_pop($breadcrumb);
    echo "<form>\n";
    foreach ($breadcrumb as $docuid)
      echo "<a href='?doc=$docuid'>&gt;</a> ";
    echo "<b>*</b>&nbsp;";
    echo "<input type='hidden' name='doc' value='$doc'>";
    echo "<input type='text' name='ypath' size=80 value=\"$ypath\"></form>\n<br>\n";
  }
}

// exploitation du graphe d'appel
class CallingGraph {
  // Le graphe d'appel est géré au travers de la variable session $_SESSION['parents'] : [ {child} => {parent} ]
  static $verbose = 1; // peut être utilisé pour afficher le statut de makeBreadcrumb
  
  // mise à jour du graphe d'appel et renvoi du fil d'ariane
  static function makeBreadcrumb(): array {
    //echo "referer ",(isset($_SERVER['HTTP_REFERER']) ? "= $_SERVER[HTTP_REFERER]" : "non défini"),"<br>\n";
    //echo "<pre>_SERVER = "; print_r($_SERVER); echo "</pre>\n";
    if (!isset($_GET['doc']))
      return [];
    $curl = "$_SERVER[REQUEST_SCHEME]://$_SERVER[HTTP_HOST]"
            .substr($_SERVER['REQUEST_URI'],0,strlen($_SERVER['REQUEST_URI'])-strlen($_SERVER['QUERY_STRING']));
    //echo "curl=$curl<br>\n";
    if (!isset($_SERVER['HTTP_REFERER']) or (strncmp($_SERVER['HTTP_REFERER'], $curl, strlen($curl))<>0)) {
      if (self::$verbose)
        echo "referer non défini ou externe<br>\n";
      return [ $_GET['doc'] ];
    }
    $refererargs = substr($_SERVER['HTTP_REFERER'], strlen($curl)).'&';
    //echo "refererargs=$refererargs<br>\n";
    if (!preg_match('!doc=([^&]*)!', $refererargs, $matches)) {
      echo "erreur: no match for args<br>\n";
      return [ $_GET['doc'] ];
    }
    //echo "matches="; print_r($matches); echo "<br>\n";
    $parent = $matches[1]; // l'id de doc extrait du referer
    $doc = $_GET['doc'];
    //echo "parent=$parent, doc=$doc<br>\n";
    //echo "<pre>_SESSION avant = "; print_r($_SESSION); echo "</pre>\n";
    if ($parent == $doc) {
      if (self::$verbose)
        echo "boucle détectée<br>\n";
    }
    elseif (isset($_SESSION['parents'])
             and in_array($doc, $_SESSION['parents'])
             and self::isAncestor($doc, $parent)) {
      if (self::$verbose)
        echo "back détecté<br>\n";
    }
    else {
      $_SESSION['parents'][$doc] = $parent;
      //echo "<pre>_SESSION après = "; print_r($_SESSION); echo "</pre>\n";
    }

    // construction du fil d'ariane
    $breadcrumb = [ $doc ];
    while (isset($_SESSION['parents'][$doc])) {
      //echo "doc=$doc<br>\n";
      $doc = $_SESSION['parents'][$doc];
      $breadcrumb[] = $doc;
      if (count($breadcrumb) > 10) {
        echo "erreur boucle<br>\n";
        break;
      }
    }
    return array_reverse($breadcrumb);
  }
  
  // $ancestor est-il un ancêtre de $child ?
  static function isAncestor(string $ancestor, string $child): bool {
    if (!($parent = self::parent($child)))
      return false;
    if ($parent == $ancestor)
      return true;
    return self::isAncestor($ancestor, $parent);
  }
  
  // renvoi le parent d'un doc ou null s'il n'existe pas
  static function parent(string $doc) {
    return isset($_SESSION['parents'][$doc]) ? $_SESSION['parents'][$doc] : null;
  }
}

echo "<!DOCTYPE HTML><html><head><meta charset='UTF-8'><title>yaml</title></head><body>\n";

show_menu(CallingGraph::makeBreadcrumb());

// les 2 premières actions ne nécessitent pas le paramètre doc
// action dump - affichage des variables de session et du document courant
if (isset($_GET['action']) and ($_GET['action']=='dump')) {
  echo "<pre>";
  echo "_SESSION = "; print_r($_SESSION);
  if (isset($_GET['doc'])) {
    $ypath = isset($_GET['ypath']) ? $_GET['ypath'] : '';
    $text = ydread($_GET['doc']);
    echo "<h2>doc $_GET[doc]</h2>\n";
    $doc = new_yamlDoc($text);
    if (!$ypath)
      echo $text;
    else
      echo "ypath=$ypath\n",
           $doc->yaml($ypath);
    echo "<h2>var_dump</h2>\n"; $doc->dump($ypath);
  }
  echo "</pre>\n";
}

// action unset - effacement des variables de session
if (isset($_GET['action']) and ($_GET['action']=='unset')) {
  foreach ($_SESSION as $key => $value)
    unset($_SESSION[$key]);
}

// évite d'avoir à tester le paramètre doc dans les actions suivantes
if (!isset($_GET['doc'])) {
  die("<a href='?doc=default'>Accès au document par défaut</a>\n");
}


// pré-action delDoc - suppression d'un document dans le catalogue
if (isset($_GET['delDoc'])) {
  YamlCatalog::delete_from_catalog($_GET['delDoc'], $_GET['doc']);
  echo "Doc $_GET[delDoc] effacé<br>\n";
}

// pré-action clone - $_GET['clone'] contient le doc à cloner et $_GET['doc'] le catalogue
if (isset($_GET['clone'])) {
  $newdocuid = uniqid();
  YamlCatalog::clone_in_catalog($newdocuid, $_GET['clone'], $_GET['doc']);
  ydwrite($newdocuid, ydread($_GET['clone']));
  echo "Document $_GET[clone] cloné dans $newdocuid<br>\n";
}


// action d'affichage d'un document
if (!isset($_GET['action'])) {
  if (($text = ydread($_GET['doc'])) === FALSE) {
    echo "<b>Erreur: le document $_GET[doc] n'existe pas</b><br>\n";
    if ($parent = CallingGraph::parent($_GET['doc']))
      echo "<a href='?delDoc=$_GET[doc]&amp;doc=$parent'>",
           "L'effacer dans le catalogue $parent</a><br>\n";
  }
  else {
    $doc = new_yamlDoc($text);
    if ($doc) {
      if ($doc->isHomeCatalog())
        $_SESSION['homeCatalog'] = $_GET['doc'];
      $ypath = isset($_GET['ypath']) ? $_GET['ypath'] : '';
      $doc->show($ypath);
    }
    else {
      echo "<b>Erreur: le document $_GET[doc] ne correspond pas à un YamlDoc</b><br>\n";
      echo "<h2>doc $_GET[doc]</h2><pre>\n$text\n</pre>\n";
    }
  }
  die();
}


// action edit - génération du formulaire d'édition du document courant
if ($_GET['action']=='edit') {
  $text = ydread($_GET['doc']);
  echo "<table><form action='?action=store&amp;doc=$_GET[doc]' method='post'>\n",
       "<tr><td><textarea name='text' rows='40' cols='80'>$text</textarea></td></tr>\n",
       "<tr><td><input type='submit' value='Enregistrer'></td></tr>\n",
       "</form></table>\n";
  die();
}

// action store - enregistrement d'un contenu à la suite d'une édition
if ($_GET['action']=='store') {
  if (strlen($_POST['text'])==0) {
    yddelete($_GET['doc']);
    echo "<b>document vide $_GET[doc] effacé</b><br>\n";
    if ($parent = CallingGraph::parent($_GET['doc']))
      echo "<a href='?delDoc=$_GET[doc]&amp;doc=$parent'>",
           "L'effacer dans le catalogue $parent</a><br>\n";
  }
  else {
    ydwrite($_GET['doc'], $_POST['text']);
    echo "Enregistrement du document $_GET[doc]<br>\n";
    $doc = new_yamlDoc($_POST['text']);
    if (!$doc) {
      echo "<b>Erreur: le document $_GET[doc] ne correspond pas à un YamlDoc</b>\n";
      echo "<h2>doc $_GET[doc]</h2><pre>\n$_POST[text]\n</pre>\n";
    }
    else {
      $ypath = isset($_GET['ypath']) ? $_GET['ypath'] : '';
      $doc->show($ypath);
    }
  }
}
