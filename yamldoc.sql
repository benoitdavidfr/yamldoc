/*PhpDoc:
name: yamldoc.sql
title: yamldoc.sql - schema de la base MySQL utilisé pour l'index full text de YamlDoc
*/
drop table if exists document;
create table document (
  docid varchar(200) not null primary key comment "id du doc",
  maj datetime comment "date et heure de maj du doc"
) COMMENT = 'fragment'
-- ENGINE = MYISAM
DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci;

drop table if exists fragment;
create table fragment (
  fragid varchar(200) not null primary key comment "id du fragment",
  text longtext comment "texte associé"
) COMMENT = 'fragment'
-- ENGINE = MYISAM
DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci;

create fulltext index fragment_fulltext on fragment(text);
