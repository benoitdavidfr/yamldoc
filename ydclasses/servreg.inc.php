<?php
/*PhpDoc:
name: servreg.inc.php
title: servreg.inc.php - classe du registre des serveurs
doc: <a href='/yamldoc/?action=version&name=servreg.inc.php'>doc intégrée en Php</a>
*/
{ // doc 
$phpDocs['servreg.inc.php']['file'] = <<<'EOT'
name: servreg.inc.php
title: servreg.inc.php - classe du registre des serveurs
journal: |
  18/7/2018:
  - adaptation
  12/5/2018:
  - création
EOT;
}

// classe du 
{ // doc 
$phpDocs['servreg.inc.php']['classes']['Servreg'] = <<<"EOT"
title: registre des serveurs
EOT;
}
class Servreg extends BasicYamlDoc {
  function title() { return $this->phpDoc['title']; }
  
  function show(string $ypath=''): void {
    echo "<h2>",$this->title(),"</h2>\n";
    echo "<h3>phpDoc</h3>\n";
    showDoc($_GET['doc'], $this->phpDoc);
    if ($this->classification) {
      echo "<h3>Classification</h3>\n";
      self::showClass($this->classification);
    }
    echo "<h3>Serveurs</h3>\n",
         "<table border=1>\n",
         "<th>name</th><th>title</th><th>class</th><th>protocol</th><th>url</th>";
    foreach ($this->servers as $name => $server) {
      if (isset($server['url'])) {
        $rs = isset($server['layers']) ? 2 : 1;
        echo "<tr><td rowspan=$rs>$name</td>",
             "<td>$server[title]</td>",
             "<td>$server[class]</td>",
             "<td>$server[protocol]</td>",
             "<td>$server[url]</td>",
             "</tr>\n";
             if (isset($server['layers'])) {
               echo "<tr><td colspan=4><table><tr><td>layers</td><td>";
               showDoc($server['layers']);
               echo "</td></tr></table></td></tr>\n";
             }
      }
      else {
        $subfile = substr($server['subfile'], 0, strlen($server['subfile'])-5);
        echo "<tr><td><a href='?doc=servreg/$subfile'>$name</a></td>",
            "<td><i>$server[title]</i></td>",
            "<td>",isset($server['class']) ? $server['class'] : '',"</td>",
            "</tr>\n";
      }
    }
    echo "</table>\n";
    //parent::show($ypath);
  }
  
  static function showClass(?array $classes) {
    //echo "<pre>classes="; print_r($classes); echo "</pre>\n";
    echo "<ul>\n";
    foreach ($classes as $id => $class) {
      echo "<li>$class[title]\n";
      if (isset($class['abstract']))
        echo "<br><i>$class[abstract]</i>\n";
      if (isset($class['children']))
        self::showClass($class['children']);
    }
    echo "</ul>\n";
  }
};