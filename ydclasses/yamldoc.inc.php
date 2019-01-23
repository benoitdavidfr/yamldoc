<?php
/*PhpDoc:
name: yamldoc.inc.php
title: yamldoc.inc.php - classe abstraite YamlDoc et interface YamlDocElement
functions:
doc: doc intégrée en Php
*/
{
$phpDocs['yamldoc.inc.php']['file'] = <<<'EOT'
name: yamldoc.inc.php
title: yamldoc.inc.php - classes abstraites Doc et YamlDoc et interface YamlDocElement
doc: |
  La classe abstraite Doc correspond à un document affichable ;
  certains documents ne sont pas des documents YamlDoc comme par exemple PdfDoc ou OdtDoc
  La classe abstraite YamlDoc correspond à un document Yaml.
  L'interface YamlDocElement définit l'interface que doit respecter un élément de YamlDoc.
journal:
  15/9/2018:
    - ajout d'une propriété Doc::$_id
    - modification de la signature des méthodes abstraites Doc::__construct(), Doc::show(), Doc::dump(),
      YamlDoc::extractByUri(), YamlDoc::writePser(), YamlDoc::writePserReally()
  28/7/2018:
    - ajout de la classe abstraite Doc
  26/7/2018:
    - correction d'un bug dans YamlDoc::replaceYDEltByArray()
  25/7/2018:
    - ajout fabrication pser pour document php
  19/7/2018:
    - améliorations
  18/7/2018:
    - première version par fork de yd.inc.php
EOT;
}

use Symfony\Component\Yaml\Yaml;

{
$phpDocs['yamldoc.inc.php']['classes']['Doc'] = <<<'EOT'
title: classe abstraite Doc correspondant à un document affichable
doc: |
  La classe abstraite Doc définit:
    - 2 méthodes abstraites que chaque sous-classe doit définir
    - des fonctions génériques utiles aux sous-classes
  En plus de définir les 2 méthodes abstraites, une classe héritant de Doc doit aussi
  soit définir la méthode __get(), soit définir les 6 propriétés suivantes:
    - $authorizedReaders, $authRd, $authorizedWriters, $authWr
    - $yamlPassword  
    - $language  
EOT;
}

abstract class Doc {
  protected $_id;
  // Les méthodes abstraites
  
  // crée un nouveau doc, $yaml est le contenu Yaml externe issu de l'analyseur Yaml
  // $yaml est généralement un array mais peut aussi être du texte
  abstract function __construct($yaml, string $docid);

  // affiche le sous-élément de l'élément défini par $ypath
  abstract function show(string $ypath=''): void;
  
  // fonction dump par défaut, dump le document et non le fragment
  function dump(string $ypath=''): void { var_dump($this); }
  
  // par défaut un document n'est pas un homeCatalog
  function isHomeCatalog() { return false; }
};

{
$phpDocs['yamldoc.inc.php']['classes']['YamlDoc'] = <<<'EOT'
title: classe abstraite YamlDoc correspond à un document Yaml
doc: |
  La classe abstraite YamlDoc définit:
    - 4 méthodes abstraites que chaque sous-classe doit définir
    - des fonctions génériques utiles aux sous-classes
    - des méthodes statiques s'appliquant à des fragments structurés comme array Php
  En plus de définir les 4 méthodes abstraites, une classe héritant de YamlDoc doit aussi
  soit définir la méthode __get(), soit définir les 6 propriétés suivantes:
    - $authorizedReaders, $authRd, $authorizedWriters, $authWr
    - $yamlPassword  
    - $language  
EOT;
}
abstract class YamlDoc extends Doc {
  // Les méthodes abstraites

  // décapsule l'objet et retourne son contenu sous la forme d'un array
  // ce décapsulage ne s'effectue qu'à un seul niveau
  // Permet de maitriser l'ordre des champs
  abstract function asArray();

  // extrait le fragment du document défini par $ypath
  // Renvoie un array ou un objet qui sera ensuite transformé par YamlDoc::replaceYDEltByArray()
  // Utilisé par YamlDoc::yaml() et YamlDoc::json()
  // Evite de construire une structure intermédiaire volumineuse avec asArray()
  abstract function extract(string $ypath);
  
  // Les méthodes concrètes
  
  // extrait le fragment défini par $ypath
  // utilisé pour générer un retour à partir d'un URI
  // Par défaut effectue un extract
  // Doit être remplacé par le traitement adapté à chaque classe de documents
  function extractByUri(string $ypath) {
    $fragment = $this->extract($ypath);
    $fragment = self::replaceYDEltByArray($fragment);
    return $fragment;
  }
  
  // Par défaut aucun .pser n'est produit
  public function writePser(): void { }
  
  // si une classe crée un .pser, elle doit appeler YamlDoc::writePserReally()
  protected function writePserReally(): void {
    $filename = __DIR__.'/../'.Store::storepath().'/'.$this->_id;
    if (!is_file("$filename.pser")
        || (is_file("$filename.yaml") && (filemtime("$filename.pser") <= filemtime("$filename.yaml")))
        || (is_file("$filename.php") && (filemtime("$filename.pser") <= filemtime("$filename.php")))
        || (!is_file("$filename.yaml") && !is_file("$filename.yaml"))) {
      file_put_contents("$filename.pser", serialize($this));
    }
  }
  
  // remplace récursivement dans une structure Php les YamlDocElement par leur représentation array Php
  // Utilise pour cela YamlDoc::asArray() et YamlDocElement::asArray()
  // La valeur retournée est soit un atome Php non objet, soit un objet DateTime, soit un array
  static protected function replaceYDEltByArray($data) {
    if (is_object($data) && (get_class($data)<>'DateTime'))
      $data = $data->asArray();
    if (is_array($data)) {
      $result = [];
      foreach ($data as $key => $value)
        $result[$key] = self::replaceYDEltByArray($value);
      return $result;
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
    //return $text;
    $pattern = '!^( *-)\n +!';
    if (preg_match($pattern, $text, $matches)) {
      $text = preg_replace($pattern, $matches[1].' ', $text, 1);
    }
    $pattern = '!(\n *-)\n +!';
    while (preg_match($pattern, $text, $matches)) {
      $text = preg_replace($pattern, $matches[1].' ', $text, 1);
    }
    return $text;
  }
  
  // retourne le fragment défini par la chaine ypath
  static public function sextract($data, string $ypath) {
    //echo "sextract(ypath=$ypath)<br>\n";
    if (!$ypath || ($ypath=='/'))
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
  // si aucun résultat alors retourne null, sinon si un seul tuple en résultat alors retourne ce tuple
  // sinon retourne la liste des tuples vérifiant le critère
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
  
  // génère le texte Yaml correspondant au fragment défini par ypath
  // améliore la sortie en supprimant les débuts de ligne
  function yaml(string $ypath): string {
    $fragment = $this->extract($ypath);
    return YamlDoc::syaml(self::replaceYDEltByArray($fragment));
  }
  
  // génère le texte JSON à partir de self::extract()
  function json(string $ypath): string {
    $fragment = $this->extract($ypath);
    $fragment = self::replaceYDEltByArray($fragment);
    $fragment = self::replaceDateTimeByString($fragment);
    return json_encode($fragment, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
  }

  // validation de la conformité du document au schéma JSON associé à la classe de documents
  // Si aucun schéma n'est associé, cette méthode n'a pas à être définie pour la classe Php
  // et la validation s'effectue par rapport au schéma YamlDoc
  function checkSchemaConformity(string $ypath): void {
    echo "YamlDoc::checkSchemaConformity(ypath=$ypath)<br>\n";
    try {
      $schema = new JsonSchema(__DIR__.'/yamldoc.schema.yaml');
      $schema->check($this->asArray(), [
        'showWarnings'=> "ok doc conforme au schéma yamldoc<br>\n",
        'showErrors'=> "KO doc NON conforme au schéma yamldoc<br>\n",
      ]);
    } catch (Exception $e) {
      echo "Erreur dans YamlDoc::checkSchemaConformity(ypath=$ypath) : ",$e->getMessage(),"<br>\n";
    }
  }

  // vérification si nécessaire du droit d'accès en consultation ou du mot de passe
  function checkReadAccess(): bool {
    // si le doc a déjà été marqué comme accessible alors retour OK
    if (ydcheckReadAccess($this->_id))
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
        ydsetReadAccess($this->_id);
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
      ydsetReadAccess($this->_id);
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
      $ret = isset($_SESSION['homeCatalog']) && is_array($this->authorizedReaders)
             && in_array($_SESSION['homeCatalog'], $this->authorizedReaders);
    elseif ($this->authRd)
      $ret = isset($_SESSION['homeCatalog']) && is_array($this->authRd)
             && in_array($_SESSION['homeCatalog'], $this->authRd);
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

{
$phpDocs['yamldoc.inc.php']['classes']['YamlDocElement'] = <<<'EOT'
title: Declaration de l'interface 'YamlDocElement'
doc: |
  Tout élément d'un YamlDoc doit être soit:
    - un type Php généré par l'analyseur Yaml y compris des objets de type DateTime
    - un objet d'une classe conforme à l'interface YamlDocElement
  Un YamlDocElement possède les méthodes:
  - extract(string $ypath) // extrait le sous-élément de l'élément défini par $ypath
  - asArray() // décapsule l'objet et retourne son contenu sous la forme d'un array
EOT;
}
interface YamlDocElement {
  // extrait le sous-élément de l'élément défini par $ypath
  // permet de traverser les objets quand on connait son chemin
  public function extract(string $ypath);
  
  // décapsule l'objet et retourne son contenu sous la forme d'un array
  // permet de parcourir tout objet sans savoir a priori ce que l'on cherche
  // est utilisé par YamlDoc::replaceYDEltByArray()
  public function asArray();
  
  // affiche un élément en Html
  // est utilisé par showDoc()
  // pas implémenté avec la même signature par tous !!!
  //public function show(string $docuid, string $prefix='');
};


if (basename(__FILE__)<>basename($_SERVER['PHP_SELF'])) return;

$str = 'code,title,(json-ld/geo),(depts/code,title)';
echo "<pre>";
echo "$str\n";
print_r(YamlDoc::protexplode(',', $str));

