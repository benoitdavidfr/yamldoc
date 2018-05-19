<?php
{
$phpDocs['git.inc.php'] = <<<EOT
name: git.inc.php
title: git.inc.php - commandes Git
doc: |
journal: |
  19/5/2018:
  - première version
EOT;
}

function git_cmde(string $cmde) {
  chdir('docs');
  exec($cmde, $output, $ret);
  if ($ret)
    echo "Erreur $ret sur: $cmde<br>\n";
  if ($output) {
    echo "<pre>\n";
    foreach ($output as $line)
      echo "$line\n";
    echo "</pre>\n";
  }
}

function git_add(string $docuid, string $ext) {
  git_cmde("git add $docuid.$ext");
}

function git_rm(string $docuid, string $ext) {
  git_cmde("git rm $docuid.$ext");
}

function git_commit(string $docuid, string $ext) {
  git_cmde("git commit $docuid.$ext -m $docuid.$ext");
}

function git_commit_a() {
  git_cmde('git -c user.name="www-data" -c user.email="no-replay@example.org" commit -am "commit form php" ');
}