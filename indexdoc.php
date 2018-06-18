<?php
/*
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

$mysqli = openMySQL(mysqlParams());
{ // SQL truncate fragment
  $sql = "truncate fragment";
  if (!($result = $mysqli->query($sql)))
    throw new Exception("Ligne ".__LINE__.", Req. \"$sql\" invalide: ".$mysqli->error);
}

// $docpath est le chemin Unix de la racine des documents
// $ssdir est le chemin relatif d'un répertoire
function scan($mysqli, string $docpath, string $ssdir='', string $fileNamePattern='') {
  $dirpath = $docpath.($ssdir ? '/'.$ssdir : '');
  if (($wd = opendir($dirpath))===FALSE) {
    throw new Exception("Erreur ouverture de $dirpath");
  }
  while (false !== ($entry = readdir($wd))) {
    //echo "$entry a traiter<br>\n";
    if (in_array($entry, ['.','..','.git','.htaccess']))
      continue;
    elseif ($fileNamePattern && !preg_match("!$fileNamePattern!", $entry))
      continue;
    elseif (is_dir($docpath.'/'.($ssdir ? "$ssdir/$entry" : $entry)))
      scan($mysqli, $docpath, $ssdir ? "$ssdir/$entry" : $entry);
    elseif (preg_match('!^(.*)\.(php|pser)$!', $entry))
      continue;
    elseif (preg_match('!^(.*)\.yaml$!', $entry, $matches)) {
      $docid = ($ssdir ? $ssdir.'/' : '').$matches[1];
      try {
        $doc = new_yamlDoc($docid);
        if (!$doc)
          echo "Erreur new_yamlDoc($docid)<br>\n";
        indexdoc($mysqli, $docid, $doc);
      }
      catch (ParseException $exception) {
        printf("<b>Analyse YAML erronée sur document %s: %s</b><br>", $docid, $exception->getMessage());
      }
    }
    else
      echo "$entry non traite<br>\n";
  }
  closedir($wd);
}
scan($mysqli, 'docs');
//scan($mysqli, 'docs', 'organization');
//scan($mysqli, __DIR__.'/docs', '', '^dublincore');
die("FIN OK<br>\n");