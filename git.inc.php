<?php
{
$phpDocs['git.inc.php'] = <<<EOT
name: git.inc.php
title: git.inc.php - commandes Git
doc: |
  ensemble de cmdes Git utilisées par YamlDoc
  git_synchro enchaine pull et push
journal: |
  21/5/2018:
  - ajout synchro
  20/5/2018:
    - améliorations
  19/5/2018:
  - première version
EOT;
}

function git_cmde(string $cmde): int {
  echo "cmde: $cmde<br>\n";
  chdir('docs');
  exec($cmde, $output, $ret);
  if ($ret)
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

function git_add(string $docuid, string $ext): int {
  return git_cmde("git add $docuid.$ext");
}

function git_rm(string $docuid, string $ext): int {
  return git_cmde("git rm $docuid.$ext");
}

function git_commit(string $docuid, string $ext): int {
  return git_cmde("git commit $docuid.$ext -m $docuid.$ext");
}

function git_commit_a(): int {
  return git_cmde('git -c user.name="www-data" -c user.email="no-replay@example.org" commit -am "commit from php" ');
}

function git_pull(): int {
  return git_cmde('git pull');
}

function git_push(): int {
  return git_cmde('git push');
}

// enchaine pull puis si ok push
// devrait être le mécanisme de synchro standard
function git_synchro() {
  if (($ret = git_pull()) <> 0)
    return $ret;
  else
    return git_push();
}

function git_log(?string $docuid) {
  if ($docuid) {
    $ext = ydext($docuid);
    git_cmde("git log -p $docuid.$ext");
  }
  else
    git_cmde("git log");
}
