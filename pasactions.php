<?php
/*PhpDoc:
name: pasactions.php
title: affichage des actions du PAS
*/

require_once __DIR__.'/yd.inc.php';
require_once __DIR__.'/yamldata.inc.php';

echo "<!DOCTYPE HTML><html><head><meta charset='UTF-8'><title>pasactions</title></head><body>\n";

if (!isset($_GET['action'])) {
  echo "<h3>Menu</h3><ul>\n";
  echo "<li><a href='?action=actions'>liste des actions</a>\n";
  echo "<li><a href='?action=acteurs'>liste des acteurs</a>\n";
  echo "<li><a href='?action=dump'>dump actions</a>\n";
  die();
}
$pas = new_yamlDoc('docs', 'satellite/pas-actions2018');
$pasactions = $pas->php()['tables']['actions']['data']->php();
$pasorganisations = $pas->php()['tables']['organisations']['data']->php();

if ($_GET['action']=='actions') {
  echo "<h3>Liste des actions</h3><ul>\n";
  foreach ($pasactions as $paid => $pasaction) {
    echo "<li><a href='index.php?doc=satellite%2Fpas-actions2018&ypath=%2Ftables%2Factions%2Fdata%2F$paid'>",
         "$pasaction[Titre]</a>\n";
  }
  die();
}

// ajoute un ou pluseurs acteurs dans le tableau d'acteurs $acteurs
function ajoutActeurs(array &$acteurs, array $ajouts) {
  foreach ($ajouts as $acteur) {
    if (($pos = strpos($acteur,' en tant que ')) !== false)
      $acteur = substr($acteur, 0, $pos);
    if (!isset($acteurs[$acteur]))
      $acteurs[$acteur] = 1;
    else
      $acteurs[$acteur]++;
  }
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

if ($_GET['action']=='acteurs') {
  $acteurs = []; // nom => nbre
  foreach ($pasactions as $paid => $pasaction) {
    //echo "<pre>pasaction="; print_r($pasaction); echo "</pre>\n";
    if (!isset($pasaction['Acteurs clés'])) {
      echo "Pas de Acteurs clés pour $id<br>\n";
    }
    else {
      $actionActeurs = []; // acteurs de l'action sans doublon
      foreach ($pasaction['Acteurs clés'] as $typacteur => $acteur) {
        if (!is_array($acteur)) {
          if (!in_array($acteur, $actionActeurs))
            $actionActeurs[] = $acteur;
        }
        else {
          foreach ($acteur as $a)
            if (!in_array($a, $actionActeurs))
              $actionActeurs[] = $a;
        }
      }
      //echo "actionActeurs pour $paid="; print_r($actionActeurs); echo "<br>\n";
      ajoutActeurs($acteurs, $actionActeurs);
    }
  }
  ksort($acteurs);
  //echo "<pre>acteurs="; print_r($acteurs); echo "</pre>\n";
  $nbacteurs = count($acteurs);
  echo "<h3>Acteurs du PAS ($nbacteurs)</h3>\n";
  $noacteur = 0;
  echo "<table><tr><td>\n";
  echo "<ul>\n";
  foreach ($acteurs as $acteur => $nbactions) {
    echo "<li><a href='?action=actionsParActeur&amp;acteur=",urlencode($acteur),
        "'>$acteur</a>",($nbactions>1?" ($nbactions)":''),"\n";
    $noacteur++;
    if (($noacteur == round($nbacteurs/3)) || ($noacteur == round(2*$nbacteurs/3)))
      echo "</td><td>\n";
  }
  echo "</td></tr></table>\n";
  die();
}

// rempalec les caractères encodés par le caractère accentué correspondant
function accents(string $url): string {
  return str_replace(['%27','%C3%A0','%C3%A7','%C3%A9'],["'",'à','ç','é'], $url);
}

// affichage des actions d'un acteur
if ($_GET['action']=='actionsParActeur') {
  echo "<h3>$_GET[acteur]</h3>\n";
  $sigle = $_GET['acteur'];
  if (isset($pasorganisations[$sigle])) {
    $acteur = $pasorganisations[$sigle];
    echo "<i>Nom:</i> $acteur[name]<br>\n";
    if (isset($acteur['sameAs']))
      echo "<i>Lien Wikipédia:</i> <a href='$acteur[sameAs]' target=_blank>",accents($acteur['sameAs']),"</a><br>\n";
    if (isset($acteur['parentOrganization'])) {
      $acteur = $pasorganisations[$acteur['parentOrganization']];
      echo "<i>Appartient à:</i> <a href='$acteur[sameAs]' target=_blank>$acteur[name]</a><br>\n";
    }
    if (isset($acteur['memberOf'])) {
      $acteur = $pasorganisations[$acteur['memberOf']];
      echo "<i>Est un(e):</i> <a href='$acteur[sameAs]' target=_blank>$acteur[name]</a><br>\n";
    }
  }
  echo "<h4>Liste des actions</h4><ul>\n";
  foreach ($pasactions as $paid => $pasaction) {
    $actionSelectionnee = false;
    foreach ($pasaction['Acteurs clés'] as $typacteur => $acteurs) {
      //echo "acteur="; print_r($acteur);
      if (checkActeur($_GET['acteur'], $acteurs))
        $actionSelectionnee = true;
    }
    if ($actionSelectionnee)
      echo "<li><a href='index.php?doc=satellite%2Fpas-actions2018&ypath=%2Ftables%2Factions%2Fdata%2F$paid'>",
           "$pasaction[Titre]</a>\n";
  }
}

if ($_GET['action']=='dump') {
  echo "<pre>actions="; print_r($pasactions);
  die();
}
