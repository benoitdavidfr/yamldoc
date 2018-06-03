<?php
/*PhpDoc:
name: yd.inc.php
title: yd.inc.php - fonctions générales pour yamldoc
functions:
doc: doc intégrée en Php
*/
{
$phpDocs['yd.inc.php'] = <<<EOT
name: yd.inc.php
title: yd.inc.php - fonctions générales pour yamldoc
doc: |
journal: |
  3/6/2018:
  - dans new_yamlDoc() utilisation de la version sérialisée du doc si elle existe et est plus récente que le Yaml
  25/5/2018:
  - ajout de la gestion des dates lors du parse et pour l'affichage
  12/5/2018:
  - protection en écriture
  - modif new_yamlDoc()
  - ajout mécanisme simple de verrouillage des documents en cours de mise à jour
  - scission avec catalog.inc.php
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
EOT;
}
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
  if (file_put_contents(__DIR__."/docs/$uid.$ext", $text)===FALSE)
    return FALSE;
  else
    return $ext;
}

// lecture d'un document, prend l'uid et retourne le texte
// cherche dans l'ordre un yaml puis un php
function ydread(string $uid) {
  //echo "ydread($uid)<br>\n";
  if (($text = @file_get_contents(__DIR__."/docs/$uid.yaml")) === false)
    $text = @file_get_contents(__DIR__."/docs/$uid.php");
  return $text;
}

// retourne l'extension d'un document
function ydext(string $uid): string {
  //echo "ydext(string $uid)";
  if (is_file(__DIR__."/docs/$uid.pser"))
    $ext = 'pser';
  elseif (is_file(__DIR__."/docs/$uid.yaml"))
    $ext = 'yaml';
  elseif (is_file(__DIR__."/docs/$uid.php"))
    $ext = 'php';
  else
    $ext = '';
  //echo " -> $ext<br>\n";
  return $ext;
}

// suppression d'un document, prend son uid
function yddelete(string $uid) {
  return (@unlink(__DIR__."/docs/$uid.yaml") or @unlink(__DIR__."/docs/$uid.php"));
}

// teste si le docuid a été marqué comme accessible en lecture par ydsetReadAccess()
function ydcheckReadAccess(string $docuid): bool {
  return (isset($_SESSION['checkedReadAccess']) && in_array($docuid, $_SESSION['checkedReadAccess']));
}

// marque le docuid comme accessible en lecture
function ydsetReadAccess(string $docuid): void {
  //echo "ydsetReadAccess($docuid)<br>\n";
  if (!ydcheckReadAccess($docuid))
    $_SESSION['checkedReadAccess'][] = $docuid;
}

function ydcheckWriteAccessForPhpCode(string $docuid) {
  $ydcheckWriteAccessForPhpCode = 1;
  $authorizedWriters = require "docs/$docuid.php";
  //echo "authorizedWriters="; print_r($authorizedWriters);
  $right = (isset($_SESSION['homeCatalog'])
            && is_array($authorizedWriters)
            && in_array($_SESSION['homeCatalog'], $authorizedWriters));
  ydsetWriteAccess($docuid, $right);
};

// marque le docuid comme accessible ou non en écriture
function ydsetWriteAccess(string $docuid, bool $right): void {
  //echo "ydsetWriteAccess($docuid, ",($right ? 1 : 0),")<br>\n";
  $_SESSION['checkedWriteAccess'][$docuid] = ($right ? 1 : 0);
}

// teste si le docuid a été marqué comme accessible en écriture
// renvoie 1 pour autorisé, 0 pour interdit et -1 pour indéfini
function ydcheckWriteAccess(string $docuid): int {
  return isset($_SESSION['checkedWriteAccess'][$docuid]) ? $_SESSION['checkedWriteAccess'][$docuid] : -1;
}

function ydlock(string $uid): bool {
  //echo "ydlock($uid)<br>\n";
  if (file_exists(__DIR__."/docs/$uid.lock"))
    return false;
  file_put_contents(__DIR__."/docs/$uid.lock", 'lock');
  $_SESSION['locks'][] = $uid;
  return true;
}

function ydunlockall() {
  //echo "ydunlockall()<br>\n";
  if (isset($_SESSION['locks']))
    foreach($_SESSION['locks'] as $uid)
      @unlink(__DIR__."/docs/$uid.lock");
  unset($_SESSION['locks']);
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
  if (is_object($data)) {
    switch (get_class($data)) {
      case 'DateTime':
        echo $data->format('Y-m-d H:i:s');
        break;
      default:
        $data->show($prefix);
    }
  }
  elseif (is_listOfAtoms($data))
    showListOfAtoms($data, $prefix);
  elseif (is_listOfTuples($data))
    showListOfTuplesAsTable($data, $prefix);
  elseif (is_array($data))
    showArrayAsTable($data, $prefix);
  elseif (is_string($data) and (strpos($data, "\n")!==FALSE))
    echo "<pre>$data</pre>";
  elseif (is_null($data))
    echo 'null';
  elseif (is_object($data) and (get_class($data)=='DateTime'))
    echo $data->format('Y-m-d H:i:s');
  else
    echo $data;
}

// crée un YamlDoc à partir du docuid du document
// S'il existe un pser et qu'il est plus récent que le yaml alors renvoie la désérialisation du pser
// Sinon Si le fichier n'existe pas renvoie null
// Sinon Si le texte correspond à du code Php alors l'exécute pour obtenir l'objet résultant et le renvoie
// Sinon Si le texte est du Yaml alors détermine sa classe en fonction du champ yamlClass
// Sinon retourne un YamlDoc contenant le text comme scalaire
function new_yamlDoc(string $docuid): ?YamlDoc {
  //rajouter un test sur le fait que le pser soit plus récent que que le yaml
  //echo "filemtime(yaml)=", @filemtime(__DIR__."/docs/$docuid.yaml"),"<br>\n";
  //echo "filemtime(pser)=", @filemtime(__DIR__."/docs/$docuid.pser"),"<br>\n";
  if (file_exists(__DIR__."/docs/$docuid.pser")
    && (filemtime(__DIR__."/docs/$docuid.pser") > filemtime(__DIR__."/docs/$docuid.yaml"))) {
      //echo "unserialize()<br>\n";
      return unserialize(@file_get_contents(__DIR__."/docs/$docuid.pser"));
  }
  if (($text = ydread($docuid)) === FALSE)
    return null;
  if (strncmp($text,'<?php', 5)==0) {
    if (!$docuid)
      throw new Exception("Erreur: le paramètre docuid n'est pas défini");
    ydcheckWriteAccessForPhpCode($docuid);
    return require "docs/$docuid.php";
  }
  $data = Yaml::parse($text, Yaml::PARSE_DATETIME);
  if (!is_array($data))
    $doc = new YamlDoc($text);
  elseif (isset($data['yamlClass'])) {
    $yamlClass = $data['yamlClass'];
    if (class_exists($yamlClass))
      $doc = new $yamlClass ($data);
    else {
      echo "<b>Erreur: la classe $yamlClass n'est pas définie</b><br>\n";
      $doc = new YamlDoc($data);
    }
  }
  else
    $doc = new YamlDoc($data);
  ydsetWriteAccess($docuid, $doc->authorizedWriter());
  return $doc;
}

// Declaration de l'interface 'YamlDocElement'
// Tout élément d'un YamlDoc doit être soit:
//  - un type Php généré par l'analyseur Yaml y compris des objets de type DateTime
//  - un objet d'une classe conforme à l'interface YamlDocElement
interface YamlDocElement {
  public function show(string $ypath);
  public function extract(string $ypath);
};

// classe YamlDoc de base
class YamlDoc implements YamlDocElement {
  protected $data; // contenu du doc sous forme d'un array Php ou d'un scalaire
  
  function __construct($data) { $this->data = $data; }
  
  // permet d'accéder aux champs du document comme si c'était un champ de la classe
  function __get(string $name) {
    return isset($this->data[$name]) ? $this->data[$name] : null;
  }
  function isHomeCatalog() { return false; }
  
  // affiche le doc ou le fragment si ypath est non vide
  function show(string $ypath): void {
    //echo "<pre>"; print_r($this->data); echo "</pre>\n";
    showDoc(self::sextract($this->data, $ypath));
  }
  
  function dump(string $ypath): void {
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
  
  function json(string $ypath): string {
    $data = self::sextract($this->data, $ypath);
    return json_encode($data,  JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
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
    //echo "sextract(ypath=$ypath)<br>\n";
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
    elseif (is_object($data))
      return $data->extract($ypath);
    else {
      echo "Cas non traité<br>\n";
      //echo "<pre>data = "; print_r($data); echo "</pre>\n";
      return $data;
    }
  }
  
  // selection dans la liste de tuples $data sur $key=$value
  static function select(array $data, string $key, string $value) {
    echo "select(data, key=$key, value=$value)<br>\n";
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
  
  // vérification si nécessaire du droit d'accès en consultation ou du mot de passe
  
  function checkReadAccess(string $docuid): bool {
    // si le doc a déjà été marqué comme accessible alors retour OK
    if (ydcheckReadAccess($docuid))
      return true;
    // Si le document contient un mot de passe
    if ($this->yamlPassword) {
      //echo "checkPassword<br>\n";
      //if (isset($_POST['password'])) echo "password=$_POST[password]<br>\n";
      if (!isset($_POST['password'])) {
        // Si aucun mot de passe n'a été fourni alors demande du mot de passe
        echo "Mot de passe du document :<br>\n";
        die("<form method='post'><input type='password' name='password'></form>\n");
      }
      // sinon  et si il est correct alors retour OK
      if (password_verify($_POST['password'], $this->yamlPassword)) {
        ydsetReadAccess($docuid);
        return true;
      }
      // sinon c'est qu'il est incorrect
      else {
        // Si non alors demande du mot de passe
        echo "Mot de passe fourni incorrect :<br>\n";
        die("<form method='post'><input type='password' name='password'></form>\n");
      }
    }
    // Si le document ne contient pas de mot de passe
    if ($this->authorizedReader()) {
      ydsetReadAccess($docuid);
      return true;
    }
    else
      return false;
  }
  
  // test du droit en lecture indépendamment d'un éventuel mot de passe
  function authorizedReader(): bool {
    //print_r($this);
    $ret = !isset($this->data['authorizedReaders'])
           || (isset($_SESSION['homeCatalog'])
              && in_array($_SESSION['homeCatalog'], $this->data['authorizedReaders']));
    //echo "authorizedReader=$ret<br>\n";
    return $ret;
  }
  
  // test du droit en écriture indépendamment d'un éventuel mot de passe
  function authorizedWriter(): bool {
    if (!$this->authorizedReader()) {
      //echo "authorizedWriter false car !reader<br>\n";
      return false;
    }
    $ret = isset($_SESSION['homeCatalog'])
           && (!isset($this->data['authorizedWriters'])
             || in_array($_SESSION['homeCatalog'], $this->data['authorizedWriters']));
    //echo "authorizedWriter=$ret<br>\n";
    return $ret;
  }
  
  // vérification de la conformité du document à son schéma
  function checkSchemaConformity() {
    echo "methode YamlDoc::checkSchemaConformity() non implémentée<br>\n";
  }
};

if (basename(__FILE__)<>basename($_SERVER['PHP_SELF'])) return;

$str = 'code,title,(json-ld/geo),(depts/code,title)';
echo "<pre>";
echo "$str\n";
print_r(YamlDoc::protexplode(',', $str));
