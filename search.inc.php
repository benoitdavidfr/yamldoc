<?php

// param sous la forme mysql://{user}:{passwd}@{host}/{database}
function openMySQL(string $param) {
  if (!preg_match('!^mysql://([^:]+):([^@]+)@([^/]+)/(.*)$!', $param, $matches))
    throw new Exception("param \"".$param."\" incorrect");
  print_r($matches);
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
  $sql = "insert into fragment values(\"".$docid."\", \"".$val."\")";
  if (!($result = $mysqli->query($sql))) {
    if (preg_match('!^Duplicate entry!', $mysqli->error))
      echo "<b>Warning: ", $mysqli->error,"</b><br>\n";
    else
      throw new Exception("Ligne ".__LINE__.", Req. \"$sql\" invalide: ".$mysqli->error);
  }
}

function indexdoc($mysqli, string $docid, $doc) {
  //return;
  //echo "indexdoc($docid)<br>\n";
  //if ($docid=='ZZorganization/misc') { echo "<pre>doc="; print_r($doc); echo "</pre>\n"; }
  static $nbremax = 100;
  if (is_array($doc))
    $content = $doc;
  elseif (is_object($doc)) {
    if (get_class($doc)<>'DateTime')
      $content = $doc->extract('');
    else
      return;
  }
  else
    die("erreur sur $docid");
  if (is_string($content)) {
    indexstring($mysqli, $docid, $content);
    return;
  }
  //if ($docid=='ZZorganization/misc') { echo "<pre>content="; print_r($content); echo "</pre>\n"; }
  foreach ($content as $key => $val) {
    //echo "key=$key<br>\n";
    if (is_string($val) || is_numeric($val))
      indexstring($mysqli, "$docid/$key", $val);
    elseif (is_array($val)  || is_object($val))
      indexdoc($mysqli, "$docid/$key", $val);
    elseif (is_bool($val) || is_null($val)) {
    }
    else {
      echo "nothing done for $docid/$key<br>\n";
      echo "<pre>val="; var_dump($val); echo "</pre>\n";
      die("FIN ligne ".__LINE__);
    }
  }
  //echo "nbremax=$nbremax<br>\n";
  //if ($nbremax-- < 0) die("FIN");
}


