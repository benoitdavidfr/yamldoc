<?php
// docs triés par date de mise à jour
foreach (['docs','pub'] as $store) {
  echo "<h2>$store</h2>\n";
  $docs = glob("$store/*.yaml");
  usort($docs, function($a, $b) {
      return filemtime($a) < filemtime($b);
  });
  echo "<ul>\n";
  foreach($docs as $doc) {
    $doc = str_replace("$store/", '', $doc);
    $doc = str_replace('.yaml', '', $doc);
    echo "<li><a href='index.php?doc=$doc'>$doc</a></li>\n";
  }
  echo "</ul>\n";
}
