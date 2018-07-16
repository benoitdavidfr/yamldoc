<?php
/*PhpDoc:
name: mlstring.inc.php
title:  mlstring.inc.php - Multi-lingual string class
doc: |
  Correspond à une chaine ou une liste de chaines dans différentes langues
  Outre la liste des langues possibles, prend pour l'initialiser:
    - soit une chaine, on considère qu'elle est dans la langue 0
    - soit une liste de chaines, on considère qu'elles sont dans la langue 0
    - soit un dictionnaire langue -> chaine
    - soit un dictionnaire langue -> liste de chaines
*/
class MLString {
  static $default = ['fr','en','n'];
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
  
  // renvoie le contenu simplifié si pour une langue il n'existe qu'une seule chaine
  function get(): array {
    $result = [];
    foreach ($this->_c as $lang => $labels) {
      if (count($labels)==1)
        $result[$lang] = $labels[0];
      else
        $result[$lang] = $labels;
    }
    return $result;
  }
  
  // renvoie le contenu en array Php ss objet
  function php(): array { return $this->get(); }
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
