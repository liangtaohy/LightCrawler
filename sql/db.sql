CREATE TABLE `urls` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '自增主键',
  `in_process` tinyint(1) DEFAULT '0' COMMENT '是否为处理中:0-不是,1-是的',
  `processed` tinyint(1) DEFAULT '0' COMMENT '是否处理完',
  `priority_level` int(11) DEFAULT '0' COMMENT '优先级',
  `distinct_hash` varchar(32) DEFAULT '' COMMENT '唯一性哈希',
  `link_raw` varchar(2048) DEFAULT '' COMMENT '原始LINK',
  `linkcode` varchar(2048) DEFAULT '' COMMENT '原始link',
  `linktext` varchar(2048) DEFAULT '' COMMENT '原始link',
  `refering_url` varchar(2048) DEFAULT '' COMMENT 'referer',
  `url_rebuild` varchar(2048) DEFAULT '' COMMENT '重建后的url',
  `is_redirect_url` tinyint(1) DEFAULT '0' COMMENT '是否为重定向url',
  `url_link_depth` int(11) DEFAULT '0' COMMENT '深度',
  `ctime` bigint(13) NOT NULL DEFAULT '0' COMMENT '入库时间',
  `mtime` bigint(13) NOT NULL DEFAULT '0' COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `distinct_hash` (`distinct_hash`),
  KEY `priority_level` (`priority_level`),
  KEY `priority_level_2` (`priority_level`),
  KEY `distinct_hash_2` (`distinct_hash`),
  KEY `in_process` (`in_process`),
  KEY `processed` (`processed`)
) ENGINE=MyISAM AUTO_INCREMENT=0 DEFAULT CHARSET=utf8 COMMENT='url cache库';

CREATE TABLE `xlegal_law_base` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT COMMENT '自增id',
  `doc_id` varchar(32) NOT NULL DEFAULT '' COMMENT '文档编码',
  `type` tinyint(4) NOT NULL DEFAULT '0' COMMENT '1-html,2-json,3-txt,4-doc,5-docx',
  `content` longtext NOT NULL COMMENT '文档',
  `ctime` bigint(13) NOT NULL DEFAULT '0' COMMENT '创建时间',
  `url_md5` varchar(32) NOT NULL DEFAULT '' COMMENT 'url的md5签名',
  `url` varchar(2048) NOT NULL DEFAULT '' COMMENT 'url字段，主要用于重入访问',
  `simhash` varchar(64) NOT NULL DEFAULT '' COMMENT '相似哈希值',
  `mtime` bigint(13) NOT NULL COMMENT '修改时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `doc_id` (`doc_id`),
  UNIQUE KEY `url_md5` (`url_md5`),
  KEY `simhash` (`simhash`),
  KEY `ctime` (`ctime`)
) ENGINE=MyISAM AUTO_INCREMENT=15689 DEFAULT CHARSET=utf8 COMMENT='法律数据基础库';

CREATE TABLE `xlegal_countys` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '自增主键',
  `abbrev` varchar(8) NOT NULL DEFAULT '' COMMENT '简称',
  `province` varchar(64) NOT NULL DEFAULT '' COMMENT '省份',
  `city` varchar(128) NOT NULL DEFAULT '' COMMENT '市级法院',
  `countys` varchar(2048) NOT NULL DEFAULT '' COMMENT '法院',
  `ctime` bigint(13) NOT NULL DEFAULT '0' COMMENT '入库时间',
  PRIMARY KEY (`id`),
  KEY `province` (`province`)
) ENGINE=MyISAM AUTO_INCREMENT=399 DEFAULT CHARSET=utf8 COMMENT='全国法院列表';

CREATE TABLE `xlegal_cause_of_action` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '自增主键',
  `coa_no` varchar(32) NOT NULL DEFAULT '' COMMENT '案由编号：[民事|刑事].[类1].[类2].[类3]',
  `coa_name` varchar(64) NOT NULL DEFAULT '' COMMENT '案由名称',
  `ctime` bigint(13) NOT NULL DEFAULT '0' COMMENT '入库时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `coa_no` (`coa_no`),
  FULLTEXT KEY `coa_name` (`coa_name`)
) ENGINE=MyISAM AUTO_INCREMENT=1281 DEFAULT CHARSET=utf8 COMMENT='案由规定表';