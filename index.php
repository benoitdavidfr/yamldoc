<?php
/*PhpDoc:
name: index.php
title: index.php - version 2 du visualiseur de documents Yaml
doc: |
  <a href='/yamldoc/?action=version&name=index.php'>voir le code</a>
*/
{ // doc 
$phpDocs['index.php'] = <<<'EOT'
name: index.php
title: index.php - version 2 du visualiseur de documents Yaml
doc: |
  Le script utilise les variables de session suivantes:
    - homeCatalog : uid du dernier catalogue d'accueil traversé ou absent si aucun ne l'a été
        identifie l'utilisateur
    - parents : graphe de navigation sous la forme d'un tableau docuid enfant vers docuid parent
        permet d'afficher le fil d'Ariane
    - checkedReadAccess : liste des docuid pour lesquels l'accès en lecture est autorisé
        évite de retester si yamlPassword existe et de redemander le mot de passe
    - checkedWriteAccess : pour chaque document indique 1 s'il est modifiable, 0 s'il ne l'est pas
        évite de retester si un document est modifiable
    - locks : liste des documents verrouillés
        permet de dévérouiller les documents verrouillés
    - language : pour chaque document multi-lingue (champ language) indique la liste des langues
        permet d'afficher la liste des langues possibles dans le menu
    
  A REVOIR:
  - les fichiers servreg devraient être considérés comme des catalogues
  - un fichier protégé et non conforme Yaml n'est pas protégé
  - le mécanisme de vérouillage semble complètement inutile
  
  IDEES:
  - intégrer la gestion de mot de passe
  
journal: |
  17/7/2018:
  - traitement des $phpDocs complexes
  - remplacement des méthodes php() par asArray()
  14/7/2018:
  - modif du titre de la page HTML
  7-10/7/2018:
  - gestion multi-lingue
  29-30/6/2018:
  - gestion multi-store
  18-19/6/2018:
  - ajout cmde reindex pour reindexer les documents en full text de manière incrémentale
  21/5/2018:
  - ajout cmde synchro qui enchaine pull et push
  - ajout cmde git_pull_src
  20/5/2018:
  - ajout cmde git pull et push
  19/5/2018:
  - sécurisation de store pour réduire les erreurs de manip
  - debuggage de la protection
  - améliorer l'affichage en cas d'erreur Yaml
  - ajout cmdes git add & commit
  16-18/5/2018:
  - ajout protection en consultation, buggée
  12-13/5/2018:
  - ajout protection en modification
  - ajout mécanisme simple de verrouillage des documents en cours de mise à jour
  - ajout gestion sous-répartoires
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
EOT;
}
session_start();
require_once __DIR__.'/search.inc.php';
if (file_exists(__DIR__.'/mysqlparams.inc.php'))
  require_once __DIR__.'/mysqlparams.inc.php';
require_once __DIR__.'/yd.inc.php';
require_once __DIR__.'/ydclasses.inc.php';
require_once __DIR__.'/git.inc.php';

use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Yaml\Exception\ParseException;

ini_set('memory_limit', '1024M');
ini_set('max_execution_time', 600);

// Affichage du menu et du fil d'ariane comme array de docid
function show_menu(string $store, array $breadcrumb) {
  // affichage du menu
  $ypath = isset($_GET['ypath']) ? $_GET['ypath'] : '';
  $ypatharg = $ypath ? '&amp;ypath='.urlencode($ypath): '';
  $langp = isset($_GET['lang']) ? "&amp;lang=$_GET[lang]" : '';
  $docuid = ($breadcrumb ? $breadcrumb[count($breadcrumb)-1] : null);
  $docp = $docuid ? "&amp;doc=$docuid" : '';
  echo "<table border=1><tr><td><b>Menu :</b></td>\n";
  if ($docuid) {
    // showAsHtml
    echo "<td><a href='?doc=$docuid$ypatharg$langp'>html</a></td>\n";
    // showAsYaml
    echo "<td><a href='?doc=$docuid$ypatharg$langp&amp;format=yaml'>yaml</a></td>\n";
    // showAsJSON
    echo "<td><a href='?doc=$docuid$ypatharg$langp&amp;format=json'>json</a></td>\n";
    // showPhpSrc - affiche le source Php
    if ($docuid && (ydext($store, $docuid)=='php'))
      echo "<td><a href='?action=showPhpSrc$docp$langp'>PhpSrc</a></td>\n";
    // check
    //echo "<td><a href='?action=check&amp;doc=$docuid$ypatharg'>check</a></td>\n";
    // edit - la possibilité n'est pas affichée si le doc courant n'est pas éditable
    if (ydcheckWriteAccess($store,$docuid)<>0)
      echo "<td><a href='?action=edit&amp;doc=$docuid$langp'>edit</a></td>\n";
    // clone - uniquement s'il existe un catalogue parent
    if ($catuid = CallingGraph::parent($docuid))
      echo "<td><a href='?clone=$docuid&amp;doc=$catuid$langp'>clone</a></td>\n";
    if (function_exists('mysqlParams'))
      echo "<td><a href='?action=reindex$docp$langp'>reindex</a></td>\n";
  }
  // dump
  echo "<td><a href='?action=dump$docp$ypatharg$langp'>dump</a></td>\n";
  // unset
  echo "<td><a href='?action=unset$docp$ypatharg$langp'>unset</a></td>\n";
  // razrw - effacement eds variables mémorisant l'accès en lecture/écriture - utile pour débugger
  //echo "<td><a href='?action=razrw",($docuid ? "&amp;doc=$docuid" : ''),"'>razrw</a></td>\n";
  if (isset($_SESSION['homeCatalog']) && in_array($_SESSION['homeCatalog'], ['benoit'])) {
    echo "<td><a href='?action=git_pull_src$docp$ypatharg$langp'>pull src</a></td>\n";
    echo "<td><a href='?action=version$docp$ypatharg$langp'>version</a></td>\n";
    echo "<td><a href='?action=git_commit_a$docp$ypatharg$langp'>commit</a></td>\n";
    echo "<td><a href='?action=git_pull$docp$ypatharg$langp'>pull</a></td>\n";
    echo "<td><a href='?action=git_push$docp$ypatharg$langp'>push</a></td>\n";
    echo "<td><a href='?action=git_synchro$docp$ypatharg$langp'>synchro</a></td>\n";
    echo "<td><a href='?action=git_log$docp$ypatharg$langp'>log</a></td>\n";
  }
  echo "</tr></table>\n";

  // affichage du fil d'ariane et du ypath
  if ($breadcrumb) {
    $doc = array_pop($breadcrumb);
    echo "<form>\n";
    foreach ($breadcrumb as $docuid2)
      echo "<a href='?doc=$docuid2'>&gt;</a> ";
    echo "<b>*</b>&nbsp;";
    echo "<input type='hidden' name='doc' value='$doc'>";
    echo isset($_GET['format']) ? "<input type='hidden' name='format' value='$_GET[format]'>" : '';
    echo "<input type='text' name='ypath' size=80 value=\"$ypath\">\n";
    
    // choix de la langue pour un document multi-lingue
    //echo "docuid=$docuid, ",implode(',',$_SESSION['language'][$docuid]);
    if (isset($_SESSION['language'][$docuid])) {
      echo str_repeat("&nbsp;", 10);
      $url = '?';
      foreach (['action','doc','ypath','format'] as $param)
        $url .= (isset($_GET[$param]) ? "$param=".urlencode($_GET[$param]).'&amp;' : '');
      foreach ($_SESSION['language'][$docuid] as $lang) {
        if (isset($_GET['lang']) && ($_GET['lang']==$lang))
          echo "<b>$lang</b>&nbsp;";
        else
          echo "<a href='${url}lang=$lang'>$lang</a>&nbsp;";
      }
    }
    echo "</form><br>\n";
  }
}

// exploitation du graphe d'appel
class CallingGraph {
  // Le graphe d'appel est géré au travers de la variable session $_SESSION['parents'] : [ {child} => {parent} ]
  static $verbose = 0; // peut être utilisé pour afficher le statut de makeBreadcrumb
  
  // retrouve le docuid du referer ou ''
  static function getRefererDocuid() {
    if (!isset($_SERVER['HTTP_REFERER']))
      return '';
    //echo "HTTP_REFERER -> $_SERVER[HTTP_REFERER]<br>\n";
    //echo "curl=http://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]<br>\n";
    //echo "QUERY_STRING=$_SERVER[QUERY_STRING]<br>\n";
    // test si le referer est un URL yamldoc, si non retour ''
    if (strncmp("http://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]", $_SERVER['HTTP_REFERER'],
                strlen("http://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]")-strlen($_SERVER['QUERY_STRING'])) <> 0) {
      echo "referer externe<br>\n";
      return '';
    }
    $refererargs = substr(
        $_SERVER['HTTP_REFERER'],
        strlen("http://$_SERVER[HTTP_HOST]") + strlen($_SERVER['REQUEST_URI']) - strlen($_SERVER['QUERY_STRING'])
      ).'&';
    //echo "refererargs=$refererargs<br>\n";
    if (!preg_match('!doc=([^&]*)!', $refererargs, $matches))
      return '';
    //echo "matches="; print_r($matches); echo "<br>\n";
    return $matches[1]; // l'id de doc extrait du referer
  }
  
  // mise à jour du graphe d'appel et renvoi du fil d'ariane
  static function makeBreadcrumb(): array {
    if (!isset($_GET['doc']))
      return [];
    $doc = $_GET['doc'];
    if ($parent = self::getRefererDocuid()) { // l'id de doc extrait du referer
      if ($parent == $doc) {
        if (self::$verbose)
          echo "boucle détectée<br>\n";
      }
      elseif (isset($_SESSION['parents'])
               && in_array($doc, $_SESSION['parents'])
               && self::isAncestor($doc, $parent)) {
        if (self::$verbose)
          echo "back détecté<br>\n";
      }
      else {
        $_SESSION['parents'][$doc] = $parent;
        //echo "<pre>_SESSION après = "; print_r($_SESSION); echo "</pre>\n";
      }
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

{ // gestion de la variable en env store 
  if (isset($_GET['store'])) // si le paramètre store est défini alors la variable est reaffectée
    $_SESSION['store'] = $_GET['store'];
  elseif (!isset($_SESSION['store'])) // si non si la variable n'est pas affectée alors elle l'est par défaut
    $_SESSION['store'] = in_array($_SERVER['SERVER_NAME'], ['georef.eu','localhost']) ? 'pub' : 'docs';
}

{ // affichage du titre du document 
  if (!isset($_GET['action']) && isset($_GET['doc']))
    $title = "$_GET[doc] ($_SESSION[store])";
  else
    $title = "yaml $_SESSION[store]";
  echo "<!DOCTYPE HTML><html><head><meta charset='UTF-8'><title>$title</title></head><body>\n";
}

//echo getcwd() . "<br>\n";
$options = isset($_GET['options']) ? explode(',', $_GET['options']) : [];
if (!in_array('hideMenu', $options))
  show_menu($_SESSION['store'], CallingGraph::makeBreadcrumb());

// si un verrou a été posé alors il est levé
// !!! à vérifier le fonctionnement / je ne comprends pas !!!
ydunlockall();

// action version - affichage Phpdoc
if (isset($_GET['action']) && ($_GET['action']=='version')) {
  if (!isset($_GET['name'])) {
    echo "<h2>Documentation des scripts Php</h2><ul>\n";
    foreach ($phpDocs as $name => $phpDoc) {
      try {
        if (!is_array($phpDoc)) {
          $phpDoc = Yaml::parse($phpDoc);
        }
        else {
          $phpDoc = Yaml::parse($phpDoc['file']);
        }
        echo "<li><a href='?action=version",
             isset($_GET['doc']) ? "&amp;doc=$_GET[doc]" : '',
             "&amp;name=$name'>$phpDoc[title]</a>\n";
      }
      catch (ParseException $exception) {
        printf("<b>Analyse YAML erronée: %s</b>", $exception->getMessage());
        echo "<pre>",$phpDoc,"</pre>\n";
      }
    }
  }
  elseif (!is_array($phpDocs[$_GET['name']])) {
    echo "<pre>",str2html($phpDocs[$_GET['name']]),"</pre>\n";
  }
  else {
    foreach ($phpDocs[$_GET['name']] as $field => $doc) {
      echo "<h2>$field</h2>";
      if (is_string($doc))
        echo "<pre>",str2html($doc),"</pre>\n";
      else {
        foreach ($doc as $sname => $sdoc) {
          echo "<h3>$sname</h3>";
          echo "<pre>",str2html($sdoc),"</pre>\n";
        }
      }
    }
  }
  die();
}

// les premières actions ne nécessitent pas le paramètre doc
// action dump - affichage des variables de session et s'il existe du document courant
if (isset($_GET['action']) && (substr($_GET['action'], 0, 4)=='dump')) {
  $docp = isset($_GET['doc']) ? "&amp;doc=$_GET[doc]" : '';
  switch ($_GET['action']) {
    case 'dump':
      echo "dump <a href='?action=dump-session$docp'>session</a>\n";
      echo " <a href='?action=dump-server$docp'>server</a>\n";
      echo " <a href='?action=dump-cookies$docp'>cookie</a>\n";
      echo "<br><pre>";
      if (isset($_GET['doc'])) {
        if (!ydcheckReadAccess($_SESSION['store'], $_GET['doc']))
          die("accès interdit");
        $ypath = isset($_GET['ypath']) ? $_GET['ypath'] : '';
        //$text = ydread($_GET['doc']);
        $doc = new_yamlDoc($_SESSION['store'], $_GET['doc']);
        echo "<h2>var_dump $_GET[doc] $ypath</h2>\n"; $doc->dump($ypath);
      }
      echo "</pre>\n";
      break;
      
      case 'dump-server':
        echo "<pre>_SERVER = "; print_r($_SERVER);
        break;
      
      case 'dump-session':
        echo "<pre>_SESSION = "; print_r($_SESSION);
        break;
      
      case 'dump-cookies':
        echo "<pre>_COOKIE = "; print_r($_COOKIE);
        break;
  }
}

// action unset - effacement des variables de session
if (isset($_GET['action']) && ($_GET['action']=='unset')) {
  foreach ($_SESSION as $key => $value)
    unset($_SESSION[$key]);
}

// action razrw - effacement des variables de session  & checkedWriteAccess
if (isset($_GET['action']) && ($_GET['action']=='razrw')) {
  foreach (['checkedReadAccess', 'checkedWriteAccess'] as $key)
    unset($_SESSION[$key]);
  unset($_GET['action']);
}

// actions git
if (isset($_GET['action']) && (substr($_GET['action'], 0, 4)=='git_')) {
  $_GET['action'](isset($_GET['doc']) ? $_GET['doc'] : null);
  die();
}


// puis les pré-actions à l'affichage
// pré-action delDoc - suppression d'un document dans le catalogue
if (isset($_GET['delDoc'])) {
  YamlCatalog::delete_from_catalog($_SESSION['store'], $_GET['delDoc'], $_GET['doc']);
  echo "Doc $_GET[delDoc] effacé du catalogue $_GET[doc] du store $_SESSION[store]<br>\n";
}

// pré-action clone - $_GET['clone'] contient le doc à cloner et $_GET['doc'] le catalogue
if (isset($_GET['clone'])) {
  $newdocuid = uniqid();
  YamlCatalog::clone_in_catalog($_SESSION['store'], $newdocuid, $_GET['clone'], $_GET['doc']);
  $ext = ydwrite($_SESSION['store'], $newdocuid, ydread($_SESSION['store'], $_GET['clone']));
  git_add($newdocuid, $ext);
  echo "Document $_GET[clone] cloné dans $newdocuid<br>\n";
}


// action d'affichage d'un document ou de recherche de documents
if (!isset($_GET['action']) && (isset($_GET['doc']) || isset($_GET['ypath']))) {
  if (!isset($_GET['doc']) || !$_GET['doc'])
    die("<a href='?doc=index'>Accès au document par défaut</a>\n");
  
  //$docuid = isset($_GET['doc']) ? $_GET['doc'] : getdocuid();
  $docuid = $_GET['doc'];
  //isset($_SESSION['parents'][$doc]);
  $ypath = isset($_GET['ypath']) ? $_GET['ypath'] : '';
  
  // ypath ne commence pas par / alors il s'agit d'uen recherche plein texte
  if ($ypath && (substr($ypath,0,1)<>'/')) {
    echo "search<br>\n";
    $dirname = dirname($_SERVER['SCRIPT_NAME']);
    //echo "dirname=$dirname<br>\n";
    header("Location: http://$_SERVER[SERVER_NAME]$dirname/search.php?value=".urlencode($ypath));
    die();
  }
  
  try {
    $doc = new_doc($_SESSION['store'], $docuid);
  }
  catch (ParseException $exception) {
    printf("<b>Analyse YAML erronée: %s</b>", $exception->getMessage());
    echo "<pre>",ydread($_SESSION['store'], $docuid),"</pre>\n";
    die();
  }
  // 
  if (!$doc) {
    echo "<b>Erreur: le document $docuid n'existe pas</b><br>\n";
    if ($parent = CallingGraph::parent($docuid))
      echo "<a href='?delDoc=$docuid&amp;doc=$parent'>",
           "L'effacer dans le catalogue $parent</a><br>\n";
  }
  elseif (!$doc->checkReadAccess($_SESSION['store'], $docuid))
    die("accès interdit");
  else {
    if ($doc->isHomeCatalog())
      $_SESSION['homeCatalog'] = $docuid;
    if ($doc->language) {
      if (is_string($doc->language))
        $_SESSION['language'][$docuid] = [$doc->language];
      else
        $_SESSION['language'][$docuid] = $doc->language;
    }
    if (!isset($_GET['format']))
      $doc->show($docuid, $ypath);
    elseif ($_GET['format']=='yaml')
      echo "<pre>",str2html($doc->yaml($ypath)),"</pre>\n";
    elseif ($_GET['format']=='json')
      echo "<pre>",str2html($doc->json($ypath)),"</pre>\n";
    else
      echo "<b>Erreur: format d'export '$_GET[format]' non reconnu</b><br>\n";
  }
  die();
}


// évite d'avoir à tester le paramètre doc dans les actions suivantes
if (!isset($_GET['doc'])) {
  die("<a href='?doc=index'>Accès au document par défaut</a>\n");
}


// action edit - génération du formulaire d'édition du document courant
if ($_GET['action']=='edit') {
  // verification que le document est consultable
  if (!ydcheckReadAccess($_SESSION['store'], $_GET['doc']))
    die("accès interdit");
  // verification que le document est modifiable
  if (ydcheckWriteAccess($_SESSION['store'], $_GET['doc'])<>1)
    die("mise à jour interdite");
  // verouillage du document pour éviter des mises à jour concurrentielles
  if (!ydlock($_SESSION['store'], $_GET['doc']))
    die("mise à jour impossible document verouillé");
  $text = ydread($_SESSION['store'], $_GET['doc']);
  echo "<table><form action='?action=store&amp;doc=$_GET[doc]' method='post'>\n",
       "<tr><td><textarea name='text' rows='40' cols='120'>$text</textarea></td></tr>\n",
       "<tr><td><input type='submit' value='Enregistrer'></td></tr>\n",
       "</form></table>\n";
  die();
}

// action store - enregistrement d'un contenu à la suite d'une édition
if ($_GET['action']=='store') {
  // graitement du cas d'appel de store non lié à l'edit
  if (!isset($_POST['text'])) {
    echo "<b>Erreur d'appel de la commande: aucun texte transmis</b><br>\n";
    $doc = new_yamlDoc($_SESSION['store'], $_GET['doc']);
    $doc->show(isset($_GET['ypath']) ? $_GET['ypath'] : '');
  }
  elseif (strlen($_POST['text'])==0) {
    yddelete($_SESSION['store'], $_GET['doc']);
    echo "<b>document vide $_GET[doc] effacé</b><br>\n";
    if ($parent = CallingGraph::parent($_GET['doc']))
      echo "<a href='?delDoc=$_GET[doc]&amp;doc=$parent'>",
           "L'effacer dans le catalogue $parent</a><br>\n";
    else
      echo "Aucun catalogue disponible<br>\n";
  }
  else {
    $ext = ydwrite($_SESSION['store'], $_GET['doc'], $_POST['text']);
    echo "Enregistrement du document $_GET[doc]<br>\n";
    //git_commit($_GET['doc'], $ext);
    try {
      $doc = new_yamlDoc($_SESSION['store'], $_GET['doc']);
      $doc->show($_GET['doc'], isset($_GET['ypath']) ? $_GET['ypath'] : '');
    }
    catch (ParseException $exception) {
      printf("<b>Analyse YAML erronée: %s</b>", $exception->getMessage());
      echo "<pre>",ydread($_SESSION['store'], $_GET['doc']),"</pre>\n";
    }
  }
}

// action check - verification de la conformité d'un document à son éventuel schema
if ($_GET['action']=='check') {
  if (!($doc = new_yamlDoc($_SESSION['store'], $_GET['doc'])))
    die("<b>Erreur: le document $_GET[doc] n'existe pas</b><br>\n");
  $doc->checkSchemaConformity(isset($_GET['ypath']) ? $_GET['ypath'] : '');
  die();
}

// action checkIntegrity - verification adhoc d'intégrité
if ($_GET['action']=='checkIntegrity') {
  if (!($doc = new_yamlDoc($_SESSION['store'], $_GET['doc'])))
    die("<b>Erreur: le document $_GET[doc] n'existe pas</b><br>\n");
  $doc->checkIntegrity();
  die();
}

// action reindex - re-indexation incrémentale de tous les fichiers du store courant
if ($_GET['action']=='reindex') {
  if (function_exists('mysqlParams')) {
    Search::incrIndex($_SESSION['store']);
    die("reindex OK<br>\n");
  }
  else
    die("reindex impossible, fonction mysqlParams() non définie<br>\n");
}

// action showPhpSrc - affiche le source Php d''une requête
if ($_GET['action']=='showPhpSrc') {
  if (ydext($_SESSION['store'], $_GET['doc'])<>'php')
    die("Le document $_GET[doc] n'est pas une requête<br>\n");
  echo "<b>Code source Php de $_GET[doc]</b>\n";
  echo "<pre>",str_replace(['<'],['&lt;'],ydread($_SESSION['store'], $_GET['doc'])),"</pre>\n";
  die("<br>\n");
}
