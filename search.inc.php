<?php
/*PhpDoc:
name: search.inc.php
title: search.inc.php - fonctions pour l'indexation et la recherche plein texte
doc: |
  <a href='/yamldoc/?action=version&name=search.inc.php'>voir le code</a>
includes: [ ../phplib/mysql.inc.php, mysqlparams.inc.php ]
*/
{ // doc
$phpDocs['search.inc.php']['file'] = <<<'EOT'
name: search.inc.php
title: search.inc.php - fonctions pour l'indexation et la recherche plein texte
journal:
  26/8/2018:
    - correction d'un bug dans scanfiles()
    - ajout de la méthode search()
    - renommage de la classe de Search en FullTextSearch
  3/8/2018:
    - reorganisation de l'interface MySql
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
require_once __DIR__.'/../phplib/mysql.inc.php';

use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Yaml\Exception\ParseException;

{ // doc
$phpDocs['search.inc.php']['classes']['FullTextSearch'] = <<<'EOT'
title: fonctions pour l'indexation et la recherche plein texte
doc: |
  La ré-indexation globale efface la base et la reconstruit à partir des fichiers.
  La ré-indexation incrémentale ne ré-indexe que les fichiers plus récents que la version en base.
  Pour la ré-indexation incrémentale je vérifie que tous les docs indexés existent encore.
EOT;
}
class FullTextSearch {
  static $currentDocs = []; // en mode incrémental liste des docs avec leur date de mise à jour en base
  
  // fonction de recherche sur un pattern de docid, un pattern de ypath et un critère de recherche de plein texte 
  static function search(string $docid, string $ypath, string $value, $options=[]) {
    $where = [];
    // solution simplifiée: si je suis benoit alors je cherche dans les différents stores,
    // sinon je ne cherche que dans pub
    if (!isset($_SESSION['homeCatalog']) || ($_SESSION['homeCatalog']<>'benoit'))
      $where[] = "store='pub'";
    if ($docid)
      $where[] = "docid like \"$docid%\"";
    if ($ypath)
      $where[] = "ypath like \"$ypath%\"";
    if ($value)
      $where[] = "match (text) against (\"$value\" in boolean mode)";
    if ($value)
      $sql = "select store, match (text) against (\"$value\" in boolean mode) relevance, docid, ypath, text from fragment\n"
        ."where ".implode(' and ', $where);
    else
      $sql = "select store, fragid, text from fragment\n"
        ."where ".implode(' and ', $where);
  
    if (isset($options['verbose']) && $options['verbose'])
      echo "<pre>sql=$sql</pre>\n";
    $results = [];
    MySql::open(require(__DIR__.'/mysqlparams.inc.php'));
    foreach (MySql::query($sql) as $tuple) {
      $results[] = [
        'relevance'=> $tuple['relevance'],
        'viewerUrl'=> Store::viewerUrl($tuple['store']),
        'docid'=> $tuple['docid'],
        'ypath'=> $tuple['ypath'],
        'text'=> $tuple['text'],
      ];
    }
    return $results;
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
    MySql::query("replace into fragment(store,docid,ypath,text) values('$storeid', \"$docid\", \"$ypath\", \"$val\")");
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
    MySql::query("replace into document(store,docid,maj,readers) values('$storeid', \"$docid\", now(), null)");
    if (!$global)
      MySql::query("delete from fragment where store='$storeid' and docid=\"$docid\"");
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

  // scanne récursivement le répertoire et appelle indexMainDoc() pour chaque fichier concerné
  // $global vaut true si 'global' ou false si 'incremental'
  // $ssdir est le chemin relatif d'un répertoire
  // $fileNamePattern est un éventuel motif de nom de fichier
  static function scanfiles(bool $global, string $ssdir, string $fileNamePattern) {
    echo "scanfiles(global=",$global?'true':'false',", ssdir=$ssdir, pattern=$fileNamePattern)<br>\n";
    $storepath = Store::storepath();
    $dirpath = __DIR__.'/'.$storepath.($ssdir ? '/'.$ssdir : ''); // chemin du répertoire
    if (($wd = opendir($dirpath)) === FALSE) {
      echo "Erreur ouverture de $dirpath<br>\n";
      return;
    }
    while (false !== ($entry = readdir($wd))) {
      //echo "$entry a traiter<br>\n";
      if (in_array($entry, ['.','..','.git','.gitignore','.htaccess','.DS_Store']))
        continue;
      elseif (is_dir("$storepath/".($ssdir ? "$ssdir/$entry" : $entry)))
        self::scanfiles($global, $ssdir ? "$ssdir/$entry" : $entry, $fileNamePattern);
      elseif (preg_match('!^(.*)\.(php|xml|png|gif|sql|tsv)$!', $entry))
        continue;
      elseif ($fileNamePattern && !preg_match("!$fileNamePattern!", $entry))
        continue;
      elseif (preg_match('!^(.*)\.yaml$!', $entry, $matches)) {
        $docid = ($ssdir ? $ssdir.'/' : '').$matches[1];
        if ($global || self::isFileNewer("$dirpath/$entry", $docid))
          self::indexMainDoc($global, $docid);
      }
      elseif (preg_match('!^(.*)\.pser$!', $entry, $matches)) {
        // je traite uniquement les pser qui n'existe pas en yaml
        if (is_file("$dirpath/$matches[1].yaml"))
          continue;
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
    MySql::open(require(__DIR__.'/mysqlparams.inc.php'));
    self::$currentDocs = [];
    $storeid = Store::id();
    $result = MySql::query("select docid, maj from document where store='$storeid'");
    foreach($result as $tuple) {
      //print_r($tuple); echo "<br>\n";
      self::$currentDocs[$tuple['docid']] = $tuple['maj'];
    }
    self::scanfiles(false, $ssdir, $fileNamePattern);
    foreach (self::$currentDocs as $docid => $maj) {
      MySql::query("delete from document where store='$storeid' and docid=\"$docid\"");
      MySql::query("delete from fragment where store='$storeid' and docid=\"$docid\"");
      echo "Suppression de $docid $maj de l'index plein texte<br>\n";
    }
  }

  // indexe tous les documents globalement une liste de store
  static function globalIndex(array $storeids, string $ssdir='', string $fileNamePattern='') {
    MySql::open(require(__DIR__.'/mysqlparams.inc.php'));
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
        MySql::query($sql);
    foreach ($storeids as $storeid) {
      Store::setStoreid($storeid);
      self::scanfiles(true, $ssdir, $fileNamePattern);
    }
    MySql::query("create fulltext index fragment_fulltext on fragment(text)");
  }
}
