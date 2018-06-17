<?php

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

$key = isset($_GET['key']) ? $_GET['key'] : '';
$value = isset($_GET['value']) ? $_GET['value'] : '';
echo "<form><table border=1>\n";
echo "<tr><td>key:</td><td><input type='text' name='key' size=80 value=\"$key\"></td></tr>\n";
echo "<tr><td>value:</td><td><input type='text' name='value' size=80 value=\"$value\"></td></tr>\n";
echo "<tr><td colspan=2><center><input type='submit' value='search'></center></td></tr>";
echo "</table></form>\n<br>\n";

if (!$key && !$value)
  die();

$mysqli = openMySQL(mysqlParams());

$sql = "select fragid, text from fragment "
  ."where ".($value ? "match (text) against (\"$value\" in boolean mode) ":'')
  .($key && $value ? " and " : '')
  .($key ? " fragid like \"%$key%\" ":'');

echo "<pre>sql=$sql</pre>\n";
if (!($result = $mysqli->query($sql)))
  throw new Exception("Ligne ".__LINE__.", Req. \"$sql\" invalide: ".$mysqli->error);
echo "<table border=1>\n";
while ($tuple = $result->fetch_array(MYSQLI_ASSOC)) {
  //print_r($tuple); echo "<br>\n";
  echo "<tr><td><a href='frag.php?fragid=$tuple[fragid]'>$tuple[fragid]</a></td><td>$tuple[text]</td></tr>\n";
}
echo "</table>\n";

