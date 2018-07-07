<?php
/*PhpDoc:
name: datamodel.inc.php
title: gestion d'un modèle de données sous la forme d'un document Yaml
doc: |
  voir le code
  
  tests:
    Paquet géologie:
      http://127.0.0.1/yamldoc/index.php?doc=inspire-datamodel&ypath=/domains/package-ge
      http://docinspire.eu/get.php?uri=http%3A%2F%2Furi.docinspire.eu%2Feutext%2Fpackage%2Fge
    SOT Forage:
      http://127.0.0.1/yamldoc/index.php?doc=inspire-datamodel&ypath=/objectTypes/ge:Borehole
      http://docinspire.eu/get.php?uri=http%3A%2F%2Furi.docinspire.eu%2Feutext%2Fspatialobjecttype%2Fge%3ABorehole
    CL But du forage:
      http://127.0.0.1/yamldoc/index.php?doc=inspire-datamodel&ypath=/schemes/BoreholePurposeValue
      http://docinspire.eu/get.php?uri=http%3A%2F%2Furi.docinspire.eu%2Feutext%2Fcodelist%2FBoreholePurposeValue

  A FAIRE:
    - vérifier l'intégrité des références internes

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
  4-6/7/2018:
  - création
*/
{
$phpDocs['datamodel.inc.php'] = <<<EOT
name: datamodel.inc.php
title: gestion d'un modèle de données
doc: |
  Un document modèle de données est une extension d'un document YamlSkos, il contient en outre:

    - un champ types qui définit les types qui comportent chacun les champs suivants:
        - domain qui liste les domaines auquel le type appartient,
        - kind
        - attribut
journal: |
  4/7/2018:
  - création
EOT;
}

class DataModel extends YamlSkos {
  protected $objectTypes; // dictionnaire des types
  
  function __construct(array &$yaml) {
    //echo "DataModel::__construct()<br>\n";
    parent::__construct($yaml);
    foreach ($this->domains as $domid => $domain)
      $this->domains[$domid] = new DMDomain($domain->asArray(), $this->language);
    if (!isset($yaml['objectTypes']))
      throw new Exception("Erreur: champ objectTypes absent dans la création DataModel");
    $this->objectTypes = [];
    foreach ($yaml['objectTypes'] as $id => $objectType)
      $this->objectTypes[$id] = new ObjectType($objectType, $this->language);
    unset($yaml['objectTypes']);
    $this->_c = $yaml;
    // remplit le lien domain -> objectType à partir du lien inverse
    ObjectType::fillObjectTypeChildren($this->objectTypes, $this->domains);
  }
  
  function show(string $ypath): void {
    try {
      parent::show($ypath);
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
          showDoc($this->objectTypes[$id]->extract($field));
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
      foreach (['domains','schemes','concepts','objecttypes'] as $field) {
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
};

// Domain adapté pour Data Model
class DMDomain extends Domain {
  function showDomainTree(array $domains, array $schemes) {
    //echo "DMDomain::showDomainTree()<br>\n";
    if ($this->narrower) {
      $children = [];
      foreach ($this->narrower as $narrower) {
        $children[$narrower] = suppAccents((string)$domains[$narrower]);
      }
      asort($children);
      echo "<ul>\n";
      foreach (array_keys($children) as $narrower) {
        if (!$domains[$narrower]->schemeChildren and !$domains[$narrower]->objectTypeChildren)
          echo "<li>$domains[$narrower]</li>\n";
        else
          echo "<li><a href='?doc=$_GET[doc]&amp;ypath=/domains/$narrower'>$domains[$narrower]</a></li>\n";
        $domains[$narrower]->showDomainTree($domains, $schemes);
      }
      echo "</ul>\n";
    }
  }
  
  function addObjectTypeChild(string $otid) { $this->_c['objectTypeChildren'][] = $otid; }
  
  function show(DataModel $datamodel) {
    //echo "<pre>DMDomain::show(), this="; print_r($this); echo "</pre>\n";
    echo "<h2>$this</h2>\n";
    if ($this->schemeChildren) {
      echo "<h3>Listes de codes et énumérations</h3><ul>\n";
      foreach ($this->schemeChildren as $sid) {
        echo "<li><a href='?doc=$_GET[doc]&amp;ypath=/schemes/$sid'>",$datamodel->schemes[$sid],"</a></li>\n";
      }
      echo "</ul>\n";
    }
    if ($this->objectTypeChildren) {
      echo "<h3>Listes des types</h3><ul>\n";
      foreach ($this->objectTypeChildren as $otid) {
        echo "<li><a href='?doc=$_GET[doc]&amp;ypath=/objectTypes/$otid'>",$datamodel->objectTypes[$otid],"</a></li>\n";
      }
      echo "</ul>\n";
    }
  }
};

class ObjectType extends Elt {
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
    //echo "<pre>ObjectType::show() on "; print_r($this); echo "</pre>\n";
    $this->showInYaml();
  }
};
