<?php
/*PhpDoc:
name: label.php
title: label.php - générateur d'étiquette
doc: |
journal: |
  21/8/2018:
    première version minimum
*/

// $labelUtf8 est en UTF-8
function makeLabelImage(string $labelUtf8, int $font, string $colorName) {
  // liste des couleurs acceptées
  static $colors = [ // [ name => [ red, green, blue ]]
    'black'=> [0, 0, 0],
    'DimGray'=> [105, 105, 105],
    'DarkSlateGray'=> [47, 79, 79],
    'white'=> [255, 255, 255],
    'red'=> [255, 0, 0],
  ];
  
  $label = utf8_decode($labelUtf8); // imagestring() demande du Latin-2 !!!
  $width = strlen($label) * imagefontwidth($font) + 2; // ajout d'un pixel à droite et à gauche
  $height = imagefontheight($font);
  // L'utilisation d'une image en vraies couleurs améliore la qualité de l'image mais en augmente la taille
  //    $image = imagecreate($size, $size)
  if (!($image = imagecreatetruecolor($width, $height)))
    throw new Exception("erreur de imagecreatetruecolor() ligne ".__LINE__);
  if (1) { // couleur de fond blanc transparent
    // en vraies couleurs il faut gérer le mode mixant (blending) et remplir l'image avec la couleur de fond
    if (!imagealphablending($image, false))
      throw new Exception("erreur de imagealphablending() ligne ".__LINE__);
    // couleur de fond transparente
    $bg_color = imagecolorallocatealpha($image, 255, 255, 255, 85); // blanc transparent à 66%
    if (!imagefilledrectangle($image, 0, 0, $width-1, $height-1, $bg_color))
      throw new Exception("erreur de imagefilledrectangle() ligne ".__LINE__);
    if (!imagealphablending($image, true))
      throw new Exception("erreur de imagealphablending() ligne ".__LINE__);
  }
  else { // couleur jaune de fond
    $bg_color = imagecolorallocate($image, 255, 255, 0);
    if (!imagefilledrectangle($image, 0, 0, $width-1, $height-1, $bg_color))
      throw new Exception("erreur de imagefilledrectangle() ligne ".__LINE__);
  }
  $color = isset($colors[$colorName]) ? $colors[$colorName] : $colors['black'];
  $text_color = imagecolorallocate($image, $color[0], $color[1], $color[2]);
  // bool imagestring(resource $image, int $font, int $x, int $y, string $string, int $color)
  if (!imagestring($image, $font, 1, 0, $label, $text_color))
    throw new Exception("erreur de imagestring() ligne ".__LINE__);

  //header('Cache-Control: max-age='.$nbSecondsInCache); // mise en cache pour $nbDaysInCache jours
  //header('Expires: '.date('r', time() + $nbSecondsInCache)); // mise en cache pour $nbDaysInCache jours
  //header('Last-Modified: '.date('r'));
  imagesavealpha($image, true);
  header('Content-type: image/png');
  // envoi de l'image
  imagepng($image);
  imagedestroy($image);
  die();
}

$font = 3;
$font = 4;

$label = "Etiquette de test avec é et è";

if (!isset($_GET['label'])) {
  echo "imagefontwidth(font=$font) = ",imagefontwidth($font),"<br>\n";
  echo "imagefontheight(font=$font) = ",imagefontheight($font),"<br>\n";
  echo "<h2>URL de test</h2><ul>\n";
  echo "<li><a href='?color=black&amp;font=$font&amp;label=",urlencode($label),"'>$label</a>\n";
  echo "</ul>\n";
  die();
}

//print_r($_GET); die();
makeLabelImage($_GET['label'], isset($_GET['font'])? $_GET['font'] : 3, isset($_GET['color'])? $_GET['color'] : 'red');
