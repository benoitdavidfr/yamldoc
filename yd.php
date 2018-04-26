<?php
// essai de gestion d'un document Yaml avec une classe YamlDoc

require_once __DIR__.'/../spyc/spyc2.inc.php';
require_once __DIR__.'/yd.inc.php';
require_once __DIR__.'/catalog.inc.php';

session_start();

class YamlDoc {
  private $data; // donnée stockée comme array
  
  // 2 cas de figure de création:
  // 1) à partir d'un texte Yaml avec possibilité d'indexation
  //    index: [ ypath => key ]
  // 2) à partir d'un array correspondant à une sous-partie du document
  function __construct($param, $index=null) {
    if (is_string($param)) {
      $this->data = spycLoadString($param);
      if ($index)
        foreach ($index as $ypath => $key) {
          $subtab = $this->extract($ypath);
          echo "<pre>subtab = "; print_r($subtab); echo "</pre>\n";
          $subtab2 = [];
          foreach ($subtab->data as $tuple) {
            $vkey = $tuple[$key];
            unset($tuple[$key]);
            $subtab2[$vkey] = $tuple;
          }
          echo "<pre>subtab2 = "; print_r($subtab2); echo "</pre>\n";
        }
    }
    elseif (is_array($param)) {
      $this->data = $param;
    }
    else
      throw new Exception("YamlDoc::__construct(): bad type for param");
  }
  
  // retourne un champ du document
  function access(string $key): YamlDoc {
    return new YamlDoc($this->data[$key]);
  }
  
  // retourne le sous-document défini par path qui est une chaine ou un array de clés
  function extract($ypath): YamlDoc {
    echo "extract(", is_string($ypath) ? $ypath : implode('/',$ypath), ")<br>\n";
    if (!$ypath)
      return $this;
    if (is_string($ypath)) {
      $ypath = explode('/', $ypath);
      if (!$ypath[0])
        array_shift($ypath);
    }
    $key = array_shift($ypath);
    return $this->access($key)->extract($ypath);
  }
  
  function set($ypath, YamlDoc $subdoc) {
    
  }
};

echo "<!DOCTYPE HTML><html><head><meta charset='UTF-8'><title>yamlDoc</title></head><body>\n";

// reinitialisation des variables de session puis lecture éventuelle d'un document dans un fichier
if (isset($_GET['action']) and ($_GET['action']=='init')) {
  unset($_SESSION['text']);
  unset($_SESSION['name']);
  unset($_SESSION['catalogs']);
  // lecture d'un document dans un fichier
  if (isset($_GET['name'])) {
    $_SESSION['name'] = $_GET['name'];
    if (($text = ydread($_SESSION['name'])) === FALSE) {
      echo "<b>Erreur le fichier $_SESSION[name].yaml n'existe pas</b>\n";
    }
    else
      $_SESSION['text'] = $text;
  }
}

echo "<pre>text = "; print_r($_SESSION['text']); echo "</pre>\n";

$doc = new YamlDoc($_SESSION['text'], ['/elts'=>'a']);

echo "<pre>doc = "; var_dump($doc); echo "</pre>\n";
