<?php
/*PhpDoc:
name: inseenomapi.inc.php
title: inseenomapi.inc.php - Utilisation de l'API INSEE Nomenclatures V1
doc: <a href='/yamldoc/?action=version&name=sireneapi.inc.php'>doc intégrée en Php</a>
*/
{ // doc 
$phpDocs['inseenomapi.inc.php']['file'] = <<<'EOT'
name: inseenomapi.inc.php
title: inseenomapi.inc.php - Utilisation de l'API INSEE Nomenclatures V1
doc: |
  La classe InseeNomApi facilite l'accès à l'API INSEE Nomenclatures V1.  
journal:
  21/10/2018:
    - création
EOT;
}

class InseeNomApi extends InseeApi {
  static $baseUrl = 'https://api.insee.fr/metadonnees/nomenclatures/v1'; // Url de base de l'API
  static $log = __DIR__.'/inseenomapi.log.yaml'; // nom du fichier de log ou false pour pas de log
  protected $_c; // contient les champs du doc initial

  // crée un nouveau doc, $yaml est le contenu Yaml externe issu de l'analyseur Yaml
  function __construct($yaml, string $docid) {
    $this->_c = $yaml;
    $this->_id = $docid;
  }

  // lit les champs
  function __get(string $name) { return isset($this->_c[$name]) ? $this->_c[$name] : null; }
    
  // ypath de la forme /(siren|siret)(/{siren|siret})?(/q={q})?(/debut={debut})?(/nombre={nombre})?
  function show(string $ypath=''): void {
    if ($ypath == '') {
      showDoc($this->_id, $this->_c);
      return;
    }
    if (preg_match('!^/codes/(nafr2/classe|nafr2/sousClasse|cj/n2|cj/n3)/([^/]+)$!', $ypath, $matches))
      $doc = $this->query(self::$baseUrl, $ypath, []);
    elseif (preg_match('!^/geo/(commune|departement|region|pays)/([^/]+)$!', $ypath, $matches))
      $doc = $this->query(self::$baseUrl, $ypath, []);
    else
      die("ypath '$ypath' non interprété<br>\n");
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
        '/codes/nafr2/classe/{code}'=> "retourne des informations sur la classe NAF rév. 2 identifiée par {code}",
        '/codes/nafr2/sousClasse/{code}'=> "retourne des informations sur la sous-classe NAF rév. 2 identifiée par {code}",
        '/codes/cj/n2/{code}'=> "retourne des informations sur la catégorie juridique de niveau 2 identifiée par {code}",
        '/codes/cj/n3/{code}'=> "retourne des informations sur la catégorie juridique de niveau 3 identifiée par {code}",
        '/geo/commune/{code}'=> "retourne des informations sur la commune identifiée par {code}",
        '/geo/region/{code}'=> "retourne des informations sur la région identifiée par {code}",
        '/geo/pays/{code}'=> "retourne des informations sur le pays identifié par {code}",
        '/token'=> "génère un nouveau token d'accès à l'API, l'enregistre et le retourne",
      ]
    ];
  }  
  
  function extractByUri(string $ypath): array {
    if (($ypath=='') || ($ypath=='/'))
      return $this->asArray();
    elseif ($ypath == '/api')
      return $this->api();
    elseif (preg_match('!^/codes/(nafr2/classe|nafr2/sousClasse|cj/n2|cj/n3)/([^/]+)$!', $ypath, $matches))
      return $this->query(self::$baseUrl, $ypath, []);
    elseif (preg_match('!^/geo/(commune|region|pays)/([^/]+)$!', $ypath, $matches))
      return $this->query(self::$baseUrl, $ypath, []);
    elseif ($ypath == '/token')
      return $this->generateToken();
    else
      return ["Erreur aucun match dans SireneApi::extractByUri()"];
  }
};
