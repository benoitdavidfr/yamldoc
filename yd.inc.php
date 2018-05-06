<?php
/*PhpDoc:
name: yd.inc.php
title: yd.inc.php - fonctions générales pour yamldoc
doc: |
  ne marche pas
    doc=baseadmin
    ypath=/tables/name=regionmetro/data/code=84/code,title,(json-ld/geo),(départements/(code,title))
journal: |
  6/5/2018:
  - ajout de sort
  3-4/5/2018:
  - traitement de ypath
  - y.c. traiter des requêtes du type: ypath=/data/title,(json-ld/geo/box)
  1/5/2018:
  - refonte pour index.php v2
  19/4/2018:
  - suppression du cryptage
  18/4/2018:
  - première version
*/

// écriture d'un document, prend l'uid et le texte
function ydwrite(string $uid, string $doc) {
  return file_put_contents("docs/$uid.yaml", $doc);
}

// lecture d'un document, prend l'uid et retourne le texte
function ydread(string $uid) {
  //echo "ydread($uid)<br>\n";
  return @file_get_contents("docs/$uid.yaml");
}

// fonction de comparaison utilisée dans le tri d'un tableau
$keys_for_sort = [];
function cmp(array $a, array $b) {
  global $keys_for_sort;
  $key = $keys_for_sort[0];
  $asc = true;
  if (!isset($a[$key]) and !isset($b[$key]))
    return 0;
  // une valeur indéfinie est inférieure à une valeur définie
  if (!isset($a[$key]))
    return $asc ? -1 : 1;
  if (!isset($b[$key]))
    return $asc ? 1 : -1;
  
  if ($a[$key] == $b[$key])
    return 0;
  if ($asc)
    return ($a[$key] < $b[$key]) ? -1 : 1;
  else
    return ($a[$key] < $b[$key]) ? 1 : -1;
}


// le paramètre est-il une liste ?
// une liste est un array pour lequel les clés sont une liste des entiers s'incrémentant de 1 à partir de 0
function is_list($list) {
  if (!is_array($list))
    return false;
  foreach (array_keys($list) as $k => $v)
    if ($k !== $v)
      return false;
  return true;
}

// le paramètre est-il une liste d'atomes, y.c. list(list), ... ?
function is_listOfAtoms($list) {
  // ce doit être un array et une liste
  if (!is_array($list) or !is_list($list))
    return false;
  // aucun des atom ne doit être un array
  foreach ($list as $elt)
    if (is_array($elt) and !is_list($elt))
      return false;
  return true;
}

// le paramètre est-il une liste de tuples ?
function is_listOfTuples($list) {
  //echo "<pre>is_list "; print_r($list); echo "</pre>\n";
  //echo "<pre>array_keys = "; print_r(array_keys($list)); echo "</pre>\n";
  $ret = is_listOfTuples_i($list);
  //echo "is_list => $ret<br>\n";
  return $ret;
}
function is_listOfTuples_i($list) {
  // ce doit être un array et une liste
  if (!is_array($list) or !is_list($list))
    return false;
  // chaque tuple doit être un array et pas une liste
  foreach ($list as $tuple)
    if (!is_array($tuple) or is_list($tuple))
      return false;
  return true;
}

// affichage d'une liste d'atomes, ou list(list) ... comme <ul><li>
function showListOfAtoms(array $list, string $prefix) {
  echo "<ul>\n";
  foreach ($list as $i => $elt) {
    echo "<li>";
    if (is_array($elt))
      showListOfAtoms($elt, "$prefix/$i");
    else
      echo "$elt\n";
  }
  echo "</ul>\n";
}

// affichage d'une liste de tuples comme table Html
function showListOfTuplesAsTable(array $table, string $prefix) {
  $keys = []; // liste des clés d'au moins un tuple
  //echo "<pre>"; print_r($tab); echo "</pre>\n";
  foreach ($table as $tuple) {
    foreach (array_keys($tuple) as $key) {
      if (!in_array($key, $keys))
        $keys[] = $key;
    }
  }
  //echo "<pre>keys = "; print_r($keys); echo "</pre>\n";

  echo "<table border=1>\n";
  foreach ($keys as $key)
    echo "<th>$key</th>";
  echo "\n";
  foreach ($table as $tuple) {
    echo "<tr>";
    foreach ($keys as $key) {
      if (!isset($tuple[$key]))
        echo "<td></td>";
      elseif (is_numeric($tuple[$key]))
        echo "<td align='right'>",$tuple[$key],"</td>";
      else {
        echo "<td>";
        showSomething($tuple[$key], "$prefix/$key");
        echo "</td>";
      }
    }
    echo "</tr>\n";
  }
  echo "</table>\n";
}

// affichage d'un array comme table Html
function showArrayAsTable(array $data, string $prefix) {
  echo "<table border=1>\n";
  foreach ($data as $key => $value) {
    echo "<tr><td>$key</td><td>\n";
    showSomething($value, "$prefix/$key");
    echo "</td></tr>\n";
  }
  echo "</table>\n";
}

// aiguille l'affichage en fonction du type du paramètre
function showSomething($data, string $prefix='') {
  if (is_listOfAtoms($data))
    showListOfAtoms($data, $prefix);
  elseif (is_listOfTuples($data))
    showListOfTuplesAsTable($data, $prefix);
  elseif (is_array($data))
    showArrayAsTable($data, $prefix);
  elseif (is_string($data) and (strpos($data, "\n")!==FALSE))
    echo "<pre>$data</pre>";
  elseif (is_null($data))
    echo 'null';
  else
    echo $data;
}

// crée un YamlDoc à partir d'un texte
// détermine sa classe en fonction du champ yamlClass
// retourne null si le texte n'est pas du Yaml
function new_yamlDoc(string $docuid, string $text) {
  if (!($data = spycLoadString($text)))
    return null;
  if (isset($data['yamlClass'])) {
    $yamlClass = $data['yamlClass'];
    if (class_exists($yamlClass))
      return new $yamlClass ($docuid, $data);
    else
      echo "<b>Erreur: la classe $yamlClass n'est pas définie</b><br>\n";
  }
  return new YamlDoc($docuid, $data);
}

// classe YamlDoc de base
class YamlDoc {
  protected $docuid; // uid du document
  protected $data; // contenu du doc sous forme d'un arrray Php
  
  function __construct(string $docuid, array $data) { $this->docuid = $docuid;  $this->data = $data; }
  function isHomeCatalog() { return null; }
  
  // affiche le doc ou le fragment si ypath est non vide
  function show(string $ypath) {
    //echo "<pre>"; print_r($this->data); echo "</pre>\n";
    showSomething(self::sextract($this->data, $ypath));
  }
  
  function dump(string $ypath) {
    var_dump(self::sextract($this->data, $ypath));
  }
  
  // génère le texte correspondant au fragment défini par ypath
  // améliore la sortie en supprimant les débuts de ligne
  function displayText(string $ypath) {
    $fragment = self::sextract($this->data, $ypath);
    $text = spycDump($fragment);
    $pattern = '!(\n +- )\n +!';
    while (preg_match($pattern, $text, $matches)) {
      $text = preg_replace($pattern, $matches[1], $text, 1);
    }
    echo $text;
  }
  
  
  // extrait le premier elt de ypath
  static function extract_ypath(string $ypath): string {
    if (substr($ypath,0,1)=='/')
      $ypath = substr($ypath,1);
    $prof = 0;
    for ($i=0; $i<strlen($ypath); $i++) {
      $c = substr($ypath, $i, 1);
      if (($c=='/') and ($prof==0))
        return substr($ypath, 0, $i);
      elseif ($c=='(')
        $prof++;
      elseif ($c==')')
        $prof--;
    }
    return $ypath;
  }
  
  // retourne le sous-document défini par path qui est une chaine
  function extract(string $ypath) {
    return self::sextract($this->data, $ypath);
  }
  
  // retourne le fragment défini par la chaine ypath
  static function sextract(array $data, string $ypath) {
    //echo "extract(ypath=$ypath)<br>\n";
    if (!$ypath)
      return $data;
    echo "ypath=$ypath<br>\n";
    $elt = self::extract_ypath($ypath);
    $ypath = substr($ypath, strlen($elt)+1);
    echo "elt=$elt<br>\n";
    if (strpos($elt,'=') !== false) {
      $query = explode('=', $elt);
      $data = self::select($data, $query[0], $query[1]);
    }
    elseif (preg_match('!^sort\(([^)]+)\)$!', $elt, $matches))
      $data = self::sort($data, $matches[1]);
    else
      $data = self::project($data, $elt);
    if (!$ypath)
      return $data;
    if (is_array($data))
      return self::sextract($data, $ypath);
    else
      return null;
  }
  
  // selection dans la liste de tuples $data sur $key=$value
  static function select(array $data, string $key, string $value) {
    $result = [];
    foreach ($data as $tuple)
      if ($tuple[$key]==$value)
        $result[] = $tuple;
    if (count($result)==0)
      return null;
    elseif (count($result)==1)
      return $result[0];
    else
      return $result;
  }
  
  // projection de $data sur $keys
  static function project(array $data, string $keys) {
    $keys = explode(',', $keys);
    if (is_listOfTuples($data)) {
      $result = [];
      foreach ($data as $tuple) {
        if (count($keys)==1) {
          $result[] = $tuple[$keys[0]];
        }
        else {
          $t = [];
          foreach ($keys as $key) {
            if (substr($key,0,1)=='(') {
              $ypath = substr($key, 1, strlen($key)-2);
              $skeys = explode('/', $ypath);
              $skey = $skeys[count($skeys)-1];
              $t[$skey] = self::sextract($tuple, $ypath);
            }
            else
              $t[$key] = $tuple[$key];
          }
          $result[] = $t;
        }
      }
      return $result;
    }
    elseif (count($keys)==1)
      return $data[$keys[0]];
    else {
      $t = [];
      foreach ($keys as $key) {
        if (substr($key,0,1)=='(') {
          $ypath = substr($key, 1, strlen($key)-2);
          $skeys = explode('/', $ypath);
          $skey = $skeys[count($skeys)-1];
          if (in_array($skey, $keys))
            $skey = $ypath;
          $t[$skey] = self::sextract($data, $ypath);
        }
        else
          $t[$key] = $data[$key];
      }
      return $t;
    }
  }
  
  // tri de $data sur $keys
  static function sort(array $data, string $keys) {
    global $keys_for_sort;
    $keys_for_sort = explode(',', $keys);
    usort($data, 'cmp');
    return $data;
  }
};

// class des catalogues
class YamlCatalog extends YamlDoc {
  function show(?string $ypath) {
    //echo "<pre>"; print_r($this->data); echo "</pre>\n";
    echo "<h1>",$this->data['title'],"</h1><ul>\n";
    foreach($this->data['contents'] as $duid => $item) {
      $title = isset($item['title']) ? $item['title'] : $duid;
      echo "<li><a href='?doc=$duid'>$title</a>\n";
    }
    echo "</ul>\n";
  }
  
  // ajoute un doc dans un catalogue
  static function store_in_catalog(string $docuid, string $catuid) {
    $contents = spycLoadString(ydread($catuid));
    //print_r($contents);
    $contents['contents'][$docuid] = ['title'=> "document $docuid" ];
    ydwrite($catuid, spycDump($contents));
  }
};

// classe des catalogues d'accueil
class YamlHomeCatalog extends YamlCatalog {
  function isHomeCatalog() { return $this->docuid; }
};


if (basename(__FILE__)<>basename($_SERVER['PHP_SELF'])) return;


