<?php
/*PhpDoc:
name: yamldata.inc.php
title: yamldata.inc.php - gestion des données
functions:
doc: doc intégrée en Php
*/
{
$phpDocs['yamldata.inc.php'] = <<<'EOT'
name: yamldata.inc.php
title: yamldata.inc.php - gestion des données
doc: |
  Pour gérer efficacement des tables assez volumineuses, il est préférable d'utiliser des clés d'accès aux
  enregistrements plutôt qu'un numéro d'ordre comme prévu dans le YamlDoc de base.
    La classe YamlData permet de définir des documents contenant une ou plusieurs tables d'enregistrements
  accessibles au travers d'une clé éventuellement composite.
  La clé est utilisée dans la structure Php ; pour une clé composite, plusieurs clés successives Php son utilisées.
  Un document YamlData doit définir à la racine un champ yamlClass avec la valeur YamlData.
  Il peut alors:
    - soit contenir une seule table stockée en Yaml dans le champ data
    - soit contenir une liste de tables stockée dans une structure Yaml
      tables:
        {nomtable}:
          title: titre de la table
          yamlSchema: schema de la table
          data: enregistrements contenus dans la table
  Dans les 2 cas un YamlSchema pour chaque table est recommandé et nécessaire si une clé composite est utilisée.
  Le YamlSchema doit contenir un champ KEYS avec un sous champ ROOT et un sous-sous champ data contenant la liste
  des clés sous la forme d'une chaine avec un nom de champ.
  De plus une version serialisée du doc est enregistrée pour accélérer la lecture des gros documents.
  
journal: |
  9/6/2018:
  - remplacement des méthodes YamlDataTable::yaml() et YamlDataTable::json() par YamlDataTable::php()
  7/6/2018:
  - ajout de YamlData::project()
  - définition des méthodes YamlDataTable::yaml() et YamlDataTable::json()
  3/6/2018:
  - amélioration sérialisation
  1/6/2018:
  - mise en place minimum de la sélection en ajoutant une méthode extract() à YamlDataTable et en l'appellant
    depuis YamlDoc::sextract
  30-31/5/2018:
  - création
EOT;
}

// class correspondant au niveau document
// remplace dans la structure Php l'array correspondant à une table par un objet YamlDataTable
class YamlData extends YamlDoc {
  function __construct($data) {
    $this->data = $data;
    if (isset($this->data['data'])) // le document ne contient qu'une seule table
      $this->data['data'] = new YamlDataTable(
          $this->data['data'],
          isset($this->data['yamlSchema']) ? $this->data['yamlSchema'] : null);
    elseif (isset($this->data['tables'])) { // le document contient une liste de tables
      foreach ($this->data['tables'] as $name => $table) {
        if (isset($table['data']))
          $this->data['tables'][$name]['data'] = new YamlDataTable(
            $table['data'],
            isset($table['yamlSchema']) ? $table['yamlSchema'] : null);
      }
    }
    else
      throw new Exception("Erreur: $_GET[doc] pas un YamlData");
  }
  
  // en plus de l'affichage, si le fichier pser n'existe pas ou n'est pas à jour, il est regénéré
  function show(string $ypath): void {
    //echo "appel de YamlData::show($ypath)<br>\n";
    parent::show($ypath);
    if (!is_file(__DIR__."/docs/$_GET[doc].pser")
     || (filemtime(__DIR__."/docs/$_GET[doc].pser") <= filemtime(__DIR__."/docs/$_GET[doc].yaml"))) {
      file_put_contents(__DIR__."/docs/$_GET[doc].pser", serialize($this));
    }
  }
  
  // fusionne la table en paramètre avec la table du document et renvoie le résultat
  function merge(YamlDataTable $table): YamlDataTable {
    echo "YamlData::merge()<br>\n";
    return $this->data['data']->merge($table);
  }
};


// contenu d'une table
// objet se retouvant à l'intérieur d'un doc
// est créé par YamData lui-même détecté par le champ yamlClass du document
class YamlDataTable implements YamlDocElement {
  protected $yamlSchema; // yamlSchema sous forme d'un array Php ou null
  protected $attrs; // liste des attributs détectés dans la table
  protected $data; // contenu de data sous forme d'un arrray Php
  
  function __construct(array $data, ?array $yamlSchema=null) {
    $this->yamlSchema = $yamlSchema;
    $this->attrs = ['KEY'];
    $this->data = $data;
    if (!$data)
      return;
    foreach ($this->tuples() as $tuple) {
      foreach (array_keys($tuple) as $attr) {
        if (!in_array($attr, $this->attrs))
          $this->attrs[] = $attr;
      }
    }
  }
    
  // transfert les clés dans le tuple sous l'attribut KEY
  // $level est le nbre de niveaux de clés
  static function addkey(int $level, array $data, string $pk='') {
    if ($level == 0) {
      $data['KEY'] = $pk;
      return $data;
    }
    //echo "avant $level: "; print_r($data);
    foreach ($data as $key => $value) {
      $addkey[] = self::addkey($level-1, $value, ($pk?$pk.'|':'').$key);
    }
    //echo "après $level: "; print_r($flat);
    return $addkey;
  }

  // Supprime un niveau de clés
  static function flattenerOne(array $data) {
    foreach ($data as $v1)
      foreach ($v1 as $v2)
        $flat[] = $v2;
    return $flat;
  }

  // Supprime $level-1 niveaux de clés
  static function flattener(int $level, array $data) {
    for($i=1; $i<$level; $i++)
      $data = self::flattenerOne($data);
    return $data;
  }

  // fournit les tuples de la table sous la forme d'une liste en ajoutant un attribut KEY avec la liste des clés
  function tuples() {
    //print_r($this);
    //echo "<tr><td><pre>"; print_r($this->yamlSchema['KEYS']); echo "</pre></td></tr>\n";
    //print_r($this->yamlSchema['KEYS']); die();
    $nbkeys = (isset($this->yamlSchema['KEYS']['ROOT']['data']) ? count($this->yamlSchema['KEYS']['ROOT']['data'])
       : 1);
    $tuples = self::addkey($nbkeys, $this->data);
    if ($nbkeys == 1)
      return $tuples;
    else
      return self::flattener($nbkeys, $tuples);
    //echo "<tr><td><pre>nbre=$nbre</pre></td></tr>\n";
  }
  
  function show(string $prefix): void {
    //print_r($this->data);
    //showListOfTuplesAsTable2($this->data, '');
    echo "<table border=1>\n";
    foreach ($this->attrs as $attr)
      echo "<th>$attr</th>";
    echo "\n";
    foreach ($this->tuples() as $tuple) {
      echo "<tr>";
      foreach ($this->attrs as $attr) {
        if (!isset($tuple[$attr]))
          echo "<td></td>";
        elseif (is_numeric($tuple[$attr]))
          echo "<td align='right'>",$tuple[$attr],"</td>";
        else {
          echo "<td>";
          showDoc($tuple[$attr], "$prefix/$attr");
          echo "</td>";
        }
      }
      echo "</tr>\n";
    }
    echo "</table>\n";
  }
  
  // retourne le fragment défini par path qui est une chaine
  function extract(string $ypath) {
    //echo "appel de YamlDataTable::extract($ypath)<br>\n";
    $elt = YamlDoc::extract_ypath('/', $ypath);
    if (strpos($elt,'=') !== false) {
      $query = explode('=', $elt);
      $data = $this->select($query[0], $query[1]);
    }
    else {
      $data = $this->project($elt);
    }
    $ypath = substr($ypath, strlen($elt)+1);
    if (!$ypath)
      return $data;
    if (is_array($data))
      return YamlDoc::sextract($data, $ypath);
    elseif (is_object($data))
      return $data->extract($ypath);
    else {
      echo "Cas non traité<br>\n";
      //echo "<pre>data = "; print_r($data); echo "</pre>\n";
      return $data;
    }
  }
  
  // selection dans la liste de tuples $data sur $key=$value
  function select(string $key, string $value) {
    //echo "YamlDataTable::select(key=$key, value=$value)<br>\n";
    $result = [];
    foreach ($this->tuples() as $tuple)
      if ($tuple[$key]==$value)
        $result[] = $tuple;
    if (count($result)==0)
      return null;
    elseif (count($result)==1)
      return $result[0];
    else
      return $result;
  }
  
  // projection de $data sur $attrs
  function project(string $attrs) {
    $attrs = YamlDoc::protexplode(',', $attrs);
    //echo "attrs="; print_r($attrs); echo "<br>\n";
    $result = [];
    foreach ($this->tuples() as $tuple) {
      if (count($attrs)==1) {
        $result[] = $tuple[$attrs[0]];
      }
      else {
        $t = [];
        foreach ($attrs as $attr) {
          if (substr($attr,0,1)=='(') {
            $ypath = substr($attr, 1, strlen($attr)-2);
            $sattrs = explode('/', $ypath);
            $sattr = $sattrs[count($sattrs)-1];
            $t[$sattr] = YamlDoc::sextract($tuple, $ypath);
          }
          elseif (isset($tuple[$attr]))
            $t[$attr] = $tuple[$attr];
        }
        $result[] = $t;
      }
    }
    return $result;
  }
  
  // retourne une structure Php
  function php() {
    return $this->data;
  }
  
  // copie les enr. de la table dans la table passée en paramètre et renvoie le résultat
  function merge(YamlDataTable $table): YamlDataTable {
    echo "YamlDataTable::merge()<br>\n";
    //$this->show('');
    //echo "<ul>\n";
    foreach ($this->data as $k => $v) {
      //echo "<li>k=$k<br>\n";
      $table->insert($k, $v);
    }
    //echo "</ul>\n";
    return $table;
  }
  
  // insertion d'un enregistrement dans la table
  function insert($k, $v) {
    foreach (array_keys($v) as $attr) {
      if (!in_array($attr, $this->attrs))
        $this->attrs[] = $attr;
    }
    $this->data[$k] = $v;
  }
};