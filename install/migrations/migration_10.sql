CREATE TABLE `%PREFIX%stat_history` (
  `stat_date` date NOT NULL,
  `stat_type` tinyint(1) unsigned NOT NULL,
  `id_owner` int(11) unsigned NOT NULL,
  `universe` tinyint(3) unsigned NOT NULL,
  `tech_rank` int(11) unsigned NOT NULL DEFAULT '0',
  `tech_points` double(50,0) unsigned NOT NULL DEFAULT '0',
  `build_rank` int(11) unsigned NOT NULL DEFAULT '0',
  `build_points` double(50,0) unsigned NOT NULL DEFAULT '0',
  `defs_rank` int(11) unsigned NOT NULL DEFAULT '0',
  `defs_points` double(50,0) unsigned NOT NULL DEFAULT '0',
  `fleet_rank` int(11) unsigned NOT NULL DEFAULT '0',
  `fleet_points` double(50,0) unsigned NOT NULL DEFAULT '0',
  `total_rank` int(11) unsigned NOT NULL DEFAULT '0',
  `total_points` double(50,0) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`stat_type`,`id_owner`,`stat_date`),
  KEY `stat_date` (`stat_date`),
  KEY `universe` (`universe`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
