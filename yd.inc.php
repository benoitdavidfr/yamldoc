<?php
/*PhpDoc:
name: yd.inc.php
title: yd.inc.php - fonctions générales pour yamldoc
doc: |
  si passwd.inc.php existe alors il doit définir la fonction ydpassword() qui renvoie le mot de passe secret
  sinon les docs ne sont pas cryptés
journal: |
  18/4/2018:
  - première version
*/
if (is_file('passwd.inc.php'))
  require_once __DIR__.'/passwd.inc.php';
  
function ydwrite(string $uid, string $doc) {
  if (function_exists('ydpassword'))
    return file_put_contents("docsc/$uid.doc", ydencrypt($doc));
  else
    return file_put_contents("docs/$uid.doc", $doc);
}

function ydread(string $uid) {
  //echo "ydread($uid)<br>\n";
  if (function_exists('ydpassword')) {
    $doc = @file_get_contents("docsc/$uid.doc");
    if ($doc === false)
      return false;
    else
      return yddecrypt($doc);
  }
  else {
    $doc = @file_get_contents("docs/$uid.yaml");
    if ($doc === false)
      return false;
    else
      return $doc;
  }
}

function ydencrypt(string $doc) {
  return openssl_encrypt($doc, 'aes-256-ctr', ydpassword(), OPENSSL_ZERO_PADDING, '1234567812345678');
}
function yddecrypt(string $crypted) {
  return openssl_decrypt($crypted, 'aes-256-ctr', ydpassword(), OPENSSL_ZERO_PADDING, '1234567812345678');
}
