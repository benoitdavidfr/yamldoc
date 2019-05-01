<?php
/*PhpDoc:
name: file.php
title: file.php - Affiche un fichier (image, PDF, ...)
doc: |
journal: |
  26/9/2018:
   - ajout rawurldecode() sur les path pour accéder aux fichiers dont le nom comprend notamment des blancs
  22/8/2018:
    - prise en compte de la gestion du store, gestion des erreurs
includes: [ store.inc.php ]
*/
require_once __DIR__.'/store.inc.php';

//echo "<pre>_SERVER="; print_r($_SERVER);
//[REQUEST_URI] => /yamldoc/image.php/topovoc/image01.gif
//[SCRIPT_NAME] => /yamldoc/image.php
session_start();

$contentTypes = [
  'txt'=> 'text/plain',
  'htm'=> 'text/html',
  'html'=> 'text/html',
  'gif'=> 'image/gif',
  'png'=> 'image/png',
  'jpeg'=> 'image/jpeg',
  'jpg'=> 'image/jpeg',
  'pdf'=> 'application/pdf',
  'svg'=> 'image/svg+xml',
  'xml'=> 'application/xml',
];

$path = substr($_SERVER['REQUEST_URI'], strlen($_SERVER['SCRIPT_NAME']));
//echo "path='$path'<br>\n";
if (!$path) {
  header("HTTP/1.1 404 Not Found");
  echo "<!DOCTYPE HTML><html><head><meta charset='UTF-8'><title>file.php</title></head><body>\n";
  echo "Aucun document défini dans file.php<br>\n";
  echo "file.php permet d'afficher un fichier stocké dans YamlDoc de type image, PDF, ..., hors Yaml et Php<br>\n";
  echo "Les extensions prévues sont:<ul>";
  foreach ($contentTypes as $ext => $format)
    echo "<li>$ext : $format\n";
  die("</ul>");
}

if (!preg_match('!\.(.+)$!', $path, $matches) || !isset($contentTypes[$matches[1]])) {
  header("HTTP/1.1 404 Not Found");
  echo "<!DOCTYPE HTML><html><head><meta charset='UTF-8'><title>file.php</title></head><body>\n";
  echo "Erreur: extension non reconnue pour $path dans file.php<br>\n";
  echo "file.php permet d'afficher un fichier stocké dans YamlDoc de type image, PDF, ..., hors Yaml et Php<br>\n";
  echo "Les extensions prévues sont:<ul>";
  foreach ($contentTypes as $ext => $format)
    echo "<li>$ext : $format\n";
  die("</ul>");
}
$ext = $matches[1];
//echo "ext=$ext";
$storeid = Store::id();
$storepath = Store::storepath();
$path = rawurldecode($path);
if (!is_file(__DIR__."/$storepath$path")) {
  header("HTTP/1.1 404 Not Found");
  echo "<!DOCTYPE HTML><html><head><meta charset='UTF-8'><title>file.php</title></head><body>\n";
  echo "Erreur dans file.php: fichier $path du store $storeid inexistant<br>\n";
  echo "file.php permet d'afficher un fichier stocké dans YamlDoc de type image, PDF, ..., hors Yaml et Php<br>\n";
  echo "Les extensions prévues sont:<ul>";
  foreach ($contentTypes as $ext => $format)
    echo "<li>$ext : $format\n";
  die("</ul>");
}

//die("OK");
header('Content-type: '.$contentTypes[$ext]);
echo file_get_contents(__DIR__."/$storepath$path");
