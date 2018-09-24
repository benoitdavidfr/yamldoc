<?php
// Benoit DAVID - 24/9/2018
// explicitation du bug sur la clé choisirgeoportail
// Lors d'un appel en Php au travers de file_get_contents(), la clé pratique génère une image
// alors que la clé choisirgeoportail génère une erreur 'HTTP/1.1 500 Internal Server Error'

if (!isset($_GET['key']) && !isset($_GET['action'])) {
  // affichage de 2 images, l'une avec la clé pratique et l'autre avec la clé choisirgeoportail
  foreach (['pratique','choisirgeoportail'] as $key) {
    echo "$key: <a href='?key=$key'><img src='?key=$key' alt='erreur'></a><br>\n";
  }
  echo "<a href='?action=src'>Affichage du source Php<br>\n";
  
}
elseif (isset($_GET['key'])) {
  // génération de l'image en Php en utilisant la clé passée en paramètre
  $url = "http://wxs.ign.fr/$_GET[key]/wmts?SERVICE=WMTS&VERSION=1.0.0&REQUEST=GetTile&LAYER=GEOGRAPHICALGRIDSYSTEMS.MAPS"
    ."&FORMAT=image%2Fjpeg&STYLE=normal&TILEMATRIXSET=PM&TILEMATRIX=2&TILECOL=1&TILEROW=1&HEIGHT=256&WIDTH=256";
  if (($image = @file_get_contents($url)) === false) {
    echo "<pre>erreur: "; print_r($http_response_header); echo "</pre>";
  }
  else {
    header('Content-type: image/jpeg');
    echo $image;
  }
}
else {
  // affichage du source du code Php
  header('Content-type: text/plain');
  echo file_get_contents(__FILE__);
}
