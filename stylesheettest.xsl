<?xml version="1.0" encoding="UTF-8"?>
<!-- Feuille de style de test pour convertir une MD ISO 19115-19119 dans un XML simplifié
test de la prise en compte des MD multi-ligues - Benoit DAVID - 1/9/2018 -->
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
        </xsl:if>
        <xsl:choose><!-- title mono/multi ligue -->
          <xsl:when test="gmd:identificationInfo/*/gmd:citation/*/gmd:title/gmd:PT_FreeText/gmd:textGroup">
            <xsl:for-each select="gmd:identificationInfo/*/gmd:citation/*/gmd:title/gmd:PT_FreeText/gmd:textGroup">
              <titleMl>
                <locale><xsl:value-of select="gmd:LocalisedCharacterString/@locale"/></locale>
                <value><xsl:value-of select="gmd:LocalisedCharacterString" /></value>
              </titleMl>
            </xsl:for-each>
          </xsl:when>
          <xsl:when test="gmd:identificationInfo/*/gmd:citation/*/gmd:title/*">
            <title><xsl:value-of select="gmd:identificationInfo/*/gmd:citation/*/gmd:title/*" /></title>
          </xsl:when>
        </xsl:choose>
        <xsl:choose> <!-- language -->
          <xsl:when test="gmd:identificationInfo/*/gmd:language/*/@codeListValue">
            <xsl:for-each select="gmd:identificationInfo/*/gmd:language/*/@codeListValue">
              <language><xsl:value-of select="." /></language>
            </xsl:for-each>
          </xsl:when>
          <xsl:when test="gmd:identificationInfo/*/gmd:language/*">
            <xsl:for-each select="gmd:identificationInfo/*/gmd:language/*">
              <language><xsl:value-of select="." /></language>
            </xsl:for-each>
          </xsl:when>
          <xsl:otherwise></xsl:otherwise>
        </xsl:choose>
        <!-- keywords multi/mono lingue -->
        <xsl:if test="gmd:identificationInfo/*/gmd:descriptiveKeywords/*/gmd:keyword">
          <!-- boucle sur chaque mot-clé -->
          <xsl:for-each select="gmd:identificationInfo/*/gmd:descriptiveKeywords/*/gmd:keyword">
            <xsl:choose>
              <xsl:when test="gmd:PT_FreeText/gmd:textGroup"> <!-- si Multi lingue -->
                <subjectMl>
                  <locale><xsl:value-of select="gmd:PT_FreeText/gmd:textGroup/gmd:LocalisedCharacterString/@locale"/></locale>
                  <value><xsl:value-of select="gmd:PT_FreeText/gmd:textGroup/gmd:LocalisedCharacterString" /></value>
                  <cvocTitle><xsl:value-of select="../gmd:thesaurusName/*/gmd:title/*" /></cvocTitle>
                </subjectMl>
              </xsl:when>
              <xsl:when test="gco:CharacterString"> <!-- si Mono lingue -->
                <subject1>
                  <value><xsl:value-of select="gco:CharacterString" /></value>
                  <cvocTitle><xsl:value-of select="../gmd:thesaurusName/*/gmd:title/*" /></cvocTitle>
                </subject1>
              </xsl:when>
            </xsl:choose>
          </xsl:for-each>
        </xsl:if>
      </metadata>
    </xsl:for-each>
  </SearchResults>
</xsl:template>
</xsl:stylesheet>
