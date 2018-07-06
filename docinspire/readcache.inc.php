<?php
/*PhpDoc:
name: readcache.inc.php
title: readcache.inc.php - lecture d'une URL avec mise en cache
doc: |
  lecture d'une URL avec relance en cas d'erreur 
journal: |
  6/7/2018:
    ajout pause et relance
  4/7/2018:
    création
*/

function readcache(string $url) {
  $nbRetries = 5;
  $md5 = md5($url);
  $cachename = __DIR__."/cache/$md5";
  if (is_file($cachename))
    return file_get_contents($cachename);
  else {
    while (($text = file_get_contents($url))===false) {
      if (--$nbRetries >= 0) {
        echo "Erreur de lecture $nbRetries sur $url<br>\n";
        sleep(3);
      }
      else
        throw new Exception("erreur dans file_get_contents($url)");
    }
    file_put_contents($cachename, $text);
    return $text;
  }
}
