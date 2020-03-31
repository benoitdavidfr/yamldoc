<?php
/*PhpDoc:
name: yd.inc.php
title: yd.inc.php - fonctions générales pour yamldoc
functions:
doc: <a href='/yamldoc/?action=version&name=yd.inc.php'>doc intégrée en Php</a>
includes:
  - store.inc.php
  -  ../markdown/markdown/PHPMarkdownLib1.8.0/Michelf/MarkdownExtra.inc.php
*/
{
$phpDocs['yd.inc.php']['file'] = <<<'EOT'
name: yd.inc.php
title: yd.inc.php - fonctions générales pour yamldoc
doc: |
  Le format externe d'un document est du Yaml.
  Le format interne dépend de chaque document et est généré lors de la création du document.
  Typiquement un document peut créer des objets à la place de certains arrays pour simplifier la définition
  des traitements.
  Le format interne peut être stocké dans les fichiers .pser
  Un document peut correspondre à une classe Php et à un schéma JSON particuliers indiqués au travers du champ $schema
journal:
  28/12/2019:
  - ajout showAsHtmlDoc()
  28/9/2019:
  - utilisation d'un répertoire vendor local
  31/7/2019:
  - ajout de la possibilité d'avoir plusieurs schema et d'utiliser le premier pour typer le document
  25/2/2019:
  - amélioration de la récriture des URL dans showText() et showString()
  24/2/2019:
  - la définition d'une classe YamlDoc se fait en affectant au champ $schema une chaine respectant le motif
    YamlDoc::SCHEMAURIPATTERN
  23/2/2019:
  - le schéma d'un doc auto-structuré est défini dans le champ $schema
  15/2/2019:
  - dans showString() une URL vers http://id.georef.eu/{id} est renvoyée vers id.php/{id}
  5/2/2019:
  - chgt de spec - un php renvoie un array Php au lieu d'un objet YamlDoc
  25/1/2019:
  - remplacement du mot-clé YamlClass par l'utilisation de $schema avec un URI commencant par YamlDoc::SCHEMAURIPREFIX
  3/1/2019:
  - améliorations
  - ajout de tests unitaires
  22/8/2018:
  - réécriture de ydext()
  28/7/2018:
  - utilisation de la classe Store pour déterminer le store
  25/7/2018:
  - ajout fabrication pser pour document php
  21/7/2018:
  - correction d'un bug dans showString() pour afficher les liens
  18/7/2018:
  - restructuration des classes
    - la classe YamlDoc est une classe abstraite de n'importe quel document, elle porte les méthodes génériques
    - les documents par défaut sont définis par la classe BasicYamlDoc
  - réorganisation des fichiers
  - l'ancien yd.inc.php est scindé en 3 fichiers
    - yd.inc.php qui contient les fonctions
    - yamldoc.inc.php qui contient la définition de la classe abstraite YamlDoc et de l'interface YamlDocElement
    - basicyamldoc.inc.php qui contient la définition de la classe BasicYamlDoc
  16/7/2018:
  - correction de yread() pour écrire les index
  15/7/2018:
  - ajout du paramètre docid dans les méthodes show()
  11/7/2018:
  - les liens Markdown internes au document sont remplacés par un lien indiquant le document courant
  29/6-2/7/2018:
  - gestion multi-store
  - modification de la signature de plusieurs fonctions
  12/6/2018:
  - correction d'un bug
  10/6/2018:
  - modification de l'affichage d'un texte et d'une chaine
  - une chaine qui commence par http:// ou https:// est remplacée à l'affichage par un lien
  - pour un texte, 3 possibilités de formattage:
    - 'si le texte commence par "Content-Type: text/plain\n" alors affichage du texte brut'
    - si le texte commence par "<html>\n" alors affichage du texte considéré comme du HTML
    - sinon le texte est considéré comme du Markdown
  9/6/2018:
  - modification de showDoc() pour qu'un texte derrière un > soit représenté comme chaine et pas comme texte
  - amélioration de l'export en Yaml et JSON notamment des dates
  - remplacement des appels des méthodes yaml() et json() sur les YamlDocElement par un appel à php()
  7/6/2018:
  - traitement du cas $data==null dans YamlDoc::sextract()
  - appel des méthodes YamlData::yaml() et YamlData::json()
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
require_once __DIR__.'/store.inc.php';
require_once __DIR__.'/vendor/autoload.php';
require_once __DIR__."/../markdown/markdown/PHPMarkdownLib1.8.0/Michelf/MarkdownExtra.inc.php";

use Symfony\Component\Yaml\Yaml;
use Michelf\MarkdownExtra;

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

// écriture d'un document, prend le store, l'uid et le texte
// s'il s'agit d'un Php l'extension est php, sinon yaml
function ydwrite(string $uid, string $text) {
  $storepath = Store::storepath();
  $ext = (strncmp($text,'<?php', 5)==0) ? 'php' : 'yaml';
  $filename = is_dir(__DIR__."/$storepath/$uid") ? __DIR__."/$storepath/$uid/index" : __DIR__."/$storepath/$uid";
  if ($ext == 'php')
    @unlink("$filename.yaml");
  if (file_put_contents("$filename.$ext", $text)===FALSE)
    return FALSE;
  else
    return $ext;
}

// lecture d'un document, prend le store, l'uid et retourne le texte
// cherche dans l'ordre un yaml puis un php puis si c'est un répertoire un fichier index.yaml ou index.php
function ydread(string $uid) {
  //echo "ydread($uid)<br>\n";
  $storepath = Store::storepath();
  if (($text = @file_get_contents(__DIR__."/$storepath/$uid.yaml")) !== false)
    return $text;
  if (($text = @file_get_contents(__DIR__."/$storepath/$uid.php")) !== false)
    return $text;
  if (is_dir(__DIR__."/$storepath/$uid")) {
    //echo "$uid est un répertoire<br>\n";
    if (($text = @file_get_contents(__DIR__."/$storepath/$uid/index.yaml")) !== false)
      return $text;
    if (($text = @file_get_contents(__DIR__."/$storepath/$uid/index.php")) !== false)
      return $text;
  }
  return false;
}

// retourne l'extension d'un document
// cherche dans l'ordre un yaml puis un php puis si c'est un répertoire un fichier index.yaml ou index.php
function ydext(string $uid): string {
  //echo "ydext(string $uid)<br>\n";
  $storepath = Store::storepath();
  foreach (['yaml','php'] as $ext)
    if (is_file(__DIR__."/$storepath/$uid.$ext"))
      return $ext;
  if (is_dir(__DIR__."/$storepath/$uid")) {
    foreach (['yaml','php'] as $ext)
      if (is_file(__DIR__."/$storepath/$uid/index.$ext"))
        return $ext;
  }
  return '';
}

// suppression d'un document, prend son store, uid
function yddelete(string $uid) {
  $storepath = Store::storepath();
  @unlink(__DIR__."/$storepath/$uid.pser");
  return (@unlink(__DIR__."/$storepath/$uid.yaml") or @unlink(__DIR__."/$storepath/$uid.php"));
}

// teste si le docuid a été marqué comme accessible en lecture par ydsetReadAccess()
function ydcheckReadAccess(string $docid): bool {
  $storepath = Store::storepath();
  return (isset($_SESSION['checkedReadAccess']) && in_array("$storepath/$docid", $_SESSION['checkedReadAccess']));
}

// marque le docuid comme accessible en lecture
function ydsetReadAccess(string $docid): void {
  //echo "ydsetReadAccess($docid)<br>\n";
  $storepath = Store::storepath();
  if (!ydcheckReadAccess($storepath, $docid))
    $_SESSION['checkedReadAccess'][] = Store::id()."/$docid";
}

// teste si le script Php peut être modifié par l'utilisateur courant et marque l'info dans l'environnement
function ydcheckWriteAccessForPhpCode(string $docid) {
  $storepath = Store::storepath();
  //echo "storepath=$storepath<br>\n";
  $ydcheckWriteAccessForPhpCode = 1;
  $authorizedWriters = require "$storepath/$docid.php";
  //echo "authorizedWriters="; print_r($authorizedWriters);
  $right = (isset($_SESSION['homeCatalog'])
            && is_array($authorizedWriters)
            && in_array($_SESSION['homeCatalog'], $authorizedWriters));
  ydsetWriteAccess($docid, $right);
};

// marque le docuid comme accessible ou non en écriture
function ydsetWriteAccess(string $docid, bool $right): void {
  //echo "ydsetWriteAccess($docid, ",($right ? 1 : 0),")<br>\n";
  $_SESSION['checkedWriteAccess'][Store::id()."/$docid"] = ($right ? 1 : 0);
}

// teste si le docuid a été marqué comme accessible en écriture
// renvoie 1 pour autorisé, 0 pour interdit et -1 pour indéfini
function ydcheckWriteAccess(string $docid): int {
  $storeid = Store::id();
  return isset($_SESSION['checkedWriteAccess']["$storeid/$docid"]) ?
    $_SESSION['checkedWriteAccess']["$storeid/$docid"]
    : -1;
}

// !!! je ne vois pas l'intérêt de ce mécanisme de lock !!!
function ydlock(string $docid): bool {
  //echo "ydlock($uid)<br>\n";
  $storepath = Store::storepath();
  if (file_exists(__DIR__."/$storepath/$docid.lock"))
    return false;
  file_put_contents(__DIR__."/$storepath/$docid.lock", 'lock');
  $_SESSION['locks'][] = Store::id()."/$docid";
  return true;
}

function ydunlockall() {
  //echo "ydunlockall()<br>\n";
  if (isset($_SESSION['locks']))
    foreach($_SESSION['locks'] as $suid)
      @unlink(__DIR__."/$suid.lock");
  unset($_SESSION['locks']);
}

// fonction de comparaison utilisée dans le tri d'un tableau
//variable globale contenant la clé du tri
$keys_for_sort = [];
function cmp(array $a, array $b) {
  global $keys_for_sort;
  $key = $keys_for_sort[0];
  $asc = true;
  if (!isset($a[$key]) && !isset($b[$key]))
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

// Teste si $data est un texte, cad ssi il contient au moins un \n en dehors de la dernière position 
function is_text($data) {
  return is_string($data) && (strpos($data, "\n")!==FALSE) && (strpos($data, "\n") < strlen($data)-1);
}

// test si un array est un tableau associatif ou une liste,  [] n'est pas un assoc_array
if (!function_exists('is_assoc_array')) {
  function is_assoc_array(array $array): bool { return count(array_diff_key($array, array_keys(array_keys($array)))); }

  if (basename(__FILE__) == basename($_SERVER['PHP_SELF'])) { // Test unitaire de is_assoc_array 
    if (isset($_GET['test']) && ($_GET['test']=='is_assoc_array')) {
      echo "Test is_assoc_array<br>\n";
      foreach ([[], [1, 2, 3], ['a'=>'a','b'=>'b']] as $array) {
        echo json_encode($array), (is_assoc_array($array) ? ' is_assoc_array' : ' is NOT assoc_array') , "<br>\n";
      }
      echo "FIN test is_assoc_array<br><br>\n";
    }
    $unitaryTests[] = 'is_assoc_array';
  }
}

// le par. est-il une liste ? cad un array dont les clés sont la liste des n-1 premiers entiers positifs, [] est une liste
function is_list($list): bool { return is_array($list) && !is_assoc_array($list); }

// le paramètre est-il une liste d'atomes, y.c. list(list), ... ?
function is_listOfAtoms($list): bool {
  if (!is_list($list)) return false; // ce doit être une liste
  // chaque elt doit être soit un atome soit une liste d'atomes
  foreach ($list as $elt)
    if (is_array($elt) && !is_listOfAtoms($elt))
      return false;
  return true;
}

if (basename(__FILE__) == basename($_SERVER['PHP_SELF'])) { // Test unitaire de is_listOfAtoms 
  if (isset($_GET['test']) && ($_GET['test']=='is_listOfAtoms')) {
    foreach ([[], [1, 2, 3], ['a'=>'a','b'=>'b'], [1, [2, 3, [4, 5, 6]]], [1, [2, 3, [4, 5, 'a'=>'b']]]] as $array) {
      echo json_encode($array), (is_listOfAtoms($array) ? ' is_listOfAtoms' : ' is NOT listOfAtoms') , "<br>\n";
    }
  }
  $unitaryTests[] = 'is_listOfAtoms';
}

// le paramètre est-il une liste de tuples ?
function is_listOfTuples($list) {
  if (!is_list($list)) return false; // ce doit être une liste
  // chaque tuple doit être un array et soit [] soit un assoc_array
  foreach ($list as $tuple)
    if (!is_array($tuple) or is_list($tuple))
      return false;
  return true;
}

if (basename(__FILE__) == basename($_SERVER['PHP_SELF'])) { // Test unitaire de is_listOfTuples 
  if (isset($_GET['test']) && ($_GET['test']=='is_listOfTuples')) {
    foreach ([[], [['a'=>'a','b'=>'b','c'=>'c']], [[1, 2, 3]], [['a'=>'a', 3]]] as $array) {
      echo json_encode($array), (is_listOfTuples($array) ? ' is_listOfTuples' : ' is NOT listOfTuples') , "<br>\n";
    }
  }
  $unitaryTests[] = 'is_listOfTuples';
}

function str2html(string $str): string { return str_replace(['&','<','>'],['&amp;','&lt;','&gt;'], $str); }

// indique si une URL est interne à YamlDoc ou non
function internalUrl(string $url): bool {
  return (preg_match('!^http://(id|docs|ydclasses).georef.eu/!', $url));
}

// si on est en local alors réécrit l'URL pour y rester
function urlrewrite(string $url): string {
  //echo '<pre>$_SERVER='; print_r($_SERVER);
  if (!in_array($_SERVER['HTTP_HOST'], ['localhost','127.0.0.1']))
    return $url;
  foreach ([
    'http://id.georef.eu/' => 'http://localhost/yamldoc/id.php/',
    'http://docs.georef.eu/' => 'http://127.0.0.1/yamldoc/id.php/',
    'http://ydclasses.georef.eu/' => 'http://localhost/yamldoc/ydclasses.php/',
  ] as $src => $dest) {
    if (strncmp($url, $src, strlen($src))==0)
      return $dest.substr($url, strlen($src));
  }
  return $url;
}

// Je considère qu'une String est une chaine ne contenant pas de \n intermédiaire, sinon c'est un texte
function showString(string $docid, $str) {
  // une URL est remplacée par une référence avec l'URL comme label
  if (is_string($str) && preg_match('!^(https?://[^ ]*)!', $str, $matches)) {
    $url = $matches[1];
    $after = substr($str, strlen($matches[0]));
    echo "<a href='",urlrewrite($url),"'",internalUrl($url) ? '' : ' target=_blank',">$url</a>$after\n";
  }
  // un motif [{label}]({href}) est remplacé par un lien avec le label
  elseif (is_string($str) && preg_match('!\[([^\]]*)\]\(([^)]+)\)!', $str, $matches)) {
    //print_r($matches);
    $label = $matches[1];
    $href = $matches[2];
    if (strncmp($href, '?ypath=', strlen('?ypath='))==0) {
      // cas d'un lien interne au doc
      $ypath = substr($href, strlen('?ypath='));
      //echo "<br>ypath=$ypath<br>\n";
      $href = "?doc=$docid&amp;ypath=".urlencode($ypath).(isset($_GET['lang']) ? "&amp;lang=$_GET[lang]": '');
      //$str = str_replace($matches[0], "<a href='$href'>$label</a>", $str);
      $link = "<a href='$href'>$label</a>";
    }
    else {
      // cas d'un lien externe au doc
      //$str = str_replace($matches[0], "<a href='$href' target=_blank>$label</a>\n", $str);
      $link = "<a href='$href' target=_blank>$label</a>";
    }
    $pos = strpos($str, $matches[0]);
    $before = substr($str, 0, $pos);
    $after = substr($str, $pos+strlen($matches[0]));
    echo str2html($before),$link,str2html($after);
  }
  // cas à traiter pour une liste de dates, dans par exemple le lexique topographique
  elseif (is_object($str) && (get_class($str)=='DateTime'))
    echo $str->format('Y-m-d H:i:s');
  else
    echo str2html("$str\n");
}

function showText(string $docid, $text) {
  // représentation brute des textes avec \n
  if (preg_match('!^Content-Type: text/plain[\r\n]+!', $text, $matches)) {
    $text = substr($text, strlen($matches[0]));
    echo "<pre>",str_replace(['&','<','>'],['&amp;','&lt;','&gt;'], $text),"</pre>\n";
  }
  elseif (preg_match('!^<html>[\r\n]+!', $text, $matches)) {
    echo substr($text, strlen($matches[0]));
  }
  else {
    // les liens Markdown internes au document sont remplacés par un lien indiquant le document courant
    // et éventuellement la langue
    $pattern = '!\(\?(ypath=([^)]*))\)!';
    while (preg_match($pattern, $text, $matches)) {
      $ypath = $matches[2];
      $replacement = "(?doc=$docid&amp;ypath=$ypath".(isset($_GET['lang']) ? "&amp;lang=$_GET[lang]": '').")";
      $text = preg_replace($pattern, $replacement, $text, 1);
    }
    if (in_array($_SERVER['HTTP_HOST'], ['localhost','127.0.0.1'])) {
      $pattern = '!\((http://(id|docs|ydclasses).georef.eu/[^)]*)\)!';
      while (preg_match($pattern, $text, $matches)) {
        $text = preg_replace($pattern, '('.urlrewrite($matches[1]).')', $text, 1);
      }
    }
    echo MarkdownExtra::defaultTransform($text);
  }
}

// affichage d'une liste d'atomes, ou list(list) ... comme <ul><li>
function showListOfAtoms(string $docid, array $list, string $prefix, int $level=0) {
  if ($level==0)
    echo "<ul style='margin:0; padding:0px; list-style-position: inside;'>\n";
  else
    echo "<ul style='margin:0px;'>\n";
  foreach ($list as $i => $elt) {
    echo "<li>";
    if (is_array($elt))
      showListOfAtoms($docid, $elt, "$prefix/$i", $level+1);
    elseif (is_text($elt))
      showText($docid, $elt);
    else
      showString($docid, $elt);
  }
  echo "</ul>\n";
}

// affichage d'une liste de tuples comme table Html
function showListOfTuplesAsTable(string $docid, array $table, string $prefix) {
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
        showDoc($docid, $tuple[$key], "$prefix/$key");
        echo "</td>";
      }
    }
    echo "</tr>\n";
  }
  echo "</table>\n";
}

// affichage d'un array comme table Html
function showArrayAsTable(string $docid, array $data, string $prefix) {
  echo "<table border=1>\n";
  foreach ($data as $key => $value) {
    echo "<tr><td>$key</td><td>\n";
    showDoc($docid, $value, "$prefix/$key");
    echo "</td></tr>\n";
  }
  echo "</table>\n";
}

// aiguille l'affichage en fonction du type du paramètre
function showDoc(string $docid, $data, string $prefix=''): void {
  if (is_object($data)) {
    if (get_class($data)=='DateTime')
      echo $data->format('Y-m-d H:i:s');
    else
      $data->show($docid, $prefix);
  }
  elseif (is_listOfAtoms($data))
    showListOfAtoms($docid, $data, $prefix);
  elseif (is_listOfTuples($data))
    showListOfTuplesAsTable($docid, $data, $prefix);
  elseif (is_array($data))
    showArrayAsTable($docid, $data, $prefix);
  elseif (is_null($data))
    echo 'null';
  // un texte derrière un > sera représenté comme chaine et pas comme texte 
  elseif (is_text($data))
    showText($docid, $data);
  else
    showString($docid, $data);
}

// affiche comme HtmlDoc
// Considère le doc comme un array de sous-docs
// L'affichage comme HtmlDoc agrège les différents sous-docs pour en faire un doc Html
function showAsHtmlDoc(string $docid, $data): void {
  if (is_array($data)) {
    foreach ($data as $k => $ssdoc) {
      showAsHtmlDoc($docid, $ssdoc);
    }
  }
  elseif (is_string($data)) {
    showText($docid, $data);
  }
  else {
    echo "<pre>"; print_r($data); echo "</pre>\n";
  }
}

// crée un Doc à partir du docid du document
// retourne null si le document n'existe pas, génère une exception si le doc n'est pas du Yaml
function new_doc(string $docid): ?Doc {
  // S'il existe un pser et qu'il est plus récent que le yaml/php alors renvoie la désérialisation du pser
  $storepath = Store::storepath();
  $filename = __DIR__."/$storepath/$docid";
  //echo "filename=$filename<br>\n";
  if (file_exists("$filename.pser")
      && ((file_exists("$filename.yaml") && (filemtime("$filename.pser") > filemtime("$filename.yaml")))
          || (file_exists("$filename.php") && (filemtime("$filename.pser") > filemtime("$filename.php")))
          || (!file_exists("$filename.yaml") && !file_exists("$filename.php")))) {
      //echo "unserialize($filename.pser)<br>\n";
      return unserialize(@file_get_contents("$filename.pser"));
  }
  // Sinon Si le fichier n'existe pas alors
  if (($text = ydread($docid)) === FALSE) {
    // Si il existe un fichier odt ou pdf alors renvoie le doc correspondant
    foreach (['odt'=> 'OdtDoc', 'pdf'=> 'PdfDoc'] as $ext => $class) {
      if (file_exists("$filename.$ext")) {
        $filename = "$filename.$ext";
        //echo "filename=$filename<br>\n";
        return new $class($filename, $docid);
      }
    }
    // sinon renvoie null
    return null;
  }
  // Donc ici le fichier existe et son contenu est copié dans $text
  // Sinon Si le texte correspond à du code Php alors l'exécute pour obtenir l'objet résultant et le renvoie
  if (strncmp($text,'<?php', 5)==0) {
    //if (!$docid)
      //throw new Exception("Erreur: le paramètre docuid n'est pas défini");
    // teste si le script Php peut être modifié par l'utilisateur courant et marque l'info dans l'environnement
    ydcheckWriteAccessForPhpCode($docid);
    // exécute le script et renvoie son retour qui doit donc être un array Php
    // cght de spec le 5/2/2019 - avant cela devait être un YamlDoc
    $storepath = Store::storepath();
    //echo "exécute ",__DIR__,"/$storepath/$docid.php<br>\n";
    $data = require __DIR__."/$storepath/$docid.php"; // retourne un array Php
    if (!is_array($data))
      throw new Exception("Erreur $docid.php doit retourner un array Php ce qui n'est pas le cas");
  }
  else {
    // Sinon parse le texte dans $data
    try {
      $data = Yaml::parse($text, Yaml::PARSE_DATETIME);
    }
    catch (ParseException $exception) {
      // en cas d'erreur d'analyse Yaml le doc est marqué comme modifiable
      ydsetWriteAccess($store, $docid, 1);
      // et je relance l'exception
      throw $exception;
    }
  }
  // si le doc correspond à un texte alors création d'un BasicYamlDoc avec le texte
  if (!is_array($data))
    $doc = new BasicYamlDoc($text, $docid);
  // Sinon détermine sa classe en fonction du champ $schema
  elseif (!isset($data['$schema'])) { // si pas de $schema c'est un YamlDoc de base
    //echo "Création d'un document BasicYamlDoc<br>\n";
    $doc = new BasicYamlDoc($data, $docid);
  }
  elseif ($data['$schema'] == 'http://json-schema.org/draft-07/schema#') // schema JSON
    $doc = new YdJsonSchema($data, $docid);
  elseif (is_string($data['$schema'])) {
    if (preg_match(YamlDoc::SCHEMAURIPATTERN, $data['$schema'], $matches) && class_exists($matches[1])) {
      $yamlClass = $matches[1];
      //echo "Création d'un document $yamlClass<br>\n";
      $doc = new $yamlClass ($data, $docid);
    }
    else {
      //$schema = $data['$schema'];
      //echo "<b>Erreur: le schema $schema n'est pas défini</b><br>\n";
      //$doc = new BasicYamlDoc($data, $docid);
      echo "Création d'un document AutoDescribed<br>\n";
      $doc = new AutoDescribed($data, $docid); // document auto-décrit
    }
  }
  elseif (is_array($data['$schema'])) {
    // si le schema est un allOf et référence en premier un schema Yaml alors je l'utilise pour typer le document
    if (isset($data['$schema']['allOf'][0]['$ref'])
      && preg_match(YamlDoc::SCHEMAURIPATTERN, $data['$schema']['allOf'][0]['$ref'], $matches)) {
        $yamlClass = $matches[1];
        //echo "Création d'un document $yamlClass<br>\n";
        $doc = new $yamlClass ($data, $docid);
    }
    else {
      //echo "Création d'un document AutoDescribed<br>\n";
      $doc = new AutoDescribed($data, $docid); // document auto-décrit
    }
  }
  else {
    echo "<b>Erreur: le schema n'est pas compris</b><br>\n";
    $doc = new BasicYamlDoc($data, $docid);
  }
  // je profite que le doc est ouvert pour tester s'il est modifiable et stocker l'info en session
  ydsetWriteAccess($docid, $doc->authorizedWriter());
  // si prévu j'écris le .pser
  $doc->writePser();
  return $doc;
}


if (basename(__FILE__) == basename($_SERVER['PHP_SELF'])) { // Menu des tests unitaires 
  echo "<br>---<br>\nTests unitaires:<ul>\n";
  foreach ($unitaryTests as $unitaryTest)
    echo "<li><a href='?test=$unitaryTest'>$unitaryTest</a>\n";
  die("</ul>\nFIN tests unitaires");
}
