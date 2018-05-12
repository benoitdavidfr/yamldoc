<?php
/*PhpDoc:
name: yd.inc.php
title: yd.inc.php - fonctions générales pour yamldoc
doc: |
journal: |
  11/5/2018:
  - migration de Spyc vers https://github.com/symfony/yaml
  10/5/2018:
  - modif ydwrite(), ydread() et yddelete()
    à l'écriture du document l'extension .php ou .yaml est choisie en fonction du contenu
    à la lecture, on regarde sur le yaml existe sinon on prend le php
  6/5/2018:
  - traitement des path avec , imbriquées du type:
      doc=baseadmin
      ypath=/tables/name=regionmetro/data/code=84/code,title,(json-ld/geo),(depts/code,title)
  - ajout de sort uniquement sur une clé et sans asc/desc
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
require_once __DIR__.'/../vendor/autoload.php';
use Symfony\Component\Yaml\Yaml;

/*PhpDoc: functions
  name:  dump
  title: function dump($string) - function affichant la chaine en séparant chaque caractère et en affichant aussi le code hexa correspondant
*/
function dump($string) {
  echo "<table border=1>";
  $line = 0;
  while($line*16 < strlen($string)) {
    echo "<tr><td>$line</td>";
    for ($i=0; $i<16; $i++)
      echo "<td>",substr($string,$line*16+$i,1),"</td>";
    echo "<td></td>";
    echo "<td></td>";
    echo "<td></td>";
    for ($i=0; $i<16; $i++)
      printf("<td>%0x</td>", ord(substr($string,$line*16+$i,1)));
    echo "</tr>\n";
    $line++;
  }
}

// écriture d'un document, prend l'uid et le texte
// s'il s'agit d'un Php l'extension est php, sinon yaml
function ydwrite(string $uid, string $text) {
  $ext = (strncmp($text,'<?php', 5)==0) ? 'php' : 'yaml';
  if ($ext == 'php')
    @unlink(__DIR__."/docs/$uid.yaml");
  return file_put_contents(__DIR__."/docs/$uid.$ext", $text);
}

// lecture d'un document, prend l'uid et retourne le texte
// si le doc n'existe pas et que $warning est vrai alors affichage d'un warning
// cherche dans l'ordre un yaml puis un php
function ydread(string $uid, int $warning=0) {
  //echo "ydread($uid)<br>\n";
  //echo __DIR__."/docs/$uid.yaml";
  if (($text = @file_get_contents(__DIR__."/docs/$uid.yaml")) === false)
    if (($text = @file_get_contents(__DIR__."/docs/$uid.php")) === false)
      if ($warning)
        echo "<b>Erreur: Document $uid non trouvé</b><br>\n";
  return $text;
}

// suppressio d'un document, prend son uid
function yddelete(string $uid) {
  return (@unlink(__DIR__."/docs/$uid.yaml") or @unlink(__DIR__."/docs/$uid.php"));
}

// teste si le docuid a été marqué comme accessible en lecture par ydsetReadAccess()
function ydcheckReadAccess(string $docuid): bool {
  return (isset($_SESSION['checkedReadAccess']) and in_array($docuid, $_SESSION['checkedReadAccess']));
}

// marque le docuid comme accessible en lecture
function ydsetReadAccess(string $docuid): void {
  if (!isset($_SESSION['checkedReadAccess']) or !in_array($docuid, $_SESSION['checkedReadAccess']));
    $_SESSION['checkedReadAccess'][] = $docuid;
}

// fonction de comparaison utilisée dans le tri d'un tableau
//variable globale contenant la clé du tri
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
        showDoc($tuple[$key], "$prefix/$key");
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
    showDoc($value, "$prefix/$key");
    echo "</td></tr>\n";
  }
  echo "</table>\n";
}

// aiguille l'affichage en fonction du type du paramètre
function showDoc($data, string $prefix='') {
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

// crée un YamlDoc à partir du texte du document
// Si le texte correspond à du code Php alors l'exécute pour obtenir l'objet résultant et le renvoie
// Sinon Si le texte est du Yaml alors détermine sa classe en fonction du champ yamlClass
// Sinon retourne un YamlDoc contenant le text comme scalaire
function new_yamlDoc(string $text, string $docuid=null): YamlDoc {
  if (strncmp($text,'<?php', 5)==0) {
    if (!$docuid)
      throw new Exception("Erreur: le paramètre docuid n'est pas défini");
    return require "docs/$docuid.php";
  }
  $data = Yaml::parse($text);
  if (!is_array($data))
    return new YamlDoc($text);
  if (isset($data['yamlClass'])) {
    $yamlClass = $data['yamlClass'];
    if (class_exists($yamlClass))
      return new $yamlClass ($data);
    else
      echo "<b>Erreur: la classe $yamlClass n'est pas définie</b><br>\n";
  }
  return new YamlDoc($data);
}

// classe YamlDoc de base
class YamlDoc {
  protected $data; // contenu du doc sous forme d'un arrray Php ou d'un scalaire
  
  function __construct($data) { $this->data = $data; }
  function isHomeCatalog() { return false; }
  
  // affiche le doc ou le fragment si ypath est non vide
  function show(string $ypath) {
    //echo "<pre>"; print_r($this->data); echo "</pre>\n";
    showDoc(self::sextract($this->data, $ypath));
  }
  
  function dump(string $ypath) {
    var_dump(self::sextract($this->data, $ypath));
  }
  
  // génère le texte correspondant au fragment défini par ypath
  // améliore la sortie en supprimant les débuts de ligne
  function yaml(string $ypath): string {
    return self::syaml(self::sextract($this->data, $ypath));
  }
  
  static function syaml($data): string {
    $text = Yaml::dump($data, 999);
    $pattern = '!^( *-)\n +!';
    if (preg_match($pattern, $text, $matches)) {
      $text = preg_replace($pattern, $matches[1].'   ', $text, 1);
    }
    $pattern = '!(\n *-)\n +!';
    while (preg_match($pattern, $text, $matches)) {
      $text = preg_replace($pattern, $matches[1].'   ', $text, 1);
    }
    return $text;
  }
  
  
  // extrait le premier elt de $ypath en utilisant le séparateur $sep
  // le séparateur n'est pas pris en compte s'il est entre ()
  static function extract_ypath(string $sep, string $ypath): string {
    if (substr($ypath,0,1)==$sep)
      $ypath = substr($ypath,1);
    $prof = 0;
    for ($i=0; $i<strlen($ypath); $i++) {
      $c = substr($ypath, $i, 1);
      if (($c==$sep) and ($prof==0))
        return substr($ypath, 0, $i);
      elseif ($c=='(')
        $prof++;
      elseif ($c==')')
        $prof--;
    }
    return $ypath;
  }
  
  // retourne le fragment défini par path qui est une chaine
  function extract(string $ypath) {
    return self::sextract($this->data, $ypath);
  }
  
  // retourne le fragment défini par la chaine ypath
  static function sextract($data, string $ypath) {
    //echo "extract(ypath=$ypath)<br>\n";
    if (!$ypath)
      return $data;
    //echo "ypath=$ypath<br>\n";
    $elt = self::extract_ypath('/', $ypath);
    $ypath = substr($ypath, strlen($elt)+1);
    //echo "elt=$elt<br>\n";
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
  
  // decompose la chaine $srce en un tableau en utilisant le séparateur $sep
  // le séparateur n'est pas pris en compte s'il est entre ()
  static function protexplode(string $sep, string $srce) {
    $results = [];
    $prof = 0;
    $j = 0;
    for ($i=0; $i<strlen($srce); $i++) {
      $c = substr($srce, $i, 1);
      if (($c==$sep) and ($prof==0)) {
        $results[] = substr($srce, $j, $i-$j);
        $j = $i+1;
      }
      elseif ($c=='(')
        $prof++;
      elseif ($c==')')
        $prof--;
    }
    $results[] = substr($srce, $j, $i);
    return $results;
  }
  
  // projection de $data sur $keys
  static function project(array $data, string $keys) {
    //$keys = explode(',', $keys);
    $keys = self::protexplode(',', $keys);
    //echo "keys="; print_r($keys); echo "<br>\n";
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
            elseif (isset($tuple[$key]))
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
  
  // nest de $data sur $keys
  static function nest(array $data, array $keys, string $nestkey) {
    //return $data;
    $results = [];
    foreach($data as $tuple) {
      //echo "tuple="; print_r($tuple); echo "<br>\n";
      $stuple = [];
      $stuple2 = [];
      foreach ($tuple as $key => $value)
        if (isset($keys[$key]))
          $stuple[$keys[$key]] = $value;
        else
          $stuple2[$key] = $value;
      $ser = serialize($stuple);
      //echo "ser=$ser<br>\n";
      if (!isset($results[$ser])) {
        $results[$ser] = $stuple;
        $results[$ser][$nestkey] = [];
      }
      $results[$ser][$nestkey][] = $stuple2;
      //showDoc($results);
    }
    return array_values($results);
  }
  
  // vérification du mot de passe si nécessaire
  function checkPassword(string $docuid): bool {
    // si le doc a déjà été marqué comme accessible alors retour OK
    if (ydcheckReadAccess($docuid))
      return true;
    // si le doc ne contient pas de yamlPassword alors retour OK
    if (!isset($this->data['yamlPassword'])) {
      ydsetReadAccess($docuid);
      return true;
    }
    // si le mot de passe a été fourni et qu'il est correct alors retour OK
    //echo "checkPassword<br>\n";
    //if (isset($_POST['password'])) echo "password=$_POST[password]<br>\n";
    if (isset($_POST['password']) and password_verify($_POST['password'], $this->data['yamlPassword'])) {
      ydsetReadAccess($docuid);
      return true;
    }
    // Si non demande du mot de passe
    echo "Mot de passe du document :<br>\n";
    die("<form method='post'><input type='password' name='password'></form>\n");
  }
  
  // vérification de la conformité du document à son schéma
  function check() {
    echo "methode YamlDoc::check() non implémentée<br>\n";
  }
};

// class des catalogues
class YamlCatalog extends YamlDoc {
  function show(string $ypath) {
    //echo "<pre>"; print_r($this->data); echo "</pre>\n";
    echo "<h1>",$this->data['title'],"</h1><ul>\n";
    foreach($this->data['contents'] as $duid => $item) {
      $title = isset($item['title']) ? $item['title'] : $duid;
      echo "<li><a href='?doc=$duid'>$title</a>\n";
    }
    echo "</ul>\n";
  }
  
  // clone un doc dans un catalogue
  static function clone_in_catalog(string $newdocuid, string $olddocuid, string $catuid) {
    $contents = Yaml::parse(ydread($catuid));
    //print_r($contents);
    $title = $contents['contents'][$olddocuid]['title'];
    $contents['contents'][$newdocuid] = ['title'=> "$title cloné $newdocuid" ];
    ydwrite($catuid, Yaml::dump($contents));
  }
  
  static function delete_from_catalog(string $docuid, string $catuid) {
    $contents = Yaml::parse(ydread($catuid));
    unset($contents['contents'][$docuid]);
    ydwrite($catuid, Yaml::dump($contents));
  }
};

// classe des catalogues d'accueil
class YamlHomeCatalog extends YamlCatalog {
  function isHomeCatalog() { return true; }
};


if (basename(__FILE__)<>basename($_SERVER['PHP_SELF'])) return;

$str = 'code,title,(json-ld/geo),(depts/code,title)';
echo "<pre>";
echo "$str\n";
print_r(YamlDoc::protexplode(',', $str));
