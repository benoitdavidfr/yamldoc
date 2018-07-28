<?php
/*PhpDoc:
name: file.php
title: file.php - Affiche un fichier (image, PDF, ...)
doc: |
*/
//echo "<pre>_SERVER="; print_r($_SERVER);
//[REQUEST_URI] => /yamldoc/image.php/topovoc/image01.gif
//[SCRIPT_NAME] => /yamldoc/image.php
session_start();
$path = substr($_SERVER['REQUEST_URI'], strlen($_SERVER['SCRIPT_NAME']));
//echo "path=$path<br>\n";
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
if (!preg_match('!\.(.+)$!', $path, $matches) || !isset($contentTypes[$matches[1]])) {
  die("Erreur: extension non reconnue pour $path dans file.php");
}
$ext = $matches[1];
//echo "ext=$ext";
if (!is_file(__DIR__."/$_SESSION[store]$path")) {
  die("Erreur: fichier $path du store $_SESSION[store] inexistant dans file.php");
}

header('Content-type: '.$contentTypes[$ext]);
echo file_get_contents(__DIR__."/$_SESSION[store]$path");