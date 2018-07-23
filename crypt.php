<?php
/*PhpDoc:
name: crypt.php
title: crypt.php - encrypte tous les docs - test non finalisé
doc: |
*/
require_once __DIR__.'/yd.inc.php';

echo "<!DOCTYPE HTML><html><head><meta charset='UTF-8'><title>crypt</title></head><body>\n";

$wd = opendir('docs');
while (false !== ($entry = readdir($wd))) {
  if (!preg_match('!^([0-9a-f]+)\.yaml$!', $entry, $matches))
    continue;
  //echo "$entry<br>\n";
  $name = $matches[1];
  $doc = file_get_contents("docs/$entry");
  echo "<pre>$doc</pre>\n";
  echo "<pre>",ydencrypt($doc),"</pre>\n";
  file_put_contents("docsc/$name.doc", ydencrypt($doc));
  echo "docsc/$name.doc<br>\n";
}
closedir($wd);

