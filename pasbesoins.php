<?php
/*PhpDoc:
name: pasbesoins.php
title: affichage des besoins du PAS
*/

require_once __DIR__.'/yd.inc.php';
require_once __DIR__.'/yamldata.inc.php';

echo "<!DOCTYPE HTML><html><head><meta charset='UTF-8'><title>pasactions</title></head><body>\n";

if (!isset($_GET['action'])) {
  echo "<h3>Menu</h3><ul>\n";
  echo "<li><a href='?action=besoins'>lsite des besoins</a>\n";
  echo "<li><a href='?action=utilisateurs'>liste des utilisateurs</a>\n";
  echo "<li><a href='?action=dump'>dump</a>\n";
  die();
}
$pasbesoins = new_YamlDoc('satellite/pas-besoins');

if ($_GET['action']=='besoins') {
  echo "<h3>Liste des besoins</h3><ul>\n";
  foreach ($pasbesoins->php()['data']->php() as $paid => $pasaction) {
    echo "<li><a href='index.php?doc=satellite%2Fpas-actions2018&ypath=%2Fdata%2F$paid'>$pasaction[Titre]</a>\n";
  }
  die();
}

// teste si un acteur $acteur fait partie de la liste
function checkActeur(string $acteur, $acteurs): bool {
  //echo "checkActeur(acteur=$acteur, acteurs=",(!is_array($acteurs)?$acteurs:implode(',',$acteurs)),")<br>\n";
  if (!is_array($acteurs)) {
    if (($pos = strpos($acteurs,' en tant que ')) !== false)
      $acteurs = substr($acteurs, 0, $pos);
    return ($acteurs==$acteur);
  }
  else { // if (is_array($acteurs))
    foreach ($acteurs as $a) {
      if (($pos = strpos($a,' en tant que ')) !== false)
        $a = substr($a, 0, $pos);
      if ($acteur==$a)
        return true;
    }
    return false;
  }
}

if ($_GET['action']=='utilisateurs') {
  $utilisateurs = []; // nom => nbre
  foreach ($pasbesoins->php()['data']->php() as $pbid => $pasbesoin) {
    //echo "<pre>pasbesoin="; print_r($pasbesoin); echo "</pre>\n";
    if (!isset($pasbesoin['utilisateurs'])) {
      echo "Pas d'utilisateurs pour $pbid<br>\n";
    }
    else {
      foreach ($pasbesoin['utilisateurs'] as $utilisateur) {
        if (!isset($utilisateurs[$utilisateur]))
          $utilisateurs[$utilisateur] = 1;
        else
          $utilisateurs[$utilisateur]++;
      }
    }
  }
  ksort($utilisateurs);
  //echo "<pre>acteurs="; print_r($acteurs); echo "</pre>\n";
  $nbutilisateurs = count($utilisateurs);
  echo "<h3>Utilisateurs des besoins du PAS ($nbutilisateurs)</h3>\n";
  $noutilisateur = 0;
  echo "<table><tr><td>\n";
  echo "<ul>\n";
  foreach ($utilisateurs as $utilisateur => $nbbesoins) {
    echo "<li><a href='?action=besoinsParUtilisateur&amp;utilisateur=",urlencode($utilisateur),
        "'>$utilisateur</a>",($nbbesoins>1?" ($nbbesoins)":''),"\n";
    $noutilisateur++;
    if (($noutilisateur == round($nbutilisateurs/3)) || ($noutilisateur == round(2*$nbutilisateurs/3)))
      echo "</td><td>\n";
  }
  echo "</td></tr></table>\n";
  die();
}

// rempalec les caractères encodés par le caractère accentué correspondant
function accents(string $url): string {
  return str_replace(['%27','%C3%A9','%C3%A7'],["'",'é','ç'], $url);
}

// affichage des actions d'un acteur
if ($_GET['action']=='besoinsParUtilisateur') {
  echo "<h3>$_GET[utilisateur]</h3>\n";
  $sigle = $_GET['utilisateur'];
  if (isset($pasbesoins->php()['sigles'][$sigle])) {
    //print_r($pasactions->php()['sigles'][$_GET['acteur']]);
    $acteur = $pasactions->php()['sigles'][$sigle];
    echo "<i>Nom:</i> $acteur[name]<br>\n";
    if (isset($acteur['sameAs']))
      echo "<i>Lien Wikipédia:</i> <a href='$acteur[sameAs]' target=_blank>",accents($acteur['sameAs']),"</a><br>\n";
    if (isset($acteur['parentOrganization'])) {
      $acteur = $pasactions->php()['sigles'][$acteur['parentOrganization']];
      echo "<i>Appartient à:</i> <a href='$acteur[sameAs]' target=_blank>$acteur[name]</a><br>\n";
    }
    if (isset($acteur['memberOf'])) {
      $acteur = $pasactions->php()['sigles'][$acteur['memberOf']];
      echo "<i>Est un(e):</i> <a href='$acteur[sameAs]' target=_blank>$acteur[name]</a><br>\n";
    }
  }
  echo "<h4>Liste des besoins</h4><ul>\n";
  foreach ($pasbesoins->php()['data']->php() as $pbid => $pasbesoin) {
    $besoinSelectionne = false;
    if (isset($pasbesoin['utilisateurs']) && in_array($_GET['utilisateur'], $pasbesoin['utilisateurs'])) {
      $besoinSelectionne = true;
    }
    if ($besoinSelectionne)
      echo "<li><a href='index.php?doc=satellite%2Fpas-besoins&ypath=%2Fdata%2F$pbid'>$pasbesoin[Titre]</a>\n";
  }
}

if ($_GET['action']=='dump') {
  echo "<pre>besoins="; print_r($pasbesoins->php());
  die();
}
