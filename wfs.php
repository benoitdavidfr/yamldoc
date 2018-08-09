<?php
// test du WFS du géoportail
// ADMINEXPRESS_COG_2018_CARTO:departement_carto

if (!isset($_GET['request']) && !isset($_GET['action'])) {
  echo "<!DOCTYPE HTML><html><head><meta charset='UTF-8'><title>wfs</title></head><body>\n";
  echo "<h2>actions</h2><ul>\n";
  echo "<li><a href='?action=liregetcap'>Lire et enregistrer les getcap</a>\n";
  echo "<li><a href='?action=showcapxml'>Afficher en XML les cap enregistrées</a>\n";
  echo "<li><a href='?action=showcap'>Afficher en HTML les cap enregistrées</a>\n";
  echo "<li><a href='?action=httptest'>Appel httptest</a>\n";
  echo "</ul>\n";
  echo "<h2>URL de test</h2><ul>\n";
  echo "<li><a href='?request=GetCapabilities'>GetCapabilities</a>\n";
  echo "<li><a href='?service=WFS&amp;version=2.0.0&amp;request=DescribeFeatureType&amp;TYPENAME=ADMINEXPRESS_COG_2018_CARTO:departement_carto'>DescribeFeatureType ADMINEXPRESS_COG_2018_CARTO:departement_carto</a>\n";
  $params = '';
  foreach([
    'service'=> 'WFS',
    'version'=> '2.0.0',
    'request'=> 'GetFeature',
    'TYPENAME'=> 'ADMINEXPRESS_COG_2018_CARTO:departement_carto',
    'OUTPUTFORMAT'=> 'GML2',
    'MAXFEATURE'=> '10',
    ] as $k => $v)
      $params .= ($params?'&amp;':'')."$k=$v";
  echo "<li><a href='?$params'>GetFeature ADMINEXPRESS_COG_2018_CARTO:departement_carto GML2</a>\n";
  $params = '';
  foreach([
    'service'=> 'WFS',
    'version'=> '2.0.0',
    'request'=> 'GetFeature',
    'TYPENAME'=> 'ADMINEXPRESS_COG_2018_CARTO:departement_carto',
    'OUTPUTFORMAT'=> 'json',
    'MAXFEATURE'=> '10',
    ] as $k => $v)
      $params .= ($params?'&amp;':'')."$k=$v";
  echo "<li><a href='?$params'>GetFeature ADMINEXPRESS_COG_2018_CARTO:departement_carto GeoJSON</a>\n";
  $params = '';
  foreach([
    'service'=> 'WFS',
    'version'=> '2.0.0',
    'request'=> 'GetFeature',
    'TYPENAME'=> 'ADMINEXPRESS_COG_2018_CARTO:departement_carto',
    'OUTPUTFORMAT'=> 'json',
    // 0.0,46.0,2.0,48.0
    'cql_filter'=> 'Intersects(the_geom,LINESTRING(46.0 0.0,48.0 2.0))',
    ] as $k => $v)
      $params .= ($params?'&amp;':'')."$k=$v";
  echo "<li><a href='?$params'>GetFeature AEC2018C:departement_carto GeoJSON cql_filter LINESTRING</a>\n";
  $params = '';
  foreach([
    'service'=> 'WFS',
    'version'=> '2.0.0',
    'request'=> 'GetFeature',
    'TYPENAME'=> 'ADMINEXPRESS_COG_2018_CARTO:departement_carto',
    'OUTPUTFORMAT'=> 'json',
    // 0.0,46.0,2.0,48.0
    'cql_filter'=> urlencode('Intersects(the_geom,POLYGON((46.0 0.0,48.0 0.0,48.0 2.0,46.0 2.0,46.0 0.0)))'),
    ] as $k => $v)
      $params .= ($params?'&amp;':'')."$k=$v";
  echo "<li><a href='?$params'>GetFeature AEC2018C:departement_carto GeoJSON cql_filter POLYGON</a>\n";
  echo "</ul>\n";
  die();
}
elseif (isset($_GET['action'])) {
  if ($_GET['action']=='liregetcap') {
    $url = 'https://wxs.ign.fr/3j980d2491vfvr7pigjqdwqw/geoportail/wfs?request=GetCapabilities';
    $context = stream_context_create(['http'=> ['referer'=>'http://gexplor.fr/']]);
    if (!($result = file_get_contents($url, false, $context))) {
      die("Erreur sur <a href='$url'>$url</a><br>\n");
    }
    file_put_contents('wfs-getcap.xml', $result);
    die("Lecture OK\n");
  }
  
  if ($_GET['action']=='showcapxml') {
    header('Content-type: application/xml');
    echo file_get_contents('wfs-getcap.xml');
    die();
  }
  
  if ($_GET['action']=='showcap') {
    echo "<!DOCTYPE HTML><html><head><meta charset='UTF-8'><title>wfs cap</title></head><body>\n";
    $cap = new SimpleXMLElement(file_get_contents('wfs-getcap.xml'));
    //echo "<pre>"; print_r($cap); echo "</pre>";
    echo "<table border=1><th>Name</th><th>Abstract</th>\n";
    foreach ($cap->FeatureTypeList->FeatureType as $FeatureType) {
      echo "<tr><td>",$FeatureType->Name,"</td>";
      //echo "<td>",$FeatureType->Title,"</td>";
      //echo "<td>",$FeatureType->Abstract,"</td>";
      //echo "<td><pre>"; print_r($FeatureType); echo "</pre></td>\n";
      echo "</tr>\n";
    }
    echo "</table>\n";
    die();
  }
  
  if ($_GET['action']=='httptest') {
    $url = 'http://localhost/yamldoc/httptest.php';
    $context = stream_context_create(['http'=> ['header'=> "referer: http://gexplor.fr/\r\n"]]);
    if (!($result = file_get_contents($url, false, $context))) {
      die("Erreur sur <a href='$url'>$url</a><br>\n");
    }
    die($result);
  }
  
  die();
}

$url = 'https://wxs.ign.fr/3j980d2491vfvr7pigjqdwqw/geoportail/wfs?'.$_SERVER['QUERY_STRING'];
$context = stream_context_create(['http'=> ['header'=> "referer: http://gexplor.fr/\r\n"]]);
if (!($result = file_get_contents($url, false, $context))) {
  die("Erreur sur <a href='$url'>$url</a><br>\n");
}
if (isset($_GET['OUTPUTFORMAT']) && ($_GET['OUTPUTFORMAT']=='json'))
  header('Content-type: application/json');
else
  header('Content-type: application/xml');
die($result);