<?xml version="1.0" encoding="UTF-8"?>
<!-- wfsgml2togeojson.xsl - traduction GML2 en pseudo GeoJSON -->
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
          - <xsl:value-of select="gml:LinearRing/gml:posList"/>
              </xsl:for-each>
            </xsl:if>
          </xsl:for-each>
        </xsl:when>
        <xsl:when test="*/ms:msGeometry/gml:Polygon">
    MultiSurface:<xsl:for-each select="*/ms:msGeometry/gml:Polygon">
      - Polygon<xsl:value-of select="gml:exterior/gml:LinearRing/gml:posList/@srsDimension"/>:
          exterior: <xsl:value-of select="gml:exterior/gml:LinearRing/gml:posList"/>
            <xsl:if test="gml:interior">
          interior:<xsl:for-each select="gml:interior">
          - <xsl:value-of select="gml:LinearRing/gml:posList"/>
              </xsl:for-each>
            </xsl:if>
          </xsl:for-each>
        </xsl:when>
      <xsl:when test="*/ms:msGeometry/gml:Point">
    Point: <xsl:value-of select="*/ms:msGeometry/gml:Point/gml:pos"/>
        </xsl:when>
        <xsl:otherwise>
          <xsl:value-of select="*/ms:msGeometry"/>
        </xsl:otherwise>
      </xsl:choose>
    </xsl:for-each>
  </xsl:template>
</xsl:stylesheet>
