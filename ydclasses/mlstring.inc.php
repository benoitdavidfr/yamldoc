<?php
/*PhpDoc:
name: mlstring.inc.php
title:  mlstring.inc.php - Multi-lingual string class
doc: |
  voir le code
*/
{ // doc 
$phpDocs['mlstring.inc.php']['file'] = <<<'EOT'
name: mlstring.inc.php
title:  mlstring.inc.php - class MLString définissant des chaines de caractères multi-lingues
journal:
  18/7/2018:
  - améliorations: ajout de getStringsInLang(), renommage php() en asArray()
EOT;
}

{ // doc 
$phpDocs['mlstring.inc.php']['classes']['MLString'] = <<<'EOT'
title: class MLString définissant des chaines de caractères multi-lingues
doc: |
  Un objet correspond à une chaine ou une liste de chaines dans différentes langues
  Outre la liste des langues possibles, prend pour l'initialiser :
  
    - soit une chaine, on considère qu'elle est dans la langue 0
    - soit une liste de chaines, on considère qu'elles sont dans la langue 0
    - soit un dictionnaire langue -> chaine
    - soit un dictionnaire langue -> liste de chaines
    
  Les langues sont soit les codes ISO 639-1 (sur 2 caractères), soit les codes ISO 639-2 (sur 3 caractères).
  Ajout du code 'n' correspondant au neutre (aucune langue).
  La conversion en string utilise s'il existe le paramètre lang, sinon l'ordre des langues défini dans la variable
  statique $default de la classe.
EOT;
}
class MLString implements YamlDocElement {
  static $default = ['fr','fre','en','eng','n'];
  protected $_c; // stockage du contenu comme [ lang => [ label ]]

  function __construct($labels, array $language) {
    if (is_string($labels))
      $this->_c[$language[0]] = [ $labels ];
    elseif (array_keys($labels)[0] === 0) {
      $this->_c[$language[0]] = $labels;
    }
    else {
      foreach ($labels as $lang => $labelLang) {
        if (is_string($labelLang))
          $this->_c[$lang] = [ $labelLang ];
        else
          $this->_c[$lang] = $labelLang;
      }
    }
  }
  
  // retourne une langue particulière
  function __get(string $name) { return isset($this->_c[$name]) ? $this->_c[$name] : null; }
  
  // renvoie la langue à afficher ou ''
  function getLang(): string {
    if (isset($_GET['lang']) && isset($this->_c[$_GET['lang']]))
      return $_GET['lang'];
    else
      foreach (self::$default as $lang)
        if (isset($this->_c[$lang]))
          return $lang;
    return '';
  }
  
  // renvoie la première chaine de la bonne langue
  function __tostring(): string {
    $lang = $this->getLang();
    return $lang ? $this->_c[$lang][0] : '';
  }
  
  // renvoie la liste de chaines dans la bonne langue
  function getInLang(): array {
    $lang = $this->getLang();
    return $lang ? $this->_c[$lang] : '';
  }
  
  // renvoie dans la bonne langue une chaine s'il y en a qu'une sinon la liste des chaines
  function getStringsInLang() {
    if (!($lang = $this->getLang()))
      return '';
    if (count($this->$lang)==1)
      return $this->$lang[0];
    else
      return $this->$lang;
  }
  
  // renvoie le contenu complet comme array Php
  // si pour une langue il n'existe qu'une seule chaine, le résultat est simplifié
  function asArray(): array {
    $result = [];
    foreach ($this->_c as $lang => $labels) {
      if (count($labels)==1)
        $result[$lang] = $labels[0];
      else
        $result[$lang] = $labels;
    }
    return $result;
  }
  
  // renvoie le contenu pour une langue donnée
  function extract(string $ypath) {
    if (preg_match('!^/(.*)$!', $ypath, $matches) && isset($this->_c[$matches[1]]))
      return $this->_c[$matches[1]];
    else
      return null;
  }
  
  // affiche dans la bonne langue la ou les chaines correspondantes 
  function show(string $docuid, string $prefix='') {
    showDoc($docuid, $this->getStringsInLang(), $prefix);
  }
};


if (basename(__FILE__)<>basename($_SERVER['PHP_SELF'])) return;

echo "<!DOCTYPE HTML><html><head><meta charset='UTF-8'><title>mlstring</title></head><body><h2>tests</h2><pre>\n";
foreach (
  [
    "chaine en francais",
    ["2 chaines en francais","deuxième"],
    ['fr'=>"en francais", 'en'=>"in english"],
    ['fr'=>["en francais", "fr2"], 'en'=>["in english", "en2"]],
  ] as $param) {
    $mlstr = new MLString($param, ['fr']);
    print_r($mlstr);
    echo "mlstr: $mlstr\n";
    echo "getInLang="; print_r($mlstr->getInLang());
}
