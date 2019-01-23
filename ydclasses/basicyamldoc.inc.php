<?php
/*PhpDoc:
name: basicyamldoc.inc.php
title: yamldoc.inc.php - classe BasicYamlDoc
functions:
doc: doc intégrée en Php
*/
{
$phpDocs['basicyamldoc.inc.php'] = <<<'EOT'
name: basicyamldoc.inc.php
title: basicyamldoc.inc.php - classe BasicYamlDoc
doc: |
journal:
  18/7/2018:
  - première version par fork de yd.inc.php
  - chgt de nom de la classe
  - transfert des métodes génériques dans YamlDoc
EOT;
}

// classe YamlDoc de base et par défaut
class BasicYamlDoc extends YamlDoc {
  protected $data; // contenu du doc sous forme d'un array Php ou d'un scalaire
  
  function __construct($data, string $docid) { $this->data = $data; $this->_id = $docid; }
  
  // permet d'accéder aux champs du document comme si c'était un champ de la classe
  function __get(string $name) {
    return isset($this->data[$name]) ? $this->data[$name] : null;
  }
  
  // décapsule l'objet et retourne son contenu sous la forme d'un array
  function asArray() { return $this->data; }
  
  // retourne le fragment défini par path qui est une chaine
  function extract(string $ypath) {
    return YamlDoc::sextract($this->data, $ypath);
  }
    
  // affiche le doc ou le fragment si ypath est non vide
  function show(string $ypath=''): void {
    //echo "<pre>show(ypath=$ypath) data="; print_r($this->data); echo "</pre>\n";
    showDoc($this->_id, YamlDoc::sextract($this->data, $ypath));
  }
};
