<?php
if(!defined('BOOM')){ die(); }
$mysqli->query("DROP TABLE IF EXISTS `pranks_log`");
$mysqli->query("DROP TABLE IF EXISTS `pranks_items`");
foreach(glob(BOOM_PATH . '/addons/pranks/files/custom/*.{mp3,png,gif,webp,jpg}', GLOB_BRACE) ?: array() as $f){ @unlink($f); }
?>
