<?php
/*PhpDoc:
name:  isometadata.inc.php
title: isometadata.inc.php - simplification des MD ISO 19115/19139
classes:
screens:
doc: |
  Importé, ce fichier définit la classe statique IsoMetadata qui permet d'effectuer la simplification et la
  standardisation des métadonnées. En effet le standard ISO 191xx est complexe et de nombreux éléments sont
  inutiles par rapport à Inspire. Ainsi un format simplifié de MD est défini par la classe IsoMetadata.
  Cette simplification s'effectue en 2 étapes:
  1) une transformation XSLT : XML -> XML
  2) une homogénéisation JSON des données (appelée standardisation) en vue de leur export en Yaml ou JSON

  Ce fichier peut être aussi exécuté en mode Web afin d'effectuer:
  1) L'affichage en HTML des éléments du format simplifié
  2) l'affichage (en texte ou en XML) de la feuille de style de la transformation XSLT
  3) le test unitaire de la simplification et de la standardisation sur les catalogues proposés par geocats
  
  A FAIRE:
  - vérifier les xpath des éléments useLimitation et accessConstraints
  - identifier les MD Inspire qui ne sont pas extraites

journal: |
  30/8-2/9/2018:
    traitement des MD multi-lingues comme celles de Sextant
  24/8/2018:
    fork de gcatas/cswharv/metadata.inc.php pour yamldoc
    modification des champs pour correspondre au JSON-schema
      https://github.com/benoitdavidfr/inspireinoai/blob/master/metadata.yaml v 0.6.0
  16/9/2017:
    réécriture de Metadata::standardize() car l'utilisation de json_encode() sur un SimpleXml n'est pas fiable
  22/8/2017:
    correction d'un bug dans asXsl()
  21/8/2017:
    Modification des xpath de keyword afin d'obtenir tous les mots-clés listés dans l'élément XML
    gmd:descriptiveKeywords et pas seulement le premier
  20/8/2017:
    Ajout de la possibilité de définir des xpath alternatifs utile pour certains éléments (comme la langue)
    Ajout systématique d'un test pour éviter de générer des éléments vides
    Changement des noms d'éléments:
      language -> resourceLanguage
      mdLanguage -> language
  11/8/2017:
    amélioration de la doc
  9/8/2017:
    amélioration de la doc
  6/8/2017:
    ajout de l'email des parties responsables et mdContact
  4/8/2017:
    améliorations
  3/8/2017:
    première version
*/
/*PhpDoc: classes
name:  class IsoMetadata
title: class IsoMetadata - simplification des MD ISO 19115/19139
privateproperties:
methods:
doc: |
  La classe statique IsoMetadata:
  - définit dans la variable statique metadata une structure simplifiée pour les métadonnées,
  - simplifie un document XML ISO 19115/19139 au moyen d'un processus XSLT fondé sur une feuille de styles
    générée à partir de la définition de la structure simplifiée,

  Les principales méthodes externes sont :
  - la méthode simplify() qui prend une chaine XML et renvoie un SimpleXMLElement correspondant à la structure
    simplifiée.
  - la méthode standardize() qui prend un SimpleXMLElement et renvoie un tableau Php correspondant à la structure
    standardisée.

  Test ML sur http://localhost/yamldoc/isometadata.inc.php/geocats/sextant?action=simplify&startpos=3781
*/
class IsoMetadata {
/*PhpDoc: privateproperties
name: metadata
title: static $metadata - Définit la structure simplifiée par une liste d'éléments
doc: |
  Chaque élément est identifié par un nom court, qui sera utilisé comme balise XML dans la structure simplifiée,
  et définit :
  - s'il s'agit d'une MD Inspire alors le no utilisé dans la partie B des annexes du règlement Métadonnées
    http://eur-lex.europa.eu/legal-content/FR/TXT/HTML/?uri=CELEX:32008R1205&from=FR
  - un titre en français et un en anglais
  - un xpath dans le XML ISO 191xx à partir de //gmd:MD_Metadata
  - si il y a plusieurs xpath possibles alors le champ xpath est remplacé par un champ xpaths qui en donne la liste
    ceci n'est possible que pour les champs atomiques unaires ou n-aires
  - une multiplicité définie pour les données et les services qui peut être:
    - absent : l'élément est absent pour les données ou les services
    - 1 : l'élément est unaire et obligatoire 
    - '0..1' : l'élément est unaire et facultatif 
    - '0..*' : l'élément est n-aire et facultatif 
    - '1..*' : l'élément est n-aire et obligatoire
  - la valeur de l'élément est soit atomique, soit une structure, ce dernier cas est défini par la présence d'un
    champ subfields qui liste les champs de la structure sous la forme nom => xpath
  Lorsqu'un élément existe pour les données et les services alors il doit être du même format
  soit unaire, soit n-aire atomique, soit n-aire structuré

  La structure Php de chaque élément de MD est la suivante:
  {name} => [ // nom court de l'élément 
    'noInspire'=> // référence Inspire s'il s'agit d'une MD Inspire
    'title-fr'=> // titre le l'élément en français
    'title-en'=> // titre le l'élément en anglais
    'xpath'=> // xpath de l'élément par rapport à gmd:MD_Metadata
    'xpaths'=> // liste de xpath possibles de l'élément par rapport à gmd:MD_Metadata
    'multiplicity'=> ['data'=>{m}, 'service'=>{m}] où {m} dans (1,'0..1','0..*','1..*')
    'subfields' => [
      {subname} => {subpath}
    ]
  ]
  
  La feuille de style de l'élément subject est définie dans le champ xsl
  Lorsqu'un xpath se termine par gco:CharacterString, l'existence d'un gmd:PT_FreeText est prise en compte.

  Référence: <a href='http://cnig.gouv.fr/wp-content/uploads/2014/07/Guide-de-saisie-des-%C3%A9l%C3%A9ments-de-m%C3%A9tadonn%C3%A9es-INSPIRE-v1.1.1.pdf'>
  Guide de saisie des éléments de métadonnées INSPIRE Appliqué aux données</a>
  - Groupe de travail « Métadonnées » Version 1.1.1 – Juillet 2014
*/
  private static $metadata = [
// fileIdentifier (hors règlement INSPIRE)
    'fileIdentifier' => [
      'title-fr' => "Identificateur du fichier",
      'title-en' => "File identifier",
      'xpath' => 'gmd:fileIdentifier/*',
      'multiplicity' => [ 'data' => 1, 'service' => 1 ],
    ],
// parentIdentifier (hors règlement INSPIRE)
    'parentIdentifier' => [
      'title-fr' => "Identificateur d'un parent",
      'title-en' => "Parent identifier",
      'xpath' => 'gmd:parentIdentifier/*',
      'multiplicity' => [ 'data' => '0..*', 'service' => '0..*' ],
    ],
  // aggregationInfo (hors règlement INSPIRE)
    'aggregationInfo' =>  [
      'title-fr' => "métadonnées agrégées",
      'title-en' => "aggregated metadata",
      'xpath' => 'gmd:identificationInfo/*/gmd:aggregationInfo',
      'multiplicity' => [ 'data' => '0..*' ],
      'subfields' => [
        'aggregateDataSetIdentifier' => '*/gmd:aggregateDataSetIdentifier/*/gmd:code/*',
        'associationType' => 'gmd:associationType/*',
        'initiativeType' => 'gmd:initiativeType/*',
      ],
    ],
  // 1.1. Intitulé de la ressource - 1.1. Resource title
    'title' => [
      'noInspire' => '1.1',
      'title-fr' => "Intitulé de la ressource",
      'title-en' => "Resource title",
      'xpath' => 'gmd:identificationInfo/*/gmd:citation/*/gmd:title/gco:CharacterString',
      'multiplicity' => [ 'data' => 1, 'service' => 1 ],
    ],
  // alternative (hors règlement INSPIRE)
    'alternative' => [
      'title-fr' => "Intitulé alternatif de la ressource",
      'title-en' => "Alternate resource title",
      'xpath' => 'gmd:identificationInfo/*/gmd:citation/*/gmd:alternateTitle/gco:CharacterString',
      'multiplicity' => [ 'data' => '0..*', 'service' => '0..*' ],
    ],
  // 1.2. Résumé de la ressource - 1.2. Resource abstract
    'abstract' => [
      'noInspire' => '1.2',
      'title-fr' => "Résumé de la ressource",
      'title-en' => "Resource abstract",
      'xpath' => 'gmd:identificationInfo/*/gmd:abstract/gco:CharacterString',
      'multiplicity' => [ 'data' => 1, 'service' => 1 ],
    ],
  // 1.3. Type de la ressource - 1.3. Resource type
    'type' => [
      'noInspire' => '1.3',
      'title-fr' => "Type de la ressource",
      'title-en' => "Resource type",
      'xpaths' => [
        'gmd:hierarchyLevel/*/@codeListValue',
        'gmd:hierarchyLevelName/gco:CharacterString',
      ],
      'valueDomain' => ['dataset','series','services','...'],
      'multiplicity' => [ 'data' => 1, 'service' => 1 ],
    ],
  // 1.4. Localisateur de la ressource - 1.4. Resource locator
    'relation'=> [
      'noInspire' => '1.4',
      'title-fr' => "Localisateur de la ressource",
      'title-en' => "Resource locator",
      'xpath' =>  'gmd:distributionInfo/*/gmd:transferOptions/*/gmd:onLine',
      'multiplicity' => [ 'data' => '0..*', 'service' => '0..*' ],
      'subfields' => [
        'url' => '*/gmd:linkage/gmd:URL',
        'protocol' => '*/gmd:protocol/*',
        'name' => '*/gmd:name/*',
      ],
    ],
  // 1.5. Identificateur de ressource unique - 1.5. Unique resource identifier
  // je force la possibilité pour un service d'avoir des URI
    'identifier' => [
      'noInspire' => '1.5',
      'title-fr' => "Identificateur de ressource unique",
      'title-en' => "Unique resource identifier",
      'xpath' => 'gmd:identificationInfo/*/gmd:citation/*/gmd:identifier',
      'multiplicity' => [ 'data'=> '1..*', 'service'=> '1..*' ],
      'subfields' => [
        'code' => '*/gmd:code/*',
        'codeSpace' => '*/gmd:codeSpace/*',
      ],
    ],
  // 1.6.  Ressource Couplée (service) - 1.6. Coupled resource
    'operatesOn' => [
      'noInspire' => '1.6',
      'title-fr' => "Ressource Couplée",
      'title-en' => "Coupled resource",
      'xpath' => 'gmd:identificationInfo/*/srv:operatesOn',
      'multiplicity' => [ 'service' => '0..*' ],
      'subfields'=> [
        'uuidref' => '@uuidref',
        'href' => '@xlink:href',
      ],
    ],
  // 1.7. Langue de la ressource - 1.7. Resource language
  // plusieurs xpath alternatifs: soit un code, soit une chaine
    'language' => [
      'noInspire' => '1.7',
      'title-fr' => "Langue de la ressource",
      'title-en' => "Resource language",
      'xpaths' => [
        'gmd:identificationInfo/*/gmd:language/*/@codeListValue',
        'gmd:identificationInfo/*/gmd:language/*',
      ],
      'multiplicity' => [ 'data' => '0..*' ],
    ],
  // Encodage (hors règlement INSPIRE)
    'format' => [
      'title-fr' => "Encodage",
      'title-en' => "Distribution format",
      'xpath' => 'gmd:distributionInfo/*/gmd:distributionFormat',
      'multiplicity' => [ 'data'=> '1..*', 'service'=> '0..*' ],
      'subfields'=> [
        'name' => '*/gmd:name/*',
        'version' => '*/gmd:version/*',
      ],
    ],
  // Encodage des caractères (hors règlement INSPIRE)
    'characterSet' => [
      'title-fr' => "Encodage des caractères",
      'title-en' => "Character set",
      'xpath' => 'gmd:identificationInfo/*/gmd:characterSet/*/@codeListValue',
      'multiplicity' => [ 'data' => '0..1' ],
    ],
  // Type de représentation géographique (hors règlement INSPIRE)
    'spatialRepresentationType' => [
      'title-fr' => "Type de représentation géographique",
      'title-en' => "Spatial representation type",
      'xpath' => 'gmd:identificationInfo/*/gmd:spatialRepresentationType/*/@codeListValue',
      'multiplicity' => [ 'data' => '1..*' ],
    ],
  
  // 2. CLASSIFICATION DES DONNÉES ET SERVICES GÉOGRAPHIQUES
  // 2.1. Catégorie thématique - 2.1. Topic category
    'topicCategory' => [
      'noInspire' => '2.1',
      'title-fr' => "Catégorie thématique",
      'title-en' => "Topic category",
      'xpath' => 'gmd:identificationInfo/*/gmd:topicCategory/*',
      'multiplicity' => [ 'data' => '1..*' ],
      'valueDomain' => [
          'farming','biota','boundaries','climatologyMeteorologyAtmosphere','economy','elevation','environment',
          'geoscientificInformation','health','imageryBaseMapsEarthCover','intelligenceMilitary','inlandWaters',
          'location','oceans','planningCadastre','society','structure','transportation','utilitiesCommunication'
      ],
    ],
  // 2.2.  Type de service de données géographiques (service) - 2.2. Spatial data service type
    'serviceType' => [
      'noInspire' => '2.2',
      'title-fr' => "Type de service de données géographiques",
      'title-en' => "Spatial data service type",
      'xpath' => 'gmd:identificationInfo/*/srv:serviceType/*',
      'multiplicity' => [ 'service' => 1 ],
      'valueDomain' => ['discovery','view','download','transformation','invoke','other']
    ],

  // 3. MOT CLÉ - KEYWORD
  // 3.1. Valeur du mot clé - Keyword value
  // 3.2. Vocabulaire contrôlé d’origine - Originating controlled vocabulary
    'subject' => [
      'noInspire' => '3.',
      'title-fr' => "Mot-clé",
      'title-en' => "Keyword",
      'xpath' => 'gmd:identificationInfo/*/gmd:descriptiveKeywords/*/gmd:keyword/gco:CharacterString',
      'multiplicity' => [ 'data' => '1..*', 'service' => '1..*' ],
      'subfields'=> [
        'value' => '.',
        'cvocIdentifier' => '../../gmd:thesaurusName/*/gmd:identifier/*/gmd:code/*',
        'cvocTitle' => '../../gmd:thesaurusName/*/gmd:title/gco:CharacterString',
        'cvocReferenceDate' => '../../gmd:thesaurusName/*/gmd:date/*',
      ],
      // feuille de style pour extraire les mots-clés multi-ligues
      'xslml'=> <<<EOT
        <!-- élément subject -->
        <xsl:if test="gmd:identificationInfo/*/gmd:descriptiveKeywords/*/gmd:keyword">
          <xsl:for-each select="gmd:identificationInfo/*/gmd:descriptiveKeywords/*/gmd:keyword">
            <subject>
              <xsl:if test="gco:CharacterString">
                <value><xsl:value-of select="gco:CharacterString" /></value>
              </xsl:if>
              <xsl:if test="gmd:PT_FreeText">
                <xsl:for-each select="gmd:PT_FreeText/gmd:textGroup">
                  <localised>
                    <locale><xsl:value-of select="gmd:LocalisedCharacterString/@locale"/></locale>
                    <value><xsl:value-of select="gmd:LocalisedCharacterString" /></value>
                  </localised>
                </xsl:for-each>
              </xsl:if>
              <xsl:if test="../gmd:thesaurusName/*/gmd:identifier/*/gmd:code/*">
                <cvocIdentifier><value>
                  <xsl:value-of select="../gmd:thesaurusName/*/gmd:identifier/*/gmd:code/*" />
                </value></cvocIdentifier>
              </xsl:if>
              <xsl:if test="../gmd:thesaurusName/*/gmd:title">
                <cvocTitle>
                  <xsl:if test="../gmd:thesaurusName/*/gmd:title/gco:CharacterString">
                    <value>
                      <xsl:value-of select="../gmd:thesaurusName/*/gmd:title/gco:CharacterString" />
                    </value>
                  </xsl:if>
                  <xsl:if test="../gmd:thesaurusName/*/gmd:title/gmd:PT_FreeText">
                    <localised>
                      <xsl:for-each select="../gmd:thesaurusName/*/gmd:title/gmd:PT_FreeText/gmd:textGroup">
                        <locale><xsl:value-of select="gmd:LocalisedCharacterString/@locale"/></locale>
                        <value><xsl:value-of select="gmd:LocalisedCharacterString" /></value>
                      </xsl:for-each>
                    </localised>
                  </xsl:if>
                </cvocTitle>
              </xsl:if>
              <xsl:if test="../gmd:thesaurusName/*/gmd:date/*">
                <cvocReferenceDate><value>
                  <xsl:value-of select="../gmd:thesaurusName/*/gmd:date/*" />
                </value></cvocReferenceDate>
              </xsl:if>
            </subject>
          </xsl:for-each>
        </xsl:if>\n
EOT
    ],
  
  // 4. SITUATION GÉOGRAPHIQUE - 4. GEOGRAPHIC LOCATION
  // 4.1. Rectangle de délimitation géographique - 4.1. Geographic bounding box
  // revoir le path pour les services
    'spatial' => [
      'noInspire' => '4.',
      'title-fr' => "Rectangle de délimitation géographique",
      'title-en' => "Geographic bounding box",
      'xpath' => 'gmd:identificationInfo/*/gmd:extent/*/gmd:geographicElement/*',
      'multiplicity' => [ 'data' => '1..*', 'service' => '0..*' ],
      'subfields'=> [
        'westlimit' => 'gmd:westBoundLongitude/*',
        'eastlimit' => 'gmd:eastBoundLongitude/*',
        'southlimit' => 'gmd:southBoundLatitude/*',
        'northlimit' => 'gmd:northBoundLatitude/*',
      ],
    ],

  // 5. RÉFÉRENCE TEMPORELLE
  // 5.1. Étendue temporelle - 5.1. Temporal extent
    'valid' => [
      'noInspire' => '5.1',
      'title-fr' => "Étendue temporelle",
      'title-en' => "Temporal extent",
      'xpath' => 'gmd:identificationInfo/*/gmd:extent/*/gmd:temporalElement',
      'multiplicity' => [ 'data' => '0..*', 'service' => '0..*' ],
    ],
  // 5.2. Date de publication - 5.2. Date of publication
    'issued' => [
      'noInspire' => '5.2',
      'title-fr' => "Date de publication",
      'title-en' => "Date of publication",
      'xpath' => "gmd:identificationInfo/*/gmd:citation/*"
              ."/gmd:date[./gmd:CI_Date/gmd:dateType/*/@codeListValue='publication']/gmd:CI_Date/gmd:date/*",
      'multiplicity' => [ 'data' => '0..*', 'service' => '0..*' ],
    ],
  // 5.3. Date de dernière révision - 5.3. Date of last revision
    'modified' => [
      'noInspire' => '5.3',
      'title-fr' => "Date de dernière révision",
      'title-en' => "Date of last revision",
      'xpath' => "gmd:identificationInfo/*/gmd:citation/*"
                ."/gmd:date[./gmd:CI_Date/gmd:dateType/*/@codeListValue='revision']/gmd:CI_Date/gmd:date/*",
      'multiplicity' => [ 'data' => '0..*', 'service' => '0..*' ],
    ],
  // 5.4. Date de création - 5.4. Date of creation
    'created' => [
      'noInspire' => '5.4',
      'title-fr' => "Date de création",
      'title-en' => "Date of creation",
      'xpath' => "gmd:identificationInfo/*/gmd:citation/*"
                ."/gmd:date[./gmd:CI_Date/gmd:dateType/*/@codeListValue='creation']/gmd:CI_Date/gmd:date/*",
      'multiplicity' => [ 'data' => '0..*', 'service' => '0..*' ],
    ],

  // 6. QUALITÉ ET VALIDITÉ - 6. QUALITY AND VALIDITY
  // 6.1. Généalogie - 6.1. Lineage
    'lineage' => [
      'noInspire' => '6.1',
      'title-fr' => "Généalogie",
      'title-en' => "Lineage",
      'xpath' => 'gmd:dataQualityInfo/*/gmd:lineage/*/gmd:statement/gco:CharacterString',
      'multiplicity' => [ 'data' => 1 ],
    ],
  // 6.2. Résolution spatiale - 6.2. Spatial resolution
    'spatialResolutionScaleDenominator' => [
      'noInspire' => '6.2',
      'title-fr' => "Résolution spatiale : dénominateur de l'échelle",
      'title-en' => "Spatial resolution: scale denominator",
      'xpath' => 'gmd:identificationInfo/*/gmd:spatialResolution/*/gmd:equivalentScale/*/gmd:denominator/*',
      'multiplicity' => [ 'data' => '0..*', 'service' => '0..*' ],
    ],
    'spatialResolutionDistance' => [
      'noInspire' => '6.2',
      'title-fr' => "Résolution spatiale : distance",
      'title-en' => "Spatial resolution: distance",
      'xpath' => 'gmd:identificationInfo/*/gmd:spatialResolution/*/gmd:distance',
      'multiplicity' => [ 'data' => '0..*', 'service' => '0..*' ],
      'subfields'=> [
        'unit' => 'gco:Distance/@uom',
        'value' => 'gco:Distance',
      ],
    ],

  // 7. CONFORMITÉ - 7. CONFORMITY
  // 7.1. Spécification - 7.1. Specification
    'conformsTo' => [
      'noInspire' => '7.',
      'title-fr' => "Spécification",
      'title-en' => "Specification",
      'xpath' => 'gmd:dataQualityInfo/*/gmd:report/*/gmd:result',
      'multiplicity' => [ 'data' => '1..*', 'service' => '1..*' ],
      'subfields'=> [
        'referenceDate' => '*/gmd:specification/*/gmd:date/*/gmd:date/*',
        'title' => '*/gmd:specification/*/gmd:title/gco:CharacterString',
  // 7.2. Degré - 7.2. Degree
        'degreeOfConformity' => '*/gmd:pass/*',
      ],
    ],

  // 8. CONTRAINTES EN MATIÈRE D’ACCÈS ET D’UTILISATION - 8. CONSTRAINT RELATED TO ACCESS AND USE
  // 8.1. Conditions applicables à l’accès et à l’utilisation - 8.1. Conditions applying to access and use
    'conditionsToAccessAndUse' => [
      'noInspire' => '8.1',
      'title-fr' => "Conditions d'utilisation",
      'title-en' => "Use conditions",
      'xpath' => 'gmd:identificationInfo/*/gmd:resourceConstraints/*/gmd:useLimitation/gco:CharacterString',
      'multiplicity' => [ 'data' => '1..*', 'service' => '1..*' ],
    ],

  // 8.2. Restrictions concernant l’accès public - 8.2. Limitations on public access
  // ajout des champs de cette sous-section le 14/3/2015 EN TEST
  // identificationInfo[1]/*/resourceConstraints/*/accessConstraints
  // structuration d'accessConstraints en 2 parties
    'limitationsOnPublicAccess' => [
      'noInspire' => '8.2',
      'title-fr' => "Restrictions concernant l’accès public",
      'title-en' => "Limitations on public access",
      'xpath' => 'gmd:identificationInfo/*/gmd:resourceConstraints/gmd:MD_LegalConstraints',
      'multiplicity' => [ 'data' => '1..*', 'service' => '1..*' ],
      'subfields'=> [
        'code' => 'gmd:accessConstraints/gmd:MD_RestrictionCode/@codeListValue',
        'others' => 'gmd:otherConstraints/gco:CharacterString',
      ],
    ],
    'classification' => [
      'title-fr' => "Contrainte de sécurité intéressant la Défense nationale",
      'title-en' => "Classification",
      'xpath' => '*/gmd:resourceConstraints/*/gmd:classification/*/@codeListValue',
      'multiplicity' => [ 'data' => '0..*', 'service' => '0..*' ],
    ],


  // 9. ORGANISATIONS RESPONSABLES DE L’ÉTABLISSEMENT, DE LA GESTION, DE LA MAINTENANCE ET DE LA DIFFUSION DES SÉRIES ET DES SERVICES DE DONNÉES GÉOGRAPHIQUES
  // 9. ORGANISATIONS RESPONSIBLE FOR THE ESTABLISHMENT, MANAGEMENT, MAINTENANCE AND DISTRIBUTION OF SPATIAL DATA SETS AND SERVICE
  // 9.1. Partie responsable - 9.1. Responsible party
    'responsibleParty' => [
      'noInspire' => '9.',
      'title-fr' => "Partie responsable",
      'title-en' => "Responsible party",
      'xpath' => 'gmd:identificationInfo/*/gmd:pointOfContact',
      'multiplicity' => [ 'data' => '1..*', 'service' => '1..*' ],
      'subfields' => [
        'name' => '*/gmd:organisationName/gco:CharacterString',
        'email' => '*/gmd:contactInfo/*/gmd:address/*/gmd:electronicMailAddress/gco:CharacterString',
  // 9.2. Rôle de la partie responsable - 9.2. Responsible party role
        'role' => '*/gmd:role/gmd:CI_RoleCode/@codeListValue',
      ],
    ],

  // 10. Métadonnées concernant les métadonnées - METADATA ON METADATA
  // 10.1. Point de contact des métadonnées - 10.1. Metadata point of contact
    'mdContact' => [
      'noInspire' => '10.1',
      'title-fr' => "Point de contact des métadonnées",
      'title-en' => "Metadata point of contact",
      'xpath' => 'gmd:contact',
      'multiplicity' => [ 'data' => '1..*', 'service' => '1..*' ],
      'subfields' => [
        'name' => '*/gmd:organisationName/gco:CharacterString',
        'email' => '*/gmd:contactInfo/*/gmd:address/*/gmd:electronicMailAddress/gco:CharacterString',
      ],
    ],
  // 10.2. Date des métadonnées - 10.2. Metadata date
    'mdDate' => [
      'noInspire' => '10.2',
      'title-fr' => "Date des métadonnées",
      'title-en' => "Metadata date",
      'xpath' => 'gmd:dateStamp/*',
      'multiplicity' => [ 'data' => 1, 'service' => 1 ],
    ],
  // 10.3. Langue des métadonnées - 10.3. Metadata language
  // plusieurs xpath alternatifs
    'mdLanguage' => [
      'noInspire' => '10.3',
      'title-fr' => "Langue des métadonnées",
      'title-en' => "Metadata language",
      'xpaths' => ['gmd:language/*/@codeListValue','gmd:language/*'],
      'multiplicity' => [ 'data' => 1, 'service' => 1 ],
    ],
  ];
  private static $xsltProcessors=[]; // processeurs XSLT en fonction de la feuille de style
  
/*PhpDoc: methods
name: function version
title: static function version() - La version des métadonnées et du processus de simplification et de normalisation
*/
  static function version() { return '201809'; }
  
/*PhpDoc: methods
name: function asHtml
title: static function asHtml() - Affiche en HTML les éléments de MD définissant le format simplifié
*/
  static function asHtml() {
    echo "<table border=1>",
         "<th>nom</th><th>no</th><th>titre</th><th>xpath</th><th>data</th><th>serv</th><th>sous-champs</th>\n";
    foreach (self::$metadata as $name => $elt) {
      echo "<tr><td>$name</td>",
           "<td>",isset($elt['noInspire'])?$elt['noInspire']:'',"</td>",
           "<td>",$elt['title-fr'],"</td>",
           "<td><code>",isset($elt['xpath']) ? $elt['xpath'] : implode('<br>',$elt['xpaths']),"</code></td>",
           "<td><code>",isset($elt['multiplicity']['data'])?$elt['multiplicity']['data']:'0',"</code></td>",
           "<td><code>",isset($elt['multiplicity']['service'])?$elt['multiplicity']['service']:'0',"</code></td>",
           "<td>\n";
      if (isset($elt['subfields'])) {
        echo "<table border=1>";
        foreach ($elt['subfields'] as $sfname => $xpath)
          echo "<tr><td>$sfname</td><td><code>$xpath</code></td></tr>\n";
        echo "</table>\n";
      }
      echo "</td></tr>\n";
    }
    echo "</table>\n";
  }
  
/*PhpDoc: methods
name: function unary
title: static function unary($mult) - Teste si un élément est unaire, cad soit pour les données soit pour les services
*/
  static function unary($mult) {
    return ((isset($mult['data']) and (($mult['data']===1) or ($mult['data']==='0..1')))
        or (isset($mult['service']) and (($mult['service']===1) or ($mult['service']==='0..1'))));
  }
  
/*PhpDoc: methods
name: function asXsl
title: static function asXsl() - Génère la feuille de style simplifiant un document XML getrecords en XML
*/
  static function asXsl() {
    $header = <<<EOT
<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet version="1.0"
  xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
  xmlns:gmd="http://www.isotc211.org/2005/gmd"
  xmlns:gco="http://www.isotc211.org/2005/gco"
  xmlns:srv="http://www.isotc211.org/2005/srv"
  xmlns:xlink="http://www.w3.org/1999/xlink">
<xsl:template match="/">
  <SearchResults>
    <xsl:for-each select="//gmd:MD_Metadata">
      <metadata>\n
EOT;

    $footer = <<<EOT
      </metadata>
    </xsl:for-each>
  </SearchResults>
</xsl:template>
</xsl:stylesheet>\n
EOT;

    $result = $header;
    foreach (self::$metadata as $name => $elt) {
// cas simple: élément unaire atomique
      if (self::unary($elt['multiplicity']) and !isset($elt['subfields'])) {
        if (isset($elt['xpath'])) {
          $result .= "        <xsl:if test=\"$elt[xpath]\">\n";
          $result .= "          <$name><xsl:value-of select=\"$elt[xpath]\" /></$name>\n";
          $result .= "        </xsl:if>\n";
        }
        else {
          $result .= "        <xsl:choose>\n";
          foreach ($elt['xpaths'] as $xpath) {
            $result .= "        <xsl:when test=\"$xpath\">\n";
            $result .= "          <$name><xsl:value-of select=\"$xpath\" /></$name>\n";
            $result .= "        </xsl:when>\n";
          }
          $result .= "          <xsl:otherwise></xsl:otherwise>\n";
          $result .= "        </xsl:choose>\n";
        }
      }
// cas : élément n-aire atomique
      elseif (!isset($elt['subfields'])) {
        if (isset($elt['xpath'])) {
          $result .= <<<EOT
        <xsl:if test="$elt[xpath]">
          <xsl:for-each select="$elt[xpath]">
            <$name><xsl:value-of select="." /></$name>
          </xsl:for-each>
        </xsl:if>\n
EOT;
        }
        else {
          $result .= "        <xsl:choose>\n";
          foreach ($elt['xpaths'] as $xpath) {
            $result .= "        <xsl:when test=\"$xpath\">\n";
            $result .= "          <xsl:for-each select=\"$xpath\">\n";
            $result .= "            <$name><xsl:value-of select=\".\" /></$name>\n";
            $result .= "          </xsl:for-each>\n";
            $result .= "        </xsl:when>\n";
          }
          $result .= "          <xsl:otherwise></xsl:otherwise>\n";
          $result .= "        </xsl:choose>\n";
        }
      }
// cas : élément n-aire structuré
      else {
        $result .= "        <xsl:if test=\"$elt[xpath]\">\n";
        $result .= "          <xsl:for-each select=\"$elt[xpath]\">\n";
        $result .= "            <$name>\n";
        foreach ($elt['subfields'] as $sfname => $xpath) {
          $result .= "              <$sfname><xsl:value-of select=\"$xpath\" /></$sfname>\n";
        }
        $result .= "            </$name>\n";
        $result .= "          </xsl:for-each>\n";
        $result .= "        </xsl:if>\n";
      }
    }
    return $result.$footer;
  }
  
/*PhpDoc: methods
name: function asXslMl
title: static function asXslMl() - Génère la feuille de style simplifiant un document XML getrecords multi-ligue en XML
*/
  static function asXslMl() {
    $header = <<<EOT
<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet version="1.0"
  xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
  xmlns:gmd="http://www.isotc211.org/2005/gmd"
  xmlns:gco="http://www.isotc211.org/2005/gco"
  xmlns:srv="http://www.isotc211.org/2005/srv"
  xmlns:xlink="http://www.w3.org/1999/xlink">
<xsl:template match="/">
  <SearchResults>
    <xsl:for-each select="//gmd:MD_Metadata">
      <metadata>
        <!-- locales -->
        <xsl:if test="gmd:locale">
          <xsl:for-each select="gmd:locale/gmd:PT_Locale">
            <locale>
              <id><xsl:value-of select="@id" /></id>
              <languageCode><xsl:value-of select="gmd:languageCode/gmd:LanguageCode/@codeListValue" /></languageCode>
            </locale>
          </xsl:for-each>
        </xsl:if>\n
EOT;

    $footer = <<<EOT
      </metadata>
    </xsl:for-each>
  </SearchResults>
</xsl:template>
</xsl:stylesheet>\n
EOT;

    $result = $header;
    foreach (self::$metadata as $name => $elt) {
      //if (!in_array($name, ['title','conditionsToAccessAndUse'])) continue;
      $result .= "        <!-- element $name -->\n";
      if (isset($elt['xslml'])) // cas d'un XSL adhoc pour l'élément, eg: subject
        $result .= $elt['xslml'];
      // cas simple: élément unaire atomique, eg: title
      elseif (self::unary($elt['multiplicity']) && !isset($elt['subfields'])) {
        if (isset($elt['xpath'])) { // 1 xpath
          if (preg_match('!/gco:CharacterString$!', $elt['xpath'])) { // Multi-lingue
            $xpath = substr($elt['xpath'], 0, strlen($elt['xpath'])-strlen('/gco:CharacterString'));
            $result .= <<<EOT
        <$name>
          <xsl:if test="$xpath/gco:CharacterString">
            <value><xsl:value-of select="$xpath" /></value>
          </xsl:if>
          <xsl:if test="$xpath/gmd:PT_FreeText/gmd:textGroup">
            <xsl:for-each select="$xpath/gmd:PT_FreeText/gmd:textGroup">
              <localised>
                <locale><xsl:value-of select="gmd:LocalisedCharacterString/@locale"/></locale>
                <value><xsl:value-of select="gmd:LocalisedCharacterString" /></value>
              </localised>
            </xsl:for-each>
          </xsl:if>\n
        </$name>\n
EOT;
          }
          else { // Mono-lingue
            $result .= "        <xsl:if test=\"$elt[xpath]\">\n";
            $result .= "          <$name><value><xsl:value-of select=\"$elt[xpath]\" /></value></$name>\n";
            $result .= "        </xsl:if>\n";
          }
        }
        else { // plusieurs xpath alternatifs
          $result .= "        <xsl:choose>\n";
          foreach ($elt['xpaths'] as $xpath) {
            $result .= "        <xsl:when test=\"$xpath\">\n";
            $result .= "          <$name><value><xsl:value-of select=\"$xpath\" /></value></$name>\n";
            $result .= "        </xsl:when>\n";
          }
          $result .= "          <xsl:otherwise></xsl:otherwise>\n";
          $result .= "        </xsl:choose>\n";
        }
      }
      // cas : élément n-aire atomique 
      elseif (!isset($elt['subfields'])) {
        if (isset($elt['xpath'])) { // 1 xpath, ex: conditionsToAccessAndUse
          if (preg_match('!/gco:CharacterString$!', $elt['xpath'])) { // Multi-lingue
            $xpath = substr($elt['xpath'], 0, strlen($elt['xpath'])-strlen('/gco:CharacterString'));
            $result .= <<<EOT
        <xsl:if test="$xpath">
          <xsl:for-each select="$xpath">
            <$name>
            <xsl:if test="gco:CharacterString">
              <xsl:for-each select="gco:CharacterString">
                <value><xsl:value-of select="." /></value>
              </xsl:for-each>
            </xsl:if>
            <xsl:if test="gmd:PT_FreeText/gmd:textGroup">
              <xsl:for-each select="gmd:PT_FreeText/gmd:textGroup">
                <localised>
                  <locale><xsl:value-of select="gmd:LocalisedCharacterString/@locale"/></locale>
                  <value><xsl:value-of select="gmd:LocalisedCharacterString" /></value>
                </localised>
              </xsl:for-each>
            </xsl:if>\n
            </$name>
            </xsl:for-each>
          </xsl:if>\n
EOT;
          }
          else { // 1 xpath mono-lingue
            $result .= <<<EOT
        <xsl:if test="$elt[xpath]">
          <xsl:for-each select="$elt[xpath]">
            <$name><value><xsl:value-of select="." /></value></$name>
          </xsl:for-each>
        </xsl:if>\n
EOT;
          }
        }
        else { // n-aire atomique, plusieurs xpath, ex: language
          $result .= "        <xsl:choose>\n";
          foreach ($elt['xpaths'] as $xpath) {
            $result .= "        <xsl:when test=\"$xpath\">\n";
            $result .= "          <xsl:for-each select=\"$xpath\">\n";
            $result .= "            <$name><value><xsl:value-of select=\".\" /></value></$name>\n";
            $result .= "          </xsl:for-each>\n";
            $result .= "        </xsl:when>\n";
          }
          $result .= "          <xsl:otherwise></xsl:otherwise>\n";
          $result .= "        </xsl:choose>\n";
        }
      }
      // cas : élément n-aire structuré
      // gestion du multi-lingue des sous-champs
      else {
        $result .= "        <xsl:if test=\"$elt[xpath]\">\n";
        $result .= "          <xsl:for-each select=\"$elt[xpath]\">\n";
        $result .= "            <$name>\n";
        foreach ($elt['subfields'] as $sfname => $xpath) {
          if (preg_match('!/gco:CharacterString$!', $xpath)) { // Multi-lingue
            $xpath = substr($xpath, 0, strlen($xpath)-strlen('/gco:CharacterString'));
            $result .= <<<EOT
        <xsl:if test="$xpath">
          <$sfname>
            <xsl:if test="$xpath/gco:CharacterString">
              <value><xsl:value-of select="$xpath/gco:CharacterString" /></value>
            </xsl:if>
            <xsl:if test="$xpath/gmd:PT_FreeText">
              <xsl:for-each select="$xpath/gmd:PT_FreeText/gmd:textGroup">
                <localised>
                  <locale><xsl:value-of select="gmd:LocalisedCharacterString/@locale"/></locale>
                  <value><xsl:value-of select="gmd:LocalisedCharacterString" /></value>
                </localised>
              </xsl:for-each>
            </xsl:if>
          </$sfname>
        </xsl:if>\n
EOT;
          }
          else {
            $result .= "              <xsl:if test=\"$xpath\">\n";
            $result .= "                <$sfname><value><xsl:value-of select=\"$xpath\" /></value></$sfname>\n";
            $result .= "              </xsl:if>\n";
          }
        }
        $result .= "            </$name>\n";
        $result .= "          </xsl:for-each>\n";
        $result .= "        </xsl:if>\n";
      }
    }
    return $result.$footer;
  }

/*PhpDoc: methods
name: function asXslForHtml
title: static function asXslForHtml() - Génère la feuille de style simplifiant un document XML getrecords en HTML
*/
  static function asXslForHtml() {
    $header = <<<EOT
<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet version="1.0"
  xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
  xmlns:gmd="http://www.isotc211.org/2005/gmd"
  xmlns:gco="http://www.isotc211.org/2005/gco"
  xmlns:srv="http://www.isotc211.org/2005/srv"
  xmlns:xlink="http://www.w3.org/1999/xlink">
<xsl:template match="/">
  <html><head><meta charset='UTF-8'/><title>asXslForHtml</title></head><body>
    <xsl:for-each select="//gmd:MD_Metadata">
      <h2><xsl:value-of select="gmd:identificationInfo/*/gmd:citation/*/gmd:title/*" /></h2>
      <table border='1'>\n
EOT;

    $footer = <<<EOT
      </table>
    </xsl:for-each>
  </body></html>
</xsl:template>
</xsl:stylesheet>\n
EOT;

    $result = $header;
    foreach (self::$metadata as $name => $elt) {
      // cas simple: élément unaire atomique
      if (self::unary($elt['multiplicity']) and !isset($elt['subfields'])) {
        if (isset($elt['xpath'])) {
          $result .= <<<EOT
        <xsl:if test="$elt[xpath]">
          <tr><td><i>$name:</i></td>
            <td><xsl:value-of select="$elt[xpath]" /></td>
          </tr>
        </xsl:if>\n
EOT;
        }
        else {
          $result .= "        <xsl:choose>\n";
          foreach ($elt['xpaths'] as $xpath)
            $result .= <<<EOT
          <xsl:when test="$xpath">
            <tr><td><i>$name:</i></td>
              <td><xsl:value-of select="$xpath" /></td>
            </tr>
          </xsl:when>\n
EOT;
          $result .= "          <xsl:otherwise></xsl:otherwise>\n";
          $result .= "        </xsl:choose>\n";
        }
      }
      // cas : élément n-aire atomique
      elseif (!isset($elt['subfields'])) {
        if (isset($elt['xpath'])) {
          $result .= <<<EOT
        <xsl:if test="$elt[xpath]">
          <tr><td><i>$name:</i></td><td><table border='1'>
            <xsl:for-each select="$elt[xpath]">
              <tr><td><xsl:value-of select="." /></td></tr>
            </xsl:for-each>
          </table></td></tr>
        </xsl:if>\n
EOT;
        }
        else {
          $result .= "        <xsl:choose>\n";
          foreach ($elt['xpaths'] as $xpath)
            $result .= <<<EOT
          <xsl:when test="$xpath">
            <tr><td><i>$name:</i></td><td><table border='1'>
              <xsl:for-each select="$xpath">
                <tr><td><xsl:value-of select="." /></td></tr>
              </xsl:for-each>
            </table></td></tr>
          </xsl:when>\n
EOT;
          $result .= "          <xsl:otherwise></xsl:otherwise>\n";
          $result .= "        </xsl:choose>\n";
        }
      }
      // cas : élément n-aire structuré
      else { 
        $result .= "        <xsl:if test=\"$elt[xpath]\">\n";
        $result .= "          <tr><td><i>$name:</i></td><td><table border='1'>\n";
        $result .= "            <th>".implode('</th><th>', array_keys($elt['subfields']))."</th>\n";
        $result .= "            <xsl:for-each select=\"$elt[xpath]\">\n";
        $result .= "              <tr>\n";
        foreach ($elt['subfields'] as $sfname => $xpath) {
          $result .= "                <td><xsl:value-of select=\"$xpath\" /></td>\n";
        }
        $result .= "              </tr>\n";
        $result .= "            </xsl:for-each>\n";
        $result .= "          </table></td></tr>\n";
        $result .= "        </xsl:if>\n";
      }
    }
    return $result.$footer;
  }
 
  
/*PhpDoc: methods
name: function simplify
title: static function simplify(string $xmlstr, string $recid, string $xsl='') - Simplifie un document XML retourné par GetRecords
doc: |
  En entrée:
    - le document XML à simplifier transmis comme chaine de caractères
    - l'identfiant du document utilisé en cas d'erreur
    - un identifiant de la feuille de style à utiliser qui peut être:
      - 'test' pour utiliser le fichier de feuille de style de test (stylesheettest.xsl)
      - 'ML' pour utiliser self::asXslMl()
      - 'html' pour utiliser self::asXslForHtml()
      - par défaut utilisation de self::asXsl()
  En sortie: le document XML renvoyé comme objet SimpleXML
  ou une exception en cas d'erreur sur le fichier XML en entrée
*/
  static function simplify(string $xmlstr, string $recid, string $xsl=''): SimpleXMLElement {
    trim($xmlstr);
    if (!isset(self::$xsltProcessors[$xsl])) {
      $stylesheet = new DOMDocument();
      $xslFile = ($xsl=='test' ? file_get_contents(__DIR__.'/stylesheettest.xsl')
          : ($xsl=='ML' ? self::asXslMl()
            : ($xsl=='html' ? self::asXslForHtml()
              : self::asXsl())));
      $stylesheet->loadXML($xslFile);
      self::$xsltProcessors[$xsl] = new XSLTProcessor;
      self::$xsltProcessors[$xsl]->importStylesheet($stylesheet);
    }
    $getrecords = new DOMDocument();
    if (!$getrecords->loadXML($xmlstr)) {
      //echo "xml=",$xmlstr,"\n";
      throw new Exception("Erreur dans IsoMetadata::simplify sur loadXML() sur l'enregistrement $recid");
    }
    return new SimpleXMLElement(self::$xsltProcessors[$xsl]->transformToXML($getrecords));
  }
    
/*PhpDoc: methods
name: function standardize
title: "static function standardize($xml) - Standardise une fiche de métadonnées"
doc: |
  La standardisation a pour objectif de faciliter les traitements consommant les données JSON
  en générant une structure JSON homogène ce qui n'est pas le cas en sortie de la simplification.
  Le principe de la standardisation est le suivant:
  - une fiche de MD est un dictionnaire JSON : nom_élément => valeur_élément
  - valeur_élément est:
    - une chaine de caractères si l'élément est défini comme unaire
    - un tableau de chaine de caractères si l'élément est défini comme n-aire atomique
    - un tableau de dictionnaires de chaine de caractères si l'élément est défini comme n-aire structuré
  - quand un élément ou un sous élément est absent, il n'apparait pas dans le dictionnaire correspondant.
  La méthode prend en entrée une fiche de MD en SimpleXml et renvoie une structure de tableaux Php
  
  L'utilisation de json_encode() sur un SimpleXml n'est pas fiable et donc abandonnée
*/
  static function standardize(SimpleXMLElement $xml): array {
    $php = [];
    //echo "xml="; var_dump($xml);
    foreach (self::$metadata as $eltname => $mdelt) {
      if (!$xml->$eltname)
        continue;
      if (self::unary($mdelt['multiplicity'])) {
        $str = trim((string)$xml->$eltname);
        if ($str)
          $php[$eltname] = $str;
      }
      elseif (!isset($mdelt['subfields'])) {
        $tab = [];
        foreach ($xml->$eltname as $subelt) {
          $str = trim((string)$subelt);
          if ($str)
            $tab[] = $str;
        }
        if ($tab)
          $php[$eltname] = $tab;
      }
      else {
        $tab = [];
        foreach ($xml->$eltname as $subelt) {
          $phpselt = [];
          foreach (array_keys($mdelt['subfields']) as $sfname) {
            $str = trim((string)$subelt->$sfname);
            if ($str)
              $phpselt[$sfname] = $str;
          }
          if ($phpselt)
            $tab[] = $phpselt;
        }
        if ($tab)
          $php[$eltname] = $tab;
      }
    }
    //echo "php="; var_dump($php);
    //echo "<pre>php="; print_r($php);
    //die("EN COURS ligne ".__LINE__);
    return $php;
  }
    
/*PhpDoc: methods
name: function standardizeMl
title: "static function standardize(SimpleXMLElement $xml): array - Standardise une fiche de métadonnées"
doc: |
  La standardisation a pour objectif de faciliter les traitements consommant les données JSON
  en générant une structure JSON homogène ce qui n'est pas le cas en sortie de la simplification.
  Le principe de la standardisation est le suivant:
  - une fiche de MD est un dictionnaire JSON : nom_élément => valeur_élément
  - valeur_élément est:
    - une chaine de caractères si l'élément est défini comme unaire
    - un tableau de chaine de caractères si l'élément est défini comme n-aire atomique
    - un tableau de dictionnaires de chaine de caractères si l'élément est défini comme n-aire structuré
  - quand un élément ou un sous élément est absent, il n'apparait pas dans le dictionnaire correspondant.
  La méthode prend en entrée une fiche de MD en SimpleXml et renvoie une structure de tableaux Php

  L'utilisation de json_encode() sur un SimpleXml n'est pas fiable et donc abandonnée
*/
  // fonction interne à standardizeMl, fabrique un MLString à partir d'un tableau vals
  static function buildMLString(array $vals, string $mdLanguage, array $locales) {
    static $defLocales = ['#EN'=>'eng','#FR'=>'fre','#locale-eng'=>'eng','#locale-fre'=>'fre'];
    
    if ((count($vals)==1) && !array_keys($vals)[0])
      return array_values($vals)[0];
    
    $mlStr = [];
    if (isset($vals['']))
      $mlStr[$mdLanguage] = $vals[''];
    foreach ($vals as $locale => $val) {
      if (!$val) continue;
      if ($locale) {
        if (isset($locales[$locale]))
          $mlStr[$locales[$locale]] = $val;
        elseif (isset($defLocales[$locale]))
          $mlStr[$defLocales[$locale]] = $val;
        else {
          echo "locale $locale non défini<br>\n";
          $mlStr[$locale] = $val;
        }
      }
    }
    return $mlStr;
  }  
  
  static function standardizeMl(SimpleXMLElement $xml): array {
    $php = [];
    $mdLanguage = trim((string)$xml->mdLanguage->value);
    $locales = []; // [ localeid => langCode ]
    if ($xml->locale) {
      foreach ($xml->locale as $localeElt) {
        $id = trim((string)$localeElt->id);
        $langCode = trim((string)$localeElt->languageCode);
        $locales["#$id"] = $langCode;
      }
    }
    // return [ 'mdLanguage'=> $mdLanguage, 'locales'=> $locales ]; // verif mdLanguage & locales 
    foreach (self::$metadata as $eltname => $mdelt) {
      if (!$xml->$eltname)
        continue;
      elseif ($eltname == 'subject') { // cas spécifique
        //continue;
        $subjects = [];
        foreach ($xml->subject as $subjectElt) {
          $subject = [];
          if ($subjectElt->value)
            $vals[''] = trim((string)$subjectElt->value);
          if ($subjectElt->localised) {
            foreach ($subjectElt->localised as $localised) {
              $locale = trim((string)$localised->locale);
              $vals[$locale] = trim((string)$localised->value);
            }
          }
          $subject['value'] = self::buildMLString($vals, $mdLanguage, $locales);
          if ($subjectElt->cvocIdentifier)
            $subject['cvocIdentifier'] = trim((string)$subjectElt->cvocIdentifier->value);
          if ($subjectElt->cvocTitle) {
            $vals = [];
            if ($subjectElt->cvocTitle->value)
              $vals[''] = trim((string)$subjectElt->cvocTitle->value);
            if ($subjectElt->cvocTitle->localised) {
              foreach ($subjectElt->cvocTitle->localised as $localised) {
                $locale = trim((string)$localised->locale);
                $vals[$locale] = trim((string)$localised->value);
              }
            }
            $subject['cvocTitle'] = self::buildMLString($vals, $mdLanguage, $locales);
          }
          if ($subjectElt->cvocReferenceDate)
            $subject['cvocReferenceDate'] = trim((string)$subjectElt->cvocReferenceDate->value);
          $subjects[] = $subject;
        }
        $php['subject'] = $subjects;
      }
      // elt unaire atomique
      elseif (self::unary($mdelt['multiplicity']) && !isset($mdelt['subfields'])) {
        $vals = [];
        if ($xml->$eltname->value)
          $vals[''] = trim((string)$xml->$eltname->value);
        if ($xml->$eltname->localised) {
          foreach ($xml->$eltname->localised as $localised) {
            $locale = trim((string)$localised->locale);
            $vals[$locale] = trim((string)$localised->value);
          }
        }
        //var_dump($vals);
        $php[$eltname] = self::buildMLString($vals, $mdLanguage, $locales);
      }
      // elt n-aire atomique, ex: alternative, valid
      elseif (!isset($mdelt['subfields'])) {
        //continue;
        foreach ($xml->$eltname as $eltVal) {
          $vals = [];
          if ($eltVal->value)
            $vals[''] = trim((string)$eltVal->value);
          if ($eltVal->localised) {
            foreach ($eltVal->localised as $localised) {
              $locale = trim((string)$localised->locale);
              $vals[$locale] = trim((string)$localised->value);
            }
          }
          $php[$eltname][] = self::buildMLString($vals, $mdLanguage, $locales);
        }
      }
      // elt composé, ex: conformsTo
      else {
        //continue;
        foreach ($xml->$eltname as $eltVal) {
          $phpselt = [];
          foreach (array_keys($mdelt['subfields']) as $sfname) {
            $vals = [];
            if ($eltVal->$sfname->value) {
              $vals[''] = trim((string)$eltVal->$sfname->value);
            }
            if ($eltVal->$sfname->localised) {
              foreach ($eltVal->$sfname->localised as $localised) {
                $locale = trim((string)$localised->locale);
                $vals[$locale] = trim((string)$localised->value);
              }
            }
            $phpselt[$sfname] = self::buildMLString($vals, $mdLanguage, $locales);
          }
          if ($phpselt)
            $php[$eltname][] = $phpselt;
        }
      }
    }
    $mdLanguages = [ $php['mdLanguage'] ];
    if ($locales)
      foreach ($locales as $langCode)
        if (!in_array($langCode, $mdLanguages))
          $mdLanguages[] = $langCode;
    $php['mdLanguage'] = $mdLanguages;
    return $php;
  }
};


// Tests élémentaires de la classe
if (basename(__FILE__)<>basename($_SERVER['SCRIPT_NAME'])) return;

//echo "<pre>"; print_r($_SERVER); echo "</pre>\n";
$server = $_SERVER['PATH_INFO'];

/*PhpDoc: screens
name:  main
title: Exécution principale du script
hrefs:
  - ?action=eltsHtml
  - ?action=xslXml
  - ?action=xslXmlMl
  - ?action=xslHtml
  - ?action=titles
doc: |
  Menu de choix
*/
if (!isset($_GET['action'])) {
  echo "<!DOCTYPE HTML><html><head><meta charset='UTF-8'><title>metadata</title></head><body>\n",
       "<h2>Tests de la classe IsoMetadata</h1><ul>\n",
       "<li><a href='?action=eltsHtml'>Affichage des éléments de MD en HTML</a>\n",
       "<li>Affichage du fichier XSL pour XML en <a href='?action=xslXml&amp;fmt=txt'>texte</a>,\n",
       "en <a href='?action=xslXml'>XML</a>\n",
       "<li>Affichage du fichier XSL ML pour XML en <a href='?action=xslXmlMl&amp;fmt=txt'>texte</a>,\n",
       "en <a href='?action=xslXmlMl'>XML</a>\n",
       "<li><a href='/yamldoc/stylesheettest.xml'>Affichage du fichier XSL Test pour XML en XML</a>\n",
       "<li>Affichage du fichier XSL pour HTML en <a href='?action=xslHtml&fmt=txt'>texte</a>, \n",
       "en <a href='?action=xslHtml'>XML</a>\n",
       "<li><a href='?action=titles'>Affichage des MD du serveur $server</a>\n";
  echo "</ul>\n";
  die();
}

/*PhpDoc: screens
name:  action=eltsHtml
title: Affichage en HTML des éléments de MD définissant le format simplifié
*/
if ($_GET['action']=='eltsHtml') {
  echo "<!DOCTYPE HTML><html><head><meta charset='UTF-8'><title>metadata</title></head><body>\n";
  echo IsoMetadata::asHtml();
  die();
}

/*PhpDoc: screens
name:  action=xslXml
title: Affichage du fichier XSL pour XML
*/
if ($_GET['action']=='xslXml') {
  $fmt = (isset($_GET['fmt']) && ($_GET['fmt']=='txt')) ? 'plain' : 'xml';
  header("Content-type: text/$fmt; charset=\"utf-8\"");
  die(IsoMetadata::asXsl());
}

/*PhpDoc: screens
name:  action=xslXmlMl
title: Affichage du fichier XSL ML pour XML
*/
if ($_GET['action']=='xslXmlMl') {
  $fmt = (isset($_GET['fmt']) && ($_GET['fmt']=='txt')) ? 'plain' : 'xml';
  header("Content-type: text/$fmt; charset=\"utf-8\"");
  die(IsoMetadata::asXslMl());
}

/*PhpDoc: screens
name:  action=xslHtml
title: Affichage du fichier XSL pour HTML
*/
if ($_GET['action']=='xslHtml') {
  $fmt = (isset($_GET['fmt']) && ($_GET['fmt']=='txt')) ? 'plain' : 'xml';
  header("Content-type: text/$fmt; charset=\"utf-8\"");
  die(IsoMetadata::asXslForHtml());
}

$docsPath = __DIR__.'/pub'; // chemin d'accès aux fichiers XML des getrecords

/*PhpDoc: screens
name:  action=titles
title: Affichage en JSON des MD simplifiées mais non standardisées
hrefs:
  - ?main
  - ?action=simplXml
  - ?action=titles
*/
if ($_GET['action']=='titles') {
  $startpos = (isset($_GET['startpos'])? $_GET['startpos'] : 1);
  echo "<!DOCTYPE HTML><html><head><meta charset='UTF-8'><title>simplify</title></head><body>\n";
  echo "<h2>Affichage des MD simplifiées mais non standardisées</h2>\n",
       "Chaque page correspond au retour d'une requête CSW GetRecords<br>\n",
       "<table border=1><tr>",
       "<td><a href='?'>^</a></td>",
       "<td>$server</td>",
       "<td>$startpos - ",$startpos+9,"</td>",
       "<td><a href='/yamldoc/file.php$server/harvest/$startpos.xml'>ISO XML</a></td>\n",
       "<td><a href='?action=simplXml&amp;startpos=$startpos'>simplXML</a></td>\n",
       "<td><a href='?action=simplXml&amp;xsl=test&amp;startpos=$startpos'>simplXML test</a></td>\n",
       "<td><a href='?action=simplXml&amp;xsl=ML&amp;startpos=$startpos'>simplXML ML</a></td>\n",
       "<td><a href='?action=titles&amp;startpos=",$startpos+10,"'>&gt;</a></td>",
       "</tr></table>\n";
  $searchResults = IsoMetadata::simplify(file_get_contents("$docsPath/$server/harvest/$startpos.xml"), $startpos, 'ML');
  $pos = 0;
  echo "<ul>\n";
  foreach ($searchResults->metadata as $md) {
   $std = IsoMetadata::standardizeMl($md);
   $href = "?action=stdone&amp;start=$startpos&amp;pos=$pos";
   if (!isset($std['title']))
     $title = 'NO TITLE';
   elseif (is_string($std['title']))
     $title = $std['title'];
   elseif (isset($std['title']['fre']))
     $title = $std['title']['fre'];
   elseif (isset($std['title']['eng']))
     $title = $std['title']['eng'];
   else
     $title = array_values($std['title'][0]);
   echo "<li><a href='$href'>$title</a> (<a href='$href&amp;dump=1'>dump</a>)</li>\n";
   $pos++;
  }
  echo "</ul>\n";
  die();
}

/*PhpDoc: screens
name:  action=simplXml
title: Affichage en XML des MD simplifiées mais non standardisées
*/
if ($_GET['action']=='simplXml') {
  header('Content-type: text/xml; charset="utf-8"');
  $simplified = IsoMetadata::simplify(
      file_get_contents("$docsPath/$server/harvest/$_GET[startpos].xml"),
      $_GET['startpos'],
      isset($_GET['xsl']) ? $_GET['xsl'] : ''
    );
  echo $simplified->asXml();
  die();
}

/*PhpDoc: screens
name:  action=stdone
title: Affichage en JSON d'une fiche de MD standardisées
*/
if ($_GET['action']=='stdone') {
  $searchResults = IsoMetadata::simplify(
      file_get_contents("$docsPath/$server/harvest/$_GET[start].xml"),
      $_GET['start'], 'ML');
  $pos = 0;
  foreach ($searchResults->metadata as $md) {
    if ($pos++==$_GET['pos']) {
      if (isset($_GET['dump'])) {
        header('Content-type: text/plain');
        echo "<pre>xml="; var_dump($md); echo "</pre>";
        die();
      }
      else {
        header('Content-type: application/json');
        $std = IsoMetadata::standardizeMl($md);
        echo json_encode($std,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE),"\n\n";
        die();
      }
    }
  }
  die();
}
