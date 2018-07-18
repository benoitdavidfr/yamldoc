<?php
/*PhpDoc:
name: yamldoc.inc.php
title: yamldoc.inc.php - classe abstraite YamlDoc et interface YamlDocElement
functions:
doc: doc intégrée en Php
*/
{
$phpDocs['yamldoc.inc.php'] = <<<'EOT'
name: yamldoc.inc.php
title: yamldoc.inc.php - classe abstraite YamlDoc et interface YamlDocElement
doc: |
  La classe abstraite YamlDoc définit:
    - 2 fonctions abstraites que chaque sous-classe doit définir
    - des fonctions génériques utiles aux sous-classes
    - des méthodes statiques s'appliquant à des fragments structurés comme array Php
journal: |
  18/7/2018:
  - première version par fork de yd.inc.php
EOT;
}

use Symfony\Component\Yaml\Yaml;

// classe abstraite YamlDoc des documents
// Les méthodes utilisent les propriétés abstraites authorizedReader, authRd, authorizedWriters et authWr
abstract class YamlDoc {
  // extrait le sous-élément de l'élément défini par $ypath
  abstract function extract(string $ypath);
  
  // décapsule l'objet et retourne son contenu sous la forme d'un array
  abstract function asArray();
  
  // par défaut un document n'est pas un homeCatalog
  function isHomeCatalog() { return false; }
  
  // Par défaut aucun .pser n'est produit
  public function writePser(string $store, string $docuid): void { }
  
  // si une classe crée un .pser, elle doit appeler YamlDoc::writePserReally()
  protected function writePserReally(string $store, string $docuid): void {
    if (!is_file(__DIR__."/$store/$docuid.pser")
     || (filemtime(__DIR__."/$store/$docuid.pser") <= filemtime(__DIR__."/$store/$docuid.yaml"))) {
      file_put_contents(__DIR__."/$store/$docuid.pser", serialize($this));
    }
  }
  
  // remplace dans une structure Php les YamlDocElement par leur représentation array Php
  // Utilise YamlDocElement::asArray()
  static protected function replaceYDEltByArray($data) {
    if (is_object($data) && (get_class($data)<>'DateTime'))
      return $data->asArray();
    elseif (is_array($data)) {
      $ret = [];
      foreach ($data as $key => $value)
        $ret[$key] = self::replaceYDEltByArray($value);
      return $ret;
    }
    else
      return $data;
  }
  
  // remplace récursivement les objets DateTime par une représentation string
  static protected function replaceDateTimeByString($data) {
    if (is_object($data) && (get_class($data)=='DateTime')) {
      return $data->format(DateTime::ATOM);
    }
    elseif (is_array($data)) {
      $ret = [];
      foreach ($data as $key => $value)
        $ret[$key] = self::replaceDateTimeByString($value);
      return $ret;
    }
    else
      return $data;
  }
  
  // améliore la sortie de Yaml::dump()
  static public function syaml($data): string {
    $text = Yaml::dump($data, 999, 2);
    return $text;
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
  
  // retourne le fragment défini par la chaine ypath
  static public function sextract($data, string $ypath) {
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
    elseif (isset($data[$elt]))
      $data = $data[$elt];
    elseif (preg_match('!^sort\(([^)]+)\)$!', $elt, $matches))
      $data = self::sort($data, $matches[1]);
    else
      $data = self::project($data, $elt);
    if (!$ypath) {
      //print_r($data);
      return $data;
    }
    if (is_array($data))
      return self::sextract($data, $ypath);
    elseif (is_object($data))
      return $data->extract($ypath);
    elseif (is_null($data))
      return null;
    else {
      echo "Cas non traité ",__FILE__," ligne ",__LINE__,"<br>\n";
      //echo "<pre>data = "; print_r($data); echo "</pre>\n";
      return $data;
    }
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
  
  // selection dans la liste de tuples $data sur $key=$value
  static private function select(array $data, string $key, string $value) {
    //echo "select(data, key=$key, value=$value)<br>\n";
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
  static private function project(array $data, string $keys) {
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
      return isset($data[$keys[0]]) ? $data[$keys[0]] : null;
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
  static private function sort(array $data, string $keys) {
    global $keys_for_sort;
    $keys_for_sort = explode(',', $keys);
    usort($data, 'cmp');
    return $data;
  }
  
  // nest de $data sur $keys
  static private function nest(array $data, array $keys, string $nestkey) {
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
  function checkReadAccess(string $store, string $docuid): bool {
    // si le doc a déjà été marqué comme accessible alors retour OK
    if (ydcheckReadAccess($store, $docuid))
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
        ydsetReadAccess($store, $docuid);
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
      ydsetReadAccess($store, $docuid);
      return true;
    }
    else
      return false;
  }
  
  // test du droit en lecture indépendamment d'un éventuel mot de passe
  // utilise la propriété abstraite authorizedReaders ou authRd
  function authorizedReader(): bool {
    //print_r($this);
    if ($this->authorizedReaders)
      $ret = isset($_SESSION['homeCatalog']) && in_array($_SESSION['homeCatalog'], $this->authorizedReaders);
    elseif ($this->authRd)
      $ret = isset($_SESSION['homeCatalog']) && in_array($_SESSION['homeCatalog'], $this->authRd);
    else
      $ret = true;
    //echo "authorizedReader=$ret<br>\n";
    return $ret;
  }
  
  // test du droit en écriture indépendamment d'un éventuel mot de passe
  // utilise la propriété abstraite authorizedWriters ou authWr
  function authorizedWriter(): bool {
    if (!$this->authorizedReader()) {
      //echo "authorizedWriter false car !reader<br>\n";
      return false;
    }
    if (!isset($_SESSION['homeCatalog']) || ($_SESSION['homeCatalog']=='default'))
      $ret = false;
    elseif ($this->authorizedWriters)
      $ret = in_array($_SESSION['homeCatalog'], $this->authorizedWriters);
    elseif ($this->authWr)
      $ret = in_array($_SESSION['homeCatalog'], $this->authWr);
    else
      $ret = true;
    //echo "authorizedWriter=$ret<br>\n";
    return $ret;
  }
};

// Declaration de l'interface 'YamlDocElement'
// Tout élément d'un YamlDoc doit être soit:
//  - un type Php généré par l'analyseur Yaml y compris des objets de type DateTime
//  - un objet d'une classe conforme à l'interface YamlDocElement
interface YamlDocElement {
  // extrait le sous-élément de l'élément défini par $ypath
  // permet de traverser les objets quand on connait son chemin
  public function extract(string $ypath);
  
  // décapsule l'objet et retourne son contenu sous la forme d'un array
  // permet de parcourir tout objet sans savoir a priori ce que l'on cherche
  // est utilisé par replaceYDEltByArray()
  public function asArray();
};
