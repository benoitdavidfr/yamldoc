<?php
/*PhpDoc:
name: mkot.php
title: mkot.php - fabrique les objecttypes de inspire-datamodel.yaml
doc: |
includes: [ ../../vendor/autoload.php ]
*/

require_once __DIR__.'/../../vendor/autoload.php';

use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Yaml\Exception\ParseException;

if (php_sapi_name()<>'cli')
  echo "<!DOCTYPE HTML><html><head><meta charset='UTF-8'><title>mkscheme</title></head><body><pre>\n";

$ot = [];
foreach(['sot','datatype','externaltype','unknowntype','uniontype'] as $mttag) {
  $ot = array_merge($ot, unserialize(file_get_contents("$mttag.pser")));
}
echo Yaml::dump(['objectTypes'=> $ot], 999, 2);
