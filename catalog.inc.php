<?php
/*PhpDoc:
name: catalog.inc.php
title: catalog.inc.php - classes des catalogues
doc: |
journal: |
  12/5/2018:
  - scission de yd.inc.php
*/
use Symfony\Component\Yaml\Yaml;

// class des catalogues
class YamlCatalog extends YamlDoc {
  function show(string $ypath) {
    //echo "<pre>"; print_r($this->data); echo "</pre>\n";
    $dirname = dirname($_GET['doc']);
    if ($dirname=='.')
      $dirname = '';
    else
      $dirname .= '/';
    //echo "dirname=$dirname<br>\n";
    echo "<h1>",$this->data['title'],"</h1><ul>\n";
    foreach($this->contents as $duid => $item) {
      $title = isset($item['title']) ? $item['title'] : $duid;
      echo "<li><a href='?doc=$dirname$duid'>$title</a>\n";
    }
    echo "</ul>\n";
  }
  
  // clone un doc dans un catalogue
  static function clone_in_catalog(string $newdocuid, string $olddocuid, string $catuid) {
    $contents = Yaml::parse(ydread($catuid));
    //print_r($contents);
    $title = $contents['contents'][$olddocuid]['title'];
    $contents['contents'][$newdocuid] = ['title'=> "$title cloné $newdocuid" ];
    ydwrite($catuid, Yaml::dump($contents, 999));
  }
  
  static function delete_from_catalog(string $docuid, string $catuid) {
    $contents = Yaml::parse(ydread($catuid));
    unset($contents['contents'][$docuid]);
    ydwrite($catuid, Yaml::dump($contents, 999));
  }
};

// classe des catalogues d'accueil
class YamlHomeCatalog extends YamlCatalog {
  function isHomeCatalog() { return true; }
};
