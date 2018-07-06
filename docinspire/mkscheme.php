<?php
// mkscheme.php - fabrique les champs scheme et concepts de inspire-datamodel.yaml

require_once __DIR__.'/../../vendor/autoload.php';

use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Yaml\Exception\ParseException;

echo "<!DOCTYPE HTML><html><head><meta charset='UTF-8'><title>mkscheme</title></head><body>\n";

$codelists = unserialize(file_get_contents('codelist.pser'));
$enums = unserialize(file_get_contents('enum.pser'));
//echo "<pre>",Yaml::dump($enums, 999, 2),"</pre>\n"; die();

echo "<pre>",
      Yaml::dump(
        [ 'schemes'=> array_merge($codelists['schemes'], $enums['schemes']),
          'concepts'=> array_merge($codelists['concepts'], $enums['concepts']),
        ],
        999, 2),
      "</pre>\n";
