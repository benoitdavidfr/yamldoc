<?php
/*PhpDoc:
name: onetable.inc.php
title: onetable.inc.php - gestionnaire de documents Yaml vus comme une grande table Html
doc: |
  Défini la fonction showDocAsTable() qui affiche un array comme table HTML
journal: |
  22/4/2018:
    création
*/

function showDocAsTable(array $data, string $prefix='') {
  echo "<table border=1>\n";
  foreach ($data as $key => $value) {
    echo "<tr><td>$key\n";
    
    if (isset($_GET['action']) and ($_GET['action']=='select') and ($_GET['tab']=="$prefix/$key"))
      echo " / $_GET[key]=$_GET[val]";
    echo "</td><td>\n";
    
    if (is_listOfTuples($value))
      showOneListOfTuples("$prefix/$key", $value);
    elseif (is_array($value))
      showDocAsTable($value, "$prefix/$key");
    else
      echo $value;
    echo "</td>\n";
  }
  echo "</table>\n";
}
