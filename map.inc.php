<?php
/*PhpDoc:
name: map.inc.php
title: map.inc.php - sous-classe de documents pour l'affichage d'une carte Leaflet
functions:
doc: <a href='/yamldoc/?action=version&name=map.inc.php'>doc intégrée en Php</a>
*/
{
$phpDocs['map.inc.php'] = <<<'EOT'
name: map.inc.php
title: map.inc.php - sous-classe de documents pour l'affichage d'une carte Leaflet
doc: |
  La carte peut être affichée par appel de son URI suivie de /display
  Chaque couche définie dans la carte génère un objet d'une sous-classe de LeafletLayer en fonction de son type.
  Le fichier map-default.yaml est utilisé pour définir une carte par défaut.
  Cette carte par défaut contient 3 couches de base et 0 overlay.
  
  A FAIRE:
    - définition de couche en fonction du zoom
    - par exemple troncon_route ou limite_administrative
  
journal: |
  6/8/2018:
    - affichage des propriétés d'un objet GeoJSON
    - stylage des objets GeoJSON par couche et en fonction d'un attribut
  5/8/2018:
    - création
EOT;
}

use Symfony\Component\Yaml\Yaml;

class Map extends YamlDoc {
  protected $_c; // contient les champs
  
  // crée un nouveau doc, $yaml est le contenu Yaml externe issu de l'analyseur Yaml
  // $yaml est généralement un array mais peut aussi être du texte
  function __construct(&$yaml) {
    $defaultParams = Yaml::parse(file_get_contents(__DIR__."/map-default.yaml"), Yaml::PARSE_DATETIME);
    $this->_c = $defaultParams;
    foreach ($yaml as $prop => $value) {
      $this->_c[$prop] = $value;
    }
    if ($this->bases) {
      foreach ($this->bases as $id => $layer) {
        $class = "Leaflet$layer[type]";
        if (!class_exists($class))
          throw new Exception("Erreur dans Map::__construct() le type de couche $layer[type] n'est pas autorisé");
        $this->_c['bases'][$id] = new $class($layer, $this->attributions);
      }
    }
    if ($this->overlays) {
      foreach ($this->_c['overlays'] as $id => $layer) {
        $class = "Leaflet$layer[type]";
        if (!class_exists($class))
          throw new Exception("Erreur dans Map::__construct() le type de couche $layer[type] n'est pas autorisé");
        $this->_c['overlays'][$id] = new $class($layer, $this->attributions);
      }
    }
  }
  
  // lit les champs
  function __get(string $name) { return isset($this->_c[$name]) ? $this->_c[$name] : null; }

  // affiche le sous-élément de l'élément défini par $ypath
  function show(string $docid, string $ypath): void {
    echo "Map::show($docid, $ypath)<br>\n";
    if (!$ypath || ($ypath=='/'))
      showDoc($docid, $this->_c);
    else
      showDoc($docid, $this->extract($ypath));
    //echo "<pre>"; print_r($this->_c); echo "</pre>\n";
  }
  
  // décapsule l'objet et retourne son contenu sous la forme d'un array
  // ce décapsulage ne s'effectue qu'à un seul niveau
  // Permet de maitriser l'ordre des champs
  function asArray() {
    $ret = $this->_c;
    foreach ($ret['bases'] as $lyrid => $layer) {
      $ret['bases'][$lyrid] = $layer->asArray();
    }
    foreach ($ret['overlays'] as $lyrid => $layer) {
      $ret['overlays'][$lyrid] = $layer->asArray();
    }
    return $ret;
  }

  // extrait le fragment du document défini par $ypath
  // Renvoie un array ou un objet qui sera ensuite transformé par YamlDoc::replaceYDEltByArray()
  // Utilisé par YamlDoc::yaml() et YamlDoc::json()
  // Evite de construire une structure intermédiaire volumineuse avec asArray()
  function extract(string $ypath) {
    return YamlDoc::sextract($this->_c, $ypath);
  }
  
  // extrait le fragment défini par $ypath, utilisé pour générer un retour à partir d'un URI
  function extractByUri(string $docuri, string $ypath) {
    if ($ypath=='/display')
      $this->display($docuri);
    else {
      $fragment = $this->extract($ypath);
      $fragment = self::replaceYDEltByArray($fragment);
      return $fragment;
    }
  }
  
  // affiche la carte
  function display(string $docid): void {
    //echo "Map::display($docid)<br>\n";
    //echo "<pre>_SERVER="; print_r($_SERVER); die();
    echo "<!DOCTYPE HTML><html><head>";
    echo "<title>",$this->title,"</title><meta charset='UTF-8'>\n";
    echo "<!-- meta nécessaire pour le mobile -->\n",
         '  <meta name="viewport" content="width=device-width, initial-scale=1.0,',
           ' maximum-scale=1.0, user-scalable=no" />',"\n";
    foreach ($this->stylesheets as $stylesheet)
      echo "  <link rel='stylesheet' href='$stylesheet'>\n";
    echo "  <script src='https://unpkg.com/leaflet@1.3/dist/leaflet.js'></script>\n";
    foreach ($this->plugins as $plugin)
      echo "  <script src='$plugin'></script>\n";
    echo "</head>\n";
    echo "<body>\n";
    echo "  <div id='map' style='height: ",$this->mapStyle['height'],
         "; width: ",$this->mapStyle['width'],"'></div>\n";
    echo "  <script>\n";
    echo "var map = L.map('map').setView([",implode(',',$this->view['latlon']),"], ",
         $this->view['zoom'],"); // view pour la zone\n";
    if ($this->locate)
      echo "map.locate({setView: ",$this->locate['setView']?'true':'false',
           ", maxZoom: ",$this->locate['maxZoom'],"});\n";
    echo "L.control.scale({position:'",$this->scaleControl['position'],"', ",
         "metric:",$this->scaleControl['metric']?'true':'false',", ",
         "imperial:",$this->scaleControl['imperial']?'true':'false',"}).addTo(map);\n";
         
    echo "var bases = {\n";
    if ($this->bases) {
      foreach ($this->bases as $lyrid => $layer) {
        $layer->showAsCode("$docid/$lyrid");
      }
    }
    echo "};\n";
         
    echo "var overlays = {\n";
    if ($this->overlays) {
      foreach ($this->overlays as $lyrid => $layer) {
        $layer->showAsCode("$docid/$lyrid");
      }
    }
    echo "};\n";
    echo "map.addLayer(bases[\"",$this->bases[$this->addLayer]->title,"\"]);\n";
    // ajout de l'outil de sélection de couche
    echo "L.control.layers(bases, overlays).addTo(map);\n";
    echo "  </script>\n</body></html>\n";
    die();
  }
};

// création d'une classe par type de couche pour modulariser l'affichage du code
abstract class LeafletLayer implements YamlDocElement {
  protected $_c; // contient les champs
  
  function __construct(&$yaml, $attributions) {
    $this->_c = [];
    foreach ($yaml as $prop => $value) {
      $this->_c[$prop] = $value;
    }
    if (isset($this->options['attribution'])) {
      $attr = $this->options['attribution'];
      if (isset($attributions[$attr]))
        $this->_c['options']['attribution'] = $attributions[$attr];
    }
  }
  
  // lit les champs
  function __get(string $name) { return isset($this->_c[$name]) ? $this->_c[$name] : null; }
  
  function extract(string $ypath) { return YamlDoc::sextract($this->_c, $ypath); }
  
  function asArray() { return $this->_c; }
  
  function show(string $docid, string $prefix='') { showDoc($docid, $this->_c); }
};

// classe pour couche L.TileLayer
class LeafletTileLayer extends LeafletLayer {
  function showAsCode(string $name): void {
    echo "  \"$this->title\" : new L.TileLayer(\n";
    echo "    '$this->url',\n";
    echo '    ',json_encode($this->options, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE),"\n";
    echo "  ),\n";
  }
};

// classe pour couche L.UGeoJSONLayer
class LeafletUGeoJSONLayer extends LeafletLayer {
  function showAsCode(string $lyrid): void {
    //print_r($this);
    echo "  \"$this->title\" : new L.UGeoJSONLayer({\n";
    echo "    lyrid: '$lyrid',\n";
    //echo "    title: '$this->title',\n";
    echo "    endpoint: '$this->endpoint',\n";
    // affichage des propriétés du feature
    //$popup = "'<pre>'+JSON.stringify(feature.properties,null,' ').replace(/[\{\}\"]/g,'')+'</pre>'";
    // affichage de la layer (debuggage)
    //$popup = "'<pre>'+JSON.stringify(layer,null,' ').replace(/[\{\}\"]/g,'')+'</pre>'";
    // test d'affichage du lyrid
    //$popup = "'<b>'+layer.options.lyrid+'</b>'";
    // affichage lyrid + propriétés
    $lyrurl = "$this->endpoint?zoom='+map.getZoom()+'";
    $popup = "'<b><a href=\"$lyrurl\">'+layer.options.lyrid+', zoom='+map.getZoom()+'</a></b><br>'"
      ."+'<pre>'+JSON.stringify(feature.properties,null,' ').replace(/[\{\}\"]/g,'')+'</pre>'";
    echo "    onEachFeature: function (feature, layer) {\n",
         //"      console.log(Object.values(layer));\n",
         "      layer.bindPopup($popup);\n",
         "    },\n";
    if ($this->style && is_array($this->style))
      echo "    style: ",json_encode($this->style),",\n";
    elseif ($this->style && is_string($this->style))
      echo "    style: ",$this->style,",\n";
    echo "    usebbox: true\n";
    echo "  }),\n";
  }
};
