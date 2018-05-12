<?php
/*PhpDoc:
name: index.php
title: index.php - version 2 du visualiseur de documents Yaml
doc: |
  version limitée:
    - pas d'affichage des textes en markdown

  Le script utilise les variables de session suivantes:
    - homeCatalog : uid du dernier catalogue d'accueil traversé ou absent si aucun ne l'a été
        identifie l'utilisateur
    - parents : graphe de navigation sous la forme d'un tableau docuid enfant vers docuid parent
        permet d'afficher le fil d'Ariane
    - checkedReadAccess : liste des docuid pour lesquels l'accès en lecture est autorisé
        évite de retester si yamlPassword existe et de redeamnder le mot de passe
    - checkedWriteAccess : pour chaque document indique 1 s'il est modifiable, 0 s'il ne l'est pas
        évite de retester si un document est modifiable
    - locks : liste des documents verrouillés
        permet de dévérouiller les documents verrouillés
  
  A REVOIR:
  - Markdown ???
journal: |
  12/5/2018:
  - ajout protection en modification
  - ajout mécanisme simple de verrouillage des documents en cours de mise à jour
  11/5/2018:
  - ajout protection en consultation
  - un texte qui ne correspond pas à du Yaml peut être stocké comme un YamlDoc
  9-10/5/2018:
  - modification de la gestion du fil d'ariane pour afficher différents docs dans différentes fenêtres
    - le doc courant est systématiquement en paramètre
    - le referer est utilisé pour analyser le graphe de navigation et l'enregistrer en session
    - le fil d'Ariane est déduit de ce graphe
  - ajout possibilité de supprimer un document et de le supprimer d'un catalogue
  - ajout accès par défault
  - ajout simple de query sous la forme d'un script Php stocké dans un document
  1-8/5/2018:
  - améliorations
  30/4/2018:
  - restructuration
*/
session_start();
require_once __DIR__.'/yd.inc.php';

// Affichage du menu et du fil d'ariane comme array de docid
function show_menu(array $breadcrumb) {
  // affichage du menu
  $ypath = isset($_GET['ypath']) ? $_GET['ypath'] : '';
  $ypatharg = $ypath ? '&amp;ypath='.urlencode($ypath): '';
  $docuid = ($breadcrumb ? $breadcrumb[count($breadcrumb)-1] : null);
  echo "<table border=1><tr><td><b>Menu :</b></td>\n";
  if ($docuid) {
    // showAsHtml
    echo "<td><a href='?doc=$docuid$ypatharg'>html</a></td>\n";
    // showAsYaml
    echo "<td><a href='?doc=$docuid$ypatharg&amp;format=yaml'>yaml</a></td>\n";
    // check
    //echo "<td><a href='?action=check&amp;doc=$docuid$ypatharg'>check</a></td>\n";
    // edit - la possibilité n'est pas affichée si le doc courant n'est pas éditable
    if (ydcheckWriteAccess($docuid)<>0)
      echo "<td><a href='?action=edit&amp;doc=$docuid'>edit</a></td>\n";
    // clone - uniquement s'il existe un catalogue parent
    if ($catuid = CallingGraph::parent($docuid))
      echo "<td><a href='?clone=$docuid&amp;doc=$catuid'>clone</a></td>\n";
  }
  // dump
  echo "<td><a href='?action=dump",($docuid ? "&amp;doc=$docuid" : ''),$ypatharg,"'>dump</a></td>\n";
  // unset
  echo "<td><a href='?action=unset",($docuid ? "&amp;doc=$docuid" : ''),"'>unset</a></td>\n";
  echo "</tr></table>\n";

  // affichage du fil d'ariane et du ypath
  if ($breadcrumb) {
    $doc = array_pop($breadcrumb);
    echo "<form>\n";
    foreach ($breadcrumb as $docuid)
      echo "<a href='?doc=$docuid'>&gt;</a> ";
    echo "<b>*</b>&nbsp;";
    echo "<input type='hidden' name='doc' value='$doc'>";
    echo isset($_GET['format']) ? "<input type='hidden' name='format' value='$_GET[format]'>" : '';
    echo "<input type='text' name='ypath' size=80 value=\"$ypath\"></form>\n<br>\n";
  }
}

// exploitation du graphe d'appel
class CallingGraph {
  // Le graphe d'appel est géré au travers de la variable session $_SESSION['parents'] : [ {child} => {parent} ]
  static $verbose = 0; // peut être utilisé pour afficher le statut de makeBreadcrumb
  
  // mise à jour du graphe d'appel et renvoi du fil d'ariane
  static function makeBreadcrumb(): array {
    //echo "referer ",(isset($_SERVER['HTTP_REFERER']) ? "= $_SERVER[HTTP_REFERER]" : "non défini"),"<br>\n";
    //echo "<pre>_SERVER = "; print_r($_SERVER); echo "</pre>\n";
    if (!isset($_GET['doc']))
      return [];
    $curl = "http://$_SERVER[HTTP_HOST]"
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

// si un verrou a été posé il est levé
ydunlockall();

// les 2 premières actions ne nécessitent pas le paramètre doc
// action dump - affichage des variables de session et s'il existe du document courant
if (isset($_GET['action']) and ($_GET['action']=='dump')) {
  echo "<pre>";
  echo "_SESSION = "; print_r($_SESSION);
  if (isset($_GET['doc'])) {
    if (!ydcheckReadAccess($_GET['doc']))
      die("accès interdit");
    $ypath = isset($_GET['ypath']) ? $_GET['ypath'] : '';
    $text = ydread($_GET['doc']);
    echo "<h2>doc $_GET[doc]</h2>\n";
    $doc = new_yamlDoc($_GET['doc']);
    if ($ypath)
      echo "ypath=$ypath\n";
    echo str_replace(['&','<'], ['&amp;','&lt;'], $doc->yaml($ypath));
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
  if (!($doc = new_yamlDoc($_GET['doc']))) {
    echo "<b>Erreur: le document $_GET[doc] n'existe pas</b><br>\n";
    if ($parent = CallingGraph::parent($_GET['doc']))
      echo "<a href='?delDoc=$_GET[doc]&amp;doc=$parent'>",
           "L'effacer dans le catalogue $parent</a><br>\n";
  }
  else {
    $doc->checkPassword($_GET['doc']);
    if ($doc->isHomeCatalog())
      $_SESSION['homeCatalog'] = $_GET['doc'];
    $ypath = isset($_GET['ypath']) ? $_GET['ypath'] : '';
    if (!isset($_GET['format']))
      $doc->show($ypath);
    elseif ($_GET['format']=='yaml')
      echo "<pre>",$doc->yaml($ypath),"</pre>\n";
  }
  die();
}


// action edit - génération du formulaire d'édition du document courant
if ($_GET['action']=='edit') {
  // verification que le document est consultable
  if (!ydcheckReadAccess($_GET['doc']))
    die("accès interdit");
  // verification que le document est modifiable
  if (ydcheckWriteAccess($_GET['doc'])<>1)
    die("mise à jour interdite");
  // verouillage du document pour éviter des mises à jour concurrentielles
  if (!ydlock($_GET['doc']))
    die("mise à jour impossible document verouillé");
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
    else
      echo "Aucun catalogue disponible<br>\n";
  }
  else {
    ydwrite($_GET['doc'], $_POST['text']);
    echo "Enregistrement du document $_GET[doc]<br>\n";
    $doc = new_yamlDoc($_GET['doc']);
    $doc->show(isset($_GET['ypath']) ? $_GET['ypath'] : '');
  }
}

// action check - verification de la conformité d'un document à son éventuel schema
if ($_GET['action']=='check') {
  if (!($doc = new_yamlDoc($_GET['doc'])))
    die("<b>Erreur: le document $_GET[doc] n'existe pas</b><br>\n");
  $doc->checkSchemaConformity(isset($_GET['ypath']) ? $_GET['ypath'] : '');
  die();
}