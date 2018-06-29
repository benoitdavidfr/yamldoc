<?php
// Affiche une image
//echo "<pre>_SERVER="; print_r($_SERVER);
//[REQUEST_URI] => /yamldoc/image.php/topovoc/image01.gif
//[SCRIPT_NAME] => /yamldoc/image.php
$imagepath = substr($_SERVER['REQUEST_URI'], strlen($_SERVER['SCRIPT_NAME']));
//echo "imagepath=$imagepath";
header('Content-type: image/gif');
echo file_get_contents(__DIR__."/docs$imagepath");