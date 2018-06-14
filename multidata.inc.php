<?php
/*PhpDoc:
name: multidata.inc.php
title: multidata.inc.php - multi-data
functions:
doc: doc intégrée en Php
*/
{
$phpDocs['multidata.inc.php'] = <<<'EOT'
name: multidata.inc.php
title: multidata.inc.php - multi-data
doc: |
  Un document MultiData contient dans le champ multi une liste de docid correspondant chacun à un sous-document
  situé dans le répertoire ayant pour nom l'id du document multi.
  Chaque sous-document est un YamlData composé d'une seule table.
  Le document MultiData est composé d'une table qui est la concaténation des tables des sous-documents.
journal: |
  14/6/2018:
  - première version simple
EOT;
}

class MultiData extends YamlDoc {
  // affiche le doc ou le fragment si ypath est non vide
  function show(string $ypath): void {
    //echo "MultiDoc::show()<br>\n";
    //parent::show($ypath);
    //echo "<pre>"; print_r($this->data); echo "</pre>\n";
    //echo "doc=$_GET[doc]<br>\n";
    $parent_doc = $_GET['doc'];
    echo "<ul>\n";
    $table = new YamlDataTable([]);
    foreach(array_keys($this->data['multi']) as $docid) {
      echo "<li>$parent_doc/$docid\n";
      $_GET['doc'] = "$parent_doc/$docid";
      $doc = new_yamldoc("$parent_doc/$docid");
      //$doc->show($ypath);
      $table = $doc->merge($table);
    }
    echo "</ul>\n";
    //echo "<pre>"; print_r($table); echo "</pre>\n";
    //showDoc($table);
    $this->data['data'] = $table;
    parent::show($ypath);
  }
}