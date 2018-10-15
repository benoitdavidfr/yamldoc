<?php
// Calcul de la surface des objets Vigne de la BD Topo et du RPG
// nvlle approche
// le WFS IGNGP est cablé pour répondre à des requêtes très localisées
// les requêtes sont donc décomposée en sous-régions de taille max 0.5 X 0.5 degrés

// résultats:
// métrople:
//    "Vigne BDTopo": 8140899386.4956665 soit 814 090 ha
//    "VRT": 43738456.91083243, soit 4 374 ha
//    "VRC": 5657512205.357957 soit 565 751 ha
//    "Vignes RPG": 5793785627.739179 soit 579 379 ha
//    "Verger BDTopo": 2698353174.8312593 soit 269 835 ha
//    "Vergers RPG": 1109303377.0075188 soit 110 930 ha


ini_set('memory_limit', '2048M');

require_once __DIR__.'/../inc.php'; // le adre de yamldoc

//echo "argc=$argc\n"; die();
$classes = [
  'bdt.vigne'=> [
    'typename' => 'BDTOPO_BDD_WLD_WGS84G:zone_vegetation',
    'fieldName' => 'nature',
    'where'=> "nature='Vigne'",
  ],
  'bdt.verger'=> [
    'typename' => 'BDTOPO_BDD_WLD_WGS84G:zone_vegetation',
    'fieldName' => 'nature',
    'where'=> "nature='Verger'",
  ],

  'rpg2016'=> [
    'typename' => 'RPG.2016:parcelles_graphiques',
    'fieldName' => 'code_cultu',
  ],
  'rpg2016.VRC'=> [
    'typename' => 'RPG.2016:parcelles_graphiques',
    'fieldName' => 'code_cultu',
    'where'=> "code_cultu='VRC'",
  ],
  'rpg2016.VRT'=> [
    'typename' => 'RPG.2016:parcelles_graphiques',
    'fieldName' => 'code_cultu',
    'where'=> "code_cultu='VRT'",
  ],
  'rpg2016.VRCT'=> [
    'typename' => 'RPG.2016:parcelles_graphiques',
    'fieldName' => 'code_cultu',
    'where'=> "(code_cultu='VRC' OR code_cultu='VRT')",
  ],
  'rpg2016.Vergers'=> [
    'typename' => 'RPG.2016:parcelles_graphiques',
    'fieldName' => 'code_group',
    'where'=> "code_group='20'",
  ],
  'rpg2016.Vignes'=> [
    'typename' => 'RPG.2016:parcelles_graphiques',
    'fieldName' => 'code_group',
    'where'=> "code_group='21'",
  ],
];
$zones = [
  'metro'=> [
    'title'=> "métropole",
    'LngLat'=> [-5.2, 41.3, 9.6, 51.1], // LongLat Métropole
  ],
  'StLV'=> [
    'title'=> "Saint-Léger-les-Vignes",
    'WM'=> [-197195, 5961740, -186807, 5968364],
  ],
];

if ($argc == 1) {
  echo "usage: php area.php {classe} {zone}?\n";
  echo "  classes:\n";
  foreach ($classes as $className => $class) {
    $where = isset($class['where']) ? $class['where'] : 'AUCUN';
    echo "    $className : typename=$class[typename], where=$where\n";
  }
  echo "  zones:\n";
  foreach ($zones as $zoneId => $zone) {
    echo "    $zoneId : $zone[title]\n";
  }
  die();
}
$className = $argv[1];
$zoneId = ($argc > 2) ? $argv[2] : 'metro';
if (!isset($classes[$className]))
  die("Erreur classe $className non définie\n");
$typename = $classes[$className]['typename'];
$fieldName = $classes[$className]['fieldName'];
$where = isset($classes[$className]['where']) ? $classes[$className]['where'] : '';

$yaml = [
  'wfsUrl'=> 'https://wxs.ign.fr/3j980d2491vfvr7pigjqdwqw/geoportail/wfs',
  'wfsOptions'=> [
    'referer'=> 'http://gexplor.fr/',
  ],
];
$igngpWfs = new WfsServerJson($yaml, 'igngpwfs');
//print_r($igngpWfs);

// the_geom
$geomPropertyName = $igngpWfs->geomPropertyName($typename); //die("$geomPropertyName\n");

if (isset($zones[$zoneId]['LngLat'])) {
  $bbox = $zones[$zoneId]['LngLat'];
}
elseif (isset($zones[$zoneId]['WM'])) {
  $bboxWM = $zones[$zoneId]['WM'];
  $ptMin = new Point([$bboxWM[0], $bboxWM[1]]);
  //echo "ptMin=$ptMin\n"; //die();
  $ptMin = $ptMin->chgCoordSys('WM', 'geo'); // Long Lat
  //echo "ptMin=$ptMin\n"; //die();
  $ptMax = new Point([$bboxWM[2], $bboxWM[3]]);
  //echo "ptMax=$ptMax\n"; //die();
  $ptMax = $ptMax->chgCoordSys('WM', 'geo'); // Long Lat
  //echo "ptMax=$ptMax\n"; //die();
  $bbox = [$ptMin->x(), $ptMin->y(), $ptMax->x(), $ptMax->y()];
  echo "bbox=",implode(',',$bbox),"\n";
}

// calcul de la somme des surfaces par valeur du champ $fieldName
function areas(array $areas, WfsServerJson $igngpWfs, string $typename, string $geomPropertyName, string $where, string $fieldName, array $bbox): array {
  $bboxwkt = WfsServer::bboxWktLatLng($bbox); // bbox en Lat/Lng, je ne sais pas pourquoi !!
  $cql_filter = urlencode("Intersects($geomPropertyName,$bboxwkt)".($where ? " AND $where" : ''));

  $startindex = 0;
  $count = 1000;

  while (1) { // Itérations sur les requêtes WFS avec augmentation du startindex jusqu'à ce que le résultat soit vide
    $request = [
      'VERSION'=> '2.0.0',
      'REQUEST'=> 'GetFeature',
      'TYPENAMES'=> $typename,
      'SRSNAME'=> 'CRS:84',
      'CQL_FILTER'=> $cql_filter,
      'OUTPUTFORMAT'=> 'application/json',
      'COUNT'=> $count,
      'STARTINDEX'=> $startindex,
    ];
    $nbIter = 0;
    $nbIterMax = 7;
    // boucle sur les itérations en cas d'erreur de query, sortie soit si pas erreur soit après nbIterMax itérations
    while (1) {
      try {
        $result = $igngpWfs->query($request);
        break;
      }
      catch (Exception $e) {
        echo "Exception ",$e->getMessage(),"\n";
        if (++$nbIter > $nbIterMax) {
          echo json_encode($areas, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE),"\n";
          die("Erreur sur startindex=$startindex, abandon après $nbIterMax itérations\n");
        }
        $seconds = 2 ** $nbIter;
        echo "Attente $seconds secondes\n";
        sleep($seconds);
      }
    }
    $geojson = json_decode($result, true);
    //echo "geojson="; print_r($geojson);
    if (!$geojson['features'])
      return $areas;
    echo count($geojson['features'])," objets lus\n";
    foreach ($geojson['features'] as $feature) {
      $geom = Geometry::fromGeoJSON($feature['geometry']);
      $geom = $geom->chgCoordSys('geo','L93'); // passage en Lambert93 pour calculer la surface
      $fieldValue = $feature['properties'][$fieldName];
      $area = $geom->area();
      if (0 && isset($feature['properties']['surf_parc'])) {
        $surf_parc = $feature['properties']['surf_parc'];
        printf("surf_parc=%s, area=%.2f ha\n", $surf_parc, $area/10000);
      }
      //echo "properties="; var_dump($feature['properties']);
      $area = abs($area);
      if (!isset($areas[$fieldValue]))
        $areas[$fieldValue] = 0.0;
      $areas[$fieldValue] += $area;
    }
    echo "startindex=$startindex\n";
    echo json_encode($areas, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE),"\n";
    $startindex += $count;
  }
}


$areas = [];
$delta = 0.5; // taille max des zones des requêtes
for($x = $bbox[0]; $x < $bbox[2]; $x += $delta) {
  for ($y = $bbox[1]; $y < $bbox[3]; $y += $delta) {
    $bb = [$x, $y, min($x + $delta, $bbox[2]), min($y + $delta, $bbox[3])];
    echo "bb=",implode(',', $bb),"\n";
    $areas = areas($areas, $igngpWfs, $typename, $geomPropertyName, $where, $fieldName, $bb);
  }
}


echo "Fin OK\n";