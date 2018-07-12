<?php
/*PhpDoc:
name: git.inc.php
title: git.inc.php - commandes Git
doc: |
  voir le code
*/
{
$phpDocs['git.inc.php'] = <<<EOT
name: git.inc.php
title: git.inc.php - commandes Git
doc: |
  ensemble de cmdes Git utilisées par YamlDoc
  git_synchro enchaine commit, pull et push
journal: |
  21/5/2018:
  - amélioration de git_commit_a()
  - ajout synchro
  - ajout git_pull_src qui met à jour le code Php à partir de Git
  20/5/2018:
    - améliorations
  19/5/2018:
  - première version
EOT;
}

// exécute une cmde Git en testant le code retour et en affichant le résultat
// si le code retour  n'est pas un des okReturnCodes alors affichage d'une erreur
function git_cmde(string $cmde, array $options=[]): int {
  //echo "cmde: $cmde<br>\n";
  //echo "getcwd=",getcwd(),"<br>\n";
  $okReturnCodes = isset($options['okReturnCodes']) ? $options['okReturnCodes'] : [0];
  if (!isset($options['src']))  
    chdir($_SESSION['store']);
  exec($cmde, $output, $ret);
  if (!isset($options['src']))
    chdir('..'); // permet d'enchainer plusieurs cmdes
  if (!in_array($ret, $okReturnCodes))
    echo "<b>Erreur $ret sur</b>: <u>$cmde</u><br>\n";
  else
    echo "cmde <u>$cmde</u> <b>ok</b><br>\n";
  if ($output) {
    echo "<pre>\n";
    foreach ($output as $line)
      echo "$line\n";
    echo "</pre>\n";
  }
  return $ret;
}

function git_add(string $docuid, string $ext): int { return git_cmde("git add $docuid.$ext"); }

function git_rm(string $docuid, string $ext): int { return git_cmde("git rm $docuid.$ext"); }

function git_commit(string $docuid, string $ext): int {
  return git_cmde("git commit $docuid.$ext -m $docuid.$ext", ['okReturnCodes'=> [0,1]]);
}

function git_commit_a(): int {
  $userName = isset($_SESSION['homeCatalog']) ? $_SESSION['homeCatalog'] : 'anonymous';
  $host = $_SERVER['HTTP_HOST'];
  $userName .= '@'.$host;
  return
    git_cmde(
      "git -c user.name='$userName' -c user.email='$userName' commit -am 'commit on $host' ",
      ['okReturnCodes'=> [0,1]]); // un code retour 1 signifie "nothing to commit" qui n'est pas une réelle erreur
}

function git_pull(): int { return git_cmde('git pull'); }

function git_push(): int { return git_cmde('git push'); }

// enchaine commit, pull puis si ok push
// devrait être le mécanisme de synchro standard
function git_synchro() {
  $ret = git_commit_a();
  // lorsqu'il n'y a aucune modif git commit vaut 1
  if (($ret <> 0) && ($ret <> 1))
    return $ret;
  elseif (($ret = git_pull()) <> 0)
    return $ret;
  else
    return git_push();
}

function git_log(?string $docuid) {
  if ($docuid) {
    $ext = ydext($_SESSION['store'], $docuid);
    git_cmde("git log -p $docuid.$ext");
  }
  else
    git_cmde("git log");
}

function git_pull_src(): int { return git_cmde('git pull', ['src'=>true]); }

