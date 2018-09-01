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
  La classe SubjectList liste les mots-clés présents dans un géocatalogue.

  Liste des points d'entrée de l'API:
    - /  - liste des cvocs
    - /{cvoc} - description d'un cvoc dont la liste des labels
    - /{cvoc}/{termid} - détail sur un terme du cvoc
    - /*  - retour complet des cvocs avec leur contenu
    
  Les noms utilisés des cvoc ne peuvent pas être des identifiants car inadapté pour un URI.
  Je construis systématiquement pour chaque cvoc un id comme rawurlencode(name).
  
  De même pour les labels des cvocs.

journal:
  29/8/2018:
    - création
EOT;
}

class SubjectList extends YamlDoc {
  private $cvocs = []; // [ cvocname => Cvoc ]
  
  function __construct(&$yaml) { }
  
  // affiche le sous-élément de l'élément défini par $ypath
  function show(string $docid, string $ypath): void {
  }
  
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
    echo "SubjectList::extractByUri($docuri, $ypath)<br>\n";
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
    elseif (preg_match('!^/([^/]+)$!', $ypath, $matches)) {
      // affichage des mots-clés d'un cvoc
      $vocname = rawurldecode($matches[1]);
      echo "vocname='$vocname'<br>\n";
      if (!isset($this->cvocs[$vocname]))
        return null;
      else
        return $this->cvocs[$vocname]->asArray();
    }
    elseif (preg_match('!^/([^/]+)/([^/]+)$!', $ypath, $matches)) {
      // affichage d'un mot-clé d'un cvoc
      $cvocname = rawurldecode($matches[1]);
      $labelname = rawurldecode($matches[2]);
      echo "vocname='$vocname'<br>\n";
      if (!isset($this->cvocs[$vocname]))
        return null;
      else
        return $this->cvocs[$vocname]->getOne($labelname);
    }
    else
      return null;
  }
  
  // Le document est stocké uniquement sous la forme d'un .pser
  function writePser(string $docuid): void { YamlDoc::writePserReally($docuid); }

  // ajoute un mot-clé
  function add(array $subject): void {
    if (!isset($subject['value']))
      return;
    $cvocid = isset($subject['cvoc']) ? $subject['cvoc'] : 'none';
    if (!isset($this->cvocs[$cvocid]))
      $this->cvocs[$cvocid] = new Cvoc($cvocid);
    $this->cvocs[$cvocid]->add($subject['value']);
  }
  
  // tri chaque vocabulaire sur les mots-clés
  function sortVocs() {
    foreach ($this->cvocs as $cvoc)
      $cvoc->sort();
  }
};

// Gestion de vocabulaires contrôlés
class Cvoc {
  private $name; // identifiant donné au vocabulaire contrôlé
  private $labelList = []; // [ label => [ 'nbreOfOccurences'=> nbre d''occurences ] ]
    
  function __construct(string $name) { $this->name = $name; }

  // ajoute un mot-clé àà ce cvoc
  function add(string $subjectValue): void {
    //echo "subject="; print_r($subject); echo "<br>\n";
    if (!isset($this->labelList[$subjectValue]))
      $this->labelList[$subjectValue]['nbreOfOccurences'] = 1;
    else
      $this->labelList[$subjectValue]['nbreOfOccurences']++;
    //echo "Cvoc::add($subject[value])<br>\n"; print_r($this); echo "<br>\n";
  }
  
  // tri le vocabulaire sur les mots-clés
  function sort() {
    ksort($this->labelList, SORT_STRING);
  }
  
  function id() { return rawurlencode($this->name); }
  
  // renvoie un résumé
  function abstract() {
    return [
      'id'=> $this->id(),
      'name'=> $this->name,
      'nbreOflabels'=> count($this->labelList),
    ];
  }
  
  // définit l'id d'un label
  function labelid(string $label) { return rawurlencode($label); }
  
  // retourne l'id et le nom du cvoc et la liste des étiquettes et leur nbre d'occurences
  function asArray(): array {
    //echo "Cvoc::asArray()<br>\n"; print_r($this); echo "<br>\n";
    $result = [
      'id'=> $this->id(),
      'name'=> $this->name,
      'nbreOflabels'=> count($this->labelList),
      'labelList'=> [],
    ];
    foreach ($this->labelList as $label => $rec) {
      $result['labelList'][$this->labelid($label)] = [
        'id'=> $this->labelid($label),
        'label'=> $label,
        'nbreOfOccurences'=> $rec['nbreOfOccurences'],
      ];
    }
    return $result;
  }
  
  // retourne un label
  function getOne(string $label) {
    if (!isset($this->labelList[$label])) {
      $label = rawurldecode($label);
      if (!isset($this->labelList[$label]))
        return null;
    }
    return [
      'id'=> rawurlencode($label),
      'label'=> $label,
      'nbreOfOccurences'=> $this->labelList[$label]['nbreOfOccurences'],
    ];
  }
};
