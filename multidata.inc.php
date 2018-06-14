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
  Le concept MultiData permet de décomposer une grande table en sous-tables stockées chacune dans un sous-document.
  Tous les sous-documents sont stockés dans un répertoire portant comme nom celui du MultiData.
  Ainsi, un document MultiData contient dans le champ multi la liste de docid correspondant chacun à un sous-document
  situé dans le répertoire ayant pour nom l'id du document multi.
  Chaque sous-document est un YamlData composé d'une seule table.
  Le document MultiData créé est composé d'une table qui est la concaténation des tables des sous-documents.
  
  A FAIRE:
  - il serait utile de vérifier la compatibilité des différents schémas déclarés et/ou effectifs.
journal: |
  14/6/2018:
  - première version simple
EOT;
}

class MultiData extends YamlDoc {
  // fabrique à la volée la table par concaténation des tables des sous-documents
  function buildTable() {
    //echo "MultiData::show()<br>\n";
    //parent::show($ypath);
    //echo "<pre>"; print_r($this->data); echo "</pre>\n";
    //echo "doc=$_GET[doc]<br>\n";
    $parent_doc = $_GET['doc'];
    //echo "<ul>\n";
    $table = new YamlDataTable([]);
    foreach(array_keys($this->data['multi']) as $docid) {
      //echo "<li>$parent_doc/$docid\n";
      $_GET['doc'] = "$parent_doc/$docid";
      $doc = new_yamldoc("$parent_doc/$docid");
      //$doc->show($ypath);
      $table = $doc->appendTable($table);
    }
    //echo "</ul>\n";
    //echo "<pre>"; print_r($table); echo "</pre>\n";
    //showDoc($table);
    $this->data['data'] = $table;
  }
  
  // affiche le doc ou le fragment si ypath est non vide
  // Pour cela fabrique à la volée la table par concaténation des tables des sous-documents
  function show(string $ypath): void {
    //echo "MultiData::show()<br>\n";
    $this->buildTable();
    parent::show($ypath);
  }
}