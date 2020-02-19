<?php
/*PhpDoc:
name: ydata.inc.php
title: ydata.inc.php - sous-classe de documents pour la gestion des données
functions:
doc: <a href='/yamldoc/?action=version&name=ydata.inc.php'>doc intégrée en Php</a>
includes: [ ../../schema/jsonschema.inc.php, yamldoc.inc.php]
*/
{ // doc 
$phpDocs['ydata.inc.php']['file'] = <<<'EOT'
name: ydata.inc.php
title: ydata.inc.php - sous-classes YData et YDataTable pour la gestion des données
journal: |
  3-5/1/2019:
  - correction affichage
  - ajout test de conformité d'une table à son schéma
  - traitement du ypath
  29/7/2018:
  - mécanismes d'accès de base
  - manque projection, sélection
  - manque json-schema
EOT;
}
require_once __DIR__.'/../../schema/jsonschema.inc.php';
if (basename(__FILE__) == basename($_SERVER['PHP_SELF'])) { // tests unitaires 
  require_once 'yamldoc.inc.php';
}
use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Yaml\Exception\ParseException;

//echo '<pre>'; print_r($_SERVER);

{ // doc 
$prototype = (isset($_SERVER['HTTP_HOST'])  && ($_SERVER['HTTP_HOST']=='localhost') ? '' : 'http://georef.eu')
    .'/yamldoc/?doc=dublincore';
$phpDocs['ydata.inc.php']['classes']['YData'] = <<<"EOT"
name: class YData
title: gestion des données structurées en tables
doc: |
  Un document YData correspond à un ensemble de tables, chacune composée d'un ensemble de lignes appelées n-uplets,
  qui doivent tous respecter un schéma JSON commun défini pour la table.  
  Chaque n-uplet est identifié par un champ particulier noté _id comme dans MongoDB.  
  Cette structuration homogène des n-uplets permet de leur appliquer des traitements,
  comme de sélectionner uniquement certains champs dans les n-uplets.  
  Chaque table comporte des métadonnées.
  
  Cette classe remplace la classe YamlData, elle utilise les schémas JSON et hérite de YamlDoc.
  
  Un document YData contient une liste de tables stockée dans une structure Yaml
      tables:
        {nomtable}:
          title: titre de la table
          data: enregistrements contenus dans la table
  
  Une version serialisée du doc est enregistrée pour accélérer la lecture des gros documents.
  
  
  prototype de Ydata: [$prototype]($prototype)
  
  Le ypath peut prendre une des formes suivantes:
  
    - métadonnée du document,
      [ex]($prototype&ypath=/title)
    - une table, renvoie la table y compris ses MD,
      [ex]($prototype&ypath=/dcmes)
    - métadonnée d'une table dont le nom de MD ne correspond pas à un identifiant de tuple,
      [ex]($prototype&ypath=/dcmes/elementURI)
    - métadonnée d'une table dont le nom de MD correspond à un identifiant de tuple,
      [ex]($prototype&ypath=/dcmes/_title)
    - un tuple d'une table identifié par sa clé,
      [ex]($prototype&ypath=/dcmes/subject)
    - valeur d'un champ d'un tuple d'une table, tuple identifié par sa clé,
      [ex]($prototype&ypath=/dcmes/subject/definition)
    - valeur d'un sous-champ d'un tuple d'une table, tuple identifié par sa clé,
      [ex]($prototype&ypath=/dcmes/subject/definition/fr),
      [ex]($prototype&ypath=/dcmes/description/refinements/tableOfContents/definition/fr)
    - valeur d'un champ d'un tuple d'une table, tuple identifié par une valeur qqc,
      [ex]($prototype&ypath=/dcmes/name.fr=Sujet/definition)
    - valeur d'un champ des tuples d'une table,
      [ex]($prototype&ypath=/dcmes/*/definition)
    - valeurs de champs des tuples d'une table,
      [ex]($prototype&ypath=/dcmes/*/name,definition),
      [ex]($prototype&ypath=/dcmes/*/name.fr,definition.fr),
      [ex]($prototype&ypath=/dcmes/*/name.fr,definition.fr,refinements.*.name.fr)
      [ex]($prototype&ypath=/dcmes/*/_id,name.fr,definition.fr,refinements.*.name.fr,refinements.*._id)
  
EOT;
}
class YData extends YamlDoc {
  protected $_c; // contient les champs
  
  // crée un nouveau doc, $yaml est le contenu Yaml externe issu de l'analyseur Yaml
  function __construct($yaml, string $docid) {
    $this->_c = [];
    $this->_id = $docid;
    if (isset($yaml['data']) && isset($yaml['tables']))
      throw new Exception("Erreur de création de YData contient data et tables");
    foreach ($yaml as $prop => $value) {
      if (in_array($prop, ['_id','_c']))
        throw new Exception("Erreur de création de YData contient $prop");
      if ($prop == 'tables') {
        $tables = $value;
        $this->_c['tables'] = [];
        if ($tables) {
          foreach ($tables as $tabid => $table) {
            if ($table) {
              foreach ($table as $prop => $value) {
                if ($prop == 'data')
                  $this->_c['tables'][$tabid]['data'] = $value ? new YDataTable($value) : null;
                else
                  $this->_c['tables'][$tabid][$prop] = $value;
              }
            }
          }
        }
      }
      else
        $this->_c[$prop] = $value;
    }
  }
  
  // lit un champ
  function __get(string $name) { return isset($this->_c[$name]) ? $this->_c[$name] : null; }

  // affiche le sous-élément de l'élément défini par $ypath
  function show(string $ypath=''): void {
    $docid = $this->_id;
    //echo "YData::show($docid, $ypath)<br>\n";
    if (!$ypath || ($ypath=='/'))
      showDoc($docid, $this->_c);
    else
      showDoc($docid, $this->extract($ypath));
    //echo "<pre>"; print_r($this->_c); echo "</pre>\n";
  }
  
  // fonction dump par défaut, dump le document et non le fragment
  function dump(string $ypath=''): void { var_dump($this->_c); }
  
  // décapsule l'objet et retourne son contenu sous la forme d'un array
  // ce décapsulage ne s'effectue qu'à un seul niveau
  // Permet de maitriser l'ordre des champs
  function asArray() { return $this->_c; }

  // extrait le fragment du document défini par $ypath
  // Renvoie un array ou un objet qui sera ensuite transformé par YamlDoc::replaceYDEltByArray()
  // Utilisé par YamlDoc::yaml() et YamlDoc::json()
  // Evite de construire une structure intermédiaire volumineuse avec asArray()
  function extract(string $ypath) {
    //echo "YData::sextract(ypath=$ypath)<br>\n";
    if (!$ypath || ($ypath=='/'))
      return $this;
    if ($this->data) {
      if ($res = $this->data->extract($ypath))
        return $res;
    }
    elseif ($this->tables) {
      if (preg_match('!^/([^/]+)(/.+)?$!', $ypath, $matches)) {
        //print_r($matches); echo "<br>\n";
        $tabid = $matches[1];
        $ypath2 = isset($matches[2]) ? $matches[2] : '';
        if (isset($this->tables[$tabid])) {
          $table = $this->tables[$tabid];
          if (!$ypath2)
            return $table;
          if ($res = $table['data']->extract($ypath2))
            return $res;
          if (!preg_match('!^/([^/]+)$!', $ypath2, $matches)) {
            echo "$ypath don't match line ".__LINE__."<br>\n";
            return null;
          }
          // cas d'un champ de MD sur la table
          $propname = $matches[1];
          if (substr($propname, 0, 1)=='_')
            $propname = substr($propname, 1);
          if (isset($table[$propname]))
            return $table[$propname];
        }
      }
    }
    if (!preg_match('!^/([^/]+)$!', $ypath, $matches)) {
      echo "$ypath don't match line ".__LINE__."<br>\n";
      return null;
    }
    // cas d'un champ de MD sur le doc
    $propname = $matches[1];
    if (substr($propname, 0, 1)=='_')
      $propname = substr($propname, 1);
    return $this->$propname;
  }
    
  // extrait le fragment défini par $ypath, utilisé pour générer un retour à partir d'un URI
  function extractByUri(string $ypath) {
    //echo "YData::extractByUri($docuri, $ypath)<br>\n";
    return self::replaceYDEltByArray($this->extract($ypath));
  }
  
  // un .pser est généré automatiquement à chaque mise à jour du .yaml
  function writePser(): void { YamlDoc::writePserReally(); }
  
  function checkSchemaConformity(string $ypath): void {
    echo "YData::checkSchemaConformity(ypath=$ypath)<br>\n";
    if (!$ypath || ($ypath=='/')) { // validation du doc / schéma ydata.schema.yaml
      if (!is_file(__DIR__.'/YData.sch.yaml')) {
        echo "Erreur fichier YData.sch.yaml absent<br>\n";
        return;
      }
      JsonSchema::autoCheck(__DIR__.'/YData.sch.yaml', [
        'showWarnings'=> "ok schéma conforme au méta-schéma<br>\n",
        'showErrors'=> "KO schéma NON conforme au méta-schéma<br>\n",
        //'verbose'=> true,
      ]);
      $schema = new JsonSchema(__DIR__.'/YData.sch.yaml');
      $schema->check($this->_c, [
        'showWarnings'=> "ok doc conforme au schéma YData<br>\n",
        'showErrors'=> "KO doc NON conforme au schéma YData<br>\n",
      ]);
      foreach ($this->tables as $tableId => $table) {
        if (!isset($table['dataSchema']) || !isset($table['data'])) {
          echo "Erreur: champ dataSchema ou data absent de la table $tableId <br>\n";
        }
        else {
          $metaschema = new JsonSchema('http://json-schema.org/draft-07/schema#');
          $metaschema->check($table['dataSchema'], [
            'showWarnings'=> "ok schéma de la table $tableId conforme au méta-schéma<br>\n",
            'showErrors'=> "KO schéma de la table $tableId NON conforme au méta-schéma",
          ]);
          $schema = new JsonSchema($table['dataSchema']);
          $status = $schema->check($table['data']->asArray(), [
            'showWarnings'=> "ok data conforme au schéma de la table $tableId<br>\n",
            'showErrors'=> "KO data NON conforme au schéma de la table $tableId<br>\n",
          ]);
        }
      }
    }
    else {
      // si le path pointe directement dans les données, je remonte dans le document de la table
      if (substr($ypath, -2)=='/*')
        $ypath = substr($ypath, 0, strlen($ypath)-2);
      $table = $this->extractByUri($ypath);
      //echo '<pre>',Yaml::dump($subdoc, 999),"</pre>\n";
      if (!isset($table['dataSchema']) || !isset($table['data'])) {
        echo "Erreur: champ dataSchema ou data absent de la table $ypath <br>\n";
        return;
      }
      $metaschema = new JsonSchema('http://json-schema.org/draft-07/schema#');
      $metaschema->check($table['dataSchema'], [
        'showWarnings'=> "ok schéma conforme au méta-schéma<br>\n",
        'showErrors'=> "KO schéma NON conforme au méta-schéma",
      ]);
      $schema = new JsonSchema($table['dataSchema']);
      $status = $schema->check($table['data'], [
        'showWarnings'=> "ok data conforme au schéma de la table<br>\n",
        'showErrors'=> "KO data NON conforme au schéma de la table<br>\n",
      ]);
    }
  }
};

{ // doc 
$phpDocs['ydata.inc.php']['classes']['YDataTable'] = <<<'EOT'
title: stockage des données contenues dans une table, classe utilisée par YData
doc: |
EOT;
}
class YDataTable implements YamlDocElement, IteratorAggregate {
  protected $attrs=[]; // liste des attributs détectés dans la table
  protected $data; // contenu de data sous forme d'un array Php
  
  // prend en entrée le contenu de la table sous la forme d'un array [ _id => array ]
  function __construct(array $data) {
    $this->data = $data;
    if ($data) {
      $this->attrs = [ '_id' ];
      foreach ($data as $tuple) {
        //print_r($tuple); echo "<br><br>\n";
        if ($tuple) {
          foreach (array_keys($tuple) as $attr) {
            if (!in_array($attr, $this->attrs))
              $this->attrs[] = $attr;
          }
        }
      }
    }
  }
  
  // fournit les tuples de la table sous la forme d'une liste en ajoutant un attribut _id avec la liste des clés
  function tuples() {
    $tuples = [];
    foreach ($this->data as $_id => $tuple) {
      if ($tuple)
        $tuples[] = array_merge(['_id'=> $_id], $tuple);
      else
        $tuples[] = ['_id'=> $_id];
    }
    return $tuples;
  }
  
  // extrait le sous-élément de l'élément défini par $ypath
  // permet de traverser les objets quand on connait son chemin
  function extract(string $ypath) {
    return self::sextract($this->data, $ypath);
  }
  
  static public function sextract(array $data, string $ypath) {
    //echo "YDataTable::sextract(data=",json_encode($data),", ypath=$ypath)<br>\n";
    //echo "ypath=$ypath<br>\n";
    if (!preg_match('!^/([^/]+)(/.*)?$!', $ypath, $matches)) {
      echo "$ypath don't match line ".__LINE__."<br>\n";
      return null;
    }
    $field = $matches[1];
    $ypath2 = isset($matches[2]) ? $matches[2] : '';
    if ($field == '*') {
      $result = [];
      foreach ($data as $id => $tuple) {
        $tuple = array_merge(['_id'=> $id], $tuple);
        $result[] = $ypath2 ? self::sextract($tuple, $ypath2) : $tuple;
      }
      return $result;
    }
    elseif (strpos($field, ',') !== false) {
      $fields = explode(',', $field);
      foreach ($fields as $f) {
        if (($f == '_id') && isset($data['_id']))
          $result[$f] = $data['_id'];
        else
          $result[$f] = self::subValue($data, $f);
      }
      return $result;
    }
    // évaluation d'un critère de sélection
    elseif (($pos = strpos($field, '=')) !== false) {
      $key = substr($field, 0, $pos);
      $val = substr($field, $pos+1);
      if (!($data2 = self::select($data, $key, $val))) {
        echo "resultat de select $key=$val null ligne ",__LINE__,"<br>\n";
        return null;
      }
    }
    // descente
    elseif (isset($data[$field]))
      $data2 = $data[$field];
    else {
      //echo "field $field non valide dans ",__FILE__," ligne ",__LINE__,"<br>\n";
      return null;
    }
    if (!$ypath2)
      return $data2;
    else
      return self::sextract($data2, $ypath2);
  }
  
  // selection dans la liste de tuples $data sur $key=$value
  // si aucun résultat alors retourne null, sinon si un seul tuple en résultat alors retourne ce tuple
  // sinon retourne la liste des tuples vérifiant le critère
  static function select(array $data, string $key, string $value) {
    //echo "select(data=",json_encode($data),", key=$key, value=$value)<br>\n";
    $result = [];
    foreach ($data as $id => $tuple)
      if (self::subValue($tuple, $key) == $value)
        $result[$id] = $tuple;
    if (count($result)==0)
      return null;
    elseif (count($result)==1)
      return array_values($result)[0];
    else
      return $result;
  }
  
  // sous-valeur d'un tableau pour une clé composite avec . comme séparateur
  static private function subValue(array $data, string $key) {
    //echo "YDataTable::subValue(data=",json_encode($data),", key=$key)<br>\n";
    $key = explode('.', $key);
    $k0 = array_shift($key);
    $key = implode('.', $key);
    if (!$key)
      return isset($data[$k0]) ? $data[$k0] : null;
    elseif (($k0 == '*') && ($key == '_id')) {
      return array_keys($data);
    }
    elseif ($k0 == '*') {
      $result = [];
      foreach ($data as $k => $v)
        $result[] = self::subValue($v, $key);
      return $result;
    }
    elseif (!isset($data[$k0]) || !is_array($data[$k0]))
      return null;
    else
      return self::subValue($data[$k0], $key);
  }
      
  // décapsule l'objet et retourne son contenu sous la forme d'un array
  // permet de parcourir tout objet sans savoir a priori ce que l'on cherche
  // est utilisé par YamlDoc::replaceYDEltByArray()
  function asArray() { return $this->data; }
  
  // affiche un élément en Html
  // est utilisé par showDoc()
  // pas implémenté avec la même signature par tous !!!
  function show(string $docid, string $prefix='') {
    //echo "YDataTable::show()";
    echo "<table border=1>\n";
    echo "<th>",implode('</td><td>', $this->attrs),"</th>";
    foreach($this as $tuple) {
      echo "<tr>";
      foreach ($this->attrs as $attr) {
        echo "<td>";
        if (isset($tuple[$attr]))
          showDoc($docid, $tuple[$attr]);
        echo "</td>";
      }
      echo "</tr>\n";
    }
    echo "</table>\n";
  }
  
  function showPart(string $docid, string $ypath) {
    showDoc($docid, $this->extract($ypath));
  }
  
  // interface IteratorAggregate
  function getIterator() { return new ArrayIterator($this->tuples()); }
};

if (basename(__FILE__) == basename($_SERVER['PHP_SELF'])) { // tests unitaires 
  print_r(YDataTable::select(
  ['a'=> ['f'=>['ff'=>'v']], 'b'=>['f'=>'v'], 'c'=>['f'=>'t']],
  'f.ff', 'v'
  ));
}