<?php
/*PhpDoc:
name: yamlskos.inc.php
title: gestion d'un thésaurus Skos organisé en micro-thésaurus
doc: |
  voir le code
includes:
  - ../../markdown/markdown/PHPMarkdownLib1.8.0/Michelf/MarkdownExtra.inc.php
  - mlstring.inc.php
*/
{ // doc 
$phpDocs['yamlskos.inc.php']['file'] = <<<EOT
name: yamlskos.inc.php
title: yamlskos.inc.php - gestion d'un thésaurus Skos organisé en micro-thésaurus
doc: |
  La structuration d'un thésaurus Skos est inspirée de celle utilisée pour EuroVoc.
  Elle a été étendue pour gérer les listes de codes et énumérations du règlement interopérabilité Inspire.
  
  Un YamlSkos définit un ensemble de concepts Skos organisés en micro-thésaurus.
  Chaque micro-thésaurus est défini comme un ConceptScheme Skos et organisé par domaines.
  Chaque domaine est défini comme concept d'un ConceptScheme particulier.
  Ces domaines permettent un affichage hiérarchique des micro-thésaurus.
  
  Ce fichier définit les classes YamlSkos, SkosElt, DomainScheme, Domain, Scheme et Concept
journal: |
  18/7/2018:
  - adaptation à la restructuration des classes
    la classe YamlSkos hérite de YamlDoc ; SkosElt implemente YamlDocElement
  8/7/2018:
  - utilisation de la classe MLString pour gérer les chaines multi-lingues
  4/7/2018:
  - possibilité d'une arborescence des domaines
  27-29/6/2018:
  - création
EOT;
}
require_once __DIR__.'/../vendor/autoload.php';
require_once __DIR__."/../../markdown/markdown/PHPMarkdownLib1.8.0/Michelf/MarkdownExtra.inc.php";
require_once __DIR__.'/mlstring.inc.php';

use Symfony\Component\Yaml\Yaml;
use Michelf\MarkdownExtra;

// suppression des accents et passage en minuscule pour le tri
function suppAccents(string $str): string {
  return strtolower(str_replace(['é','É','Î'],['e','e','i'], $str));
}

{ // doc 
$phpDocs['yamlskos.inc.php']['classes']['YamlSkos'] = <<<EOT
name: class YamlSkos
title: gestion d'un thésaurus Skos organisé en micro-thésaurus
doc: |
  La classe YamlSkos hérite de la classe abstraite YamlDoc.  
  Un document YamlSkos comprend:
  
    - des champs de métadonnées DublinCore dont au moins:
      - title: le titre du thésaurus
      - language: la ou les langues utilisées
    - un champ domainScheme qui contient le Scheme des domaines qui est un objet de la classe DomainScheme
      l'objet domainScheme comporte le champ suivant:
        - hasTopConcept qui liste les identifiants des domaines de premier niveau
    - un champ domains qui contient le dictionnaire des domaines ;
      chaque domaine est défini comme un concept Skos, identifié par une clé
      et objet de la classe Domain, il contient au moins les champs:
        - prefLabel qui porte une étiquette mono ou multi-lingue,
      Les domaines qui ne sont pas de premier niveau doivent définir un champ broader définissant un concept plus
      générique.
    - un champ schemes qui contient le dictionnaire des micro-thésaurus ;
      chacun défini comme scheme Skos, identifié par une clé et objet de la classe Scheme
    - un champ concepts qui contient le dictionnaire des concepts ;
      chacun identifié par une clé et objet de la classe Concept
EOT;
}
class YamlSkos extends YamlDoc {
  // traduction des champs utilisés en français et en anglais pour l'affichage
  static $keyTranslations = [
    'domain'=> ['fr'=>"Domaine", 'en'=> "Domain"],
    'hasPart'=> ['fr'=>"Est composé de", 'en'=> "Has part"],
    'isPartOf'=> ['fr'=>"Est une partie de", 'en'=> "Is part of"],
    'content'=> ['fr'=>"Contenu", 'en'=>"Content"],
    'inScheme'=> ['fr'=>"Schéma de l'élément", 'en'=>"In scheme"],
    'hasTopConcept'=> ['fr'=>"Elements de premier niveau", 'en'=>"Top concepts"],
    'topConceptOf'=> ['fr'=>"Elément de premier niveau de", 'en'=>"Top concept of"],
    'prefLabel'=> ['fr'=>"Forme lexicale préférentielle", 'en'=>"Prefered label"],
    'altLabel'=> ['fr'=> "Formes lexicales alternatives", 'en'=>"Alternative labels"],
    'hiddenLabel'=> ['fr'=>"Formes cachées", 'en'=>"Hidden labels"],
    'definition'=> ['fr'=>"Définition", 'en'=> "Definition"],
    'note'=> ['fr'=>"Note", 'en'=>"Note"],
    'scopeNote'=> ['fr'=>"Note d'application ", 'en'=>"Scope note"],
    'historyNote'=> ['fr'=>"Note historique", 'en'=>"History note"],
    'editorialNote'=> ['fr'=>"Note éditoriale", 'en'=>"Editorial note"], 
    'example'=> ['fr'=>"Exemple", 'en'=>"Example"],
    'broader'=> ['fr'=>"Concepts génériques", 'en'=>"Broader"],
    'narrower'=> ['fr'=>"Concepts spécifiques", 'en'=>"Narrower"],
    'related'=> ['fr'=>"Concepts associés", 'en'=>"Related"],
    'xxx'=> ['fr'=>"xxx", 'en'=>"yyy"],
  ];
  protected $_c; // contient les champs qui n'ont pas été transférés dans les champs ci-dessous
  protected $title; // titre comme MLString
  protected $alternative=null; // titre alternatif comme MLString
  protected $language; // liste des langues
  protected $domainScheme; // thésaurus des domaines
  protected $domains; // dictionnaire des domaines décrits comme concepts Skos
  protected $schemes; // dictionnaire des micro-thésaurus
  protected $concepts; // dictionnaire des concepts
  
  function __construct($yaml, string $docid) {
    $this->_id = $docid;
    if (!is_array($yaml))
      throw new Exception("Erreur dans YamlSkos::__construct() : le paramètre doit être un array");
    unset($yaml['yamlClass']);
    if (!isset($yaml['language']))
      throw new Exception("Erreur: champ language absent dans la création YamlSkos");
    $this->language = is_string($yaml['language']) ? [ $yaml['language'] ] : $yaml['language'];
    unset($yaml['language']);
    if (!isset($yaml['title']))
      throw new Exception("Erreur: champ title absent dans la création YamlSkos");
    $this->title = new MLString($yaml['title'], $this->language);
    unset($yaml['title']);
    if (isset($yaml['alternative'])) {
      $this->alternative = new MLString($yaml['alternative'], $this->language);
      unset($yaml['alternative']);
    }
    if (!isset($yaml['domainScheme']))
      throw new Exception("Erreur: champ domainScheme absent dans la création YamlSkos");
    $this->domainScheme = new DomainScheme($yaml['domainScheme'], $this->language);
    unset($yaml['domainScheme']);
    if (!isset($yaml['domains']))
      throw new Exception("Erreur: champ domains absent dans la création YamlSkos");
    $this->domains = [];
    foreach ($yaml['domains'] as $id => $domain)
      $this->domains[$id] = new Domain($domain, $this->language);
    unset($yaml['domains']);
    if (!isset($yaml['schemes']))
      throw new Exception("Erreur: champ schemes absent dans la création YamlSkos");
    $this->schemes = [];
    foreach ($yaml['schemes'] as $id => $scheme)
      $this->schemes[$id] = new Scheme($scheme, $this->language);
    unset($yaml['schemes']);
    if (!isset($yaml['concepts']))
      throw new Exception("Erreur: champ concepts absent dans la création YamlSkos");
    $this->concepts = [];
    foreach ($yaml['concepts'] as $id => $concept)
      $this->concepts[$id] = new Concept($concept, $this->language);
    unset($yaml['concepts']);
    $this->_c = $yaml;
    // remplit le lien domain -> scheme à partir du lien inverse
    Scheme::fillSchemeChildren($this->schemes, $this->domains);
    // Si les micro-thésaurus ne référencent pas de topConcept alors ils sont déduits des concepts
    Scheme::fillTopConcepts($this);
    // Si les domaines ne référencent pas de narrower alors ils sont déduits des broader
    Concept::fillNarrowers($this->domains);
    // Si les concepts ne comportent pas de narrower alors ils sont déduits des broader
    Concept::fillNarrowers($this->concepts);
  }
  
  // un .pser est généré automatiquement à chaque mise à jour du .yaml
  function writePser(): void { YamlDoc::writePserReally(); }
  
  // traduction dans la bonne langue des noms des champs
  static function keyTranslate(string $key): string {
    if (!isset(self::$keyTranslations[$key]))
      return $key;
    if (isset($_GET['lang']) && isset(self::$keyTranslations[$key][$_GET['lang']]))
      return self::$keyTranslations[$key][$_GET['lang']];
    else
      foreach (['fr','en','n'] as $lang)
        if (isset(self::$keyTranslations[$key][$lang]))
          return self::$keyTranslations[$key][$lang];
    return "<b>Traduction non définie pour $key</b>";
  }
  
  function __get(string $name) {
    return isset($this->$name) ? $this->$name : (isset($this->_c[$name]) ? $this->_c[$name] : null);
  }
  
  // affichage du thésaurus ou d'un de ses fragments
  function show(string $ypath=''): void {
    $docid = $this->_id;
    //echo "<pre> yamlSkos ="; print_r($this); echo "</pre>\n";
    if (!$ypath || ($ypath == '/')) {
      showDoc($_GET['doc'], $this->_c);
      $this->domainScheme->show($this->domains, $this->schemes);
    }
    elseif (preg_match('!^/([^/]*)$!', $ypath, $matches) && isset($this->_c[$matches[1]]))
      showDoc($_GET['doc'], $this->_c[$matches[1]]);
    elseif (preg_match('!^/(schemes|concepts|domains)(/([^/]*))?(/(.*))?$!', $ypath, $matches)) {
      //print_r($matches);
      $what = $matches[1];
      // affichage de tous les schemes ou tous les concepts
      if (!isset($matches[3])) {
        if ($what=='schemes')
          //$this->domainScheme->show($this->domains, $this->schemes);
          Scheme::showSchemes($this->schemes);
        elseif ($what=='concepts')
          Concept::showConcepts($this->concepts);
        elseif ($what=='domains')
          $this->domainScheme->show($this->domains, $this->schemes);
      }
      else {
        $id = $matches[3];
        // affichage d'un scheme ou d'un concept particulier
        if (!isset($matches[5])) {
          if ($what=='schemes')
            $this->showScheme($id, null);
          elseif ($what=='concepts')
            $this->showConcept($id, null);
          elseif ($what=='domains')
            $this->showDomain($id, null);
        }
        else {
          // affichage d'un fragment d'un scheme ou d'un concept particulier
          $field = $matches[5];
          if ($what=='schemes')
            showDoc($_GET['doc'], $this->schemes[$id]->extract($field));
          else
            showDoc($_GET['doc'], $this->concepts[$id]->extract($field));
        }
      }
    }
    else {
      throw new Exception("ypath=$ypath non reconnu");
    }
  }
  
  // affiche un domaine
  function showDomain(string $domid, ?string $format) {
    //echo "YamlSkos::showScheme(sid=$sid)<br>\n";
    if ($this->domains[$domid]) {
      $this->domains[$domid]->show($this);
    }
    else {
      echo "Erreur: domaine $domid inconnu<br>\n";
    }
  }
  
  // affiche un micro-thésaurus
  function showScheme(string $sid, ?string $format) {
    //echo "YamlSkos::showScheme(sid=$sid)<br>\n";
    if ($this->schemes[$sid]) {
      $this->schemes[$sid]->show($this->concepts, $this);
    }
    else {
      echo "Erreur: scheme $sid inconnu<br>\n";
    }
  }
  
  // affiche un concept
  function showConcept(string $cid, ?string $format) {
    //echo "YamlSkos::showConcept(cid=$cid)<br>\n";
    if (!$this->concepts[$cid])
      echo "Erreur: concept $cid inconnu<br>\n";
    elseif (!$format)
      $this->concepts[$cid]->show($this);
    else
      $this->concepts[$cid]->showInYaml($this);
  }
  
  // méthode dump
  function dump(string $ypath=''): void {
    if (!$ypath) {
      var_dump($this);
    }
    elseif (preg_match('!^/([^/]*)$!', $ypath, $matches) && isset($this->_c[$matches[1]]))
      var_dump($this->_c[$matches[1]]);
    elseif (preg_match('!^/(schemes|concepts|domains)(/([^/]*))?(/(.*))?$!', $ypath, $matches)) {
      //print_r($matches);
      $what = $matches[1];
      // affichage de tous les schemes ou tous les concepts
      if (!isset($matches[3])) {
        var_dump($this->$what);
      }
      else {
        $id = $matches[3];
        // affichage d'un scheme, un concept ou un domaine particulier
        if (!isset($matches[5])) {
          var_dump($this->$what[$id]);
        }
        else {
          // affichage d'un fragment d'un scheme ou d'un concept particulier
          $field = $matches[5];
          $this->$what[$id]->dump($field);
        }
      }
    }
    else {
      throw new Exception("ypath=$ypath non reconnu");
    }
  }

  // décapsule l'objet et retourne son contenu sous la forme d'un array
  // L'objet principal est de définir l'ordre des champs
  function asArray() {
    $result = [
      'title'=> $this->title,
    ];
    if ($this->alternative)
      $result['alternative'] = $this->alternative;
    $result['language'] = $this->language;
    $result = array_merge($result, $this->_c);
    foreach (['domainScheme','domains','schemes','concepts'] as $field)
      $result[$field] = $this->$field;
    return $result;
  }
  
  // extrait le fragment du document défini par $ypath ; Renvoie un array ou un objet
  function extract(string $ypath) {
    //echo "YamlSkos::extract($ypath)<br>\n";
    if (!$ypath  || ($ypath == '/'))
      return $this;
    elseif (preg_match('!^/(domainScheme|domains|schemes|concepts)$!', $ypath, $matches)) {
      return $this->{$matches[1]};
    }
    elseif (preg_match('!^/(domains|schemes|concepts)/([^/]*)$!', $ypath, $matches)) {
      return $this->{$matches[1]}[$matches[2]];
    }
    elseif (preg_match('!^/(domains|schemes|concepts)/([^/]*)/!', $ypath, $matches)) {
      $spath = substr($ypath, strlen($matches[0])-1);
      return $this->{$matches[1]}[$matches[2]]->extract($spath);
    }
    elseif (preg_match('!^/([^/]*)$!', $ypath, $matches) && isset($this->_c[$matches[1]]))
      return $this->_c[$matches[1]];
    else
      throw new Exception("Erreur YamlSkos::extract(ypath=$ypath), ypath non reconnu");
  }
  
  // vérification de l'intégrité du document
  function checkIntegrity() {
    echo "methode YamlSkos::checkIntegrity()<br>\n";
    foreach ($this->concepts as $cid => $concept)
      $concept->checkIntegrityOfAConcept($cid, $this->concepts, $this->schemes);
    echo "checkIntegrity() on concepts ok<br>\n";
  
    foreach ($this->schemes as $sid => $scheme)
      $scheme->checkIntegrity($sid, $this->domains, $this->schemes);
    echo "checkIntegrity() on schemes ok<br>\n";
  
    foreach ($this->domains as $did => $domain)
      $domain->checkIntegrityOfAConcept($did, $this->domains);
    echo "checkIntegrity() on domains ok<br>\n";
  }
};


{ // doc 
$phpDocs['yamlskos.inc.php']['classes']['SkosElt'] = <<<'EOT'
name: class SkosElt
title: définition de la classe abstraite SkosElt super-classe de DomainScheme, Domain, Scheme et Concept
doc: |
  La classe SkosElt implémente YamlDocElement.
  Toutes les infos sont stockées dans la propriété $_c.
  A la construction les champs string et text sont transformés en objet MLString.
EOT;
}
abstract class SkosElt implements YamlDocElement {
  static $strFields = ['prefLabel','altLabel','hiddenLabel'];
  static $txtFields = ['definition','note','scopeNote','editorialNote','historyNote','example'];
  protected $_c; // stockage du contenu comme array
  
  function __construct(array $yaml, array $language) {
    $this->_c = $yaml;
    // remplace les champs string et text par des MLString
    foreach (array_merge(self::$strFields,self::$txtFields) as $field) {
      if ($this->$field)
        $this->_c[$field] = new MLString($this->$field, $language);
    }
  }
  
  function __get(string $name) { return isset($this->_c[$name]) ? $this->_c[$name] : null; }
  
  function addNarrower(string $nid): void { $this->_c['narrower'][] = $nid; }
  
  function asArray(): array {
    $result = [];
    foreach ($this->_c as $field => $value) {
      if (in_array($field, self::$strFields))
        $result[$field] = $value->asArray();
      else
        $result[$field] = $value;
    }
    return $result;
  }
  
  function extract(string $ypath) { return YamlDoc::sextract($this->asArray(), $ypath); }
  
  // le prefLabel est utilisé pour afficher un élément
  function __tostring(): string { return $this->prefLabel->__toString(); }
  
  // affichage de liens
  function showLinks(string $eltField, string $skosDict, YamlSkos $skos) {
    if ($this->$eltField) {
      echo "<b>",YamlSkos::keyTranslate($eltField),":</b><ul style='margin-top:0;'>\n";
      $langp = isset($_GET['lang']) ? "&amp;lang=$_GET[lang]" : '';
      foreach ($this->$eltField as $lid) {
        //echo "lid=$lid<br>\n";
        if (isset($skos->$skosDict[$lid]))
          echo "<li><a href='?doc=$_GET[doc]&amp;ypath=/$skosDict/$lid$langp'>",
               $skos->$skosDict[$lid],"</a>\n";
        else
          echo "<li>lien $lid trouvé dans $skosDict\n";
      }
      echo "</ul>\n";
    }
  }
  
  // affichage de liens comme une ligne dans une table
  // $eltField est le nom du champ dans l'élément contenant les liens
  // $skosDict est le nom du champ de $skos qui contient le dictionnaire dans lequel les id des liens sont cherchés
  function showLinksInTable(string $eltField, string $skosDict, YamlSkos $skos) {
    if ($this->$eltField) {
      $nblinks = count($this->$eltField);
      echo "<tr><td>",$skos->keyTranslate($eltField),"</td><td>";
      if ($nblinks > 1)
        echo "<ul style='margin-top:0;'>\n";
      foreach ($this->$eltField as $lid) {
        //echo "lid=$lid<br>\n";
        $langp = isset($_GET['lang']) ? "&amp;lang=$_GET[lang]" : '';
        if ($nblinks > 1)
          echo "<li>";
        if (isset($skos->$skosDict[$lid]))
          echo "<a href='?doc=$_GET[doc]&amp;ypath=/$skosDict/$lid$langp'>",
               $skos->$skosDict[$lid],"</a>\n";
        elseif (preg_match('!^https?://!', $lid))
          echo "<a href='$lid' target=_blank>$lid</a>\n";
        else
          echo "lien $lid trouvé dans $skosDict\n";
      }
      if ($nblinks > 1)
        echo "</ul>";
      echo "</td></tr>\n";
    }
  }
  
  // affichage de textes
  function showStrings(string $key) {
    if (!$this->$key)
      return;
    $lang = $this->$key->getLang();
    echo "<b>",YamlSkos::keyTranslate($key)," ($lang):</b><br>\n<ul style='margin-top:0;'>\n";
    foreach ($this->$key->getInLang() as $label) {
      echo '<li>',str2html($label),"\n";
    }
    echo "</ul>\n";
  }
  
  // affichage de textes
  function showTexts(string $key) {
    if (!$this->$key)
      return;
    $lang = $this->$key->getLang();
    echo "<b>",YamlSkos::keyTranslate($key)," ($lang):</b><br>\n";
    foreach ($this->$key->getInLang() as $label) {
      echo MarkdownExtra::defaultTransform($label);
    }
  }
  
  // affichage de texts comme une ligne dans une table
  function showTextsInTable(string $key) {
    if (!$this->$key)
      return;
    $lang = $this->$key->getLang();
    $labels = $this->$key->getInLang();
    $nblabels = count($labels);
    echo "<tr><td rowspan='$nblabels'>",YamlSkos::keyTranslate($key)," ($lang):</td>\n";
    foreach ($labels as $i => $label) {
      echo $i?'<tr>':'','<td>',MarkdownExtra::defaultTransform($label),'</td></tr>';
    }
  }
  
  // vérifie l'intégrité d'un concept
  // Si $schemes est [] ne vérifie pas les liens avec le scheme
  function checkIntegrityOfAConcept(string $cid, array $concepts, array $schemes=[]) {
    if (!$this->prefLabel)
      echo "Concept $cid sans prefLabel<br>\n";
    if ($schemes) {
      if (!$this->inScheme)
        echo "Concept $cid sans inScheme<br>\n";
      else
        foreach ($this->inScheme as $s)
          if (!isset($schemes[$s]))
            echo "Concept $cid inScheme absent de schemes<br>\n";
      if (!$this->topConceptOf && !$this->broader)
        echo "Concept $cid sans topConceptOf et sans broader<br>\n";
      if ($this->topConceptOf)
        foreach ($this->topConceptOf as $s)
          if (!isset($schemes[$s]))
            echo "Concept $cid topConceptOf absent schemes<br>\n";
    }
    foreach (['broader','narrower','related'] as $key)
      if ($this->$key)
        foreach ($this->$key as $c)
          if (!isset($concepts[$c]))
            echo "Concept $cid $key absent concepts<br>\n";
  }
};

{ // doc 
$phpDocs['yamlskos.inc.php']['classes']['DomainScheme'] = <<<'EOT'
title: définition de la classe DomainScheme
EOT;
}
class DomainScheme extends SkosElt {
  // Affiche l'arbre des domaines avec un lien vers chaque micro-thésaurus
  function show(array $domains, array $schemes) {
    echo "<h2>$this</h2><ul>\n";
    //echo "<pre>domainScheme="; print_r($this); echo "</pre>\n";
    foreach ($this->hasTopConcept as $domid) {
      echo "<li>$domains[$domid]</li>\n";
      $domains[$domid]->showDomainTree($domid, $domains, $schemes);
    }
    echo "</ul>\n";
  }
};
  
{ // doc 
$phpDocs['yamlskos.inc.php']['classes']['Domain'] = <<<'EOT'
title: définition de la classe Domain
EOT;
}
class Domain extends SkosElt {
  // affiche le sous-arbre correspondant au domaine avec un lien vers chaque micro-thésaurus
  function showDomainTree(string $id, array $domains, array $schemes) {
    //echo "<pre>this = "; print_r($this); echo "</pre>\n";
    if ($this->narrower) {
      $children = [];
      foreach ($this->narrower as $narrower) {
        $children[$narrower] = suppAccents((string)$domains[$narrower]);
      }
      asort($children);
      echo "<ul>\n";
      foreach (array_keys($children) as $narrower) {
        echo "<li>$domains[$narrower]</li>\n";
        $domains[$narrower]->showDomainTree($narrower, $domains, $schemes);
      }
      echo "</ul>\n";
    }
    if ($this->schemeChildren) {
      echo "<ul>\n";
      $langp = (isset($_GET['lang']) ? "&amp;lang=$_GET[lang]" : '');
      foreach ($this->schemeChildren as $sid) {
        echo "<li><a href='?doc=$_GET[doc]&amp;ypath=/schemes/$sid$langp'>$schemes[$sid]</a></li>\n";
      }
      echo "</ul>\n";
    }
  }
  
  function addSchemeChild(string $sid) { $this->_c['schemeChildren'][] = $sid; }
};

{ // doc 
$phpDocs['yamlskos.inc.php']['classes']['Scheme'] = <<<EOT
title: définition de la classe Scheme des micro-thésaurus
doc: |
  La notion Skos de scheme est étendue pour gérer les listes de listes de codes définies pour Inspire.
  Une telle liste est définie comme liste de code et comporte une propriété hasPart contenant la liste des
  identifiants des différentes listes contenues. Les sous-listes comportent une propriété isPartOf avec les listes
  auxquelles elles appartiennent. Ces 2 propriétés hasPart et isPartOf proviennent de Dublin Core.
  
  Chaque scheme, identifié par une clé, contient au moins les champs:
    - prefLabel qui porte une étiquete mono ou multi-lingue,
    - le rattachement hiérarchique qui est soit:
      - domain qui contient la liste des identifiants des domaines auxquels le scheme est rattaché
      - isPartOf qui contient la liste des identifiants des schemes auxquels le scheme fait partie
        
EOT;
}
class Scheme extends SkosElt {
  // remplit le lien domain -> scheme à partir du lien inverse
  static function fillSchemeChildren(array $schemes, array $domains) {
    foreach ($schemes as $sid => $scheme) {
      if ($scheme->domain) {
        foreach ($scheme->domain as $domid) {
          $domains[$domid]->addSchemeChild($sid);
        }
      }
    }
  }
    
  // Si les micro-thésaurus ne référencent pas de topConcept alors ils sont déduits des concepts
  static function fillTopConcepts(YamlSkos $skos) {
    foreach ($skos->schemes as $sid => $scheme) {
      if (!$scheme->hasTopConcept) {
        echo "fillTopConcepts pour $sid<br>\n";
        $scheme->_c['hasTopConcept'] = [];
        foreach ($skos->concepts as $cid => $concept) {
          if ($concept->topConceptOf && in_array($sid, $concept->topConceptOf))
            $scheme->_c['hasTopConcept'][] = $cid;
        }
      }
    }
  }
  
  // affiche la liste des schemes
  static function showSchemes(array $schemes) {
    echo "<h2>Liste des micro-thésaurus</h2><ul>\n";
    $langp = isset($_GET['lang']) ? "&amp;lang=$_GET[lang]" : '';
    foreach ($schemes as $id => $scheme) {
      echo "<li><a href='?doc=$_GET[doc]&amp;ypath=/schemes/$id$langp'>$scheme</a></li>\n";
    }
    echo "</ul>\n";
  }

  // affiche un micro-thesaurus avec l'arbre des termes correspondant
  function show(array $concepts, YamlSkos $skos) {
    //echo "<pre>Scheme::show("; print_r($this); echo ")</pre>\n";
    $type = $this->type ? ' ('.implode(',',$this->type).')' : '';
    echo "<h2>$this$type</h2>\n";
    $this->showTexts('definition');
    foreach (['hasPart'=> 'schemes','isPartOf'=> 'schemes'] as $linkField => $skosDict)
      $this->showLinks($linkField, $skosDict, $skos);
    if ($this->hasTopConcept) {
      echo "<h3>",YamlSkos::keyTranslate('content'),"</h3><ul>\n";
      $children = []; // [ id => label ]
      foreach ($this->hasTopConcept as $cid) {
        //echo "cid=$cid<br>\n";
        $children[$cid] = suppAccents((string)$concepts[$cid]);
      }
      if ($this->options && in_array('sort', $this->options))
        asort($children);
      //echo "<pre>children="; print_r($children); echo "</pre>\n";
      $langp = isset($_GET['lang']) ? "&amp;lang=$_GET[lang]" : '';
      foreach (array_keys($children) as $childid) {
        $child = $concepts[$childid];
        echo "<li><a href='?doc=$_GET[doc]&amp;ypath=/concepts/$childid$langp'>$child</a></li>\n";
        $concepts[$childid]->showConceptTree($concepts);
      }
      echo "</ul>\n";
    }
  }
  
  function checkIntegrity(string $sid, array $domains, array $schemes) {
    if (!$this->prefLabel)
      echo "Scheme $sid sans prefLabel<br>\n";
    if (!$this->domain && !$this->isPartOf)
      echo "Scheme $sid sans domain ni isPartOf<br>\n";
    if ($this->domain)
      foreach ($this->domain as $d)
        if (!isset($domains[$d]))
          echo "Scheme $sid domain absent de domains<br>\n";
    if ($this->isPartOf)
      foreach ($this->isPartOf as $s)
        if (!isset($schemes[$s]))
          echo "Scheme $sid isPartOf $s absent de schemes<br>\n";
  }
};

{ // doc 
$phpDocs['yamlskos.inc.php']['classes']['Concept'] = <<<EOT
name: class Concept
title: définition de la classe Concept
doc: |
  La notion Skos de concept est étendue avec la possibilité d'illustrer le concept par des images.
  On utilise pour cela le tag depiction défini par foaf (http://xmlns.com/foaf/0.1/)
  comme indiqué dans https://www.w3.org/2004/02/skos/core/guide/2004-11-25.html#secdepict

  Chaque concept, identifié par une clé, contient au moins les champs:
    - prefLabel qui porte une étiquette mono ou multi-lingue,
    - inScheme qui contient la liste des identifiants des micro-thésaurus auquel le concept appartient,
    - le rattachement hiérarchique qui est soit:
      - topConceptOf qui contient les identifiants des micro-thésaurus dont le concept est concept de premier niveau
      - broader qui contient les identifiants des concepts plus génériques
EOT;
}
class Concept extends SkosElt {
  static $txtFields = ['definition','note','scopeNote','historyNote','example','editorialNote','changeNote'];
  static $linkFields = [ // sous la forme tag => dictionnaire arrivée
    'inScheme'=> 'schemes',
    'topConceptOf'=> 'schemes',
    'broader'=> 'concepts',
    'narrower'=> 'concepts',
    'related'=> 'concepts',
  ];
  
  // affiche l'arbre des concepts correspondant à un thésaurus
  static function showConcepts(array $concepts) {
    echo "<ul>\n";
    foreach ($concepts as $id => $concept)
      if ($concept->topConceptOf)
        self::showConceptTree($concepts, $id);
    echo "</ul>\n";
  }
  
  // affiche le sous-arbre des concepts du concept courant
  function showConceptTree(array $concepts) {
    if ($this->narrower) {
      $children = [];
      foreach ($this->narrower as $nid) {
        $children[$nid] = suppAccents($concepts[$nid]->prefLabel->__toString());
      }
      asort($children);
      $langp = isset($_GET['lang']) ? "&amp;lang=$_GET[lang]" : '';
      echo "<ul>\n";
      foreach (array_keys($children) as $nid) {
        $narrower = $concepts[$nid];
        echo "<li><a href='?doc=$_GET[doc]&amp;ypath=/concepts/$nid$langp'>$narrower</a></li>\n";
        $narrower->showConceptTree($concepts);
      }
      echo "</ul>\n";
    }
  }
  
  // Si les concepts ne référencent pas de narrower alors ils sont déduits des broader
  static function fillNarrowers(array $concepts) {
    foreach ($concepts as $cid => $concept) {
      //echo "Concept::fillNarrowers($cid)<br>\n";
      if ($concept->broader) {
        foreach ($concept->broader as $broaderId) {
          $broader = $concepts[$broaderId];
          if (!$broader->narrower || !in_array($cid, $broader->narrower)) {
            //echo "Ajout $broaderId narrower $cid<br>\n";
            $broader->addNarrower($cid);
          }
        }
      }
    }
  }
    
  function show(YamlSkos $skos) {
    //echo "<pre>Concept::show("; print_r($this); echo ")</pre>\n";
    echo "<h3>$this</h3>\n";
    $this->showStrings('altLabel');
    
    foreach (self::$linkFields as $linkField => $skosDict)
      $this->showLinks($linkField, $skosDict, $skos);
    
    //echo "<pre>this="; print_r($this); echo "</pre>\n";
    foreach (self::$txtFields as $field)
      $this->showTexts($field);
    
    if ($this->depiction) {
      echo "<b>depiction:</b><br>";
      foreach ($this->depiction as $imagepath)
        echo  "<img src='file.php/$imagepath' alt='image $imagepath'>\n";
    }
  }
};