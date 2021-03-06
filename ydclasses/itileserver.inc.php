<?php
/*PhpDoc:
name: itileserver.inc.php
title: itileserver.inc.php - serveur abstrait de tuiles
classes:
doc: <a href='/yamldoc/?action=version&name=itileserver.inc.php'>doc intégrée en Php</a>
*/
{ // doc 
$phpDocs['itileserver.inc.php']['file'] = <<<'EOT'
name: itileserver.inc.php
title: itileserver.inc.php - serveur abstrait de tuiles
journal:
  11-15/10/2018:
    - modification de l'interface pour que le résultat de tile() soit réutilisable
    - ajout de displayTile() qui affiche la tuile
  7/10/2018:
    - restructuration comme interface et non comme classe abstraite
  2/10/2018:
    - création
EOT;
}

{ // doc 
$phpDocs['itileserver.inc.php']['classes']['iTileServer'] = <<<'EOT'
title: interface définissant un serveur de tuiles utilisé par ViewDataset
doc: |
  L'interface iTileServer définit l'interface des serveurs WMS, WMTS, TileServer et FeatureViewer utilisés par ViewDataset
  
  Cette interface est définie par:
  
    - l'appel show() avec ypath d'une des formes:
      -  /layers/{lyrName}(/style/{style})?
      -  /layers/{lyrName}(/style/{style})?/{zoom}/{x}/{y}
      -  /layers/{lyrName}(/style/{style})?/{zone}
    - l'appel uri de la forme /layers/{lyrName}(/style/{style})?/{zoom}/{x}/{y}(.{fmt})?
    - les méthodes Php:
      - layers(): array 
      - layer($lyrName): array
      - tile($lyrName, $style, $zoom, $x, $y, $fmt): renvoie une tuile
      - displayTile($lyrName, $style, $zoom, $x, $y, $fmt): void qui affiche une tuile
EOT;
}
Interface iTileServer {
  /* renvoie la liste des couches sous la forme:
    [name => [
      'title'=>title, 'abstract'=>abstract, 'format'=>format?,
      'tileMatrixSet'=>tileMatrixSet?, 'minZoom'=>minZoom?, 'maxZoom'=>maxZoom?,
    ]]
  */
  function layers(): array;
  
  /* renvoie les infos sur la couche sous la forme:
    [ 'title'=>title, 'abstract'=>abstract, 'format'=>format?,
      'tileMatrixSet'=>tileMatrixSet?, 'minZoom'=>minZoom?, 'maxZoom'=>maxZoom?,
      'styles'=> [ styleName => ['title'=> title, 'abstract'=> abstract]]?
    ]
  */
  function layer(string $name): array;
  
  // retourne la tuile de la couche $lyrName pour $zoom/$x/$y, $fmt est l'extension: 'png', 'jpg' ou ''
  // retourne soit:
  //   - un array ['format'=>format, 'image'=> image] où
  //     - image est la tuile transmise comme string
  //     - format est son format MIME (image/png ou image/jpeg)
  //   - une ressource GD
  // ou génère une exception
  function tile(string $lyrName, string $style, int $zoom, int $x, int $y, string $fmt);
  
  // affiche la tuile de la couche $lyrName pour $zoom/$x/$y, $fmt est l'extension: 'png', 'jpg' ou ''
  // ou lève une exception
  function displayTile(string $lyrName, string $style, int $zoom, int $x, int $y, string $fmt): void;
};

// affiche en HTML les tuiles d'une couche d'un iTileServer et gère l'IHM
function showTilesInHtml(string $docid, string $lyrName, string $style, array $zxy) {
  $zoom = $zxy ? $zxy[0] : 2;
  //$this->tileMatrixLimits($lyrName, $zoom);
  $col = $zxy ? max($zxy[1], 0) : 0;
  $cmin = $zxy ? max($zxy[1]-1, 0) : 0;
  $cmax = $zxy ? min($zxy[1]+2, 2**$zoom - 1) : 2**$zoom - 1;
  $row = $zxy ? $zxy[2] : 0;
  $rmin = $zxy ? max($zxy[2]-1, 0) : 0;
  $rmax = $zxy ? min($zxy[2]+2, 2**$zoom - 1): 2**$zoom - 1;
  if ($style)
    $lyrName = "$lyrName/style/$style";
  echo "<table style='border:1px solid black; border-collapse:collapse;'>\n";
  if ($zoom) { // bouton de zoom-out si zoom > 0
    $href = sprintf("?doc=$docid&amp;ypath=/layers/$lyrName/%d/%d/%d", $zoom-1, $col/2, $row/2);
    echo "<tr><td><a href='$href'>$zoom</a></td>";
  }
  else // sinon si zoom == 0 affichage du niveau de zoom
    echo "<tr><td>$zoom</td>";
  for($col=$cmin; $col <= $cmax; $col++) {
    echo "<td align='center'>col=$col</td>";
  }
  echo "<tr>\n";
  for($row=$rmin; $row <= $rmax; $row++) {
    echo "<tr><td>row=<br>$row</td>";
    for($col=$cmin; $col <= $cmax; $col++) {
      if (($row==$rmin) || ($row==$rmax) || ($col==$cmin) || ($col==$cmax))
        $href = sprintf("?doc=$docid&amp;ypath=/layers/$lyrName/%d/%d/%d", $zoom, $col, $row);
      else
        $href = sprintf("?doc=$docid&amp;ypath=/layers/$lyrName/%d/%d/%d", $zoom+1, $col*2, $row*2);
      //$tdstyle = " style='border:1px solid blue;'";
      //$tdstyle = " style='border-collapse: collapse;'";
      $tdstyle = " style='padding: 0px; border:1px solid blue;'";
      $src = "http://$_SERVER[SERVER_NAME]/yamldoc/id.php/$docid/layers/$lyrName/$zoom/$col/$row";
      $img = "<img src='$src' alt='$lyrName/$zoom/$col/$row' height='256' width='256'>";
      echo "<td$tdstyle><a href='$href'>$img</a></td>\n";
    }
    echo "</tr>\n";
  }
  echo "</table>\n";
}
