<?php
/*PhpDoc:
name: sireneapi.inc.php
title: sireneapi.inc.php - Utilisation de l'API SIRENE
doc: <a href='/yamldoc/?action=version&name=sireneapi.inc.php'>doc intégrée en Php</a>
*/
{ // doc 
$phpDocs['sireneapi.inc.php']['file'] = <<<'EOT'
name: sireneapi.inc.php
title: sireneapi.inc.php - Utilisation de l'API SIRENE
journal:
  20/10/2018:
    - création
EOT;
}

// suppression des champs vides
function clean(array $tab): array {
  $result = [];
  foreach ($tab as $k => $v) {
    if (is_array($v)) {
      if ($r2 = clean($v))
        $result[$k] = $r2;
    }
    elseif (!is_null($v) && ($v <> '')) {
      $result[$k] = $v;
    }
  }
  return $result;
}

{ // doc 
$phpDocs['sireneapi.inc.php']['classes']['SireneApi'] = <<<'EOT'
name: sireneapi.inc.php
title: utilisation l'API SIRENE
doc: |
  Le document peut soit ne contenir aucune info soit contenir:
  - un no SIRET ou SIREN
  - une requête sur SIREN ou sur SIRET
EOT;
}
class SireneApi extends InseeApi {
  static $baseUrl = 'https://api.insee.fr/entreprises/sirene/V3'; // Url de base de l'API
  static $log = __DIR__.'/sireneapi.log.yaml'; // nom du fichier de log ou false pour pas de log
  protected $_c; // contient les champs du doc initial

  // crée un nouveau doc, $yaml est le contenu Yaml externe issu de l'analyseur Yaml
  function __construct($yaml, string $docid) {
    $this->_c = $yaml;
    $this->_id = $docid;
  }

  // lit les champs
  function __get(string $name) { return isset($this->_c[$name]) ? $this->_c[$name] : null; }
  
  function buildYpath(): string {
    foreach (['siren','siret'] as $sirent) {
      if ($this->$sirent) {
        //echo "<pre>$sirent = "; var_dump($this->$sirent); echo "</pre>\n";
        if (is_array($this->$sirent)) {
          $q = array_keys($this->$sirent)[0].':'.array_values($this->$sirent)[0];
          return "/$sirent/q=$q";
        }
        elseif (is_string($this->$sirent)) {
          return "/$sirent/".$this->$sirent;
          //echo "uri=$uri<br>\n";
        }
      }
    }
    return '';
  }
  
  // ypath de la forme /(siren|siret)(/{siren|siret})?(/q={q})?(/debut={debut})?(/nombre={nombre})?
  function show(string $ypath=''): void {
    if ($ypath == '') {
      $ypath = $this->buildYpath();
    }
    if ($ypath == '') {
      showDoc($this->_id, $this->_c);
      return;
    }
    if (!preg_match('!^/(siren|siret)(/([0-9]+))?(/q=([^/]*))?(/debut=([0-9]+))?(/nombre=([0-9]+))?$!', $ypath, $matches))
        die("ypath '$ypath' non interprété<br>\n");
    //echo "<pre>matches = "; print_r($matches); echo "</pre>";
    $sirent = $matches[1];
    $id = $matches[2] ? $matches[3] : '';
    $args = [];
    if (isset($matches[4]) && $matches[4])
      $args['q'] = $matches[5];
    if (isset($matches[6]) && $matches[6])
      $args['debut'] = $matches[7];
    if (isset($matches[8]) && $matches[8])
      $args['nombre'] = $matches[9];
    if ($id) {
      $result = $this->query(self::$baseUrl, "/$sirent/$id", []);
      if ($sirent == 'siren') {
        $periodesUniteLegale = $result['uniteLegale']['periodesUniteLegale'];
        $doc = ['denominationUniteLegale' => array_values($periodesUniteLegale)[0]['denominationUniteLegale']];
      }
      else
        $doc = ['denominationUniteLegale' => $result['etablissement']['uniteLegale']['denominationUniteLegale']];
      $doc = array_merge($doc, clean($result));
    }
    else {
      $result = $this->query(self::$baseUrl, "/$sirent", $args);
      //print_r($result);
      $header = $result['header'];
      $suivant = "doc=$this->_id&amp;ypath=/$sirent/q=$args[q]"
        ."/debut=".($header['debut'] + $header['nombre'])
        ."/nombre=".$header['nombre'];
      $fin = $header['debut'] + $header['nombre'] - 1;
      $header = "<html>\n"
        . "statut: $header[statut], message: $header[message], $header[debut] - $fin / $header[total]"
        . " <a href='?$suivant'><b>&gt;</b></a>";
      $doc = ['header'=> $header, 'etablissements'=> []];
      foreach ($result['etablissements'] as $etablissement) {
        $e = [
          'siren'=> "<html>\n<a href='?doc=$this->_id&amp;ypath=/siren/$etablissement[siren]'>$etablissement[siren]</a>",
          //'siren'=> $etablissement['siren'],
          //'siret'=> $etablissement['siret'],
          'siret'=> "<html>\n<a href='?doc=$this->_id&amp;ypath=/siret/$etablissement[siret]'>$etablissement[siret]</a>",
        ];
        foreach (['denominationUniteLegale','nomUniteLegale','nomUsageUniteLegale','prenomUsuelUniteLegale'] as $k)
          if ($etablissement['uniteLegale'][$k])
            $e[$k] = $etablissement['uniteLegale'][$k];
        $doc['etablissements'][] = $e;
      }
    }
    showDoc($this->_id, $doc);
  }
  
  // décapsule l'objet et retourne son contenu sous la forme d'un array
  function asArray(): array { return array_merge(['_id'=> $this->_id], $this->_c); }

  // extrait le fragment du document défini par $ypath
  function extract(string $ypath) { return YamlDoc::sextract($this->_c, $ypath); }
  
  static function api(): array {
    return [
      'class'=> get_class(), 
      'title'=> "description de l'API de la classe ".get_class(), 
      'abstract'=> "document correspondant à un serveur de tuiles",
      'api'=> [
        '/'=> "retourne le contenu du document ".get_class(),
        '/api'=> "retourne les points d'accès de ".get_class(),
        '/siren/{siren}'=> "retourne la description de l'entité correspondant au SIREN {siren}",
        '/siren?q={query}'=> "retourne les entités satisfaisant à la requête",
        '/siret/{siret}'=> "retourne la description de l'établissement correspondant au SIRET {siret}",
        '/siret?q={query}'=> "retourne les établissements satisfaisant à la requête",
        '/token'=> "génère un nouveau token d'accès à l'API, l'enregistre et le retourne",
      ]
    ];
  }  
  
  function extractByUri(string $ypath): array {
    if (($ypath=='') || ($ypath=='/'))
      return $this->asArray();
    elseif ($ypath == '/api')
      return $this->api();
    elseif (preg_match('!/(siren|siret)/([^/]+)!', $ypath, $matches))
      return $this->query(self::$baseUrl, $ypath, []);
    elseif ((($ypath == '/siren') || ($ypath == '/siret')) && isset($_GET['q']))
      return $this->query(self::$baseUrl, $ypath, $_GET);
    elseif ($ypath == '/token')
      return $this->generateToken();
    else
      return ["Erreur aucun match dans SireneApi::extractByUri()"];
  }
};
