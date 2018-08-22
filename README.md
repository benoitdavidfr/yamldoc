# Visualiseur de documents YamlDoc
YamlDoc est un système de gestion documentaire extensible [décrit ici](http://georef.eu/yamldoc/?doc=yamldoc)
intégrant la gestion de données géographiques.  
Ce répertoire Github correspond au code Php du visualiseur de ce système.  
Les documents de l'espace public peuvent être consultés sur le
[site de consultation](http://georef.eu/yamldoc/?doc=index) ;
ils sont disponibles dans le [répertoire yamldocs](https://github.com/benoitdavidfr/yamldocs)

Ce logiciel utilise MySQL comme index plein texte et pour stocker certaines séries de données géographiques.
Pour cela il faut paramétrer l'accès à un serveur et une base en spécialisant le fichier
mysqlparams.inc.php.model et en l'appelant mysqlparams.inc.php  
Les paramètres doivent être insérés dans le fichier en suivant les principes décrits dans le fichier.

Une documentation est disponible dans le
[fichier phpdoc.yaml](https://github.com/benoitdavidfr/yamldoc/blob/master/phpdoc.yaml).