<?php
/*PhpDoc:
name: indexdoc.php
title: indexdoc.php - ré-indexation des documents dans MySQL soit globalement soit incrémentale
doc: |
  La ré-indexation globale efface la base et la reconstruit à partir des fichiers.
  La ré-indexation incrémentale ne ré-indexe que les fichiers plus récents que la version en base.
  Pour la ré-indexation incrémentale il faut aussi vérifier que tous les docs indexés existent encore.
journal: |
  1-2/7/2018:
    - adaptation au multi-store
    - lecture de la liste des stores dans le fichier de configuration
  19-21/6/2018:
    - ajout indexation incrémentale
  17-18/6/2018:
    - création
*/
require_once __DIR__.'/yd.inc.php';
require_once __DIR__.'/ydclasses.inc.php';
require_once __DIR__.'/search.inc.php';
if (file_exists(__DIR__.'/mysqlparams.inc.php'))
  require_once __DIR__.'/mysqlparams.inc.php';

ini_set('memory_limit', '1024M');
ini_set('max_execution_time', 600);

// lecture de la liste des stores dans le fichier de configuration
$stores = array_keys(config()['stores']);

echo "<!DOCTYPE HTML><html><head><meta charset='UTF-8'><title>indexdoc</title></head><body>\n";

if (!function_exists('mysqlParams')) {
  die("L'indexation n'est pas disponible car l'utilisation de MySQL n'a pas été paramétrée.<br>\n"
    ."Pour le paramétrer voir le fichier <b>mysqlparams.inc.php.model</b><br>\n");
}

if (!isset($_GET['action'])) {
  echo "<ul>",
       "<li><a href='?action=global'>réindexation globale\n",
       "<li><a href='?action=incremental'>réindexation incrémentale\n",
       "</ul>\n";
  die();
}

if ($_GET['action']=='global')
  Search::globalIndex($stores);
else
  foreach ($stores as $store)
    Search::incrIndex($store);
die("FIN OK<br>\n");