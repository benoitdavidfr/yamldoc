drop table if exists fragment;
create table fragment (
  fragid varchar(256) not null primary key comment "id du fragment",
  text longtext comment "texte associé"
) COMMENT = 'fragment'
-- ENGINE = MYISAM
DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci;
create fulltext index fragment_fulltext on fragment(text);
