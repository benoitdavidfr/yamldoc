<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet version="1.0"
  xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
  xmlns:wfs="http://www.opengis.net/wfs/2.0"
  xmlns:gml="http://www.opengis.net/gml/3.2"
  {targetNamespaceDef}>
      
  <xsl:output method="text" />

  <xsl:template match="/wfs:FeatureCollection">
    <xsl:for-each select="//wfs:member">
- Feature:
    properties:{xslProperties}
    <xsl:choose>
    <xsl:when test="*/ms:msGeometry/gml:MultiCurve">
    MultiCurve:<xsl:for-each select="*/ms:msGeometry/gml:MultiCurve/gml:curveMember/gml:LineString/gml:posList">
      - LineString<xsl:value-of select="@srsDimension"/>: <xsl:value-of select="."/>
        </xsl:for-each>
      </xsl:when>
    <xsl:when test="*/ms:msGeometry/gml:LineString">
    MultiCurve:<xsl:for-each select="*/ms:msGeometry/gml:LineString/gml:posList">
      - LineString<xsl:value-of select="@srsDimension"/>: <xsl:value-of select="."/>
        </xsl:for-each>
      </xsl:when>
      <xsl:when test="*/ms:msGeometry/gml:MultiSurface">
    MultiSurface:<xsl:for-each select="*/ms:msGeometry/gml:MultiSurface/gml:surfaceMember/gml:Polygon">
      - Polygon<xsl:value-of select="gml:exterior/gml:LinearRing/gml:posList/@srsDimension"/>:
          exterior: <xsl:value-of select="gml:exterior/gml:LinearRing/gml:posList"/>
            <xsl:if test="gml:interior">
          interior:<xsl:for-each select="gml:interior">
          - <xsl:value-of select="gml:LinearRing/gml:posList"/>
              </xsl:for-each>
            </xsl:if>
          </xsl:for-each>
        </xsl:when>
      </xsl:choose>
    </xsl:for-each>
  </xsl:template>
</xsl:stylesheet>
