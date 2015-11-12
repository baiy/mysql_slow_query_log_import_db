CREATE TABLE `db_analysis` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `query_time` double(20,8) unsigned NOT NULL COMMENT '查询时间',
  `lock_time` double(20,8) unsigned NOT NULL COMMENT '等待表锁时间',
  `rows_sent` int(10) unsigned NOT NULL COMMENT '查询返回的行数',
  `rows_examined` int(10) unsigned NOT NULL COMMENT '查询检查的行数',
  `timestamp` datetime NOT NULL COMMENT '查询时间',
  `sql` text NOT NULL,
  `table` varchar(100) NOT NULL,
  `user` varchar(100) NOT NULL,
  `host` varchar(50) NOT NULL,
  `dbid` varchar(20) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `query_time` (`query_time`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;