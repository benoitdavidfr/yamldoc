<?xml version="1.0" encoding="UTF-8"?>
<!-- wfsgml2togeojson.xsl - traduction GML2 en pseudo GeoJSON
En GML2 la géométrie est dans le champ ms:geometry et non ms:msGeometry
 -->
<xsl:stylesheet version="1.0"
  xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
  xmlns:wfs="http://www.opengis.net/wfs"
  xmlns:gml="http://www.opengis.net/gml"
  {targetNamespaceDef}>
      
  <xsl:output method="text" />

  <xsl:template match="/wfs:FeatureCollection">
    <xsl:for-each select="//gml:featureMember">
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
      <xsl:when test="*/ms:geometry/gml:MultiPolygon">
    MultiSurface:<xsl:for-each select="*/ms:geometry/gml:MultiPolygon/gml:polygonMember/gml:Polygon">
      - Polygon2:
          exterior: <xsl:value-of select="gml:outerBoundaryIs/gml:LinearRing/gml:coordinates"/>
            <xsl:if test="gml:interior">
          interior:<xsl:for-each select="gml:interior">
          - <xsl:value-of select="gml:LinearRing/gml:coordinates"/>
              </xsl:for-each>
            </xsl:if>
          </xsl:for-each>
        </xsl:when>
        <xsl:when test="*/ms:geometry/gml:Polygon">
    MultiSurface:<xsl:for-each select="*/ms:geometry/gml:Polygon">
      - Polygon2:
          exterior: <xsl:value-of select="gml:outerBoundaryIs/gml:LinearRing/gml:coordinates"/>
            <xsl:if test="gml:innerBoundaryIs">
          interior:<xsl:for-each select="gml:innerBoundaryIs">
          - <xsl:value-of select="gml:LinearRing/gml:coordinates"/>
              </xsl:for-each>
            </xsl:if>
          </xsl:for-each>
        </xsl:when>
      <xsl:when test="*/ms:geometry/gml:Point">
    Point: <xsl:value-of select="*/ms:geometry/gml:Point/gml:coordinates"/>
        </xsl:when>
        <xsl:otherwise>
          <xsl:value-of select="*/ms:geometry"/>
        </xsl:otherwise>
      </xsl:choose>
    </xsl:for-each>
  </xsl:template>
</xsl:stylesheet>
