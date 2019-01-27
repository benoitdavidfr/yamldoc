<?php
/*PhpDoc:
name: catalog.inc.php
title: catalog.inc.php - classes des catalogues V2
doc: |
  <a href='/yamldoc/?action=version&name=catalog.inc.php'>voir le code</a>
*/
{
$phpDocs['catalog.inc.php'] = <<<'EOT'
name: catalog.inc.php
title: catalog.inc.php - classes YamlCatalog des catalogues V2
doc: |
  Dans la première version, les catalogues sont composés d'un champ title et d'un champ contents.  
  Le champ contents correspond à un dictionnaire [ docid => [ 'title' => titre ]]  
  En V2, on conserve la compatibilité en permettant d'avoir d'autres champs.
journal:
  26/1/2019:
    - prise en compte de l'utilisation du champ $schema à la place de yamlClass
  18/7/2018:
    - prise en compte de la nouvelle classe YamlDoc
  21/6/2018:
    - V2
  12/5/2018:
    - scission de yd.inc.php
EOT;
}
use Symfony\Component\Yaml\Yaml;

// classe des catalogues
class YamlCatalog extends BasicYamlDoc {
  
  function show(string $ypath=''): void {
    //echo "<pre>"; print_r($this->data); echo "</pre>\n";
    $docid = $this->_id;
    $storepath = Store::storepath();
    if (is_dir(__DIR__."/../$storepath/$docid")) {
      //echo "$_GET[doc] est un répertoire";
      $dirname = "$docid/";
    }
    elseif (($dirname = dirname($docid)) == '.')
      $dirname = '';
    else
      $dirname .= '/';
    //echo "dirname=$dirname<br>\n";
    if (isset($this->data['title']))
      echo "<h1>",$this->data['title'],"</h1>\n";
    $otherKeyShown = false;
    foreach ($this->data as $key => $value) {
      if ($key=='contents') {
        if ($otherKeyShown)
          echo "<h3>Contenu du catalogue</h3>\n";
        echo "<ul>\n";
        foreach($this->contents as $duid => $item) {
          $title = isset($item['title']) ? $item['title'] : $duid;
          echo "<li><a href='?doc=$dirname$duid'>$title</a>\n";
        }
        echo "</ul>\n";
      }
      elseif (!in_array($key,['title','$schema','authorizedWriters','yamlPassword'])) {
        echo "<h3>$key</h3>\n";
        showDoc($_GET['doc'], $this->data[$key], $key);
        $otherKeyShown = true;
      }
    }
  }
  
  // clone un doc dans un catalogue
  static function clone_in_catalog(string $newdocuid, string $olddocuid, string $catuid) {
    $contents = Yaml::parse(ydread($catuid));
    //print_r($contents);
    $title = $contents['contents'][$olddocuid]['title'];
    $contents['contents'][$newdocuid] = ['title'=> "$title cloné $newdocuid" ];
    ydwrite($catuid, Yaml::dump($contents, 999));
  }
  
  static function delete_from_catalog(string $docuid, string $catuid) {
    $contents = Yaml::parse(ydread($catuid));
    unset($contents['contents'][$docuid]);
    ydwrite($catuid, Yaml::dump($contents, 999));
  }
};

// classe des catalogues d'accueil
class YamlHomeCatalog extends YamlCatalog {
  function isHomeCatalog() { return true; }
};
