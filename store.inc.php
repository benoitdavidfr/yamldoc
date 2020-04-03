<?php
/*PhpDoc:
name: store.inc.php
title: store.inc.php - gestion des store
functions:
doc: <a href='/yamldoc/?action=version&name=store.inc.php'>doc intégrée en Php</a>
*/
{ // doc du fichier 
$phpDocs['store.inc.php']['file'] = <<<'EOT'
name: store.inc.php
title: gestion des store
doc: |
  Un espace documentaire, ou store, correspond à:
    - un identifiant et un titre,
    - un espace physique de stockage (ou instance) en local et un autre sur le web, chacun défini par:
      - un ou 2 noms de serveurs, le premier pour le visualiseur, le seconde pour le résolveur, évt. identiques
      - le protocole (http|https) de l'espace http hébergeant le stockage, par défaut http
      - le chemin du répertoire yamldoc sur le serveur qui permet de définir l'url du visualiseur
      - un chemin du stockage sur le serveur à partir du répertoire yamldoc
  
  En sapi<>'cli' le nom du serveur http permet de déterminer le store et l'instance.

  3 cas d'utilisation de cette classe:
  
    1) en sapi <> 'cli', le store et son instance sont déterminés par le nom du serveur
      puis utilisé par Store::id() ou Store::storepath()
    
    2) le store est défini par un appel à Store::setStoreid(), l'instance est définie
      si sapi<>'cli' alors par le nom du serveur
      sinon par le fichier place.inc.php dépendant de l'implémentation du code 
    
    3) appel de Store::viewerUrl() avec un storeid en paramètre, l'instance est définie comme dans le cas 2
      à la différence du cas 2, il n'y a pas d'initialisation
  
  Le cas 1 fonctionne en sapi<>'cli' car en cli le nom du serveur n'est pas défini.
  Les cas 2 et 3 fonctionnent en sapi=='cli' mais nécessite alors le fichier place.inc.php
  qui définit le lieu d'éxécution.
  
journal: |
  2/4/2020:
    amélioration de la doc
  4/1/2019:
    modification de Store:$definition et Store::ids() pour tenir compte de la possibilité
    d'avoir différents serveurs pour une instance et une place (georef.eu + id.georef.eu)
  29/7/2018:
    création
    
EOT;
}
class Store {
  // La définition des différents stores et de leurs instances
  const DEFINITION = [
    'docs'=> [                      // identifiant du store
      'title'=> "Espace de Benoit", // nom du store
      'instances'=> [               // liste des 2 instances, respt. local et distant
        'local'=>[                  // soit 'local' pour le serveur local, soit 'web' pour le serveur sur le web
          //'scheme'=> 'https',     // vaut 'https' ssi le serveur doit être utilisé en https ; absent sinon
          'servers'=> ['127.0.0.1'], // liste d'1 ou 2 noms de serveur, resp. pour le visualiseur et le résolveur
          'ydpath'=> 'yamldoc',      // chemin du répertoire yamldoc
          'storepath'=> 'docs',      // chemin du répertoire racine des docs du store dans yamldoc
        ],
        'web'=> [
          'servers'=> ['bdavid.alwaysdata.net'],
          'ydpath'=> 'yamldoc',
          'storepath'=> 'docs',
        ],
      ],
    ],
    'pub'=> [
      'title'=> "Espace public",
      'instances'=> [
        'local'=> [
          'servers'=> ['localhost'],
          'ydpath'=> 'yamldoc',
          'storepath'=> 'pub',
        ],
        'web'=> [
          'scheme'=> 'https',       // vaut 'https' ssi le serveur doit être utilisé en https ; absent sinon
          'servers'=> ['georef.eu', 'id.georef.eu'],
          'ydpath'=> 'yamldoc',
          'storepath'=> 'pub',
        ],
      ],
    ],
  ];
  static $id = null; // valeur courante
  static $place = null; // valeur courante 'local' ou 'web'
  
  static function definition(): array { return self::DEFINITION; }
  
  // initialisation dans le cas d'utilisation 1
  static function init(): void {
    $ids = self::ids();
    self::$id = $ids['id'];
    self::$place = $ids['place'];
  }

  // initialisation dans le cas d'utilisation 2
  static function setStoreid(string $id): void {
    //echo "Store::setStoreid($id)<br>\n";
    self::$id = $id;
    self::$place = self::place();
  }
  
  // 
  static function place(): string {
    if (php_sapi_name()=='cli')
      return require 'place.inc.php';
    else
      return self::ids()['place'];
  }
  
  // calcul du id et du place à partir du server_name dans le cas sapi<>'cli'
  static function ids(): array {
    //echo "Store::ids()<br>\n";
    if (php_sapi_name()=='cli')
      throw new Exception("Erreur: Store::ids() ne doit pas être appelé en CLI");
    $server_name = $_SERVER['SERVER_NAME'];
    foreach (self::DEFINITION as $id => $store) {
      foreach ($store['instances'] as $place => $instance) {
        if (in_array($server_name, $instance['servers'])) {
          //echo "id=",$id,", place=",$place,"<br>\n";
          return ['id'=> $id, 'place'=> $place];
        }
      }
    }
    throw new Exception("Erreur: ids not found pour server_name $server_name");
  }
  
  // retourne le id du store
  static function id() {
    if (!self::$id)
      self::init();
    return self::$id;
  }
  
  // retourne le storepath du store et de l'instance
  static function storepath() {
    if (!self::$id)
      self::init();
    //echo "id=",self::$id,", place=",self::$place,"<br>\n";
    return self::DEFINITION[self::$id]['instances'][self::$place]['storepath'];
  }
  
  // 3ème cas d'utilisation : recherche de l'URL du viewer en fonction du storeid
  // l'instance est déterminée par le server d'exécution ou le fichier place.inc.php
  static function viewerUrl(string $storeid): string {
    $place = self::place();
    $instance = self::DEFINITION[$storeid]['instances'][$place];
    $scheme = $instance['scheme'] ?? 'http';
    return "$scheme://$instance[server]/$instance[ydpath]/";
  }
};