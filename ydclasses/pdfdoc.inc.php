<?php
/*PhpDoc:
name: pdfdoc.inc.php
title: gestion des fichiers PDF
doc: |
  voir le code
*/
{
$phpDocs['pdfdoc.inc.php'] = <<<EOT
name: pdfdoc.inc.php
title: pdfdoc.inc.php - affichage des fichiers PDF
doc: |
journal:
  22/8/2018:
  - modif PdfDoc::checkReadAccess()
  28/7/2018:
  - création
EOT;
}

class PdfDoc extends Doc  {
  public $authorizedReaders, $authRd, $authorizedWriters, $authWr, $yamlPassword , $language;
  private $path = '';
  
  function __construct($path, string $docid) { $this->path = $path; $this->_id = $docid; }
  
  function show(string $ypath=''): void {
    $docid = $this->_id;
    echo "PdfDoc::show<br>\n";
    $dirname = dirname($_SERVER['SCRIPT_NAME']);
    echo("Location: http://$_SERVER[SERVER_NAME]$dirname/file.php/$docid.pdf\n");
    header("Location: http://$_SERVER[SERVER_NAME]$dirname/file.php/$docid.pdf");
  } 
  
  function checkReadAccess(): bool { return true; }
};
