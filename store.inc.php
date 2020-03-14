<?php
/*PhpDoc:
name: store.inc.php
title: store.inc.php - gestion des store
functions:
doc: <a href='/yamldoc/?action=version&name=store.inc.php'>doc intégrée en Php</a>
*/
{
$phpDocs['store.inc.php']['file'] = <<<'EOT'
name: store.inc.php
title: gestion des store
doc: |
  Un espace documentaire, ou store, correspond à:
    - un espace physique de stockage en local et un autre sur le web, chacun défini par:
      - le nom du serveur à la fois utilisé comme identifiant de l'espace et serveur http hébergeant le stockage
      - le protocole (http|https) de l'espace http hébergeant le stockage, par défaut http
      - le chemin du répertoire yamldoc sur le serveur qui permet de définir l'url du visualiseur
      - un chemin du stockage sur le serveur à partir du répertoire yamldoc
  L'identification du store se fait par le serveur sur lequel l'appli est exécutée

  3 cas d'utilisation de cette classe:
    1) le store et son instance sont déterminés par le nom du serveur
    2) une intialisation définit le store et l'instance (l'espace pysique) est déduite du nom du serveur
    3) obtenir l'URL de l'appli pour un storeid donné et pour l'instance déduite du nom du serveur
       à la différence avec le cas 2, il n'y a pas d'initialisation
  
  Le cas 1 fonctionne en sapi<>'cli' car en cli le nom du serveur n'est pas défini.
  Les cas 2 et 3 fonctionnent en sapi=='cli' mais nécessite alors le fichier place.inc.php
  qui définit le lieu d'éxécution.
  
journal: |
  4/1/2019:
    modification de Store:$definition et Store::ids() pour tenir compte de la possibilité d'avoir différents serveurs
    pour une instance et une place (georef.eu + id.georef.eu)
  29/7/2018:
    création
    
EOT;
}
class Store {
  /* [ id => [
         'title'=> title
         local/web => [
           'servers'=> [server],
           'ydpath'=> ydpath,
           'storepath'=> storepath
        ]
      ]
    ];
  */
  static $definition = [
    'docs'=> [
      'title'=> "Espace de Benoit",
      'instances'=> [
        'local'=>[
          'servers'=> ['127.0.0.1'],
          'ydpath'=> 'yamldoc',
          'storepath'=> 'docs',
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
          'scheme'=> 'https',
          'servers'=> ['georef.eu', 'id.georef.eu'],
          'ydpath'=> 'yamldoc',
          'storepath'=> 'pub',
        ],
      ],
    ],
  ];
  static $id = null; // valeur courante
  static $place = null; // valeur courante 'local' ou 'web'
  
  static function definition(): array { return self::$definition; }
  
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
  
  static function place(): string {
    if (php_sapi_name()=='cli')
      return require 'place.inc.php';
    else
      return self::ids()['place'];
  }
  
  // calcul du id et du place à partir du server_name
  static function ids(): array {
    //echo "Store::ids()<br>\n";
    if (php_sapi_name()=='cli')
      throw new Exception("Erreur: Store::ids() ne doit pas être appelé en CLI");
    $server_name = $_SERVER['SERVER_NAME'];
    foreach (self::$definition as $id => $store) {
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
  
  static function storepath() {
    if (!self::$id)
      self::init();
    //echo "id=",self::$id,", place=",self::$place,"<br>\n";
    return self::$definition[self::$id]['instances'][self::$place]['storepath'];
  }
  
  // 3ème cas d'utilisation : recherche de l'URL du viewer en fonction du storeid
  // l'instance est déterminée par le server d'exécution ou le fichier place.inc.php
  static function viewerUrl(string $storeid): string {
    $place = self::place();
    $instance = self::$definition[$storeid]['instances'][$place];
    $scheme = isset($instance['scheme']) ? $instance['scheme'] : 'http';
    return "$scheme://$instance[server]/$instance[ydpath]/";
  }
};