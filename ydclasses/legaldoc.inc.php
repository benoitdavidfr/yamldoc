<?php
/*PhpDoc:
name: legaldoc.inc.php
title: gestion d'un texte juridique
doc: |
  voir le code
*/
{ // doc 
$phpDocs[basename(__FILE__)]['file'] = <<<EOT
name: legaldoc.inc.php
title: legaldoc.inc.php - gestion d'un texte juridique
doc: |
  La structuration est inspirée de celle de la directive Inspire.
  Ce fichier définit les classes LegalDoc et LegalPart.
  
  Idée: utiliser isReplacedBy pour exprimer par exemple que le document annexe I
  est remplacé par le thésaurus inspireThemesAnnexI  
  A l'intérieur du document utiliser:
  
      isReplacedBy: { ypath: /schemes/annex1Themes }
      
  Entre documents, utiliser l'URI:
  
      isReplacedBy: http://id.georef.eu/inspire-directive/schemes/annex1Themes
      
journal:
  23/7/2018:
  - création
EOT;
}
//require_once __DIR__.'/../vendor/autoload.php';
//require_once __DIR__."/../yamldoc/markdown/PHPMarkdownLib1.8.0/Michelf/MarkdownExtra.inc.php";
require_once __DIR__.'/mlstring.inc.php';

//use Symfony\Component\Yaml\Yaml;
//use Michelf\MarkdownExtra;

{ // doc 
$phpDocs[basename(__FILE__)]['classes']['LegalDoc'] = <<<EOT
name: class LegalDoc
title: définition de la classe LegalDoc gérant un texte juridique
doc: |
  document juridique pouvant définir des thésaurus (et un modèle de données ?)
    - Il hérite de YamlSkos et comporte donc des champs title, domainScheme, domains, schemes et concepts,
    - il comporte en outre les champs:
      - visa qui est un texte mono ou multi-lingues
      - recitals qui est un dictionnaire de textes mono ou multi-lingues
      - body qui est un dictionnaire de LegalPart
      - signature qui est un texte mono ou multi-lingue
      - notes qui est un dictionnaire de textes mono ou multi-lingue
      - annexes qui est un dictionnaire de LegalPart
    - la liste de LegalPart de body et annexes est lue en séquence ; si la clé d'un LegalPart n'a pas été repéré
      comme partie d'un précédent LegalPart alors il est au niveau 1.
EOT;
}
class LegalDoc extends YamlSkos {
  static $keyTranslations = [
    'recitals'=> ['fr'=>"Considérants", 'en'=>"Recitals"],
    'notes'=> ['fr'=>"Notes", 'en'=>"Notes"],
    'xxx'=> ['fr'=>"xxx", 'en'=>"yyy"],
  ];
  protected $visa; // MLString
  protected $recitals; // [key => MLString]
  protected $body;  // [key => LegalPart]
  protected $signature; // MLString
  protected $notes; // [key => MLString]
  protected $annexes;  // [key => LegalPart]
  
  function __construct($yaml, string $docid) {
    parent::__construct($yaml, $docid);
    if (isset($this->_c['visa'])) {
      $this->visa = new MLString($this->_c['visa'], $this->language);
      unset($this->_c['visa']);
    }
    if (isset($this->_c['recitals'])) {
      $this->recitals = [];
      foreach ($this->_c['recitals'] as $key => $recital) {
        $this->recitals[$key] = new MLString($recital, $this->language);
      }
      unset($this->_c['recitals']);
    }
    if (isset($this->_c['body'])) {
      $this->body = [];
      foreach ($this->_c['body'] as $key => $legalPart) {
        $this->body[$key] = new LegalPart($legalPart, $this->language);
      }
      unset($this->_c['body']);
    }
    if (isset($this->_c['signature'])) {
      $this->signature = new MLString($this->_c['signature'], $this->language);
      unset($this->_c['signature']);
    }
    if (isset($this->_c['notes'])) {
      $this->notes = [];
      foreach ($this->_c['notes'] as $key => $notes) {
        $this->notes[$key] = new MLString($notes, $this->language);
      }
      unset($this->_c['notes']);
    }
    if (isset($this->_c['annexes'])) {
      $this->annexes = [];
      foreach ($this->_c['annexes'] as $key => $legalPart) {
        $this->annexes[$key] = new LegalPart($legalPart, $this->language);
      }
      unset($this->_c['annexes']);
    }
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
  
  // affichage du document ou d'un fragment
  function show(string $ypath=''): void {
    $docid = $this->_id;
    if (!$ypath) {
      echo '<h1>',$this->title,"</h1>\n";
      if ($this->alternative)
        echo '<b>',$this->alternative,"</b></p>\n";
      echo "<a href='?doc=$docid&amp;ypath=/visa'>Visa</a><br>\n";
      echo "<a href='?doc=$docid&amp;ypath=/recitals'>Considérants</a><br>\n";
      echo "<h3>Corps du texte</h3>\n";
      echo "<ul>";
      foreach ($this->body as $key => $legalPart)
        echo "<li><a href='?doc=$docid&amp;ypath=/body/$key'>$legalPart</a></li>\n";
      echo "</ul>\n";
      echo "<a href='?doc=$docid&amp;ypath=/signature'>Signature</a><br>\n";
      echo "<a href='?doc=$docid&amp;ypath=/notes'>Notes</a><br>\n";
      echo "<h3>Annexes</h3>\n";
      echo "<ul>";
      foreach ($this->annexes as $key => $legalPart)
        echo "<li><a href='?doc=$docid&amp;ypath=/annexes/$key'>$legalPart</a></li>\n";
      echo "</ul>\n";
      echo "<a href='?doc=$docid&amp;ypath=/parent'>Vocabulaires définis par ce texte</a><br>\n";
    }
    elseif ($ypath == '/parent') {
      parent::show();
    }
    elseif (preg_match('!^/(visa|signature)$!', $ypath, $matches)) {
      $prop = $matches[1];
      showDoc($docid, $this->$prop->__toString());
    }
    elseif (preg_match('!^/(recitals|notes)$!', $ypath, $matches)) {
      $prop = $matches[1];
      echo "<h2>",self::keyTranslate($prop),"</h2>\n";
      echo "<table border=1>\n";
      foreach ($this->$prop as $key => $value) {
        echo "<tr><td><a href='?doc=$docid&amp;ypath=/$prop/$key'>$key</a></td><td>";
        showDoc($docid, $value->__toString());
        echo "</td></tr>\n";
      }
      echo "</table>\n";
    }
    elseif (preg_match('!^/(recitals|notes)/([^/]*)$!', $ypath, $matches)) {
      $prop = $matches[1];
      $key = $matches[2];
      echo "<table border=1>\n";
      echo "<tr><td>$key</td><td>";
      showDoc($docid, $this->$prop[$key]->__toString());
      echo "</td></tr>\n";
      echo "</table>\n";
    }
    elseif (preg_match('!^/body/([^/]+)(/paragraphs/[^/]+)?$!', $ypath, $matches)) {
      $key = $matches[1];
      $ypath = isset($matches[2]) ? $matches[2] : '';
      $this->body[$key]->show($docid, $ypath, $this, 'body');
    }
    elseif (preg_match('!^/annexes/([^/]+)(/paragraphs/[^/]+)?$!', $ypath, $matches)) {
      $key = $matches[1];
      $ypath = isset($matches[2]) ? $matches[2] : '';
      $this->annexes[$key]->show($docid, $ypath, $this, 'annexes');
    }
    else {
      try {
        parent::show($docid, $ypath);
      }
      catch (Exception $exception) {
        echo "ypath=$ypath inconnu fichier ",__FILE__,", ligne ",__LINE__,"<br>\n";
      }
    }
  }
  
  // décapsule l'objet et retourne son contenu sous la forme d'un array
  function asArray() {
    $result = parent::asArray();
    foreach (['visa','recitals','body','signature','notes','annexes'] as $field)
      $result[$field] = $this->$field;
    return $result;
  }
  
  // renvoie un array du fragment défini par ypath
  function extract(string $ypath) {
    //echo "LegalDoc::extract($ypath)<br>\n";
    try {
      return parent::extract($ypath);
    }
    catch (Exception $exception) {
    }
    if (preg_match('!^/(visa|recitals|body|signature|notes|annexes)$!', $ypath, $matches)) {
      return $this->{$matches[1]};
    }
    elseif (preg_match('!^/(recitals|body|notes|annexes)/([^/]*)$!', $ypath, $matches)) {
      return $this->{$matches[1]}[$matches[2]];
    }
    elseif (preg_match('!^/(recitals|body|notes|annexes)/([^/]*)/!', $ypath, $matches)) {
      $spath = substr($ypath, strlen($matches[0])-1);
      return $this->{$matches[1]}[$matches[2]]->extract($spath);
    }
    else
      throw new Exception("Erreur LegalDoc::extract(ypath=$ypath), ypath non reconnu");
  }
  
  function dump(string $ypath=''): void {
    echo "<pre>"; var_dump($this); echo "</pre>\n";
  }
};

{ // doc 
$phpDocs[basename(__FILE__)]['classes']['LegalPart'] = <<<'EOT'
name: class LegalPart
title: définition de la classe LegalPart, élément directement identifiable d'un LegalDoc (article, chapitre, ...)
doc: |
  Chaque LegalPart comprend:
    - un titre (title)
    - évent. un en-tête (head) qui est une texte mono ou multi-lingue
    - soit:
      - des sous-parties (hasPart) identifiées par leur clé
      - un dictionnaire de paragraphes (paragraph), chacun étant un texte mono ou multi-lingue,
      - un text qui est un texte mono ou multi-lingue
    - évent. une queue (tail) qui est un texte mono ou multi-lingue
  Un LegalPart peut porter d'autres champs comme:
      - source
      - ...

  La classe LegalPart implémente YamlDocElement.
  Toutes les infos sont stockées dans la propriété $_c.
  A la construction les champs string et text sont transformés en objet MLString.
EOT;
}
class LegalPart implements YamlDocElement {
  static $strFields = ['title'];
  static $txtFields = ['head','text','tail'];
  protected $_c; // stockage du contenu comme array

  function __construct(array $yaml, array $language) {
    $this->_c = $yaml;
    // remplace les champs string et text par des MLString
    foreach (array_merge(self::$strFields,self::$txtFields) as $field) {
      if ($this->$field)
        $this->_c[$field] = new MLString($this->$field, $language);
    }
    if ($this->paragraph)
      foreach ($this->paragraph as $key => $paragraph)
        $this->_c['paragraph'][$key] = new MLString($paragraph, $language);
  }
  
  function __get(string $name) { return isset($this->_c[$name]) ? $this->_c[$name] : null; }
  
  function asArray(): array {
    $result = [];
    foreach ($this->_c as $field => $value) {
      $result[$field] = $value;
    }
    return $result;
  }
  
  function extract(string $ypath) { return YamlDoc::sextract($this->asArray(), $ypath); }
  
  // le prefLabel est utilisé pour afficher un élément
  function __tostring(): string { return $this->title->__toString(); }
  
  function show(string $docid, string $ypath, LegalDoc $doc, string $prop) {
    if ($ypath=='') {
      echo "<h2>$this</h2>\n";
      if ($this->head)
        echo showDoc($docid, $this->head->__toString());
      if ($this->text)
        echo showDoc($docid, $this->text->__toString());
      if ($this->paragraph) {
        echo "<table border=1>\n";
        foreach ($this->paragraph as $key => $paragraph) {
          echo "<tr><td><a href='?doc=$docid&amp;ypath=$_GET[ypath]/paragraphs/$key'>$key</a></td><td>";
          showDoc($docid, $paragraph);
          echo "</td></tr>\n";
        }
        echo "</table>\n";
      }
      if ($this->hasPart) {
        echo "<ul>\n";
        foreach ($this->hasPart as $part)
          echo "<li><a href='?doc=$docid&amp;ypath=/$prop/$part'>",$doc->$prop[$part],"</a></li>\n";
        echo "</ul>\n";
        
      }
      if ($this->tail)
        echo showDoc($docid, $this->tail->__toString());
    }
    elseif (preg_match('!^/paragraphs/([^/]+)$!', $ypath, $matches)) {
      $pkey = $matches[1];
      echo "<h2>$this § $pkey</h2>\n";
      if ($this->paragraph) {
        echo "<table border=1><tr><td>$pkey</td><td>";
        showDoc($docid, $this->paragraph[$pkey]->__toString());
        echo "</td></tr></table>\n";
      }
    }
    else
      echo "ypath=$ypath inconnu ligne ",__LINE__,"<br>\n";
  }
};
