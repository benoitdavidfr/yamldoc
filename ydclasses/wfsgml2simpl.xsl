<?xml version="1.0" encoding="UTF-8"?>
<!-- wfsgml2simpl.xsl - traduction GML2 en XML simplifié
En GML2 la géométrie est dans le champ ms:geometry et non ms:msGeometry
 -->
<xsl:stylesheet version="1.0"
  xmlns="http://georef.eu/yamldoc"
  xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
  xmlns:wfs="http://www.opengis.net/wfs"
  xmlns:gml="http://www.opengis.net/gml"
  {targetNamespaceDef}>
  
  <xsl:template match="/wfs:FeatureCollection">
    <FeatureCollection>
    <xsl:for-each select="//gml:featureMember">
<Feature>
  <properties>{xslProperties}</properties>
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
      <MultiPolygon>
        <xsl:for-each select="*/ms:geometry/gml:MultiPolygon/gml:polygonMember/gml:Polygon">
          <Polygon>
            <outerBoundaryIs><xsl:value-of select="gml:outerBoundaryIs/gml:LinearRing/gml:coordinates"/></outerBoundaryIs>
            <xsl:if test="gml:innerBoundaryIs">
              <xsl:for-each select="gml:innerBoundaryIs">
                <innerBoundaryIs><xsl:value-of select="gml:LinearRing/gml:coordinates"/></innerBoundaryIs>
              </xsl:for-each>
            </xsl:if>
          </Polygon>
        </xsl:for-each>
      </MultiPolygon>
    </xsl:when>
    <xsl:when test="*/ms:geometry/gml:Polygon">
      <MultiPolygon>
        <xsl:for-each select="*/ms:geometry/gml:Polygon">
          <Polygon>
            <outerBoundaryIs><xsl:value-of select="gml:outerBoundaryIs/gml:LinearRing/gml:coordinates"/></outerBoundaryIs>
            <xsl:if test="gml:innerBoundaryIs">
              <xsl:for-each select="gml:innerBoundaryIs">
                <innerBoundaryIs><xsl:value-of select="gml:LinearRing/gml:coordinates"/></innerBoundaryIs>
              </xsl:for-each>
            </xsl:if>
          </Polygon>
        </xsl:for-each>
      </MultiPolygon>
    </xsl:when>
    <xsl:when test="*/ms:geometry/gml:Point">
      <Point><xsl:value-of select="*/ms:geometry/gml:Point/gml:coordinates"/></Point>
    </xsl:when>
    <xsl:otherwise>
      <xsl:value-of select="*/ms:geometry"/>
    </xsl:otherwise>
  </xsl:choose>
</Feature>
    </xsl:for-each>
    </FeatureCollection>
  </xsl:template>
</xsl:stylesheet>
