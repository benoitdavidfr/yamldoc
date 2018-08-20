<?php
/*PhpDoc:
name: markerlib.inc.php
title: markerlib.inc.php - classe MarkerLib - bibliothèque de symboles
functions:
doc: doc intégrée en Php
*/
{
$phpDocs['markerlib.inc.php'] = <<<'EOT'
name: markerlib.inc.php
title: markerlib.inc.php - classe MarkerLib - bibliothèque de symboles
doc: |
  contient les définitions d'icones pour les cartes Leaflet
  La méthode asJavaScript() génère le code Javascript à intégrer en début de code de la carte
journal: |
  20/8/2018:
  - création
EOT;
}

class MarkerLib extends YamlDoc {
  protected $_c; // contenu du doc sous forme d'un array Php
  
  function __construct(&$yaml) { $this->_c = $yaml; }
  
  // permet d'accéder aux champs du document comme si c'était un champ de la classe
  function __get(string $name) { return isset($this->_c[$name]) ? $this->_c[$name] : null; }
  
  // décapsule l'objet et retourne son contenu sous la forme d'un array
  function asArray() { return $this->_c; }
  
  // retourne le fragment défini par path qui est une chaine
  function extract(string $ypath) { return YamlDoc::sextract($this->_c, $ypath); }
    
  // affiche le doc ou le fragment si ypath est non vide
  function show(string $docuid, string $ypath): void {
    //echo "<pre>"; print_r($this->data); echo "</pre>\n";
    showDoc($docuid, self::sextract($this->_c, $ypath));
  }
  
  // extrait le fragment défini par $ypath, utilisé pour générer un retour à partir d'un URI
  function extractByUri(string $docuri, string $ypath) {
    //echo "MarkerLib::extractByUri($docuri, $ypath)\n";
    //var_dump($this);
    if (preg_match('!^/([^/]+)$!', $ypath, $matches)) {
      $markerid = $matches[1];
      if (($pos = strrpos($markerid, '.')) && ($ext = substr($markerid, $pos)) && in_array($ext, ['.png','.jpg'])) {
        $markerid = substr($markerid, 0, $pos);
        if (!isset($this->markers[$markerid]))
          return null;
        Store::init();
        $sid = Store::id();
        //echo "<pre>"; print_r($_SERVER);
        //die();
        //header('Content-type: image/png');
        die(file_get_contents(__DIR__."/$sid$_SERVER[PATH_INFO]"));
      }
      if (!isset($this->markers[$markerid]))
        return null;
      echo "marker $markerid\n";
      return $this->markers[$markerid];
    }
    elseif ($ypath == '/-/asJavaScript') {
      header('Content-type: text/plain');
      die($this->asJavaScript($docuri));
    } else {
      $fragment = $this->extract($ypath);
      $fragment = self::replaceYDEltByArray($fragment);
      return $fragment;
    }
  }
  
  // génère le code JavaScript de définition de la bibliothèque
  /*
  var markerLib = {
    church: {
      icon: L.icon({
        iconUrl: '/yamldoc/id.php/markerlib/church.png',
        iconSize: [32, 37], iconAnchor: [22, 20], popupAnchor: [-3, -7]
      })
    }
  };
  */
  function asJavaScript(string $docuri) {
    $no = 0;
    //print_r($this);
    //print_r($_SERVER);
    echo "// code généré par MarkerLib::asJavaScript($docuri)\n";
    echo "var ",$this->javaScripVarName," = {\n";
    foreach ($this->markers as $id => $marker) {
      if ($no++)
        echo ",\n";
      $iconSize = implode(',', $marker['iconSize']);
      $iconAnchor = isset($marker['iconAnchor']) ? implode(',', $marker['iconAnchor']) : '0,0';
      $popupAnchor = isset($marker['popupAnchor']) ? implode(',', $marker['popupAnchor']) : '0,0';
      echo "  $id: {\n";
      echo "    icon: L.icon({\n";
      echo "      iconUrl: 'http://$_SERVER[SERVER_NAME]/yamldoc/id.php/$docuri/$id.$marker[extension]',\n";
      echo "      iconSize: [$iconSize], iconAnchor: [$iconAnchor], popupAnchor: [$popupAnchor]\n";
      echo "    })\n";
      echo "  }";
    }
    echo "\n};\n";
  }
};
