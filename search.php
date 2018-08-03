<?php
/*PhpDoc:
name: search.php
title: search.php - script de recherche
doc: |
journal: |
  29/7/2018:
    adaptation à Store
*/
session_start();
require_once __DIR__.'/store.inc.php';
require_once __DIR__.'/yd.inc.php';
require_once __DIR__.'/ydclasses.inc.php';
require_once __DIR__.'/search.inc.php';

use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Yaml\Exception\ParseException;

//ini_set('memory_limit', '1024M');
//ini_set('max_execution_time', 600);

echo "<!DOCTYPE HTML><html><head><meta charset='UTF-8'><title>search</title></head><body>\n";
//echo "<pre>_SESSION = "; print_r($_SESSION); echo "<pre>";

if (!MySql::available()) {
  die("La recherche n'est pas disponible car l'utilisation de MySQL n'a pas été paramétrée.<br>\n"
    ."Pour le paramétrer voir le fichier <b>mysqlparams.inc.php.model</b><br>\n");
}

$docid = isset($_GET['doc']) ? $_GET['doc'] : '';
$ypath = isset($_GET['ypath']) ? $_GET['ypath'] : '';
$value = isset($_GET['value']) ? $_GET['value'] : '';
echo "<form><table border=1>\n";
echo "<tr><td>doc:</td><td><input type='text' name='doc' size=80 value=\"$docid\"></td></tr>\n";
echo "<tr><td>ypath:</td><td><input type='text' name='ypath' size=80 value=\"$ypath\"></td></tr>\n";
echo "<tr><td>value:</td><td><input type='text' name='value' size=80 value=\"$value\"></td></tr>\n";
echo "<tr><td colspan=2><center><input type='submit' value='search'></center></td></tr>";
echo "</table></form>\n<br>\n";

if (!$docid && !$ypath && !$value)
  die();

$where = [];
// solution simplifiée: si je suis benoit alors je cherche dans les différents stores,
// sinon je ne cherche que dans pub
if (!isset($_SESSION['homeCatalog']) || ($_SESSION['homeCatalog']<>'benoit'))
  $where[] = "store='pub'";
if ($docid)
  $where[] = "docid like \"$docid%\"";
if ($ypath)
  $where[] = "ypath like \"$ypath%\"";
if ($value)
  $where[] = "match (text) against (\"$value\" in boolean mode)";
if ($value)
  $sql = "select store, match (text) against (\"$value\" in boolean mode) relevance, docid, ypath, text from fragment\n"
    ."where ".implode(' and ', $where);
else
  $sql = "select store, fragid, text from fragment\n"
    ."where ".implode(' and ', $where);

/*
SELECT MATCH('Content') AGAINST ('keyword1
keyword2') as Relevance FROM table WHERE MATCH
('Content') AGAINST('+keyword1 +keyword2' IN
BOOLEAN MODE) HAVING Relevance > 0.2 ORDER
BY Relevance DESC
*/
  
echo "<pre>sql=$sql</pre>\n";
echo "<table border=1>\n";
foreach (MySql::query($sql) as $tuple) {
  //print_r($tuple); echo "<br>\n";
  echo "<tr>";
  if ($value)
    printf('<td>%.2f</td>', $tuple['relevance']);
  $viewerUrl = Store::viewerUrl($tuple['store']);
  echo "<td><a href='$viewerUrl?doc=$tuple[docid]&amp;ypath=$tuple[ypath]'>",
       "$tuple[docid]$tuple[ypath]</a></td>";
  echo "<td>";
  showDoc($tuple['docid'], $tuple['text']);
  echo "</td></tr>\n";
}
echo "</table>\n";

