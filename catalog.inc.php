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
  14/4/2018:
    création
*/

function create_catalog() {
  $default_catalog = [
    'type'=> 'catalog',
    'contents'=> [],
  ];
  $catalog = uniqid();
  file_put_contents("$catalog.yaml", spycDump($default_catalog));
  echo "Création du catalogue $catalog<br>\n";
  return $catalog;
}

function store_in_catalog(string $name, string $catalog) {
  $contents = file_get_contents("$catalog.yaml");
  $contents = spycLoadString($contents);
  //print_r($contents);
  $contents['contents'][$name] = ['title'=> "document $name" ];
  file_put_contents("$catalog.yaml", spycDump($contents));
}

function show_catalog(array $data) {
  if (isset($data['title']))
    echo "<h2>$data[title]</h2>\n";
  echo "<ul>\n";
  foreach ($data['contents'] as $name => $content) {
    $title = isset($content['title']) ? $content['title'] : $name;
    echo "<li><a href='?action=read&amp;name=$name'>$title</a>\n";
  }
  echo "</ul>\n";
}