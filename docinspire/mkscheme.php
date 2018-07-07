<?php
/*PhpDoc:
name: mkscheme.php
title: mkscheme.php - fabrique les champs scheme et concepts de inspire-datamodel.yaml
doc: |
*/

require_once __DIR__.'/../../vendor/autoload.php';

use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Yaml\Exception\ParseException;

if (php_sapi_name()<>'cli')
  echo "<!DOCTYPE HTML><html><head><meta charset='UTF-8'><title>mkscheme</title></head><body><pre>\n";

$codelists = unserialize(file_get_contents('codelist.pser'));
$enums = unserialize(file_get_contents('enum.pser'));
//echo "<pre>",Yaml::dump($enums, 999, 2),"</pre>\n"; die();

echo Yaml::dump(
        [ 'schemes'=> array_merge($codelists['schemes'], $enums['schemes']),
          'concepts'=> array_merge($codelists['concepts'], $enums['concepts']),
        ],
        999, 2);
