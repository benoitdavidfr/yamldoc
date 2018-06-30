<?php
/*PhpDoc:
name: frag.php
title: frag.php - transforme un appel avec un fragid en un appel d'index.php avec doc et ypath
doc: |
  utilise header('Location: url') pour effectuer la transformation
*/

$store = $_SESSION['store'];
$fragid = explode('/', $_GET['fragid']);
$dirpath = ''; // vide ou se termine par /
$id0 = array_shift($fragid);
//echo "id0=$id0<br>\n";
while (is_dir(__DIR__."/$store/$dirpath$id0")) {
  $dirpath = "$dirpath$id0/";
  $id0 = array_shift($fragid);
}
//echo "dirpath=$dirpath<br>\n";
if (is_file(__DIR__."/$store/$dirpath$id0.yaml")) {
  $ypath = '/'.implode('/', $fragid);
  echo "<a href='index.php?doc=$dirpath$id0&amp;ypath=$ypath'>$_GET[fragid]</a><br>";
  $dirname = dirname($_SERVER['SCRIPT_NAME']);
  //echo "dirname=$dirname<br>\n";
  header("Location: http://$_SERVER[SERVER_NAME]$dirname/index.php?doc=$dirpath$id0&ypath=$ypath");
}
else {
  echo "Erreur dans frag.php: $dirpath$id0.yaml Not a file<br>\n";
}
