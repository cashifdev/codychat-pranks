<?php
if(!defined('BOOM')){ die(); }
/* custom1 = seconds between pranks, custom2 = theme (0 auto, 1 light, 2 dark), custom3 = credit line,
 * custom4 = volume 0-100, custom5 = max sound size in KB, custom6 = built-in sounds on/off */
$ad = array('name' => 'pranks', 'access' => 0, 'custom1' => '15', 'custom2' => '0', 'custom3' => '1', 'custom4' => '80', 'custom5' => '1024', 'custom6' => '1');
$mysqli->query("CREATE TABLE IF NOT EXISTS `pranks_log` (
	`id` INT(11) NOT NULL AUTO_INCREMENT,
	`user_id` INT(11) NOT NULL,
	`target_id` INT(11) NOT NULL,
	`room_id` INT(11) NOT NULL DEFAULT 0,
	`prank` VARCHAR(20) NOT NULL DEFAULT '',
	`bounced` TINYINT(1) NOT NULL DEFAULT 0,
	`created` INT(11) NOT NULL,
	PRIMARY KEY (`id`), KEY `idx_user` (`user_id`, `id`), KEY `idx_target` (`target_id`, `id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
$mysqli->query("CREATE TABLE IF NOT EXISTS `pranks_items` (
	`id` INT(11) NOT NULL AUTO_INCREMENT,
	`pkey` VARCHAR(20) NOT NULL DEFAULT '',
	`builtin` TINYINT(1) NOT NULL DEFAULT 0,
	`name` VARCHAR(40) NOT NULL DEFAULT '',
	`emoji` VARCHAR(40) NOT NULL DEFAULT '',
	`img` VARCHAR(64) NOT NULL DEFAULT '',
	`snd` VARCHAR(64) NOT NULL DEFAULT '',
	`verb` VARCHAR(80) NOT NULL DEFAULT '',
	`word` VARCHAR(24) NOT NULL DEFAULT '',
	`grp` VARCHAR(12) NOT NULL DEFAULT 'more',
	`fx` VARCHAR(10) NOT NULL DEFAULT 'burst',
	`active` TINYINT(1) NOT NULL DEFAULT 1,
	`created` INT(11) NOT NULL DEFAULT 0,
	PRIMARY KEY (`id`), UNIQUE KEY `pkey` (`pkey`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
?>
