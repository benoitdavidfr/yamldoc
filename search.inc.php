<?php
/*PhpDoc:
name: search.inc.php
title: search.inc.php - fonctions pour l'indexation et la recherche plein texte
doc: |
  <a href='/yamldoc/?action=version&name=search.inc.php'>voir le code</a>
*/
{
$phpDocs['search.inc.php'] = <<<'EOT'
name: search.inc.php
title: search.inc.php - fonctions pour l'indexation et la recherche plein texte
doc: |
  La ré-indexation globale efface la base et la reconstruit à partir des fichiers.
  La ré-indexation incrémentale ne ré-indexe que les fichiers plus récents que la version en base.
  Pour la ré-indexation incrémentale je vérifie que tous les docs indexés existent encore.
journal: |
  14/7/2018:
    - redéfinition de la tables SQL fragment pour y dissocier docid et ypath
  1/7/2018:
    - redéfinition des tables SQL pour y intégrer le multi-store
    - possibilité d'effectuer une recherche sur pub si non benoit
    - redéfinition des points d'entrée d'indexation
  20-21/6/2018:
    - finalisation de l'indexation incrémentale
    - restructuration des fonctions en classe statique partageant $mysqli et $currentDocs
  19/6/2018:
    - mise en ooeuvre de l'indexation incrémentale
  18/6/2018:
    - création
EOT;
}
use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Yaml\Exception\ParseException;

class Search {
  static $mysqli=null; // connexion MySQL
  static $currentDocs = []; // en mode incrémental liste des docs avec leur date de mise à jour en base

  // ouvre une connexion avec MySQL, enregistre la variable en variable statique de classe et la renvoie
  // param sous la forme mysql://{user}:{passwd}@{host}/{database}
  static function openMySQL(string $param) {
    if (!preg_match('!^mysql://([^:]+):([^@]+)@([^/]+)/(.*)$!', $param, $matches))
      throw new Exception("param \"".$param."\" incorrect");
    //print_r($matches);
    self::$mysqli = new mysqli($matches[3], $matches[1], $matches[2], $matches[4]);
    if (mysqli_connect_error())
  // La ligne ci-dessous ne s'affiche pas correctement si le serveur est arrêté !!!
  //    throw new Exception("Connexion MySQL impossible pour $server_name : ".mysqli_connect_error());
      throw new Exception("Connexion MySQL impossible sur $param");
    if (!self::$mysqli->set_charset ('utf8'))
      throw new Exception("mysqli->set_charset() impossible : ".self::$mysqli->error);
    return self::$mysqli;
  }
  
  // exécute une requête MySQL, soulève une exception en cas d'erreur, renvoie le résultat
  static function query(string $sql) {
    if (!($result = self::$mysqli->query($sql)))
      throw new Exception("Req. \"$sql\" invalide: ".self::$mysqli->error);
    return $result;
  }
  
  // indexe un fragment élémentaire ($docid, $val)
  static function indexstring(string $docid, string $ypath, string $val) {
    if (strlen($docid) > 200) {
      echo "docid \"$docid\" trop long non indexé<br>\n";
      return;
    }
    if (strlen($ypath) > 200) {
      echo "docid \"$ypath\" trop long non indexé<br>\n";
      return;
    }
    $storeid = Store::id();
    $docid = str_replace(['\\','"'],['\\\\','\"'], $docid);
    $val = str_replace(['\\','"'],['\\\\','\"'], $val);
    self::query("replace into fragment(store,docid,ypath,text) values('$storeid', \"$docid\", \"$ypath\", \"$val\")");
  }

  // indexe un document ou un fragment
  // Utilise le fait que tout document ou élément implémente asArray()
  static function indexdoc(string $docid, string $ypath, $doc) {
    //return;
    //echo "indexdoc($store, $docid)<br>\n";
    //if (true || ($docid=='ZZorganization/misc')) { echo "<pre>doc="; print_r($doc); echo "</pre>\n"; }
    if (is_array($doc))
      $content = $doc;
    elseif (is_object($doc)) {
      if (get_class($doc)<>'DateTime')
        $content = $doc->asArray();
      else
        return;
    }
    else
      die("erreur dans indexdoc() sur $docid");
    if (is_string($content)) {
      self::indexstring($docid, $ypath, $content);
      return;
    }
    //if ($docid=='ZZorganization/misc') { echo "<pre>content="; print_r($content); echo "</pre>\n"; }
    foreach ($content as $key => $val) {
      //echo "key=$key<br>\n";
      if (is_string($val))
        self::indexstring($docid, "$ypath/$key", $val);
      elseif (is_numeric($val) || is_bool($val) || is_null($val)) {}
      elseif (is_array($val) || is_object($val))
        self::indexdoc($docid, "$ypath/$key", $val);
      else {
        $storeid = Store::id();
        echo "nothing done for $storeid $docid $ypath/$key<br>\n";
        echo "<pre>val="; var_dump($val); echo "</pre>\n";
        die("FIN ligne ".__LINE__);
      }
    }
  }

  // indexe un doc principal cad correspondant à un fichier
  static function indexMainDoc(bool $global, string $docid) {
    echo "indexMainDoc($docid)<br>\n";
    $storeid = Store::id();
    self::query("replace into document(store,docid,maj,readers) values('$storeid', \"$docid\", now(), null)");
    if (!$global)
      self::query("delete from fragment where store='$storeid' and docid=\"$docid\"");
    try {
      $doc = new_doc($docid);
      if (!$doc)
        echo "Erreur new_doc($docid)<br>\n";
      self::indexdoc($docid, '', $doc);
    }
    catch (ParseException $exception) {
      printf("<b>Analyse YAML erronée sur document %s/%s: %s</b><br>", $storeid, $docid, $exception->getMessage());
    }
  }

  // teste si le fichier est plus récent que la date de mise à jour de la base
  static function isFileNewer(string $filename, string $docid) {
    if (!isset(self::$currentDocs[$docid])) {
      //echo "$docid absent de la base<br>\n";
      return true;
    }
    //echo "maj($docid)=$maj<br>\n";
    $maj = DateTime::createFromFormat('Y-m-d H:i:s', self::$currentDocs[$docid]);
    //echo "maj($docid)=",$maj->format('Y-m-d H:i:s'),"<br>\n";
    $fmtime = new DateTime;
    $fmtime->setTimestamp(filemtime($filename));
    //echo "fmtime=",$fmtime->format('Y-m-d H:i:s'),"<br>\n";
    $interval = $fmtime->diff($maj);
    //echo "interval=",$interval->format("%R %a jours %h heures %i minutes %s secondes<br>\n");
    if ($interval->format('%R')=='-')
      echo "document $docid mis à jour<br>\n";
    unset(self::$currentDocs[$docid]);
    return ($interval->format('%R')=='-');
  }

  // scanne récursiveme,nt le répertoire et appelle indexMainDoc() pour chaque fichier concerné
  // $global vaut true si 'global' ou false si 'incremental'
  // $$store est le nom du store
  // $ssdir est le chemin relatif d'un répertoire
  // $fileNamePattern est un éventuel motif de nom de fichier
  static function scanfiles(bool $global, string $ssdir, string $fileNamePattern) {
    echo "scanfiles(global=",$global?'true':'false',", ssdir=$ssdir, pattern=$fileNamePattern)<br>\n";
    $storepath = Store::storepath();
    $dirpath = __DIR__.'/'.$storepath.($ssdir ? '/'.$ssdir : ''); // chemin du répertoire
    if (($wd = opendir($dirpath))===FALSE) {
      echo "Erreur ouverture de $dirpath<br>\n";
      return;
    }
    while (false !== ($entry = readdir($wd))) {
      //echo "$entry a traiter<br>\n";
      if (in_array($entry, ['.','..','.git','.gitignore','.htaccess','.DS_Store']))
        continue;
      elseif (is_dir("$storepath/$entry"))
        self::scanfiles($global, $ssdir ? "$ssdir/$entry" : $entry, $fileNamePattern);
      elseif (preg_match('!^(.*)\.(php|pser)$!', $entry))
        continue;
      elseif ($fileNamePattern && !preg_match("!$fileNamePattern!", $entry))
        continue;
      elseif (preg_match('!^(.*)\.yaml$!', $entry, $matches)) {
        $docid = ($ssdir ? $ssdir.'/' : '').$matches[1];
        if ($global || self::isFileNewer("$dirpath/$entry", $docid))
          self::indexMainDoc($global, $docid);
      }
      else
        echo "$entry non traite<br>\n";
    }
    closedir($wd);
  }

  // indexe de manière incrémentale un store
  static function incrIndex(string $ssdir='', string $fileNamePattern='') {
    self::openMySQL(mysqlParams());
    self::$currentDocs = [];
    $storeid = Store::id();
    $result = self::query("select docid, maj from document where store='$storeid'");
    while ($tuple = $result->fetch_array(MYSQLI_ASSOC)) {
      //print_r($tuple); echo "<br>\n";
      self::$currentDocs[$tuple['docid']] = $tuple['maj'];
    }
    self::scanfiles(false, $ssdir, $fileNamePattern);
    foreach (self::$currentDocs as $docid => $maj) {
      self::query("delete from document where store='$storeid' and docid=\"$docid\"");
      self::query("delete from fragment where store='$storeid' and docid=\"$docid\"");
      echo "Suppression de $docid $maj de l'index plein texte<br>\n";
    }
  }

  // indexe tous les documents globalement une liste de store
  static function globalIndex(array $storeids, string $ssdir='', string $fileNamePattern='') {
    self::openMySQL(mysqlParams());
    foreach (
      [
        "drop table if exists document",
        "create table document (
          store varchar(20) not null comment 'store',
          docid varchar(200) not null comment 'id du doc',
          maj datetime comment 'date et heure de maj du doc',
          readers varchar(200) comment 'liste des lecteurs autorisés, null ssi tous',
          primary key storedocid (store,docid)
        ) COMMENT = 'document'
        DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci",

        "drop table if exists fragment",
        "create table fragment (
          store varchar(20) not null comment 'store',
          docid varchar(200) not null comment 'id du doc',
          ypath varchar(200) not null comment 'ypath',
          text longtext comment 'texte associé',
          primary key storefragid (store,docid,ypath)
        ) COMMENT = 'fragment'
        DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci",
      ] as $sql)
        self::query($sql);
    foreach ($storeids as $storeid) {
      Store::setStoreid($storeid);
      self::scanfiles(true, $ssdir, $fileNamePattern);
    }
    self::query("create fulltext index fragment_fulltext on fragment(text)");
  }
}
