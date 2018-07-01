/*PhpDoc:
name: yamldoc.sql
title: yamldoc.sql - schema de la base MySQL utilisé pour l'index full text de YamlDoc
*/
drop table if exists document;
create table document (
  store varchar(20) not null comment 'store',
  docid varchar(200) not null comment 'id du doc',
  maj datetime comment 'date et heure de maj du doc',
  readers varchar(200) comment 'liste des lecteurs autorisés, null ssi tous',
  primary key storedocid (store,docid)
) COMMENT = 'document'
-- ENGINE = MYISAM
DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci;

drop table if exists fragment;
create table fragment (
  store varchar(20) not null comment 'store',
  fragid varchar(200) not null comment 'id du fragment',
  text longtext comment 'texte associé',
  primary key storefragid (store,fragid)
) COMMENT = 'fragment'
-- ENGINE = MYISAM
DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci;

create fulltext index fragment_fulltext on fragment(text);
