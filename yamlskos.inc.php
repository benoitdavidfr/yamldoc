<?php
/*PhpDoc:
name: yamlskos.inc.php
title: gestion d'un YamlSkos
doc: |
  voir le code
journal: |
  27-28/6/2018:
  - création
*/
{
$phpDocs['yamlskos.inc.php'] = <<<EOT
  name: yamlskos.inc.php
  title: gestion d'un YamlSkos
  doc: |
    Un document YamlSkos comprend:
  
      - des champs de métadonnées DublinCore dont au moins:
        - title: le titre du thésaurus
        - language: la ou les langues
      - un champ concepts qui liste les concepts ; chacun identifié par une clé et contenant au moins les champs:
          - prefLabel qui porte une étiquete mono ou multi-lingue,
          - inScheme qui contient les identifiants des micro-thésaurus auquel le concept appartient,
          - soit:
              - topConceptOf qui contient les identifiants des micro-thésaurus dont le concept est concept de premier niveau
              - broader avec les concepts plus génériques
      - un champ schemes qui contient les micro-thésaurus définis comme scheme Skos ; chaque scheme est identifié
        par une clé et contient au moins les champs:
          - prefLabel qui porte une étiquete mono ou multi-lingue,
          - domain qui contient l'identifiant du domaine auquel le scheme est rattaché
      - un champ domains qui liste les domaines ; chacun est défini comme concept Skos, est identifié par une clé et
        contient au moins les champs:
          - prefLabel qui porte une étiquete mono ou multi-lingue,
      - un champ domainScheme qui est le thésaurus des domaines qui comporte le champ suivant:
          - hasTopConcept qui liste les identifiants des domaines de 
  journal: |
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
  protected $_c; // contient les champs qui n'ont pas été transférés dans les champs ci-dessous
  protected $language; // liste des langages
  protected $domainScheme; // thésaurus des domaines
  protected $domains; // liste des domaines décrits comme concepts Skos
  protected $schemes; // liste des micro-thésaurus
  protected $concepts; // liste des concepts
  
  function __construct(array $yaml) {
    if (!isset($yaml['title']))
      throw new Exception("Erreur: title absent dans la création YamlSkos");
    if (!isset($yaml['language']))
      throw new Exception("Erreur: language absent dans la création YamlSkos");
    $this->language = is_string($yaml['language']) ? [ $yaml['language'] ] : $yaml['language'];
    if (!isset($yaml['domainScheme']))
      throw new Exception("Erreur: domainScheme absent dans la création YamlSkos");
    $this->domainScheme = $yaml['domainScheme'];
    unset($yaml['domainScheme']);
    if (!isset($yaml['domains']))
      throw new Exception("Erreur: domains absent dans la création YamlSkos");
    $this->domains = [];
    foreach ($yaml['domains'] as $id => $domain)
      $this->domains[$id] = new Domain($domain, $this->language);
    unset($yaml['domains']);
    if (!isset($yaml['schemes']))
      throw new Exception("Erreur: schemes absent dans la création YamlSkos");
    $this->schemes = [];
    foreach ($yaml['schemes'] as $id => $scheme)
      $this->schemes[$id] = new Scheme($scheme, $this->language);
    unset($yaml['schemes']);
    if (!isset($yaml['concepts']))
      throw new Exception("Erreur: concepts absent dans la création YamlSkos");
    $this->concepts = [];
    foreach ($yaml['concepts'] as $id => $concept)
      $this->concepts[$id] = new Concept($concept, $this->language);
    unset($yaml['concepts']);
    $this->_c = $yaml;
    // Si les micro-thésaurus ne référencent pas de topConcept alors ils sont déduits des concepts
    Scheme::fillTopConcepts($this);
    // Si les concepts ne référencent pas de narrower alors ils sont déduits des broader
    Concept::fillNarrowers($this->concepts);
  }
  
  function writePser(string $store, string $docuid): void { YamlDoc::writePserReally($store, $docuid); }
  
  function __get(string $name) {
    return $this->$name ? $this->$name : (isset($this->_c[$name]) ? $this->_c[$name] : null);
  }
  
  
  function show(string $ypath): void {
    if (!$ypath) {
      showDoc($this->_c);
      $this->showSchemes();
    }
    elseif (preg_match('!^/([^/]*)$!', $ypath, $matches) && isset($this->_c[$matches[1]]))
      showDoc($this->_c[$matches[1]]);
    elseif (preg_match('!^/(schemes|concepts)(/([^/]*))?(/(.*))?$!', $ypath, $matches)) {
      //print_r($matches);
      $what = $matches[1];
      if (!isset($matches[3])) {
        if ($what=='schemes')
          $this->showSchemes();
        else
          Concept::showConcepts($this->concepts);
      }
      else {
        $id = $matches[3];
        if (!isset($matches[5])) {
          if ($what=='schemes')
            $this->showScheme($id, null);
          else
            $this->showConcept($id, null);
        }
        else {
          $field = $matches[5];
          if ($what=='schemes')
            showDoc($this->schemes[$id]->extract($field));
          else
            showDoc($this->concepts[$id]->extract($field));
        }
      }
    }
    else {
      echo "ypath=$ypath inconnu<br>\n";
    }
  }
  
  // Affiche l'ensemble des micro-thésaurus organisés par domaine
  function showSchemes() {
    $prefLabel = $this->domainScheme['prefLabel'];
    if (!is_string($prefLabel))
      $prefLabel = $prefLabel['fr'];
    echo "<h2>$prefLabel</h2>\n";
    //echo "<pre>domainScheme="; print_r($this->domainScheme); echo "</pre>\n";
    echo "<ul>\n";
    foreach ($this->domainScheme['hasTopConcept'] as $did) {
      $domain = $this->domains[$did];
      echo "<li>",$domain,"<ul>\n";
      $children = [];
      foreach ($this->schemes as $sid => $scheme) {
        if ($scheme->domain && in_array($did, $scheme->domain)) {
          $children[(string)$scheme] = $sid;
        }
      }
      ksort($children);
      foreach ($children as $label => $sid)
        echo "<li><a href='?doc=$_GET[doc]&amp;ypath=/schemes/$sid'>$label</a></li>\n";
      echo "</ul>\n";
    }
    echo "</ul>\n";
  }
  
  // affiche un micro-thésaurus
  function showScheme(string $sid, ?string $format) {
    //echo "YamlSkos::showScheme(sid=$sid)<br>\n";
    if ($this->schemes[$sid]) {
      $this->schemes[$sid]->show($this->concepts);
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
  
  function extract(string $ypath) {
    if ($ypath)
      throw new Exception("Erreur YamlSkos::extract(ypath=$ypath)");
    $result = $this->_c;
    foreach($this->schemes as $sid => $scheme)
      $result['schemes'][$sid] = $scheme->asArray();
    foreach($this->concepts as $cid => $concept)
      $result['concepts'][$cid] = $concept->asArray();
    return $result;
  }
};

class Elt {
  protected $_c; // stockage du contenu comme array
  
  function __construct(array $yaml, array $language) {
    $this->_c = $yaml;
    if (is_string($this->prefLabel)) {
      if (count($language)==1)
        $this->prefLabel = [$language[0] => $this->prefLabel];
      else
        throw new Exception("Erreur sur prefLabel dans Elt::__construct()");
    }
  }
  
  function __get(string $name) { return isset($this->_c[$name]) ? $this->_c[$name] : null; }
  function  __tostring() { return $this->prefLabel['fr']; }
  function asArray() { return $this->_c; }
  function extract(string $ypath) { return YamlDoc::sextract($this->_c, $ypath); }
  function showInYaml(YamlSkos $skos) { echo "<pre>",Yaml::dump($this->_c),"</pre>"; }
};

class Domain extends Elt {
};

class Scheme extends Elt {
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
  function show(array $concepts) {
    //echo "<pre>Scheme::show("; print_r($this); echo ")</pre>\n";
    echo "<h2>",$this->prefLabel['fr'],"</h2><ul>\n";
    $children = []; // [ id => label ]
    foreach ($this->hasTopConcept as $cid) {
      $children[$cid] = suppAccents($concepts[$cid]->prefLabel['fr']);
    }
    asort($children);
    //echo "<pre>children="; print_r($children); echo "</pre>\n";
    foreach (array_keys($children) as $childid) {
      Concept::showConceptTree($concepts, $childid);
    }
    echo "</ul>\n";
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
  
  // affiche l'arbre des concepts correspondant à un thésaurus
  static function showConceptTree(array $concepts, string $id) {
    $concept = $concepts[$id];
    echo "<li><a href='?doc=$_GET[doc]&amp;ypath=/concepts/$id'>",$concept->prefLabel['fr'],"</a></li>\n";
    if ($concept->narrower) {
      $children = [];
      foreach ($concept->narrower as $narrower) {
        $children[$narrower] = suppAccents($concepts[$narrower]->prefLabel['fr']);
      }
      asort($children);
      echo "<ul>\n";
      foreach (array_keys($children) as $narrower) {
        self::showConceptTree($concepts, $narrower);
      }
      echo "</ul>\n";
    }
  }
  
  // Si les concepts ne référencent pas de narrower alors ils sont déduits des broader
  static function fillNarrowers(array $concepts) {
    foreach ($concepts as $cid => $concept) {
      if ($concept->broader) {
        foreach ($concept->broader as $broaderId) {
          $broader = $concepts[$broaderId];
          if (!$broader->narrower || !in_array($cid, $broader->narrower)) {
            echo "Ajout $broaderId narrower $cid<br>\n";
            $broader->addNarrower($cid);
          }
        }
      }
    }
  }
  
  function addNarrower(string $nid) {
    $this->_c['narrower'][] = $nid;
  }
  
  function  __tostring() { return $this->prefLabel['fr']; }
  
  function show(YamlSkos $skos) {
    //echo "<pre>Concept::show("; print_r($this); echo ")</pre>\n";
    echo "<h3>",$this,"</h3>\n";
    if (isset($this->prefLabel['en']))
      echo "<b>prefLabel (en):</b> ",$this->prefLabel['en'],"<br><br>\n";
    if ($this->altLabel) {
      if (is_numeric(array_keys($this->altLabel)[0])) {
        echo "<b>altLabel:</b><ul style='margin-top:0;'>\n";
        foreach ($this->altLabel as $label)
          echo "<li>$label\n";
        echo "</ul>\n";
      }
      else {
        foreach ($this->altLabel as $lang => $labels) {
          echo "<b>altLabel ($lang):</b><ul style='margin-top:0;'>\n";
          foreach ($labels as $label)
            echo "<li>$label\n";
          echo "</ul>\n";
        }
      }
    }
    foreach (['inScheme','topConceptOf','broader','narrower','related','hasTopConcept'] as $key) {
      if ($this->$key) {
        echo "<b>$key:</b><ul style='margin-top:0;'>\n";
        foreach ($this->$key as $lid) {
          //echo "lid=$lid<br>\n";
          if (isset($skos->concepts[$lid]))
            echo "<li><a href='?doc=$_GET[doc]&amp;ypath=/concepts/$lid'>",$skos->concepts[$lid],"</a>\n";
          elseif (isset($skos->schemes[$lid]))
            echo "<li><a href='?doc=$_GET[doc]&amp;ypath=/schemes/$lid'>",$skos->schemes[$lid],"</a>\n";
          else
            echo "<li>lien $lid trouvé ni dans concepts ni dans schemes\n";
        }
        echo "</ul>\n";
      }
    }
    //echo "<pre>this="; print_r($this); echo "</pre>\n";
    foreach (['definition','scopeNote','historyNote','example'] as $key) {
      if ($this->$key) {
        if (is_string($this->$key)) {
          echo "<b>$key:</b><br>\n";
          echo MarkdownExtra::defaultTransform($this->$key);
          //foreach (explode("\n", $this->$key) as $line)
            //echo "$line<br>\n";
        }
        else {
          foreach ($this->$key as $lang => $labels) {
            echo "<b>$key($lang):</b><br>\n";
            foreach ($labels as $label) {
              echo MarkdownExtra::defaultTransform($label);
              //foreach (explode("\n", $label) as $line)
                //echo "$line<br>\n";
            }
          }
        }
      }
    }
    if ($this->depiction) {
      echo "<b>depiction:</b><br>";
      foreach ($this->depiction as $imagepath)
        echo  "<img src='image.php/$imagepath' alt='image $imagepath'>\n";
    }
  }
};