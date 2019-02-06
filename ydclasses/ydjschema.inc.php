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
  Permet de vérifier un schéma JSON et ses examples
journal:
  6/2/2019:
  - première version
EOT;
}

{
$phpDocs['ydjschema.inc.php']['classes']['BasicYamlDoc'] = <<<'EOT'
title: classe JsonSchema comme classe YamlDoc
EOT;
}

class YdJsonSchema extends BasicYamlDoc {

  // validation de la conformité du schéma au au métaschéma
  function checkSchemaConformity(string $ypath): void {
    echo "YdJsonSchema::checkSchemaConformity(ypath=$ypath)<br>\n";
    $metaschema = new JsonSchema(__DIR__.'/../../schema/json-schema.schema.json', false);
    $metaschema->check($this->data, [
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
        $storepath = Store::storepath();
        $docid = $this->_id;
        $defSch = new JsonSchema(__DIR__."/../$storepath/$docid.yaml#/definitions/$defName", false);
        foreach (['examples'=> 'exemple', 'counterexamples'=> 'contre-exemple'] as $key=> $label) {
          if (isset($definition[$key])) { # et je vérifie les exemples et contre-ex pour cette définition
            foreach ($definition[$key] as $i => $ex) {
              //echo "check de ",json_encode($ex),"<br>\n";
              $defSch->check($ex, [
                'showWarnings'=> "ok $label de $defName no $i conforme au schéma<br>\n",
                'showErrors'=> "KO $label de $defName no $i NON conforme au schéma<br>\n",
              ]);
            }
          }
        }
      }
    }
  }
};
