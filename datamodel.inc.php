<?php
/*PhpDoc:
name: datamodel.inc.php
title: gestion d'un modèle de données sous la forme d'un document Yaml
doc: |
  voir le code

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
  4-8/7/2018:
  - création
*/
{
$phpDocs['datamodel.inc.php'] = <<<EOT
name: datamodel.inc.php
title: gestion d'un modèle de données
doc: |
  Un document modèle de données est une extension d'un document YamlSkos,
  il contient en outre un champ objectTypes qui définit les types qui comportent chacun les champs suivants:
    - type liste les types peuvent être 'spatialobjecttype', 'datatype', 'uniontype', 'externaltype', 'unknowntype'
    - domain liste les domaines auqxuels le type appartient (sauf pour les unknowntype),
    - prefLabel fournit le nom du type en multi-lingue ou en neutre
    - definition fournit la definition du type en multi-lingue (sauf externaltype et unkowntype)
    - subtypeOf? liste éventuellement les super-types
    - property? contient éventuellement 'abstracttype' ou 'associationclass'
    - source liste de ressources desquelles dérive l'élément, chaque ressource est identifié par une clé et peut
      être définie dans différentes langues, les mots-clés suivants sont utilisés:
      - eutext pour identifier les extraits de la directive Inspire
    - attributes liste les attributs
    - relations liste les relations
  Les attributs et relations sont identfiés par un nom et comporte les champs suivants:
  - definition fournit la definition multi-lingue de l'attribut ou relation
  - type sous la forme [ typedetype => nomdutype ]
    les typedetype sont 'spatialobjecttype', 'datatype', 'uniontype', 'externaltype', 'unknowntype'
  - voidability vaut 'voidable' ou 'notVoidable'
journal: |
  4-8/7/2018:
  - création
EOT;
}
use Michelf\MarkdownExtra;

class DataModel extends YamlSkos {
  static $keyTranslations = [
    'attributes'=> ['fr'=>"Attributs", 'en'=>"Attributes"],
    'relations'=> ['fr'=>"Relations", 'en'=>"Relations"],
    'subtypeOf'=> ['fr'=>"Sous-type de", 'en'=>"Subtype of"],
    'xxx'=> ['fr'=>"xxx", 'en'=>"yyy"],
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
      echo "objid=$objid<br>\n";
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
  
  function show(string $docid, string $ypath): void {
    try {
      parent::show($docid, $ypath);
    }
    catch (Exception $exception) {
      if (preg_match('!^/objectTypes/([^/]*)(/(.*))?$!', $ypath, $matches)) {
        $id = $matches[1];
        if (!isset($matches[3])) {
          //echo "<pre>objectType $id: "; print_r($this->objectTypes[$id]); echo "</pre>\n";
          $this->objectTypes[$id]->show($this);
        }
        else {
          $field = $matches[3];
          showDoc($_GET['doc'], $this->objectTypes[$id]->extract($field));
        }
      }
      else
        echo $exception->getMessage(),"<br>\n";
    }
  }
  
  // renvoie un array récursif du fragment défini par ypath
  function extract(string $ypath) {
    if (!$ypath) {
      $result = $this->_c;
      foreach (['domains','schemes','concepts','objectTypes'] as $field) {
        foreach($this->$field as $id => $elt)
          $result[$field][$id] = $elt->asArray();
      }
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
  
  // vérification de l'intégrité du document
  function checkIntegrity() {
    parent::checkIntegrity();
    foreach ($this->objectTypes as $id => $objectType)
      $objectType->checkIntegrity($id, $this->domains, $this->objectTypes);
    echo "methode DataModel::checkIntegrity() non implémentée<br>\n";
  }
};

// classe du domainScheme adaptée 
class DMDomainScheme extends DomainScheme {
    
  // Affiche l'arbre des domaines avec un lien vers chaque micro-thésaurus
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
  
// Domain adapté pour Data Model
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

class ObjectType extends Elt {
  function __construct(array $yaml, array $language) {
    parent::__construct($yaml, $language);
    //echo "ObjectType::__contruct($this)<br>\n";
    foreach (['attributes','relations'] as $prop) {
      if ($this->$prop) {
        foreach ($this->$prop as $name => $elt) {
          foreach (['label','definition'] as $field) {
            if (isset($elt[$field]))
              $this->_c[$prop][$name][$field] = new MLString($elt[$field], $language);
          }
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
  
  // affichage de liens comme une ligne dans une table
  function showLinksInTable(string $key, YamlSkos $skos) {
    if ($this->$key) {
      $nblinks = count($this->$key);
      echo "<tr><td>",DataModel::keyTranslate($key),"</td><td>";
      if ($nblinks > 1)
        echo "<ul style='margin-top:0;'>\n";
      foreach ($this->$key as $lid) {
        //echo "lid=$lid<br>\n";
        $langp = isset($_GET['lang']) ? "&amp;lang=$_GET[lang]" : '';
        if ($nblinks > 1)
          echo "<li>";
        if (isset($skos->concepts[$lid]))
          echo "<a href='?doc=$_GET[doc]&amp;ypath=/concepts/$lid$langp'>",
               $skos->concepts[$lid],"</a>\n";
        elseif (isset($skos->schemes[$lid]))
          echo "<a href='?doc=$_GET[doc]&amp;ypath=/schemes/$lid$langp'>",
               $skos->schemes[$lid],"</a>\n";
        elseif (isset($skos->domains[$lid]))
          echo "<a href='?doc=$_GET[doc]&amp;ypath=/domains/$lid$langp'>",
               $skos->domains[$lid],"</a>\n";
        elseif (isset($skos->objectTypes[$lid]))
          echo "<a href='?doc=$_GET[doc]&amp;ypath=/objectTypes/$lid$langp'>",
               $skos->objectTypes[$lid],"</a>\n";
        else
          echo "lien $lid trouvé ni dans concepts, ni dans schemes, ni dans domains, ni dans objectTypes\n";
      }
      if ($nblinks > 1)
        echo "</ul>";
      echo "</td></tr>\n";
    }
  }
  
  function show(DataModel $datamodel) {
    $type = $this->type ? ' ('.implode(',',$this->type).')' : '';
    echo "<h2>$this$type</h2>\n";
    echo "<table border=1>\n";
    $this->showLinksInTable('subtypeOf', $datamodel);
    foreach (['definition','scopeNote','historyNote','example'] as $key) {
      $this->showTextsInTable($key);
    }
    $this->showLinksInTable('domain', $datamodel);
    echo "</table>\n";
    //$this->showInYaml();
    foreach (['attributes','relations'] as $prop) {
      if ($this->$prop) {
        echo "<h3>",DataModel::keyTranslate($prop),"</h3>\n";
        echo "<table border=1><th>name</th><th>label</th><th>definition</th><th>type</th><th>multiplicity</th><th>voidability</th>\n";
        foreach ($this->$prop as $name => $elt) {
          echo "<tr><td>$name</td>";
          echo "<td>",isset($elt['label']) ? str2html($elt['label']->__toString()) : '',"</td>\n";
          echo "<td>",isset($elt['definition']) ? MarkdownExtra::defaultTransform($elt['definition']->__toString()) : '',"</td>\n";
          $toftype = array_keys($elt['type'])[0];
          $type = $elt['type'][$toftype];
          $url = "?doc=$_GET[doc]&amp;ypath="
                  .urlencode(in_array($toftype,['codelist','enum']) ? '/schemes/' : '/objectTypes/').$type
                  .(isset($_GET['lang']) ? "&amp;lang=$_GET[lang]" : '');
          echo "<td><a href='$url'>$type</a> ($toftype)</td>";
          echo "<td>",isset($elt['multiplicity']) ? $elt['multiplicity'] : '', "</td>\n";
          echo "<td>",isset($elt['voidability']) ? $elt['voidability'] : '', "</td>\n";
          echo "</tr>\n";
        }
        echo "</table>\n";
      }
    }
    //$this->showInYaml();
    //echo "<pre>"; print_r($this); echo "</pre>\n";
  }
  
  function asArray(): array {
    $result = parent::asArray();
    foreach (['attributes','relations'] as $key) {
      if ($this->$key) {
        foreach ($this->$key as $name => $elt) {
          $result[$key][$name]['definition'] = $elt['definition']->get();
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
