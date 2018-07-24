<?php
/*PhpDoc:
name: datamodel.inc.php
title: gestion d'un modèle de données sous la forme d'un document Yaml
doc: |
  voir le code
*/
{ // doc 
$phpDocs['datamodel.inc.php']['file'] = <<<EOT
name: datamodel.inc.php
title: datamodel.inc.php - gestion d'un modèle de données comme extension d'un YamlSkos
doc: |
  Un document modèle de données est une extension d'un document YamlSkos,
  il contient en outre un champ objectTypes qui est un dictionnaire de types d'objets de la classe ObjectType

  Version ultérieure:
    - gestion:
      - codelist.(requirement|technicalguide|refdoc|docextract) -> source
      - codelist.extensibility
      - codelist.requirement -> scopeNote
      - scheme.exactMatch
      - objecttype.broadMatch
    - lien vers la source du règlement: source ?
      ex: http://docinspire.eu/eutext/?CELEX=02010R1089&annex=IV&section=20.3.3.14.&language=es
journal: |
  17/7/2018:
    - ajout de la classe Attribute
  4-8/7/2018:
    - création
EOT;
}

class DataModel extends YamlSkos {
  static $keyTranslations = [
    'attributes'=> ['fr'=>"Attributs", 'en'=>"Attributes"],
    'relations'=> ['fr'=>"Relations", 'en'=>"Relations"],
    'subtypeOf'=> ['fr'=>"Sous-type de", 'en'=>"Subtype of"],
    'xxx'=> ['fr'=>"xxx", 'en'=>"yyy"],
  ];
  protected $objectTypes; // dictionnaire des types
  
  function __construct(array &$yaml) {
    echo "DataModel::__construct()<br>\n";
    parent::__construct($yaml);
    $this->domainScheme = new DMDomainScheme($this->domainScheme->asArray(), $this->language);
    foreach ($this->domains as $domid => $domain)
      $this->domains[$domid] = new DMDomain($domain->asArray(), $this->language);
    if (!isset($yaml['objectTypes']))
      throw new Exception("Erreur: champ objectTypes absent dans la création DataModel");
    //print_r($yaml['objectTypes']);
    foreach ($yaml['objectTypes'] as $objid => $objectType) {
      //echo "objid=$objid<br>\n";
      $this->objectTypes[$objid] = new ObjectType($objectType, $this->language);
    }
    unset($this->_c['objectTypes']);
    unset($yaml['objectTypes']);
    // remplit le lien domain -> objectType à partir du lien inverse
    ObjectType::fillObjectTypeChildren($this->objectTypes, $this->domains);
  }
  
  // traduction dans la bonne langue des noms des champs
  static function keyTranslate(string $key): string {
    if (!isset(self::$keyTranslations[$key]))
      return parent::keyTranslate($key);
    if (isset($_GET['lang']) && isset(self::$keyTranslations[$key][$_GET['lang']]))
      return self::$keyTranslations[$key][$_GET['lang']];
    else
      foreach (['fr','en','n'] as $lang)
        if (isset(self::$keyTranslations[$key][$lang]))
          return self::$keyTranslations[$key][$lang];
    return "<b>Traduction non définie pour $key</b>";
  }
  
  // affiche le document ou un de ses fragments
  function show(string $docid, string $ypath): void {
    try {
      parent::show($docid, $ypath);
    }
    catch (Exception $exception) {
      if (preg_match('!^/objectTypes$!', $ypath, $matches)) {
        echo "<h1>Liste des types d'objets</h1>\n";
        foreach ($this->objectTypes as $id => $objectType) {
          $objectType->show($this);
        }
      }
      elseif (preg_match('!^/objectTypes/([^/]*)$!', $ypath, $matches)) {
        $id = $matches[1];
        //echo "<pre>objectType $id: "; print_r($this->objectTypes[$id]); echo "</pre>\n";
        $this->objectTypes[$id]->show($this);
      }
      elseif (preg_match('!^/objectTypes/([^/]*)/(.*)$!', $ypath, $matches)) {
        $id = $matches[1];
        $field = $matches[2];
        //echo "<pre>"; print_r($this->objectTypes[$id]->extract($field)); echo "</pre>";
        showDoc($_GET['doc'], $this->objectTypes[$id]->extract($field));
      }
      else
        echo $exception->getMessage(),"<br>\n";
    }
  }

  // renvoie un array récursif du fragment défini par ypath
  function extract(string $ypath) {
    //echo "DataModel::extract($ypath)\n";
    if (!$ypath) {
      $result = parent::extract('');
      foreach($this->objectTypes as $id => $elt)
        $result['objectTypes'][$id] = $elt->asArray();
      return $result;
    }
    else {
      try {
        return parent::extract($ypath);
      }
      catch (Exception $exception) {
        if (preg_match('!^/objectTypes(/([^/]*))?(/(.*))?$!', $ypath, $matches)) {
          if (!isset($matches[2])) {
            foreach ($this->objectTypes as $id => $elt) {
              $result[$id] = $elt->asArray();
            return $result;
            }
          }
          elseif (!isset($matches[4])) {
            return $this->objectTypes[$matches[2]]->asArray();
          }
          else {
            $id = $matches[2];
            $field = $matches[4];
            return $this->objectTypes[$id]->extract($field);
          }
        }
        else
          echo $exception->getMessage(),"<br>\n";
      }
    }
  }
  
  // dump le document ou un de ses fragments
  function dump(string $ypath): void {
    try {
      parent::dump($ypath);
    }
    catch (Exception $exception) {
      if (preg_match('!^/objectTypes$!', $ypath))
        var_dump($this->objectTypes);
      elseif (preg_match('!^/objectTypes/([^/]*)$!', $ypath, $matches))
        var_dump($this->objectTypes[$matches[1]]);
      elseif (preg_match('!^/objectTypes/([^/]*)(/.*)$!', $ypath, $matches))
        $this->objectTypes[$matches[1]]->dump($matches[2]);
      else
        echo $exception->getMessage(),"<br>\n";
    }
  }

  // vérification de l'intégrité du document
  function checkIntegrity() {
    parent::checkIntegrity();
    foreach ($this->objectTypes as $id => $objectType)
      $objectType->checkIntegrity($id, $this->domains, $this->objectTypes);
    echo "methode DataModel::checkIntegrity() non implémentée<br>\n";
  }
};

// classe du domainScheme adaptée pour DataModel
class DMDomainScheme extends DomainScheme {
  // Affiche l'arbre des domaines avec un lien vers chaque scheme
  function show(array $domains, array $schemes) {
    //echo "DMDomainScheme::showDomainTree($this)<br>\n";
    echo "<h2>$this</h2><ul>\n";
    //echo "<pre>domainScheme="; print_r($this); echo "</pre>\n";
    foreach ($this->hasTopConcept as $domid) {
      $domains[$domid]->showDomainTree($domid, $domains, $schemes);
    }
    echo "</ul>\n";
  }
};
  
// Domain adapté pour DataModel
class DMDomain extends Domain {
  function showDomainTree(string $id, array $domains, array $schemes) {
    //echo "DMDomain::showDomainTree()<br>\n";
    $langp = (isset($_GET['lang']) ? "&amp;lang=$_GET[lang]" : '');
    if (!$this->schemeChildren && !$this->objectTypeChildren)
      echo "<li>$this</li>\n";
    else
      echo "<li><a href='?doc=$_GET[doc]&amp;ypath=/domains/$id$langp'>$this</a></li>\n";
    if ($this->narrower) {
      $children = [];
      foreach ($this->narrower as $narrower) {
        $children[$narrower] = suppAccents((string)$domains[$narrower]);
      }
      asort($children);
      echo "<ul>\n";
      foreach (array_keys($children) as $narrower) {
        $domains[$narrower]->showDomainTree($narrower, $domains, $schemes);
      }
      echo "</ul>\n";
    }
  }
  
  function addObjectTypeChild(string $otid) { $this->_c['objectTypeChildren'][] = $otid; }
  
  // affiche le domaine
  function show(DataModel $datamodel) {
    //echo "<pre>DMDomain::show(), this="; print_r($this); echo "</pre>\n";
    echo "<h2>$this</h2>\n";
    $langp = (isset($_GET['lang']) ? "&amp;lang=$_GET[lang]" : '');
    if ($this->schemeChildren) {
      echo "<h3>Listes de codes et énumérations</h3><ul>\n";
      foreach ($this->schemeChildren as $sid) {
        echo "<li><a href='?doc=$_GET[doc]&amp;ypath=".urlencode("/schemes/$sid")."$langp'>",
             $datamodel->schemes[$sid],"</a></li>\n";
      }
      echo "</ul>\n";
    }
    if ($this->objectTypeChildren) {
      echo "<h3>Listes des types</h3><ul>\n";
      foreach ($this->objectTypeChildren as $otid) {
        echo "<li><a href='?doc=$_GET[doc]&amp;ypath=".urlencode("/objectTypes/$otid")."$langp'>",
             $datamodel->objectTypes[$otid],"</a></li>\n";
      }
      echo "</ul>\n";
    }
  }
};

{ // doc 
$phpDocs['datamodel.inc.php']['classes']['ObjectType'] = <<<EOT
name: class ObjectType
title: gestion d'un ObjectType
doc: |
  Un ObjectType définit un type qui comporte les champs suivants:
    - type liste les natures de type qui peuvent être:
      'spatialobjecttype', 'datatype', 'uniontype', 'externaltype', 'unknowntype'
    - domain liste les domaines auxquels le type appartient (sauf pour les unknowntype),
    - prefLabel fournit l'étiquette du type en multi-lingue ou en neutre
    - definition fournit la definition du type en multi-lingue (sauf externaltype et unkowntype)
    - note, scopeNote, editorialNote, changeNote, historyNote et example peuvent être utilisées
    - subtypeOf? liste éventuellement les super-types:
      - soit comme identifiant d'un type
      - soit comme URI définissant un type
    - property? contient éventuellement 'abstracttype' ou 'associationclass'
    - source liste de ressources desquelles dérive l'élément, chaque ressource est identifié par une clé et peut
      être définie dans différentes langues, les mots-clés suivants sont utilisés:
      - eutext pour identifier les extraits de la directive Inspire
    - attributes est le dictionnaire des attributs, objets de la classe Attribute
    - relations est le dictionnaire des relations, objets de la classe Attribute
EOT;
}

class ObjectType extends SkosElt {
  static $strFields = ['label'];
  static $textFields = ['definition','note','scopeNote','editorialNote','changeNote','historyNote','example'];
  static $linkFields = ['subtypeOf'=> 'objectTypes', 'domain'=> 'domains'];
  
  function __construct(array $yaml, array $language) {
    parent::__construct($yaml, $language);
    //echo "ObjectType::__contruct($this)<br>\n";
    foreach (['attributes','relations'] as $prop) {
      if ($this->$prop) {
        foreach ($this->$prop as $name => $elt) {
          $this->_c[$prop][$name] = new Attribute($elt, $language);
        }
      }
    }
  }
  
  // remplit le lien domain -> scheme à partir du lien inverse
  static function fillObjectTypeChildren(array $objectTypes, array $domains) {
    foreach ($objectTypes as $otid => $objectType) {
      if ($objectType->domain) {
        foreach ($objectType->domain as $domid) {
          $domains[$domid]->addObjectTypeChild($otid);
        }
      }
    }
  }
    
  function show(DataModel $datamodel) {
    $type = $this->type ? ' ('.implode(',',$this->type).')' : '';
    echo "<h2>$this$type</h2>\n";
    echo "<table border=1>\n";
    foreach (self::$linkFields as $linkField => $linkTarget) {
      $this->showLinksInTable($linkField, $linkTarget, $datamodel);
    }
    foreach (self::$textFields as $textField) {
      $this->showTextsInTable($textField);
    }
    echo "</table>\n";
    //$this->showInYaml();
    foreach (['attributes','relations'] as $prop) {
      if ($this->$prop) {
        echo "<h3>",DataModel::keyTranslate($prop),"</h3>\n";
        echo "<table border=1><th>",
             implode('</th><th>',['name','label','definition','n','type','multiplicity','voidability']),
             "</th>\n";
        foreach ($this->$prop as $name => $elt) {
          $elt->showAsRowOfTable($prop, $name);
        }
        echo "</table>\n";
      }
    }
    //$this->showInYaml();
    //echo "<pre>"; print_r($this); echo "</pre>\n";
  }
  
  // dump un fragment d'un ObjectType
  function dump(string $ypath): void {
    if (preg_match('!^/(attributes|relations)$!', $ypath, $matches)) {
      $prop = $matches[1];
      var_dump($this->$prop);
    }
    elseif (preg_match('!^/(attributes|relations)/([^/]*)$!', $ypath, $matches)) {
      $prop = $matches[1];
      $name = $matches[2];
      var_dump($this->$prop[$name]);
    }
    else
      echo "Erreur dans ObjectType::dump($ypath): ypath non reconnu<br>\n";
  }

  function asArray(): array {
    //print_r($this);
    $result = parent::asArray();
    foreach (['attributes','relations'] as $prop) {
      if ($this->$prop) {
        foreach ($this->$prop as $name => $elt) {
          $result[$prop][$name] = $elt->asArray();
        }
      }
    }
    return $result;
  }
  
  function checkIntegrity(string $id, array $domains, array $objecttypes): void {
    // type liste les types peuvent être 'spatialobjecttype', 'datatype', 'uniontype', 'externaltype', 'unknowntype'
    if (!$this->type)
      echo "ObjectType $id sans type<br>\n";
    else
      foreach ($this->type as $type)
        if (!in_array($type,['spatialobjecttype', 'datatype', 'uniontype', 'externaltype', 'unknowntype']))
          echo "ObjectType $id type $type hors liste<br>\n";
    // domain liste les domaines auqxuels le type appartient (sauf pour les unknowntype),
    if (!in_array('unknowntype', $this->type)) {
      if (!$this->domain)
        echo implode(',',$this->type)," $id sans domain<br>\n";
      else
        foreach ($this->domain as $d)
          if (!isset($domains[$d]))
            echo implode(',',$this->type)," $sid domain absent de domains<br>\n";
    }
    // prefLabel fournit le nom du type en multi-lingue ou en neutre
    if (!$this->prefLabel)
      echo "ObjectType $id sans prefLabel<br>\n";
    // definition fournit la definition du type en multi-lingue (sauf externaltype et unkowntype)
    if (!in_array('externaltype', $this->type) && !in_array('unknowntype', $this->type)) {
      if (!$this->definition)
        echo implode(',',$this->type)," $id sans definition<br>\n";
    }
    // property? contient éventuellement 'abstracttype' ou 'associationclass'
    if ($this->property)
      foreach ($this->property as $property)
        if (!in_array($property,['abstracttype', 'associationclass']))
          echo implode(',',$this->type)," $id property $property hors liste<br>\n";
    // subtypeOf? liste les super-types
    if ($this->subtypeOf)
      foreach ($this->subtypeOf as $subtypeOf)
        if (!isset($objecttypes[$subtypeOf]))
          echo implode(',',$this->type)," $id subtypeOf $subtypeOf absent de objecttypes<br>\n";
  }
};

{ // doc
$phpDocs['datamodel.inc.php']['classes']['Attribute'] = <<<EOT
name: class Attribute
title: gestion d'un attribut ou relation d'un ObjectType
doc: |
  Un attribut ou une relation comporte les champs suivants:
  - label fournit l'étiquette multi-lingue de l'attribut ou relation
  - definition fournit la definition multi-lingue de l'attribut ou relation
  - note, scopeNote, editorialNote, changeNote, historyNote et example peuvent être utilisées
  - type sous la forme [ typedetype => nomdutype ]
    les typedetype sont 'spatialobjecttype', 'datatype', 'uniontype', 'externaltype', 'unknowntype'
  - multiplicity peut valoir 1, '0..*', '1..*' ou ne pas être défini
  - voidability peut valoir 'voidable' ou 'notVoidable' ou ne pas être défini
journal: |
  17/7/2018:
  - création
EOT;
}

class Attribute extends SkosElt {
  static $strFields = ['label'];
  static $textFields = ['definition','note','scopeNote','editorialNote','changeNote','historyNote','example'];
  
  function __construct(array $yaml, array $language) {
    parent::__construct($yaml, $language);
    //echo "Attribute::__contruct($this)<br>\n";
    foreach (array_merge(self::$strFields,self::$textFields) as $field) {
      if (isset($yaml[$field]))
        $this->_c[$field] = new MLString($yaml[$field], $language);
    }
  }

  function __tostring(): string { return $this->label->__toString(); }
  
  // affiche l'attribut ou la relation comme une ligne d'une table
  // ayant comme headers: ['name','label','definition','n','type','multiplicity','voidability']
  function showAsRowOfTable(string $prop, string $name) {
    echo "<tr><td>$name</td>";
    echo "<td>", $this->label ? str2html($this->label->__toString()) : '',"</td>\n";
    echo "<td>";
    if ($this->definition) {
      showDoc($_GET['doc'], $this->definition->__toString());
    }
    echo "</td>\n";
    echo "<td>";
    if ($this->note || $this->scopeNote || $this->editorialNote || $this->historyNote || $this->example) {
          $url = "?doc=$_GET[doc]&amp;ypath="
                  .urlencode("$_GET[ypath]/$prop/$name")
                  .(isset($_GET['lang']) ? "&amp;lang=$_GET[lang]" : '');
          echo "<a href='$url'>n</a>";
    }
    echo "</td>\n";
    $toftype = array_keys($this->type)[0];
    $type = $this->type[$toftype];
    $url = "?doc=$_GET[doc]&amp;ypath="
            .urlencode(in_array($toftype,['codelist','enum']) ? '/schemes/' : '/objectTypes/').$type
            .(isset($_GET['lang']) ? "&amp;lang=$_GET[lang]" : '');
    echo "<td><a href='$url'>$type</a> ($toftype)</td>";
    echo "<td>", $this->multiplicity ? $this->multiplicity : '', "</td>\n";
    echo "<td>", $this->voidability ? $this->voidability : '', "</td>\n";
    echo "</tr>\n";
  }
  
  // affiche l'attribut ou la relation comme une table
  function show() {
    echo "<h2>$this</h2>\n";
    echo "<table border=1>\n";
    foreach (self::$textFields as $textField) {
      if ($this->$textField) {
        echo "<tr><td>$textField</td><td>";
        showDoc($_GET['doc'], $this->$textField->getStringsInLang());
        echo "</td></tr>\n";
      }
    }
    $toftype = array_keys($this->type)[0];
    $type = $this->type[$toftype];
    $url = "?doc=$_GET[doc]&amp;ypath="
            .urlencode(in_array($toftype,['codelist','enum']) ? '/schemes/' : '/objectTypes/').$type
            .(isset($_GET['lang']) ? "&amp;lang=$_GET[lang]" : '');
    echo "<tr><td>type</td><td><a href='$url'>$type</a> ($toftype)</td></tr>";
    if ($this->multiplicity)
      echo "<tr><td>multiplicity</td><td>",$this->multiplicity,"</td></tr>\n";
    if ($this->voidability)
      echo "<tr><td>voidability</td><td>",$this->voidability,"</td></tr>\n";
    echo "</table>\n";
  }
};
