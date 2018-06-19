<?php
{
$phpDocs['search.inc.php'] = <<<EOT
name: search.inc.php
title: search.inc.php - fonctions pour l'indexation et la recherche plein texte
doc: |
  La ré-indexation globale efface la base et la reconstruit à partir des fichiers.
  La ré-indexation incrémentale ne ré-indexe que les fichiers plus récents que la version en base.
  Pour la ré-indexation incrémentale il faut aussi vérifier que tous les docs indexés existent encore.
journal: |
  19/6/2018:
  - mise en ooeuvre de l'indexation incrémentale
  18/6/2018:
  - création
EOT;
}

// param sous la forme mysql://{user}:{passwd}@{host}/{database}
function openMySQL(string $param) {
  if (!preg_match('!^mysql://([^:]+):([^@]+)@([^/]+)/(.*)$!', $param, $matches))
    throw new Exception("param \"".$param."\" incorrect");
  //print_r($matches);
  $mysqli = new mysqli($matches[3], $matches[1], $matches[2], $matches[4]);
  if (mysqli_connect_error())
// La ligne ci-dessous ne s'affiche pas correctement si le serveur est arrêté !!!
//    throw new Exception("Connexion MySQL impossible pour $server_name : ".mysqli_connect_error());
    throw new Exception("Connexion MySQL impossible sur $param");
  if (!$mysqli->set_charset ('utf8'))
    throw new Exception("mysqli->set_charset() impossible : ".$mysqli->error);
  return $mysqli;
}

function indexstring($mysqli, string $docid, string $val) {
  if (strlen($docid) > 250) {
    echo "docid \"$docid\" trop long non indexé<br>\n";
    return;
  }
  $docid = str_replace(['\\','"'],['\\\\','\"'], $docid);
  $val = str_replace(['\\','"'],['\\\\','\"'], $val);
  $sql = "replace into fragment values(\"".$docid."\", \"".$val."\")";
  if (!($result = $mysqli->query($sql)))
    throw new Exception("Ligne ".__LINE__.", Req. \"$sql\" invalide: ".$mysqli->error);
}

function indexdoc($mysqli, string $docid, $doc) {
  //return;
  //echo "indexdoc($docid)<br>\n";
  //if (true || ($docid=='ZZorganization/misc')) { echo "<pre>doc="; print_r($doc); echo "</pre>\n"; }
  if (is_array($doc))
    $content = $doc;
  elseif (is_object($doc)) {
    if (get_class($doc)<>'DateTime')
      $content = $doc->extract('');
    else
      return;
  }
  else
    die("erreur dans indexdoc() sur $docid");
  if (is_string($content)) {
    indexstring($mysqli, $docid, $content);
    return;
  }
  //if ($docid=='ZZorganization/misc') { echo "<pre>content="; print_r($content); echo "</pre>\n"; }
  foreach ($content as $key => $val) {
    //echo "key=$key<br>\n";
    if (is_string($val))
      indexstring($mysqli, "$docid/$key", $val);
    elseif (is_numeric($val) || is_bool($val) || is_null($val)) {}
    elseif (is_array($val) || is_object($val))
      indexdoc($mysqli, "$docid/$key", $val);
    else {
      echo "nothing done for $docid/$key<br>\n";
      echo "<pre>val="; var_dump($val); echo "</pre>\n";
      die("FIN ligne ".__LINE__);
    }
  }
}


// indexe un doc principal cad correspondant à un fichier
function indexMainDoc($mysqli, $docid) {
  echo "indexMainDoc($docid)<br>\n";
  try {
    $sql = "replace into document values(\"".$docid."\", now())";
    if (!($result = $mysqli->query($sql)))
      throw new Exception("Ligne ".__LINE__.", Req. \"$sql\" invalide: ".$mysqli->error);
    $doc = new_yamlDoc($docid);
    if (!$doc)
      echo "Erreur new_yamlDoc($docid)<br>\n";
    indexdoc($mysqli, $docid, $doc);
    $sql = "replace into document values(\"".$docid."\", now())";
    if (!($result = $mysqli->query($sql)))
      throw new Exception("Ligne ".__LINE__.", Req. \"$sql\" invalide: ".$mysqli->error);
  }
  catch (ParseException $exception) {
    printf("<b>Analyse YAML erronée sur document %s: %s</b><br>", $docid, $exception->getMessage());
  }
}

// teste si le fichier est plus récent que la date de mise à jour de la base
function isFileNewer($mysqli, string $filename, string $docid) {
  $sql = "select maj from document where docid=\"$docid\"";
  if (!($result = $mysqli->query($sql)))
    throw new Exception("Ligne ".__LINE__.", Req. \"$sql\" invalide: ".$mysqli->error);
  $maj = null;
  while ($tuple = $result->fetch_array(MYSQLI_ASSOC)) {
    //print_r($tuple); echo "<br>\n";
    $maj = $tuple['maj'];
  }
  if (!$maj) {
    //echo "$docid absent de la base<br>\n";
    return true;
  }
  //echo "maj($docid)=$maj<br>\n";
  $maj = DateTime::createFromFormat('Y-m-d H:i:s', $maj);
  //echo "maj($docid)=",$maj->format('Y-m-d H:i:s'),"<br>\n";
  $fmtime = new DateTime;
  $fmtime->setTimestamp(filemtime($filename));
  //echo "fmtime=",$fmtime->format('Y-m-d H:i:s'),"<br>\n";
  $interval = $fmtime->diff($maj);
  //echo "interval=",$interval->format("%R %a jours %h heures %i minutes %s secondes<br>\n");
  if ($interval->format('%R')=='-')
    echo "document $docid mis à jour<br>\n";
  return ($interval->format('%R')=='-');
}

// $docpath est le chemin Unix relatif de la racine des documents
// $ssdir est le chemin relatif d'un répertoire
// $fileNamePattern est un éventuel motif de nom de fichier
// $global vaut true si 'global' ou false si 'incremental'
function scanfiles(bool $global, $mysqli, string $docpath, string $ssdir, string $fileNamePattern) {
  $dirpath = __DIR__.'/'.$docpath.($ssdir ? '/'.$ssdir : ''); // chemin du répertoire
  if (($wd = opendir($dirpath))===FALSE) {
    throw new Exception("Erreur ouverture de $dirpath");
  }
  while (false !== ($entry = readdir($wd))) {
    //echo "$entry a traiter<br>\n";
    if (in_array($entry, ['.','..','.git','.htaccess']))
      continue;
    elseif (is_dir("$dirpath/$entry"))
      scanfiles($global, $mysqli, $docpath, $ssdir ? "$ssdir/$entry" : $entry, $fileNamePattern);
    elseif (preg_match('!^(.*)\.(php|pser)$!', $entry))
      continue;
    elseif ($fileNamePattern && !preg_match("!$fileNamePattern!", $entry))
      continue;
    elseif (preg_match('!^(.*)\.yaml$!', $entry, $matches)) {
      $docid = ($ssdir ? $ssdir.'/' : '').$matches[1];
      if ($global || isFileNewer($mysqli, "$dirpath/$entry", $docid))
        indexMainDoc($mysqli, $docid);
    }
    else
      echo "$entry non traite<br>\n";
  }
  closedir($wd);
}

// indexe tous les documents globalement ou de manière incrémentale
function indexAllDocs(bool $global, string $docpath, string $ssdir='', string $fileNamePattern='') {
  $mysqli = openMySQL(mysqlParams());
  if ($global) { // SQL truncate doc & fragment
    if (!($result = $mysqli->query($sql = "truncate document")))
      throw new Exception("Ligne ".__LINE__.", Req. \"$sql\" invalide: ".$mysqli->error);
    if (!($result = $mysqli->query($sql = "truncate fragment")))
      throw new Exception("Ligne ".__LINE__.", Req. \"$sql\" invalide: ".$mysqli->error);
  }
  else
    echo "Attention il faut aussi vérifier que tous les docs indexés existent encore<br>\n";
  scanfiles($global, $mysqli, $docpath, $ssdir, $fileNamePattern);
}

