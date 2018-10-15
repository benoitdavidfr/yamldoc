<?php
// Calcul de la surface des objets Vigne de la BD Topo
// 1ère version:
// utilisation du flux WFS IGNGP en JSON EPSG:2154
// Je n'arrive ni à définir un bbox, ni à limiter les requêtes aux vignes
// 2ème version:
// utilisation du flux WFS IGNGP en JSON CRS:84
// J'arrive à définir un bbox et à limiter les requêtes aux vignes
// NE FONCTIONNE PAS FRANCE ENTIERE

// Resultats FAUX:
// Métropole:
// "Vigne": 71243178.77369122 soit 7 124 ha
// "VRC": 4648557.947372983 soit 464 ha
// "VRT": 3220531.0514636985 soit 322 ha
// "Vigne": 3196691.5290115345 soit 319 ha
// "Vigne": 71243178.77369122 soit 7 124 ha

// Saint-Léger-les-Vignes:
// "Vigne": 2271727.732101542 soit 227 ha
// "VRC": 1515985.2678259155, soit 151 ha


ini_set('memory_limit', '2048M');

require_once __DIR__.'/../inc.php';

//echo "argc=$argc\n"; die();
$classes = [
  'bdt.vigne'=> [
    'typename' => 'BDTOPO_BDD_WLD_WGS84G:zone_vegetation',
    'fieldName' => 'nature',
    'where'=> "nature='Vigne'",
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
];
$zones = [
  'metro'=> [
    'title'=> "métropole",
    'LngLat'=> [-5.441, 41.325, 9.630, 51.098], // LongLat Métropole
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
  
$bboxwkt = WfsServer::bboxWktLatLng($bbox); // bbox en Lat/Lng, je ne sais pas pourquoi !!
$cql_filter = urlencode("Intersects($geomPropertyName,$bboxwkt)".($where ? " AND $where" : ''));

$areas = [];
$startindex = 0;
$count = 100;

while(1) { // Itérations sur les requêtes WFS avec augmentation du startindex jusqu'à ce que le résultat soit vide
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
  while(1) {
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
    break;
  echo count($geojson['features'])," objets lus\n";
  foreach ($geojson['features'] as $feature) {
    $geom = Geometry::fromGeoJSON($feature['geometry']);
    $geom = $geom->chgCoordSys('geo','L93');
    $fieldValue = $feature['properties'][$fieldName];
    $area = $geom->area();
    if (0 && isset($feature['properties']['surf_parc'])) {
      $surf_parc = $feature['properties']['surf_parc'];
      printf("surf_parc=%s, area=%.2f ha\n", $surf_parc, $area/10000);
    }
    $area = abs($area);
    if (!isset($areas[$fieldValue]))
      $areas[$fieldValue] = 0.0;
    $areas[$fieldValue] += $area;
  }
  echo "startindex=$startindex\n";
  echo json_encode($areas, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE),"\n";
  $startindex += $count;
}

echo "Fin OK\n";