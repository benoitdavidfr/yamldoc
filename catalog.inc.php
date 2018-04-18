<?php
/*PhpDoc:
name: catalog.inc.php
title: catalog.inc.php - gestion d'un catalogue de documents structurés
doc: |
  shema:
    title: titre du catalogue
    doc: documentation du catalogue
    type: http://yaml.gexplor.fr/type/catalog
    contents:
      {name}:
        title: titre du document
journal: |
  18/4/2018:
    utilisation de ydread() et ydwrite()
  14/4/2018:
    création
*/

function create_catalog() {
  $default_catalog = [
    'type'=> 'catalog',
    'contents'=> [],
  ];
  $uid = uniqid();
  ydwrite($uid, spycDump($default_catalog));
  echo "Création du catalogue $uid<br>\n";
  return $uid;
}

function store_in_catalog(string $uid, string $catalog) {
  $contents = spycLoadString(ydread($catalog));
  //print_r($contents);
  $contents['contents'][$uid] = ['title'=> "document $uid" ];
  ydwrite($catalog, spycDump($contents));
}

function show_catalog(array $contents) {
  if (isset($contents['title']))
    echo "<h2>$contents[title]</h2>\n";
  echo "<ul>\n";
  foreach ($contents['contents'] as $uid => $content) {
    $title = isset($content['title']) ? $content['title'] : $uid;
    echo "<li><a href='?action=read&amp;name=$uid'>$title</a>\n";
  }
  echo "</ul>\n";
}