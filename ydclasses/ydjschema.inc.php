<?php
/*PhpDoc:
name: ydjschema.inc.php
title: ydjschema.inc.php - classe JsonSchema comme classe YamlDoc
functions:
doc: <a href='/yamldoc/?action=version&name=ydjschema.inc.php'>doc intégrée en Php</a>
*/
{
$phpDocs['ydjschema.inc.php']['file'] = <<<'EOT'
name: ydjschema.inc.php
title: ydjschema.inc.php - classe JsonSchema comme classe YamlDoc
doc: |
  Permet d'afficher le schéma JSON et de le vérifier ainsi que ses examples
journal:
  6-8/2/2019:
  - première version
EOT;
}

{
$phpDocs['ydjschema.inc.php']['classes']['BasicYamlDoc'] = <<<'EOT'
title: classe JsonSchema comme classe YamlDoc
EOT;
}

class YdJsonSchema extends BasicYamlDoc {
  // $data contient l'array Php correspondant au source Yaml
  
  // validation de la conformité du schéma au au métaschéma
  function checkSchemaConformity(string $ypath): void {
    echo "YdJsonSchema::checkSchemaConformity(ypath=$ypath)<br>\n";
    JsonSchema::autoCheck($this->data, [
      'showOk'=> "ok schéma conforme au méta-schéma<br>\n",
      'showErrors'=> "KO schéma NON conforme au méta-schéma<br>\n",
    ]);
    // puis je valide les exemples et les contre-exemples du schema et des définitions
    $schema = new JsonSchema($this->data, false);
    foreach (['examples'=> 'exemple', 'counterexamples'=> 'contre-exemple'] as $key=> $label) {
      if (isset($this->data[$key])) { # et je vérifie les exemples et contre-ex
        foreach ($this->data[$key] as $i => $ex) {
          $title = !isset($ex['title']) ? $i : (!is_array($ex['title']) ? "\"$ex[title]\"" : json_encode($ex['title']));
          $schema->check($ex, [
            'showWarnings'=> "ok $label $title conforme au schéma<br>\n",
            'showErrors'=> "KO $label $title NON conforme au schéma<br>\n",
          ]);
        }
      }
    }
    if (isset($this->data['definitions'])) {
      foreach ($this->data['definitions'] as $defName => $definition) {
        $defSchFrgt = new JsonSchFragment($definition, $schema, false);
        foreach (['examples'=> 'exemple', 'counterexamples'=> 'contre-exemple'] as $key=> $label) {
          if (isset($definition[$key])) { # et je vérifie les exemples et contre-ex pour cette définition
            foreach ($definition[$key] as $i => $ex) {
              //echo "check de ",json_encode($ex),"<br>\n";
              if ($defSchFrgt->check($ex)->ok())
                echo "ok $label de $defName no $i conforme au schéma<br>\n";
              else
                echo "KO $label de $defName no $i NON conforme au schéma<br>\n";
            }
          }
        }
      }
    }
  }
};
