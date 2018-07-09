# Visualisateur de documents YamlDoc
YamlDoc est un système de gestion documentaire [décrit ici](http://georef.eu/yamldoc/?doc=yamldoc).  
Ce répertoire Github correspond à l'archive Git du visualisateur de ce système.  
Les documents de l'espace public peuvent être consultés sur le
[site de consultation](http://georef.eu/yamldoc/?doc=index).

Ce logiciel utilise MySQL comme index plein texte.
Pour cela il faut paramétrer l'accès à un serveur et une base en spécialisant le fichier
mysqlparams.inc.php.model et en l'appelant mysqlparams.inc.php  
Les paramètres doivent être insérés dans le fichier en suivant les principes décrits dans le fichier.