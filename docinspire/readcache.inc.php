<?php
// readcache.inc.php - lecture d'une URL avec mise en cache

function readcache(string $url) {
  $nbRetries = 5;
  $md5 = md5($url);
  $cachename = __DIR__."/cache/$md5";
  if (is_file($cachename))
    return file_get_contents($cachename);
  else {
    while (($text = file_get_contents($url))===false) {
      if (--$nbRetries > 0)
        sleep(3);
      else
        throw new Exception("erreur dans file_get_contents($url)");
    }
    file_put_contents($cachename, $text);
    return $text;
  }
}
