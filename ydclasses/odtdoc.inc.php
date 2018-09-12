<?php
/*PhpDoc:
name: odtdoc.inc.php
title: gestion des fichiers ODT
doc: |
  voir le code
*/
{
$phpDocs['odtdoc.inc.php'] = <<<EOT
name: odtdoc.inc.php
title: odtdoc.inc.php - affichage des fichiers ODT
doc: |
journal:
  22/8/2018:
  - modif OdtDoc::checkReadAccess()
  28/7/2018:
  - création
EOT;
}
require_once __DIR__.'/../../phplib/ophir.inc.php';

class OdtDoc extends Doc  {
  public $authorizedReaders, $authRd, $authorizedWriters, $authWr, $yamlPassword , $language;
  private $path = '';
  
  function __construct(&$path) { $this->path = $path; }
  
  function show(string $docid, string $ypath): void {
    //echo "OdtDoc::show($docid, $ypath)<br>\n";
    //echo "path=$this->path<br>\n";
    $OPHIR_CONF["footnote"] = 0; //Do not import footnotes
    $OPHIR_CONF["annotation"] = 0; //Do not import annotations
    $OPHIR_CONF["list"] = 1; //Import lists, but prints them as simple text (no ul or li tags will be generated)
    $OPHIR_CONF["link"] = 1; //Import links, but prints them as simple text (only extract text from the links)
    /*Available parameters are:
    "header", "quote", "list", "table", "footnote", "link", "image", "note", and "annotation"
    */
    echo odt2html($this->path);
  } 
  
  function checkReadAccess(string $docuid): bool { return true; }
};