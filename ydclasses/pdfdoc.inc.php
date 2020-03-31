<?php
/*PhpDoc:
name: pdfdoc.inc.php
title: gestion des fichiers PDF
doc: |
  voir le code
*/
{ // doc 
$phpDocs['pdfdoc.inc.php']['file'] = <<<EOT
name: pdfdoc.inc.php
title: pdfdoc.inc.php - affichage des fichiers PDF
doc: |
journal:
  14/3/2020:
    - correction bug accès par URI dans le store pub
  19/2/2020:
    - lors d'un appel par URI le fichier PDF est transmis
  22/8/2018:
    - modif PdfDoc::checkReadAccess()
  28/7/2018:
    - création
EOT;
}

{ // doc 
$phpDocs['pdfdoc.inc.php']['classes']['PdfDoc'] = <<<EOT
title: affichage des fichiers PDF
doc: |
EOT;
}
class PdfDoc extends Doc  {
  public $authorizedReaders, $authRd, $authorizedWriters, $authWr, $yamlPassword , $language;
  private $path = '';
  
  function __construct($path, string $docid) { $this->path = $path; $this->_id = $docid; }
  
  // lors d'une visu par le viewer le PDF est transmis
  function show(string $ypath=''): void {
    $docid = $this->_id;
    echo "PdfDoc::show<br>\n";
    $dirname = dirname($_SERVER['SCRIPT_NAME']);
    echo("Location: http://$_SERVER[SERVER_NAME]$dirname/file.php/$docid.pdf\n");
    header("Location: http://$_SERVER[SERVER_NAME]$dirname/file.php/$docid.pdf");
    die();
  } 
  
  function checkReadAccess(): bool { return true; }
  
  // lors d'un appel comme URI le PDF est transmis
  function extractByUri(string $ypath) {
    echo "PdfDoc::extractByUri()@{_id: $this->_id}<br>\n";
    Store::init();
    echo "<pre>ids="; print_r(Store::ids());
    echo "definition="; print_r(Store::$definition);
    $instance = Store::$definition[Store::ids()['id']]['instances'][Store::ids()['place']];
    echo "instance="; print_r($instance);
    $path = ($instance['scheme'] ?? 'http').'://'.$instance['servers'][0]
        ."/$instance[ydpath]/file.php/".$this->_id.'.pdf';
    echo "path=$path\n";
    header("Location: $path\n");
    die();
  }
};
