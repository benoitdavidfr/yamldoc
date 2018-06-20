<?php
/*PhpDoc:
name: indexdoc.php
title: indexdoc.php - ré-indexation des documents dans MySQL soit globalement soit incrémentale
doc: |
  La ré-indexation globale efface la base et la reconstruit à partir des fichiers.
  La ré-indexation incrémentale ne ré-indexe que les fichiers plus récents que la version en base.
  Pour la ré-indexation incrémentale il faut aussi vérifier que tous les docs indexés existent encore.
journal: |
  19/6/2018:
    - ajout indexation incrémentale
  17-18/6/2018:
    - création
*/
require_once __DIR__.'/yd.inc.php';
require_once __DIR__.'/catalog.inc.php';
require_once __DIR__.'/servreg.inc.php';
require_once __DIR__.'/tree.inc.php';
require_once __DIR__.'/yamldata.inc.php';
require_once __DIR__.'/multidata.inc.php';
require_once __DIR__.'/search.inc.php';
require_once __DIR__.'/mysqlparams.inc.php';
use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Yaml\Exception\ParseException;

ini_set('memory_limit', '1024M');
ini_set('max_execution_time', 600);

echo "<!DOCTYPE HTML><html><head><meta charset='UTF-8'><title>indexdoc</title></head><body>\n";

if (!isset($_GET['action'])) {
  echo "<ul>",
       "<li><a href='?action=global'>réindexation globale\n",
       "<li><a href='?action=incremental'>réindexation incrémentale\n",
       "</ul>\n";
  die();
}

Search::indexAllDocs($_GET['action']=='global', 'docs');
//indexalldocs($_GET['action']=='global', 'docs', 'organization');
//indexalldocs($_GET['action']=='global', 'docs', '', '^dublincore');
die("FIN OK<br>\n");