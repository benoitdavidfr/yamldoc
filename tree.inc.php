<?php
/*PhpDoc:
name: tree.inc.php
title: tree.inc.php - classe Tree
doc: |
journal: |
  13/5/2018:
  - création
*/

// classe Tree
class Tree extends YamlDoc {
  
  static function showTree(array $children): void {
    echo "<ul>\n";
    foreach ($children as $child) {
      echo "<li>$child[t]\n";
      if (isset($child['c']))
        self::showTree($child['c']);
    }
    echo "</ul>\n";
  }
  
  function show(string $docid, string $ypath): void {
    echo "<h1>",$this->data['title'],"</h1>\n";
    self::showTree($this->data['children']);
  }
};