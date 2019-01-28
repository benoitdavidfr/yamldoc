<?php
/*PhpDoc:
name: ydclasses.php
title: ydclasses.php - resolveur d'URI de classe YamlDoc
doc: |
  S'utilise soit par appel direct de l'URI,
  soit par appel du script avec des paramètres
  L'appel à l'URI utilise la définition sur Alwaysdata d'un site ydclasses.georef.eu comme site Php pointant sur
    ~/prod/georef/yamldoc/ydclasses.php/
    
  La résolution de l'URI d'une classe fournit:
    - comme doc HTML une documentation de la classe
    - sinon la description de l'API REST en OAI Yaml
  Les URL suivantes sont déduites de l'URI d'une classe:
    - http://ydclasses.georef.eu/{yamlClass}.schema.json fournit le schéma de la classe en JSON
    - http://ydclasses.georef.eu/{yamlClass}.schema.yaml fournit le schéma de la classe en Yaml
    - http://ydclasses.georef.eu/{yamlClass}/api décrit l'API REST de la classe en OAI et en Yaml

  Exemples:
    http://ydclasses.georef.eu/YData
    http://localhost/yamldoc/ydclasses.php/YData
    http://localhost/yamldoc/ydclasses.php/YData.schema.yaml
    http://localhost/yamldoc/ydclasses.php/YData.schema.json
  
  A FAIRE:
    - mettre en oeuvre une version machine to machine

journal: |
  27/1/2019:
    première version
*/
require_once __DIR__.'/yd.inc.php';
require_once __DIR__.'/store.inc.php';
require_once __DIR__.'/ydclasses/inc.php';

use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Yaml\Exception\ParseException;
use Michelf\MarkdownExtra;

/*ini_set('memory_limit', '2048M');
if (php_sapi_name()<>'cli')
  ini_set('max_execution_time', 600);*/

// URL de tests
if (isset($_GET['action']) && ($_GET['action']=='tests')) {
  echo "<h2>Cas de tests directs</h2><ul>\n";
  foreach ([
    '/'=> "répertoire racine",
    '/YData'=> "classes YData",
    '/YData/schema'=> "schéma",
  ] as $uri=> $title)
    echo "<li><a href='$_SERVER[SCRIPT_NAME]$uri'>$title : $uri</a>\n";
  echo "</ul>\n";
  echo "<pre>_GET="; print_r($_GET); echo "</pre>\n";
  echo "<pre>_SERVER = "; print_r($_SERVER);
  die("Fin des tests");
}

if (isset($_GET['action']) && ($_GET['action']=='phpfiles')) {
  if (!isset($_GET['file'])) {
    echo "<!DOCTYPE HTML><html><head><meta charset='UTF-8'><title>ydc phpfiles</title></head><body>\n";
    echo "<h2>Liste des fichiers Php</h2><ul>";
    foreach ($phpDocs as $fiphp => $phpDoc) {
      $phpDocFile = Yaml::parse($phpDoc['file']);
      echo "<li><a href='?action=phpfiles&amp;file=$fiphp'>$fiphp</a> - $phpDocFile[title]\n";
      if (isset($phpDoc['classes'])) {
        echo "<br><i>classes</i>: \n";
        foreach ($phpDoc['classes'] as $className => $docClass) {
          echo "<a href='$_SERVER[SCRIPT_NAME]/$className'>$className</a> \n";
        }
      }
    }
    echo "</ul>\n";
  }
  else {
    echo "<!DOCTYPE HTML><html><head><meta charset='UTF-8'><title>ydc $_GET[file]</title></head><body>\n";
    echo "<h2>Fichier $_GET[file]</h2>";
    $doc = $phpDocs[$_GET['file']];
    if (isset($doc['file'])) {
      $docfile = Yaml::parse($doc['file']);
      echo "<h3>File: $docfile[title]</h3>\n";
      if (isset($docfile['doc']) && $docfile['doc'])
        echo '<h4>doc</h4><pre>',$docfile['doc'],"</pre>\n";
      if (isset($docfile['journal']))
        echo "<h4>journal</h4><pre>",$docfile['journal'],"</pre>\n";
    }
    //echo '<pre>',$doc['file'],"</pre>\n";
    if (isset($doc['classes'])) {
      foreach ($doc['classes'] as $cname => $cdoc) {
        $cdoc = Yaml::parse($cdoc);
        echo "<h3>classe $cname : $cdoc[title]</h3>";
        if (isset($cdoc['doc']))
          echo MarkdownExtra::defaultTransform($cdoc['doc']);
      }
    }
    //var_dump($doc);
    //echo '<pre>',Yaml::dump($doc, 999),"</pre>\n";
  }
  die();
}

$path = isset($_SERVER['PATH_INFO']) ? $_SERVER['PATH_INFO'] : null;

// cas chemin vide, listage des classes
if (!$path || ($path == '/')) {
  echo "<!DOCTYPE HTML><html><head><meta charset='UTF-8'><title>ydclasses</title></head><body>\n";
  echo "<h2>Liste des classes de documents</h2><ul>";
  foreach ($phpDocs as $fiphp => $phpDoc) {
    //echo "<pre>$fiphp:"; print_r($phpDoc); echo "</pre>\n";
    if (!isset($phpDoc['classes'])) {
      //echo "<li><b>$fiphp - Aucune classe définie</b>\n";
    }
    else {
      //echo "<li><b>$fiphp</b><ul>\n";
      foreach ($phpDoc['classes'] as $className => $classDoc) {
        $class_parents = @class_parents("$className", false);
        if ($class_parents === false)
          echo "<li><b>Erreur $className n'est pas une classe</b>\n";
        elseif (($className<>'Doc') && !in_array('Doc', $class_parents))
          echo ''; // '"<li><i>$className n'est pas une sous-classe de Doc (",implode(',', $class_parents),")</i>\n";
        else {
          $classDoc = Yaml::parse($classDoc);
          $href = "$_SERVER[SCRIPT_NAME]/$className";
          echo "<li><a href='$href'>$className</a> : $classDoc[title]\n";
          if ($class_parents)
            echo " - fille de: ",implode(', ', $class_parents),"\n";
        }
        //echo "<pre>$className:"; print_r($classDoc); echo "</pre>\n";
      }
      //echo "</ul>\n";
    }
  }
  echo "</ul>\n";
  echo "<a href='$_SERVER[SCRIPT_NAME]?action=phpfiles'>Liste des fichiers Php<br>\n";
  die();
}

// cas /{yamlClass} - description de la classe
if (preg_match('!^/([^/\.]+)$!', $path, $matches)) {
  $className = $matches[1];
  echo "<!DOCTYPE HTML><html><head><meta charset='UTF-8'><title>ydclasses $className</title></head><body>\n";
  $classDoc = null;
  foreach ($phpDocs as $fiphp => $phpDoc) {
    if (isset($phpDoc['classes'][$className])) {
      $classDoc = Yaml::parse($phpDoc['classes'][$className]);
      break;
    }
  }
  if (!$classDoc)
    die("Erreur : classe $className non trouvée<br>\n");
  echo "<h2>$className - $classDoc[title]</h2>\n";
  
  $class_parents = @class_parents("$className", false);
  echo "classes parentes: (",implode(', ', $class_parents),")<br>\n";
  $schemaClass = null;
  if (is_file(__DIR__."/ydclasses/$className.schema.yaml"))
    $schemaClass = $className;
  else
    foreach ($class_parents as $class_parent)
      if (is_file(__DIR__."/ydclasses/$class_parent.schema.yaml"))
        $schemaClass = $class_parent;
  if (!$schemaClass)
    echo "Aucun schéma n'est associé à cette classe de documents<br>\n";
  else {
    $href = "$_SERVER[SCRIPT_NAME]/$schemaClass.schema";
    echo "Les documents de cette classe doivent respecter le schéma ",
         "<a href='$href.yaml'>$schemaClass.schema.yaml</a> (<a href='$href.json'>json</a>)<br>\n";
  }
  echo "La classe est définie dans le fichier $fiphp<br>\n";
  if (isset($classDoc['doc'])) {
    echo "<h3>Documentation de la classe</h3>\n";
    //echo '<pre>',$classDoc['doc'],"</pre>\n\n";
    echo MarkdownExtra::defaultTransform($classDoc['doc']);
  }
  die();
}

// cas /{yamlClass}.schema.yaml - affichage du schéma JSON en Yaml
if (preg_match('!^/([^/\.]+)\.schema\.yaml$!', $path, $matches)) {
  $className = $matches[1];
  if (!is_file(__DIR__."/ydclasses/$className.schema.yaml")) {
    echo "pas de fichier $className.schema.yaml<br>\n";
  }
  else {
    header('Content-type: text/plain');
    readfile(__DIR__."/ydclasses/$className.schema.yaml");
  }
  die();
}

// cas /{yamlClass}.schema.json - affichage du schéma JSON en JSON
if (preg_match('!^/([^/\.]+)\.schema\.json$!', $path, $matches)) {
  $className = $matches[1];
  if (!is_file(__DIR__."/ydclasses/$className.schema.yaml")) {
    echo "pas de fichier $className.schema.yaml<br>\n";
  }
  else {
    header('Content-type: application/json');
    $text = file_get_contents(__DIR__."/ydclasses/$className.schema.yaml");
    $php = Yaml::parse($text, Yaml::PARSE_DATETIME);
    echo json_encode($php, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE),"\n";
  }
  die();
}

echo "Ne correspond à aucun cas<br>\n";
echo "PATH_INFO=$path<br>\n";

// cas /{yamlClass}/schema fournit le schéma de la classe par défaut en JSON
/*
- http://ydclasses.georef.eu/{yamlClass}/schema fournit le schéma de la classe par défaut en JSON
- http://ydclasses.georef.eu/{yamlClass}/schema.json fournit le schéma de la classe en JSON
- http://ydclasses.georef.eu/{yamlClass}/api décrit l'API REST de la classe en OAI et en Yaml
*/