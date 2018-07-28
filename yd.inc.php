<?php
/*PhpDoc:
name: yd.inc.php
title: yd.inc.php - fonctions générales pour yamldoc
functions:
doc: <a href='/yamldoc/?action=version&name=yd.inc.php'>doc intégrée en Php</a>
*/
{
$phpDocs['yd.inc.php'] = <<<'EOT'
name: yd.inc.php
title: yd.inc.php - fonctions générales pour yamldoc
doc: |
  Le format externe d'un document est du Yaml.
  Le format interne dépend de chaque document et est généré lors de la création du document.
  Typiquement un document peut créer des objets à la place de certains arrays pour simplifier la définition
  des traitements.
  Le format interne est stocké dans les fichiers .pser
    
journal: |
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
    - si le texte commence par "Content-Type: text/plain\n" alors affichage du texte brut
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
require_once __DIR__.'/../vendor/autoload.php';
require_once __DIR__."/markdown/PHPMarkdownLib1.8.0/Michelf/MarkdownExtra.inc.php";

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

// lecture du fichier de configuration
function config() {
  static $config = null;
  if (!$config) {
    try {
      $config = Yaml::parse(@file_get_contents(__DIR__.'/config.yaml'), Yaml::PARSE_DATETIME);
    }
    catch (ParseException $exception) {
      printf("<b>Analyse YAML erronée: %s</b>", $exception->getMessage());
      echo "<pre>",file_get_contents(__DIR__.'/config.yaml'),"</pre>\n";
      die();
    }
  }
  return $config;
}

// écriture d'un document, prend le store, l'uid et le texte
// s'il s'agit d'un Php l'extension est php, sinon yaml
function ydwrite(string $store, string $uid, string $text) {
  $ext = (strncmp($text,'<?php', 5)==0) ? 'php' : 'yaml';
  $filename = is_dir(__DIR__."/$store/$uid") ? __DIR__."/$store/$uid/index" : __DIR__."/$store/$uid";
  if ($ext == 'php')
    @unlink("$filename.yaml");
  if (file_put_contents("$filename.$ext", $text)===FALSE)
    return FALSE;
  else
    return $ext;
}

// lecture d'un document, prend le store, l'uid et retourne le texte
// cherche dans l'ordre un yaml puis un php puis si c'est un répertoire un fichier index.yaml ou index.php
function ydread(string $store, string $uid) {
  //echo "ydread($uid)<br>\n";
  if (($text = @file_get_contents(__DIR__."/$store/$uid.yaml")) !== false)
    return $text;
  if (($text = @file_get_contents(__DIR__."/$store/$uid.php")) !== false)
    return $text;
  if (is_dir(__DIR__."/$store/$uid")) {
    //echo "$uid est un répertoire<br>\n";
    if (($text = @file_get_contents(__DIR__."/$store/$uid/index.yaml")) !== false)
      return $text;
    if (($text = @file_get_contents(__DIR__."/$store/$uid/index.php")) !== false)
      return $text;
  }
  return false;
}

// retourne l'extension d'un document
function ydext(string $store, string $uid): string {
  //echo "ydext(string $uid)";
  foreach (['pser','yaml','php'] as $ext)
    if (is_file(__DIR__."/$store/$uid.pser"))
      return $ext;
  return '';
}

// suppression d'un document, prend son store, uid
function yddelete(string $store, string $uid) {
  @unlink(__DIR__."/$store/$uid.pser");
  return (@unlink(__DIR__."/$store/$uid.yaml") or @unlink(__DIR__."/$store/$uid.php"));
}

// teste si le docuid a été marqué comme accessible en lecture par ydsetReadAccess()
function ydcheckReadAccess(string $store, string $docuid): bool {
  return (isset($_SESSION['checkedReadAccess']) && in_array("$store/$docuid", $_SESSION['checkedReadAccess']));
}

// marque le docuid comme accessible en lecture
function ydsetReadAccess(string $store, string $docuid): void {
  //echo "ydsetReadAccess($docuid)<br>\n";
  if (!ydcheckReadAccess($store, $docuid))
    $_SESSION['checkedReadAccess'][] = "$store/$docuid";
}

// teste si le script Php peut être modifié par l'utilisateur courant et marque l'info dans l'environnement
function ydcheckWriteAccessForPhpCode(string $store, string $docuid) {
  $ydcheckWriteAccessForPhpCode = 1;
  $authorizedWriters = require "$store/$docuid.php";
  //echo "authorizedWriters="; print_r($authorizedWriters);
  $right = (isset($_SESSION['homeCatalog'])
            && is_array($authorizedWriters)
            && in_array($_SESSION['homeCatalog'], $authorizedWriters));
  ydsetWriteAccess($store, $docuid, $right);
};

// marque le docuid comme accessible ou non en écriture
function ydsetWriteAccess(string $store, string $docuid, bool $right): void {
  //echo "ydsetWriteAccess($docuid, ",($right ? 1 : 0),")<br>\n";
  $_SESSION['checkedWriteAccess']["$store/$docuid"] = ($right ? 1 : 0);
}

// teste si le docuid a été marqué comme accessible en écriture
// renvoie 1 pour autorisé, 0 pour interdit et -1 pour indéfini
function ydcheckWriteAccess(string $store, string $docuid): int {
  return isset($_SESSION['checkedWriteAccess']["$store/$docuid"]) ?
    $_SESSION['checkedWriteAccess']["$store/$docuid"]
    : -1;
}

// !!! je ne vois pas l'intérêt de ce mécanisme de lock !!!
function ydlock(string $store, string $docuid): bool {
  //echo "ydlock($uid)<br>\n";
  if (file_exists(__DIR__."/$store/$docuid.lock"))
    return false;
  file_put_contents(__DIR__."/$store/$docuid.lock", 'lock');
  $_SESSION['locks'][] = "$store/$docuid";
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

// Teste si $data est un texte, cad ssi il contient au moins un \n en dehors de la dernière position 
function is_text($data) {
  return is_string($data) && (strpos($data, "\n")!==FALSE) && (strpos($data, "\n") < strlen($data)-1);
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

function str2html(string $str): string { return str_replace(['&','<','>'],['&amp;','&lt;','&gt;'], $str); }

// Je considère qu'une String est une chaine ne contenant pas de \n intermédiaire, sinon c'est un texte
function showString(string $docuid, $str) {
  // une URL est replacée par une référence avec l'URL comme label
  if (is_string($str) && preg_match('!^(https?://[^ ]*)!', $str, $matches)) {
    $href = $matches[1];
    $after = substr($str, strlen($matches[0]));
    echo "<a href='$href' target=_blank>$href</a>$after\n";
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
      $href = "?doc=$docuid&amp;ypath=".urlencode($ypath).(isset($_GET['lang']) ? "&lang=$_GET[lang]": '');
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
  elseif (is_object($str) && (get_class($str)=='DateTime')) {
    echo $str->format('Y-m-d H:i:s');
  }
  else
    echo str2html("$str\n");
}

// affichage d'une liste d'atomes, ou list(list) ... comme <ul><li>
function showListOfAtoms(string $docuid, array $list, string $prefix, int $level=0) {
  if ($level==0)
    echo "<ul style='margin:0; padding:0px; list-style-position: inside;'>\n";
  else
    echo "<ul style='margin:0px;'>\n";
  foreach ($list as $i => $elt) {
    echo "<li>";
    if (is_array($elt))
      showListOfAtoms($docuid, $elt, "$prefix/$i", $level+1);
    else
      showString($docuid, $elt);
  }
  echo "</ul>\n";
}

// affichage d'une liste de tuples comme table Html
function showListOfTuplesAsTable(string $docuid, array $table, string $prefix) {
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
        showDoc($docuid, $tuple[$key], "$prefix/$key");
        echo "</td>";
      }
    }
    echo "</tr>\n";
  }
  echo "</table>\n";
}

// affichage d'un array comme table Html
function showArrayAsTable(string $docuid, array $data, string $prefix) {
  echo "<table border=1>\n";
  foreach ($data as $key => $value) {
    echo "<tr><td>$key</td><td>\n";
    showDoc($docuid, $value, "$prefix/$key");
    echo "</td></tr>\n";
  }
  echo "</table>\n";
}

// aiguille l'affichage en fonction du type du paramètre
function showDoc(string $docuid, $data, string $prefix=''): void {
  if (is_object($data)) {
    if (get_class($data)=='DateTime')
      echo $data->format('Y-m-d H:i:s');
    else
      $data->show($docuid, $prefix);
  }
  elseif (is_listOfAtoms($data))
    showListOfAtoms($docuid, $data, $prefix);
  elseif (is_listOfTuples($data))
    showListOfTuplesAsTable($docuid, $data, $prefix);
  elseif (is_array($data))
    showArrayAsTable($docuid, $data, $prefix);
  elseif (is_null($data))
    echo 'null';
  // un texte derrière un > sera représenté comme chaine et pas comme texte 
  elseif (is_text($data)) {
    //echo "pos=",strpos($data, "\n"),"<br>\n";
    //echo "len=",strlen($data),"<br>\n";
    // représentation brute des textes avec \n
    if (preg_match('!^Content-Type: text/plain[\r\n]+!', $data, $matches)) {
      $data = substr($data, strlen($matches[0]));
      echo "<pre>",str_replace(['&','<','>'],['&amp;','&lt;','&gt;'], $data),"</pre>\n";
    }
    elseif (preg_match('!^<html>[\r\n]+!', $data, $matches)) {
      echo substr($data, strlen($matches[0]));
    }
    else {
      // les liens Markdown internes au document sont remplacés par un lien indiquant le document courant
      // et éventuellement la langue
      $pattern = '!\(\?(ypath=([^)]*))\)!';
      while (preg_match($pattern, $data, $matches)) {
        $ypath = $matches[2];
        $replacement = "(?doc=$docuid&amp;ypath=$ypath".(isset($_GET['lang']) ? "&amp;lang=$_GET[lang]": '').")";
        $data = preg_replace($pattern, $replacement, $data, 1);
      }
      echo MarkdownExtra::defaultTransform($data);
    }
  }
  else
    showString($docuid, $data);
}

// crée un Doc à partir du store et du docuid du document
// retourne null si le document n'existe pas
// génère une exception si le doc n'est pas du Yaml
function new_doc(string $store, string $docuid): ?Doc {
  // S'il existe un pser et qu'il est plus récent que le yaml/php alors renvoie la désérialisation du pser
  $filename = __DIR__."/$store/$docuid";
  //echo "filename=$filename<br>\n";
  if (file_exists("$filename.pser")
      && ((file_exists("$filename.yaml") && (filemtime("$filename.pser") > filemtime("$filename.yaml")))
          || (file_exists("$filename.php") && (filemtime("$filename.pser") > filemtime("$filename.php"))))) {
      //echo "unserialize()<br>\n";
      return unserialize(@file_get_contents(__DIR__."/$store/$docuid.pser"));
  }
  // Sinon Si le fichier n'existe pas renvoie null
  if (($text = ydread($store, $docuid)) === FALSE) {
    foreach (['odt'=> 'OdtDoc', 'pdf'=> 'PdfDoc'] as $ext => $class) {
      if (file_exists("$filename.$ext")) {
        $filename = "$filename.$ext";
        //echo "filename=$filename<br>\n";
        return new $class($filename);
      }
    }
    return null;
  }
  // Sinon Si le texte correspond à du code Php alors l'exécute pour obtenir l'objet résultant et le renvoie
  if (strncmp($text,'<?php', 5)==0) {
    //if (!$docuid)
      //throw new Exception("Erreur: le paramètre docuid n'est pas défini");
    // teste si le script Php peut être modifié par l'utilisateur courant et marque l'info dans l'environnement
    ydcheckWriteAccessForPhpCode($store, $docuid);
    // exécute le script et renvoie son retour qui doit donc être un YamlDoc ou null
    $doc = require "$store/$docuid.php";
    $doc->writePser($store, $docuid);
    return $doc;
  }
  // Sinon parse le texte dans $data
  try {
    $data = Yaml::parse($text, Yaml::PARSE_DATETIME);
  }
  catch (ParseException $exception) {
    // en cas d'erreur d'analyse Yaml le doc est marqué comme modifiable
    ydsetWriteAccess($store, string, 1);
    // et je relance l'exception
    throw $exception;
  }
  // si le doc correspond à un texte alors création d'un YamlDoc avec le texte
  if (!is_array($data))
    $doc = new BasicYamlDoc($text);
  // Sinon Si c'est un array alors détermine sa classe en fonction du champ yamlClass
  elseif (!isset($data['yamlClass'])) {
    // si pas de YamlClass c'est un YamlDoc de base
    $doc = new BasicYamlDoc($data);
  }
  else {
    $yamlClass = $data['yamlClass'];
    if (class_exists($yamlClass))
      $doc = new $yamlClass ($data);
    else {
      echo "<b>Erreur: la classe $yamlClass n'est pas définie</b><br>\n";
      $doc = new BasicYamlDoc($data);
    }
  }
  // je profite que le doc est ouvert pour tester s'il est modifiable et stocker l'info en session
  ydsetWriteAccess($store, $docuid, $doc->authorizedWriter());
  // si prévu j'écris le .pser
  $doc->writePser($store, $docuid);
  return $doc;
}

