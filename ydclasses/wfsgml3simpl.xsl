<?xml version="1.0" encoding="UTF-8"?>
<!-- wfsgml3simpl.xsl - traduction GML3 en XML simplifié -->
<xsl:stylesheet version="1.0"
  xmlns="http://georef.eu/yamldoc"
  xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
  xmlns:wfs="http://www.opengis.net/wfs/2.0"
  xmlns:gml="http://www.opengis.net/gml/3.2"
  {targetNamespaceDef}>
      
  <xsl:template match="/wfs:FeatureCollection">
    <FeatureCollection>
    <xsl:for-each select="//wfs:member">
<Feature>
  <properties>{xslProperties}</properties>
    <xsl:choose>
      <xsl:when test="*/ms:msGeometry/gml:MultiCurve">
        <MultiLineString>
          <xsl:for-each select="*/ms:msGeometry/gml:MultiCurve/gml:curveMember/gml:LineString/gml:posList">
            <LineString>
              <srsDimension><xsl:value-of select="@srsDimension"/></srsDimension>
              <posList><xsl:value-of select="."/></posList>
            </LineString>
          </xsl:for-each>
        </MultiLineString>
      </xsl:when>
      <xsl:when test="*/ms:msGeometry/gml:LineString">
        <MultiLineString>
          <xsl:for-each select="*/ms:msGeometry/gml:LineString/gml:posList">
            <LineString>
              <srsDimension><xsl:value-of select="@srsDimension"/></srsDimension>
              <posList><xsl:value-of select="."/></posList>
            </LineString>
          </xsl:for-each>
        </MultiLineString>
      </xsl:when>
      <xsl:when test="*/ms:msGeometry/gml:MultiSurface">
        <MultiPolygon>
          <xsl:for-each select="*/ms:msGeometry/gml:MultiSurface/gml:surfaceMember/gml:Polygon">
            <Polygon>
              <srsDimension><xsl:value-of select="gml:exterior/gml:LinearRing/gml:posList/@srsDimension"/></srsDimension>
              <outerBoundaryIs><xsl:value-of select="gml:exterior/gml:LinearRing/gml:posList"/></outerBoundaryIs>
              <xsl:if test="gml:interior">
                <xsl:for-each select="gml:interior">
                  <innerBoundaryIs><xsl:value-of select="gml:LinearRing/gml:posList"/></innerBoundaryIs>
                </xsl:for-each>
              </xsl:if>
            </Polygon>
          </xsl:for-each>
        </MultiPolygon>
      </xsl:when>
        <xsl:when test="*/ms:msGeometry/gml:Polygon">
          <MultiPolygon>
            <xsl:for-each select="*/ms:msGeometry/gml:Polygon">
              <Polygon>
                <srsDimension><xsl:value-of select="gml:exterior/gml:LinearRing/gml:posList/@srsDimension"/></srsDimension>
                <outerBoundaryIs><xsl:value-of select="gml:exterior/gml:LinearRing/gml:posList"/></outerBoundaryIs>
                <xsl:if test="gml:interior">
                  <xsl:for-each select="gml:interior">
                    <innerBoundaryIs><xsl:value-of select="gml:LinearRing/gml:posList"/></innerBoundaryIs>
                  </xsl:for-each>
                </xsl:if>
              </Polygon>
            </xsl:for-each>
          </MultiPolygon>
        </xsl:when>
      <xsl:when test="*/ms:msGeometry/gml:Point">
    Point: <xsl:value-of select="*/ms:msGeometry/gml:Point/gml:pos"/>
        </xsl:when>
        <xsl:otherwise>
          <xsl:value-of select="*/ms:msGeometry"/>
        </xsl:otherwise>
      </xsl:choose>
    </Feature>
    </xsl:for-each>
  </FeatureCollection>
  </xsl:template>
</xsl:stylesheet>
