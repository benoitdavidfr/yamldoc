<?php

session_start();
require_once __DIR__.'/yd.inc.php';
require_once __DIR__.'/catalog.inc.php';
require_once __DIR__.'/servreg.inc.php';
require_once __DIR__.'/tree.inc.php';
require_once __DIR__.'/yamldata.inc.php';
require_once __DIR__.'/multidata.inc.php';
require_once __DIR__.'/search.inc.php';
require_once __DIR__.'/mysqlparams.inc.php';
use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Yaml\Exception\ParseException;

ini_set('memory_limit', '1024M');
ini_set('max_execution_time', 600);

echo "<!DOCTYPE HTML><html><head><meta charset='UTF-8'><title>search</title></head><body>\n";
//echo "<pre>_SESSION = "; print_r($_SESSION); echo "<pre>";
if ($_SESSION['homeCatalog']<>'benoit') {
  die("accès interdit<br>\n");
}

$key = isset($_GET['key']) ? $_GET['key'] : '';
$value = isset($_GET['value']) ? $_GET['value'] : '';
echo "<form><table border=1>\n";
echo "<tr><td>key:</td><td><input type='text' name='key' size=80 value=\"$key\"></td></tr>\n";
echo "<tr><td>value:</td><td><input type='text' name='value' size=80 value=\"$value\"></td></tr>\n";
echo "<tr><td colspan=2><center><input type='submit' value='search'></center></td></tr>";
echo "</table></form>\n<br>\n";

if (!$key && !$value)
  die();

$where = [];
if ($key)
  $where[] = "fragid like \"%$key%\"";
if ($value)
  $where[] = "match (text) against (\"$value\" in boolean mode)";
if ($value)
  $sql = "select store, match (text) against (\"$value\" in boolean mode) relevance, fragid, text from fragment\n"
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
$mysqli = Search::openMySQL(mysqlParams());
$result = Search::query($sql);
echo "<table border=1>\n";
while ($tuple = $result->fetch_array(MYSQLI_ASSOC)) {
  //print_r($tuple); echo "<br>\n";
  echo "<tr>";
  if ($value)
    printf('<td>%.2f</td>', $tuple['relevance']);
  echo "<td><a href='frag.php?store=$tuple[store]&amp;fragid=$tuple[fragid]'>$tuple[fragid]</a></td>";
  echo "<td>";
  showDoc($tuple['text']);
  echo "</td></tr>\n";
}
echo "</table>\n";

