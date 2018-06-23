<?php
//echo "<pre>_SERVER="; print_r($_SERVER); echo "</pre>\n";
$fragid = explode('/', $_GET['fragid']);
$dirpath = __DIR__."/docs";
$id0 = array_shift($fragid);
//echo "id0=$id0<br>\n";
while (is_dir("$dirpath/$id0")) {
  $dirpath = "$dirpath/$id0";
  $id0 = array_shift($fragid);
}
//echo "dirpath=$dirpath<br>\n";
if (is_file("$dirpath/$id0.yaml")) {
  $dirpath = substr($dirpath, strlen(__DIR__."/docs/"));
  $docid = ($dirpath ? "$dirpath/$id0" : $id0);
  $ypath = '/'.implode('/', $fragid);
  echo "<a href='index.php?doc=$docid&amp;ypath=$ypath'>$_GET[fragid]</a><br>";
  $dirname = dirname($_SERVER['SCRIPT_NAME']);
  //echo "dirname=$dirname<br>\n";
  header("Location: http://$_SERVER[SERVER_NAME]$dirname/index.php?doc=$docid&ypath=$ypath");
}
else {
  echo "$dirpath/$id0.yaml Not a file<br>\n";
}
