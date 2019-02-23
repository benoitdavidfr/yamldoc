<?php
/*PhpDoc:
name: ydclasses.inc.php
title:  ydclasses.inc.php - liste des classes de documents YamlDoc
*/
require_once __DIR__.'/yamldoc.inc.php';
//require_once __DIR__.'/dumbdoc.inc.php';
require_once __DIR__.'/basicyamldoc.inc.php';
require_once __DIR__.'/catalog.inc.php';
require_once __DIR__.'/servreg.inc.php';
require_once __DIR__.'/tree.inc.php';
require_once __DIR__.'/ydata.inc.php';
require_once __DIR__.'/yamldata.inc.php';
require_once __DIR__.'/multidata.inc.php';
require_once __DIR__.'/strucdata.inc.php';
require_once __DIR__.'/yamlskos.inc.php';
require_once __DIR__.'/datamodel.inc.php';
require_once __DIR__.'/legaldoc.inc.php';
require_once __DIR__.'/odtdoc.inc.php';
require_once __DIR__.'/pdfdoc.inc.php';

require_once __DIR__.'/ydjschema.inc.php';

# données géo vecteur
require_once __DIR__.'/wfsserver.inc.php';
require_once __DIR__.'/wfsjson.inc.php';
require_once __DIR__.'/wfsgml.inc.php';
require_once __DIR__.'/featureds.inc.php';
require_once __DIR__.'/fdsspecs.inc.php';
require_once __DIR__.'/markerlib.inc.php';
require_once __DIR__.'/map.inc.php';

# consultation
require_once __DIR__.'/itileserver.inc.php';
require_once __DIR__.'/wmsserver.inc.php';
require_once __DIR__.'/wmtsserver.inc.php';
require_once __DIR__.'/tileserver.inc.php';
require_once __DIR__.'/featureviewer.inc.php';
require_once __DIR__.'/tilecache.inc.php';
require_once __DIR__.'/viewds.inc.php';

# métadonnées
require_once __DIR__.'/cswserver.inc.php';
require_once __DIR__.'/geocat.inc.php';
require_once __DIR__.'/mddb.inc.php';
require_once __DIR__.'/gcsubjlist.inc.php';

# API INSEE
require_once __DIR__.'/inseeapi.inc.php';
require_once __DIR__.'/sireneapi.inc.php';
require_once __DIR__.'/inseenomapi.inc.php';
