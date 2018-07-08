<?php
/*PhpDoc:
name: yamlskos.inc.php
title: gestion d'un YamlSkos
doc: |
  voir le code
*/
{
$phpDocs['yamlskos.inc.php'] = <<<EOT
name: yamlskos.inc.php
title: gestion d'un YamlSkos
doc: |
  La définition de YamlSkos est inspirée de la structure utilisée pour EuroVoc.
  Elle a été étendue pour gérer les listes de codes et énumérations du règlement interopérabilité Inspire.
  
  Un document YamlSkos comprend:

    - des champs de métadonnées DublinCore dont au moins:
      - title: le titre du thésaurus
      - language: la ou les langues
    - un champ concepts qui liste les concepts ; chacun identifié par une clé et contenant au moins les champs:
      - prefLabel qui porte une étiquete mono ou multi-lingue,
      - inScheme qui contient les identifiants des micro-thésaurus auquel le concept appartient,
      - soit:
        - topConceptOf qui contient les identifiants des micro-thésaurus dont le concept est concept de premier niveau
        - broader avec les identifiants des concepts plus génériques
    - un champ schemes qui contient les micro-thésaurus définis comme scheme Skos ; chaque scheme est identifié
      par une clé et contient au moins les champs:
        - prefLabel qui porte une étiquete mono ou multi-lingue,
        - soit:
          - domain qui contient la liste des identifiants des domaines auxquels le scheme est rattaché
          - isPartOf qui contient la liste des identifiants des schemes auxquels il fait partie
    - un champ domains qui liste les domaines ; chacun est défini comme concept Skos, est identifié par une clé et
      contient au moins les champs:
        - prefLabel qui porte une étiquette mono ou multi-lingue,
      Les domaines qui ne sont pas de premier niveau doivent définir un champ broader définissant un concept plus
      générique.
    - un champ domainScheme qui est le thésaurus des domaines qui comporte le champ suivant:
        - hasTopConcept qui liste les identifiants des domaines de premier niveau
        
  La notion de scheme est étendue pour gérer les listes de listes de codes.
  Une telle liste est définie comme liste de code et comporte une propriété hasPart contenant la liste des
  identifiants des différentes listes contenues. Les sous-listes comportent une propriété isPartOf avec les listes
  auxquelles elles appartiennent. Ces propriétés sont définies dans Dublin Core.
journal: |
  4/7/2018:
  - possibilité d'une arborescence des domaines
  - définition d'une classe DomainScheme
  27-29/6/2018:
  - création
EOT;
}
require_once __DIR__.'/../vendor/autoload.php';
require_once __DIR__."/../yamldoc/markdown/PHPMarkdownLib1.8.0/Michelf/MarkdownExtra.inc.php";

use Symfony\Component\Yaml\Yaml;
use Michelf\MarkdownExtra;

// suppression des accents et passage en minuscule pour le tri
function suppAccents(string $str): string {
  return strtolower(str_replace(['é','É','Î'],['e','e','i'], $str));
}

class YamlSkos extends YamlDoc {
  // traduction des champs utilisés en français et en anglais pour l'affichage
  static $keyTranslations = [
    'hasPart'=> ['fr'=>"Est composé de", 'en'=> "Has part"],
    'isPartOf'=> ['fr'=>"Est une partie de", 'en'=> "Is part of"],
    'content'=> ['fr'=>"Contenu", 'en'=>"Content"],
    'inScheme'=> ['fr'=>"Elément du schéma", 'en'=>"In scheme"],
    'hasTopConcept'=> ['fr'=>"Elements de premier niveau", 'en'=>"Top concepts"],
    'topConceptOf'=> ['fr'=>"Elément de premier niveau du schéma", 'en'=>"Top concept of"],
    'prefLabel'=> ['fr'=>"Forme lexicale préférentielle", 'en'=>"Prefered label"],
    'altLabel'=> ['fr'=> "Forme lexicale alternative", 'en'=>"Alternative label"],
    'hiddenLabel'=> ['fr'=>"Forme cachée", 'en'=>"Hidden label"],
    'definition'=> ['fr'=>"Définition", 'en'=> "Definition"],
    'note'=> ['fr'=>"Note", 'en'=>"Note"],
    'scopeNote'=> ['fr'=>"Note d'application ", 'en'=>"Scope note"],
    'historyNote'=> ['fr'=>"Note historique", 'en'=>"History note"],
    'editorialNote'=> ['fr'=>"Note éditoriale", 'en'=>"Editorial note"], 
    'example'=> ['fr'=>"Exemple", 'en'=>"Example"],
    'broader'=> ['fr'=>"concept générique", 'en'=>"Broader"],
    'narrower'=> ['fr'=>"concept spécifique", 'en'=>"Narrower"],
    'related'=> ['fr'=>"concept associé", 'en'=>"Related"],
    'xxx'=> ['fr'=>"xxx", 'en'=>"yyy"],
  ];
  protected $_c; // contient les champs qui n'ont pas été transférés dans les champs ci-dessous
  protected $language; // liste des langues
  protected $domainScheme; // thésaurus des domaines
  protected $domains; // dictionnaire des domaines décrits comme concepts Skos
  protected $schemes; // dictionnaire des micro-thésaurus
  protected $concepts; // dictionnaire des concepts
  
  function __construct(array &$yaml) {
    if (!isset($yaml['title']))
      throw new Exception("Erreur: champ title absent dans la création YamlSkos");
    if (!isset($yaml['language']))
      throw new Exception("Erreur: champ language absent dans la création YamlSkos");
    $this->language = is_string($yaml['language']) ? [ $yaml['language'] ] : $yaml['language'];
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
  
  function writePser(string $store, string $docuid): void { YamlDoc::writePserReally($store, $docuid); }
  
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
  
  function show(string $ypath): void {
    //echo "<pre> yamlSkos ="; print_r($this); echo "</pre>\n";
    if (!$ypath) {
      showDoc($this->_c);
      $this->domainScheme->show($this->domains, $this->schemes);
    }
    elseif (preg_match('!^/([^/]*)$!', $ypath, $matches) && isset($this->_c[$matches[1]]))
      showDoc($this->_c[$matches[1]]);
    elseif (preg_match('!^/(schemes|concepts|domains)(/([^/]*))?(/(.*))?$!', $ypath, $matches)) {
      //print_r($matches);
      $what = $matches[1];
      // affichage de tous les schemes ou tous les concepts
      if (!isset($matches[3])) {
        if ($what=='schemes')
          $this->domainScheme->show($this->domains, $this->schemes);
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
            showDoc($this->schemes[$id]->extract($field));
          else
            showDoc($this->concepts[$id]->extract($field));
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
  
  // renvoie un array récursif du fragment défini par ypath
  function extract(string $ypath) {
    if (!$ypath) {
      $result = $this->_c;
      foreach (['domains','schemes','concepts'] as $field) {
        foreach($this->$field as $id => $elt)
          $result[$field][$id] = $elt->asArray();
      }
      return $result;
    }
    elseif ($ypath == '/domainScheme')
      return $this->domainScheme->asArray();
    elseif (preg_match('!^/(domains|schemes|concepts)(/([^/]*))?$!', $ypath, $matches)) {
      if (!isset($matches[2])) {
        foreach($this->{$matches[1]} as $id => $elt)
          $result[$id] = $elt->asArray();
        return $result;
      }
      else
        return $this->{$matches[1]}[$matches[3]]->asArray();
    }
    elseif (preg_match('!^/([^/]*)$!', $ypath, $matches) && isset($this->_c[$matches[1]]))
      return $this->_c[$matches[1]];
    else
      throw new Exception("Erreur YamlSkos::extract(ypath=$ypath)");
  }
  
  // génère le texte correspondant au fragment défini par ypath
  // améliore la sortie en supprimant les débuts de ligne
  function yaml(string $ypath): string {
    $fragment = $this->extract($ypath);
    return YamlDoc::syaml(YamlDoc::replaceYDEltByPhp($fragment));
  }
  
  function json(string $ypath): string {
    $fragment = $this->extract($ypath);
    return json_encode($fragment, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
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

// la classe Elt est une super-classe de Domain, Scheme et Concept
class Elt {
  protected $_c; // stockage du contenu comme array
  
  function __construct(array $yaml, array $language) {
    $this->_c = $yaml;
    if (is_string($this->prefLabel)) {
      if (count($language)==1)
        $this->_c['prefLabel'] = [$language[0] => $this->prefLabel];
      else {
        print_r($yaml);
        print_r($language);
        throw new Exception("Erreur sur prefLabel dans Elt::__construct()");
      }
    }
  }
  
  function addNarrower(string $nid): void { $this->_c['narrower'][] = $nid; }
  function __get(string $name) { return isset($this->_c[$name]) ? $this->_c[$name] : null; }
  function asArray(): array { return $this->_c; }
  function extract(string $ypath): array { return YamlDoc::sextract($this->_c, $ypath); }
  function showInYaml(): void { echo "<pre>",Yaml::dump($this->_c, 999, 2),"</pre>"; }
  
  // pour un texte/chaine multi-lingue renvoie la langue à afficher ou ''
  function getLangForText(string $key): string {
    if (isset($_GET['lang']) && isset($this->$key[$_GET['lang']]))
      return $_GET['lang'];
    else
      foreach (['fr','en','n'] as $lang)
        if (isset($this->$key[$lang]))
          return $lang;
    return '';
  }
  
  function __tostring() {
    $lang = $this->getLangForText('prefLabel');
    return $lang ? $this->prefLabel[$lang] : 'aucun prefLabel';
  }
  
  function showLinks(string $key, YamlSkos $skos) {
    if ($this->$key) {
      echo "<b>",YamlSkos::keyTranslate($key),":</b><ul style='margin-top:0;'>\n";
      foreach ($this->$key as $lid) {
        //echo "lid=$lid<br>\n";
        $langp = isset($_GET['lang']) ? "&amp;lang=$_GET[lang]" : '';
        if (isset($skos->concepts[$lid]))
          echo "<li><a href='?doc=$_GET[doc]&amp;ypath=/concepts/$lid$langp'>",
               $skos->concepts[$lid],"</a>\n";
        elseif (isset($skos->schemes[$lid]))
          echo "<li><a href='?doc=$_GET[doc]&amp;ypath=/schemes/$lid$langp'>",
               $skos->schemes[$lid],"</a>\n";
        else
          echo "<li>lien $lid trouvé ni dans concepts ni dans schemes\n";
      }
      echo "</ul>\n";
    }
  }
  
  function showTexts(string $key) {
    if ($this->$key) {
      if (is_string($this->$key)) { // texte mono-lingue
        echo "<b>$key:</b><br>\n";
        echo MarkdownExtra::defaultTransform($this->$key);
      }
      else {
        $lang = $this->getLangForText($key);
        $labels = $this->$key[$lang];
        echo "<b>",YamlSkos::keyTranslate($key)," ($lang):</b><br>\n";
        if (is_string($labels))
          echo MarkdownExtra::defaultTransform($labels);
        else {
          foreach ($labels as $label) {
            echo MarkdownExtra::defaultTransform($label);
          }
        }
      }
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

// classe du domainScheme
class DomainScheme extends Elt {
    
  // Affiche l'arbre des domaines avec un lien vers chaque micro-thésaurus
  function show(array $domains, array $schemes) {
    echo "<h2>$this</h2><ul>\n";
    //echo "<pre>domainScheme="; print_r($this); echo "</pre>\n";
    foreach ($this->hasTopConcept as $domid) {
      echo "<li>$domains[$domid]</li>\n";
      $domains[$domid]->showDomainTree($domains, $schemes);
    }
    echo "</ul>\n";
  }
};
  
class Domain extends Elt {
  // affiche le sous-arbre correspondant au domaine avec un lien vers chaque micro-thésaurus
  function showDomainTree(array $domains, array $schemes) {
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
        $domains[$narrower]->showDomainTree($domains, $schemes);
      }
      echo "</ul>\n";
    }
    if ($this->schemeChildren) {
      echo "<ul>\n";
      foreach ($this->schemeChildren as $sid) {
        echo "<li><a href='?doc=$_GET[doc]&amp;ypath=/schemes/$sid'>$schemes[$sid]</a></li>\n";
      }
      echo "</ul>\n";
    }
  }
  
  function addSchemeChild(string $sid) { $this->_c['schemeChildren'][] = $sid; }
};

class Scheme extends Elt {
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
        //echo "fillTopConcepts pour $sid<br>\n";
        $scheme->hasTopConcept = [];
        foreach ($skos->concepts as $cid => $concept) {
          if ($concept->topConceptOf && in_array($sid, $concept->topConceptOf))
            $scheme->hasTopConcept[] = $cid;
        }
      }
    }
  }
  
  // affiche un micro-thesaurus avec l'arbre des termes correspondant
  function show(array $concepts, YamlSkos $skos) {
    //echo "<pre>Scheme::show("; print_r($this); echo ")</pre>\n";
    $type = $this->type ? ' ('.implode(',',$this->type).')' : '';
    echo "<h2>$this$type</h2>\n";
    foreach (['hasPart','isPartOf'] as $key)
      $this->showLinks($key, $skos);
    $this->showTexts('definition');
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
      foreach (array_keys($children) as $childid) {
        $child = $concepts[$childid];
        echo "<li><a href='?doc=$_GET[doc]&amp;ypath=/concepts/$childid'>$child</a></li>\n";
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

class Concept extends Elt {
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
        $children[$nid] = suppAccents($concepts[$nid]->prefLabel['fr']);
      }
      asort($children);
      echo "<ul>\n";
      foreach (array_keys($children) as $nid) {
        $narrower = $concepts[$nid];
        echo "<li><a href='?doc=$_GET[doc]&amp;ypath=/concepts/$nid'>$narrower</a></li>\n";
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
    echo "<h3>",$this,"</h3>\n";
    if ($this->altLabel) {
      if (is_numeric(array_keys($this->altLabel)[0])) {
        echo "<b>altLabel:</b><ul style='margin-top:0;'>\n";
        foreach ($this->altLabel as $label)
          echo "<li>$label\n";
        echo "</ul>\n";
      }
      else {
        $lang = $this->getLangForText('altLabel');
        $labels = $this->altLabel[$lang];
        echo "<b>",YamlSkos::keyTranslate('altLabel')," ($lang):</b><ul>\n";
        foreach ($labels as $label) {
          echo "<li>$label\n";
        }
        echo "</ul>\n";
      }
    }
    
    foreach (['inScheme','topConceptOf','broader','narrower','related','hasTopConcept'] as $key) {
      $this->showLinks($key, $skos);
    }
    
    //echo "<pre>this="; print_r($this); echo "</pre>\n";
    foreach (['definition','scopeNote','historyNote','example'] as $key) {
      $this->showTexts($key);
    }
    
    if ($this->depiction) {
      echo "<b>depiction:</b><br>";
      foreach ($this->depiction as $imagepath)
        echo  "<img src='image.php/$imagepath' alt='image $imagepath'>\n";
    }
  }
};