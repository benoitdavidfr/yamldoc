<?php
/*PhpDoc:
name: dumbdoc.inc.php
title: dumbdoc.inc.php - classe DumbDoc
doc: doc intégrée en Php
*/
{ // doc 
$phpDocs['dumbdoc.inc.php']['file'] = <<<'EOT'
name: dumbdoc.inc.php
title: dumbdoc.inc.php - définition des classes DumbDoc et DumbDocP
doc: |
  Classes minimums pour tester la définition de la classe YamlDoc.
journal:
  19/7/2018:
  - création
EOT;
}

{ // doc 
$phpDocs['dumbdoc.inc.php']['classes']['DumbDoc'] = <<<'EOT'
title: classe de test de la définition de la classe YamlDoc
doc: |
  La classe DumbDoc implémente uniquement les méthodes abstraites de YamlDoc plus la méthode __get().
  Elle ne devrait pas créer d'ereur dans son utilisation.
  La classe recopie le contenu Yaml dans le champ $_c
  
  En pratique la classe DumbDoc est identique à la classe BasicYamlDoc.
EOT;
}
class DumbDoc extends YamlDoc {
  protected $_c;
  
  // crée un nouveau doc, $yaml est le contenu Yaml externe issu de l'analyseur Yaml, cela peut aussi être du texte
  function __construct($yaml, string $docid) { $this->_c = $yaml; $this->_id = $docid; }
  
  // affiche le sous-élément de l'élément défini par $ypath
  function show(string $ypath=''): void { showDoc($this->_id, YamlDoc::sextract($this->_c, $ypath)); }
  
  // extrait le sous-élément de l'élément défini par $ypath
  function extract(string $ypath) { return YamlDoc::sextract($this->_c, $ypath); }
  
  // décapsule l'objet et retourne son contenu sous la forme d'un array
  function asArray() { return $this->_c; }
  
  // permet d'accéder aux champs du document comme si c'était un champ de la classe
  function __get(string $name) { return isset($this->_c[$name]) ? $this->data[$name] : null; }  
};

{ // doc 
$phpDocs['dumbdoc.inc.php']['classes']['DumbDocP'] = <<<'EOT'
title: classe de test de la définition de la classe YamlDoc
doc: |
  La classe DumbDocP implémente uniquement les méthodes abstraites de YamlDoc et PAS la méthode __get().
  Pour ne pas générer d'erreur elle doit définir les 6 propriétés utilisées dans les méthodes de YamlDoc.
  En ayant pour ces propriétés une valeur nulle, cela simule une absence de ces propriétés dans les documents.
EOT;
}
class DumbDocP extends YamlDoc {
  protected $_c;
  //public $authorizedReaders=['benoit'], $authorizedWriters=['benoit'];
  public $authorizedReaders, $authRd, $authorizedWriters, $authWr;
  public $yamlPassword;
  public $language;
  
  // crée un nouveau doc, $yaml est le contenu Yaml externe issu de l'analyseur Yaml, cela peut aussi être du texte
  function __construct($yaml, string $docid) { $this->_c = $yaml; $this->_id = $docid; }
  
  // affiche le sous-élément de l'élément défini par $ypath
  function show(string $ypath=''): void { showDoc($this->_id, YamlDoc::sextract($this->_c, $ypath)); }
  
  // extrait le sous-élément de l'élément défini par $ypath
  function extract(string $ypath) { return YamlDoc::sextract($this->_c, $ypath); }
  
  // décapsule l'objet et retourne son contenu sous la forme d'un array
  function asArray() { return $this->_c; }
};
