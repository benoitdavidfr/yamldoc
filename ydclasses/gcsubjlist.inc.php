<?php
/*PhpDoc:
name: gcsubjlist.inc.php
title: gcsubjlist.inc.php - sous-document d'un géocatalogue constitué des mots-clés présents
functions:
doc: <a href='/yamldoc/?action=version&name=gcsubjlist.inc.php'>doc intégrée en Php</a>
*/
{ // doc 
$phpDocs['gcsubjlist.inc.php']['file'] = <<<'EOT'
name: gcsubjlist.inc.php
title: gcsubjlist.inc.php - sous-document d'un géocatalogue constitué des mots-clés présents
doc: |
  La classe SubjectList définit des objets qui enregistrent les mots-clés présents dans un géocatalogue.

  Liste des points d'entrée de l'API:
    - /  - liste des cvocs
    - /{cvoc} - description d'un cvoc dont la liste des labels
    - /{cvoc}/{termid} - détail sur un terme du cvoc
    - /*  - retour complet des cvocs avec leur contenu
    
  Les noms utilisés des cvoc ne peuvent pas être des identifiants car inadapté pour un URI.
  Je construis systématiquement pour chaque cvoc un id comme rawurlencode(name).
  
  NON: De même pour les labels des cvocs.
  Pour les labels des cvocs, utilisant comme identifiant d'un no d'ordre.

journal:
  2/9/2018:
    - gestion multi-lingue
  29/8/2018:
    - création
EOT;
}

require_once __DIR__.'/yamldoc.inc.php';

// Correspond à un ensemble de Cvoc construits à partir des mots-clés d'une moisson
class SubjectList extends YamlDoc {
  private $cvocs = []; // [ cvocname => Cvoc ]
  
  // à la création l'objet est vide et sa constitution s'effectue en ajoutant des mots-clés avec la méthode add()
  function __construct(&$yaml) { }
  
  // liste des langues présentes dans le doc courant
  function language() {
    $language = [];
    foreach ($this->cvocs as $cvoc) {
      foreach ($cvoc->languageNbre() as $lang=>$nbre) {
        if (!isset($language[$lang]))
          $language[$lang] = $nbre;
        else
          $language[$lang] += $nbre;
      }
    }
    arsort($language);
    //print_r($language);
    return array_keys($language);
  }
  
  // affiche le sous-élément de l'élément défini par $ypath
  function show(string $docid, string $ypath): void {
    if (($ypath=='') || ($ypath=='/')) {
      //echo "<pre>language="; print_r($this->language()); echo "</pre>\n";
      echo "<h3>Liste des vocabulaires</h3><ul>\n";
      $lang = isset($_GET['lang']) ? $_GET['lang'] : $this->language()[0];
      foreach ($this->cvocs as $cvocname => $cvoc) {
        $abstract = $cvoc->abstract();
        $href = "?doc=$docid&amp;ypath=/$abstract[id]";
        $title = $cvoc->title($lang) ? $cvoc->title($lang) : $cvocname;
        echo "<li><a href='$href'>$title</a> ($abstract[nbreOflabels])</li>\n";
        //echo "<pre>"; print_r($abstract); echo "</pre>";
      }
    }
    else {
      $cvocid = substr($ypath, 1);
      if (!isset($this->cvocs[$cvocid]))
        die("cvoc $cvocid inexistant<br>\n");
      $this->cvocs[$cvocid]->show($docid);
    }
  }
  
  function __get(string $name) { return $name == 'language' ? $this->language() : null; }
  
  // retourne la liste des vocabulaires contrôlés construits
  function asArray(): array {
    //print_r($this->cvocs);
    $result = [];
    foreach ($this->cvocs as $cvoc)
      $result[$cvoc->id()] = $cvoc->asArray();
    return $result;
  }

  // extrait le fragment du document défini par $ypath
  // Renvoie un array ou un objet qui sera ensuite transformé par YamlDoc::replaceYDEltByArray()
  // Utilisé par YamlDoc::yaml() et YamlDoc::json()
  // Evite de construire une structure intermédiaire volumineuse avec asArray()
  // A REVOIR
  function extract(string $ypath) { return YamlDoc::sextract($this->_c, $ypath); }

  // extrait le fragment défini par $ypath, utilisé pour générer un retour à partir d'un URI
  function extractByUri(string $docuri, string $ypath) {
    //echo "SubjectList::extractByUri($docuri, $ypath)<br>\n";
    if (!$ypath || ($ypath=='/')) {
      // affichage de la liste des cvocs sans les mots clés
      $result = [];
      foreach ($this->cvocs as $cvoc)
        $result[$cvoc->id()] = $cvoc->abstract();
      return $result;
    }
    elseif ($ypath == '/*') {
      // affichage de la liste des cvocs avec les mots clés
      $result = [];
      foreach ($this->cvocs as $cvoc)
        $result[$cvoc->id()] = $cvoc->asArray();
      return $result;
    }
    // /{cvocname}
    elseif (preg_match('!^/([^/]+)$!', $ypath, $matches)) {
      // affichage des mots-clés d'un cvoc
      $vocname = rawurldecode($matches[1]);
      //echo "vocname='$vocname'<br>\n";
      if (!isset($this->cvocs[$vocname]))
        return null;
      else
        return $this->cvocs[$vocname]->asArray();
    }
    // /{cvocname}/{labelname}
    elseif (preg_match('!^/([^/]+)/([^/]+)$!', $ypath, $matches)) {
      // affichage d'un mot-clé d'un cvoc
      $cvocname = rawurldecode($matches[1]);
      $termId = rawurldecode($matches[2]);
      echo "cvocname='$cvocname'<br>\n";
      if (!isset($this->cvocs[$cvocname]))
        return null;
      else
        return $this->cvocs[$cvocname]->getOne($termId);
    }
    else
      return null;
  }
  
  // Le document est stocké uniquement sous la forme d'un .pser
  function writePser(string $docuid): void { YamlDoc::writePserReally($docuid); }

  // ajoute un mot-clé aux cvoc
  function add(array $subject, string $defaultMdLanguage): void {
    if (!isset($subject['value']))
      return;
    // suppression des langues pour lesquelles il n'y a pas de label
    //echo "<pre>subject="; print_r($subject); echo "</pre>\n";
    if (is_array($subject['value'])) {
      foreach ($subject['value'] as $lang => $label) {
        if (!$label)
          unset($subject['value'][$lang]);
      }
    }
    if (!$subject['value'])
      return;
    try {
      $term = new SimpleMlString($subject['value'], $defaultMdLanguage);
      $cvoc = null;
      if (isset($subject['cvocIdentifier'])) {
        $cvoc = ['id'=> $subject['cvocIdentifier']];
        if (isset($subject['cvocTitle']))
          $cvoc['title'] = $subject['cvocTitle'];
        if (isset($subject['cvocReferenceDate']))
          $cvoc['referenceDate'] = $subject['cvocReferenceDate'];
      }
      elseif (isset($subject['cvocTitle']) && $subject['cvocTitle']) {
        if (is_string($subject['cvocTitle']))
          $cvoc = ['id'=> $subject['cvocTitle']];
        elseif (is_array($subject['cvocTitle'])) {
          $cvocTitle = array_values($subject['cvocTitle'])[0];
          if ($cvocTitle)
            $cvoc = ['id'=> $cvocTitle];
        }
      }
      if (!$cvoc)
        $cvoc = ['id'=> 'none'];
      if (!isset($this->cvocs[$cvoc['id']]))
        $this->cvocs[$cvoc['id']] = new Cvoc($cvoc);
      else
        $this->cvocs[$cvoc['id']]->improve($cvoc);
      $this->cvocs[$cvoc['id']]->add($term);
    } catch (Exception $e) {
      echo "Erreur SubjectList::add() : ",$e->getMessage(),"<br>\n";
      echo "<pre>subject="; print_r($subject); echo "</pre>\n";
    }
  }
  
  // tri chaque vocabulaire sur les mots-clés
  function sortVocs() {
    foreach ($this->cvocs as $cvoc)
      $cvoc->sort(['fre','fra','fr','eng','en']);
  }
};

// Gestion d'un vocabulaire contrôlé
class Cvoc {
  private $name; // identifiant du vocabulaire contrôlé provenant des mots-clés
  private $title=null; // titre du vocabulaire contrôlé provenant des mots-clés, null, string | [lang => string]
  private $referenceDate=null; // referenceDate du vocabulaire contrôlé provenant des mots-clés
  private $languageNbre = []; // liste des langues avec nbre d'occurences d'ajouts: [lang => nbre]
  private $language = []; // liste des langues ordonnées par nbre d'occurences décroisantes
  private $termList = []; // [ [ 'labels'=> SimpleMlString, 'nbreOfOccurences'=> nbre d''occurences ] ]
    
  function __construct(array $cvoc) {
    $this->name = $cvoc['id'];
    if (isset($cvoc['title']))
      $this->title = $cvoc['title'];
    if (isset($cvoc['referenceDate']))
      $this->referenceDate = $cvoc['referenceDate'];
  }
  
  function title(string $lang): ?string {
    if (!$this->title)
      return null;
    elseif (is_string($this->title))
      return $this->title;
    elseif (isset($this->title[$lang]))
      return $this->title[$lang];
    else
      return array_values($this->title)[0];
  }
  
  function languageNbre() { return $this->languageNbre; }
  function language() {
    if ($this->language)
      return $this->language;
    arsort($this->languageNbre);
    $this->language = array_keys($this->languageNbre);
    return $this->language;
  }
  
  function show(string $docid): void {
    //echo "<pre>this="; print_r($this); echo "</pre>\n";
    $lang = isset($_GET['lang']) ? $_GET['lang'] : $this->language()[0];
    echo "<h3>",$this->title($lang) ? $this->title($lang) : $this->name,"</h3>\n";
    echo "languages: ",implode(', ', $this->language()),"<br>\n";
    echo "language: $lang<br>\n";
    echo "<ul>";
    foreach ($this->termList as $termid => $term) {
      $labelInLang = $term['labels']->labelInLang($lang);
      $pdocid = dirname($docid);
      //echo "pdocid=$pdocid<br>\n";
      $href = "?doc=$pdocid&amp;ypath=/search&amp;subject=".rawurlencode($term['labels']->label0());
      echo "<li><a href='$href'>$labelInLang</a> ($term[nbreOfOccurences])</li>\n";
    }
  }
  
  // améliore éventuellement la définition du cvoc
  function improve(array $cvoc) {
    if (!$this->title && isset($cvoc['title']))
      $this->title = $cvoc['title'];
    if (!$this->referenceDate && isset($cvoc['referenceDate']))
      $this->referenceDate = $cvoc['referenceDate'];
  }

  // recherche un subjectValue avec une langue par défaut donnée
  function findTerm(SimpleMlString $labels) {
    foreach ($this->termList as $noTerm => $term) {
      if ($completed = $term['labels']->equal($labels)) {
        $completed->check();
        $this->termList[$noTerm]['labels'] = $completed;
        return $noTerm;
      }
    }
    return -1;
  }
  
  // ajoute un mot-clé au cvoc, subject est soit un string soit un array représentant un multi-string
  function add(SimpleMlString $labels): void {
    if (($noTerm = $this->findTerm($labels)) <> -1) {
      $this->termList[$noTerm]['nbreOfOccurences']++;
    }
    else {
      $this->termList[] = [
        'labels'=> $labels,
        'nbreOfOccurences'=> 1,
      ];
    }
    foreach ($labels->language() as $lang) {
      if (!isset($this->languageNbre[$lang]))
        $this->languageNbre[$lang] = 1;
      else
        $this->languageNbre[$lang]++;
    }
  }
  
  // tri le vocabulaire sur les mots-clés pour une liste de langues
  function sort(array $languages) {
    $terms = [];
    foreach ($this->termList as $term) {
      $label = null;
      foreach ($languages as $lang) {
        if ($label = $term['labels']->label($lang))
          break;
      }
      if (!$label) {
        echo "term = "; print_r($term); var_dump($term);
        throw new Exception("Cvoc::sort() impossible");
      }
      $terms[$label] = $term;
    }
    ksort($terms, SORT_STRING);
    $this->termList = array_values($terms);
  }
  
  function id() { return rawurlencode($this->name); }
  
  // renvoie un résumé
  function abstract() {
    $abstract = [
      'id'=> $this->id(),
      'name'=> $this->name,
    ];
    if ($this->title)
      $abstract['title'] = $this->title;
    $abstract['nbreOflabels'] = count($this->termList);
    $abstract['language'] = $this->language();
    $abstract['languageNbre'] = $this->languageNbre;
    return $abstract;
  }
  
  // définit l'id d'un label
  //function labelid(string $label) { return rawurlencode($label); }
  
  // retourne l'id et le nom du cvoc et la liste des étiquettes et leur nbre d'occurences
  function asArray(): array {
    //print_r($this); die();
    $result = [
      'id'=> $this->id(),
      'name'=> $this->name,
      'nbreOflabels'=> count($this->termList),
      'termList'=> [],
    ];
    foreach ($this->termList as $no => $term) {
      $result['termList'][$no] = [
        'id'=> $no,
        'labels'=> $term['labels']->asArray(),
        'nbreOfOccurences'=> $term['nbreOfOccurences'],
      ];
    }
    return $result;
  }
  
  // retourne un label
  function getOne(string $termId) {
    if (!isset($this->termList[$termId])) {
      return null;
    }
    return [
      'id'=> $termId,
      'labels'=> $this->termList[$termId]['labels']->__toString(),
      'nbreOfOccurences'=> $this->termList[$termId]['nbreOfOccurences'],
    ];
  }
};

// un terme défini dans plusieurs langues
class SimpleMlString {
  private $labels; // [ lang => string ]
  
  function __construct($labels, $defaultLanguage) {
    if (is_string($labels))
      $this->labels = [$defaultLanguage => $labels];
    elseif (is_array($labels)) {
      foreach ($labels as $lang => $label) {
        if (!is_string($label)) {
          echo "label = "; print_r($label); echo "<br>\n";
          throw new Exception("SimpleMlString incorrect: label not a string");
        }
        if (!$label) {
          throw new Exception("SimpleMlString incorrect: label empty");
        }
        if (!is_string($lang)) {
          echo "lang = "; print_r($lang); echo "<br>\n";
          throw new Exception("SimpleMlString incorrect: lang not a string");
        }
        $this->labels[$lang] = $label;
      }
    }
    else
      throw new Exception("SimpleMlString incorrect: ni string ni array");
  }
  
  function check(): void {
    foreach ($this->labels as $lang => $label)
      if (!$label)
        throw new Exception("SimpleMlString::check() erreur");
  }
  
  function language() { return array_keys($this->labels); }
  
  function asArray() { return $this->labels; }
  
  function __toString(): string { return json_encode($this->labels, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE); }
    
  // si 2 SimpleMlString sont compatibles, cad:
  // (1) les chaines pour les mêmes langues sont les mêmes et
  // (2) au moins une langue en commun
  // alors retourne le SimpleMlString le plus complet
  // sinon retourne null
  function equal_int(SimpleMlString $other): ?SimpleMlString {
    $oneCommonLabel = false;
    $result = clone $other;
    foreach ($this->labels as $lang => $label) {
      if (isset($other->labels[$lang])) {
        if ($other->labels[$lang]<>$label)
          return null;
        else
          $oneCommonLabel = true;
      }
      else {
        $result->labels[$lang] = $label;
      }
    }
    if (!$oneCommonLabel)
      return null;
    return $result;
  }
  function equal(SimpleMlString $other): ?SimpleMlString {
    $result = $this->equal_int($other);
    //if ($result) echo "$this equal $other -> ",$result?$result:'null',"<br>\n";
    return $result;
  }
  
  // retourne le label dans la langue demandée ou null
  function label(string $lang): ?string { return isset($this->labels[$lang]) ? $this->labels[$lang] : null; }
  
  // retourne un label si possible dans la langue demandée, sinon une autre langue
  function labelInLang(string $lang) {
    return isset($this->labels[$lang]) ? $this->labels[$lang]
       : array_values($this->labels)[0].'@'.array_keys($this->labels)[0];
  }
  
  // retourne la première étiquette
  function label0(): string {
    return array_values($this->labels)[0];
  }
};


if (basename(__FILE__)<>basename($_SERVER['PHP_SELF'])) return;

  
$t1 = new SimpleMlString(['fre'=> "Outil de surveillance", 'eng'=> "Monitoring tool"], 'fre');
echo "t1=$t1<br>\n";
$t2 = new SimpleMlString(['fre'=> "Outil de surveillance"], 'fre');
echo "t2=$t2<br>\n";
echo "t1 eq t2 :",$t1->equal($t2) ? $t1->equal($t2) :'false',"<br>\n";
echo "t2 eq t1 :",$t2->equal($t1) ? $t2->equal($t1) :'false',"<br>\n";

$t3 = new SimpleMlString("xx", 'eng');
echo "t3=$t3<br>\n";
echo "t1 eq t3 :",$t1->equal($t3) ? $t1->equal($t3) :'false',"<br>\n";
echo "t2 eq t3 :",$t2->equal($t3) ? $t2->equal($t3) :'false',"<br>\n";

try {
  $t4 = new SimpleMlString(['eng'=>''], 'fre');
  echo "Erreur non traitée pour t4<br>\n";
} catch (Exception $e) {
  echo "Erreur correctement traitée pour t4<br>\n";
}
