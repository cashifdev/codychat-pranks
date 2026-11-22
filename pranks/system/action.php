<?php
/* Pranks endpoint
 * members: act=prank (to, k) | act=bounce (id)
 * admin:   act=admin_save | admin_add | admin_edit | admin_del | admin_toggle | admin_sound | admin_nosound
 * by M Kashif (github.com/cashifdev) · chatnoregister.com */
$load_addons = 'pranks';
require(__DIR__ . '/../../../system/config_addons.php');
header('Content-Type: application/json');

function pkOut($a){ echo json_encode($a); exit; }
function pkIn($k, $max){ return isset($_POST[$k]) ? mb_substr(trim(strip_tags((string)$_POST[$k])), 0, $max, 'UTF-8') : ''; }
/* stores emoji as &#x..; so it survives any database charset */
function pkEnt($s){ return function_exists('mb_encode_numericentity') ? mb_encode_numericentity($s, array(0x80, 0x10FFFF, 0, 0x1FFFFF), 'UTF-8') : $s; }
function pkDb($s){ global $mysqli; return $mysqli->real_escape_string($s); }

$act = isset($_POST['act']) ? (string)$_POST['act'] : '';
$cfg = pranksCfg($addons);
$t   = time();

/* ---------------- admin ---------------- */
if(strpos($act, 'admin_') === 0){
	if(!canManageAddons()){ pkOut(array('ok' => 0, 'msg' => 'Only staff who manage add-ons can do that.')); }

	if($act === 'admin_save'){
		$n = function($k, $min, $max, $def){ return isset($_POST[$k]) ? max($min, min($max, (int)$_POST[$k])) : $def; };
		$s = array('custom1' => $n('gap', 0, 3600, 15), 'custom2' => $n('theme', 0, 2, 0), 'custom3' => $n('credit', 0, 1, 1),
			'custom4' => $n('vol', 0, 100, 80), 'custom5' => $n('maxkb', 100, 5120, 1024), 'custom6' => $n('synth', 0, 1, 1));
		$set = array();
		foreach($s as $k => $v){ $set[] = "$k = '" . (int)$v . "'"; }
		$mysqli->query("UPDATE boom_addons SET " . implode(', ', $set) . " WHERE addons = 'pranks'");
		if(function_exists('redisUpdateAddons')){ redisUpdateAddons('pranks'); }
		pkOut(array('ok' => 1));
	}

	if($act === 'admin_add' || $act === 'admin_edit'){
		$id   = isset($_POST['id']) ? (int)$_POST['id'] : 0;
		$old  = null;
		if($act === 'admin_edit'){
			$q = $mysqli->query("SELECT * FROM pranks_items WHERE id = '$id' AND builtin = 0 LIMIT 1");
			if(!$q || !$q->num_rows){ pkOut(array('ok' => 0, 'msg' => 'That prank was not found.')); }
			$old = $q->fetch_assoc();
		}
		$name  = pkIn('name', 40);
		$emoji = pkIn('emoji', 16);
		$verb  = pkIn('verb', 80);
		$word  = pkIn('word', 24);
		$grp   = isset(pranksGroups()[$_POST['grp'] ?? '']) ? $_POST['grp'] : 'more';
		$fx    = isset(pranksEffects()[$_POST['fx'] ?? '']) ? $_POST['fx'] : 'burst';
		if($name === ''){ pkOut(array('ok' => 0, 'msg' => 'Give the prank a name.')); }
		list($img, $e1) = pranksUpload('img', 'img', $cfg['maxkb']);
		if($e1 !== ''){ pkOut(array('ok' => 0, 'msg' => $e1)); }
		list($snd, $e2) = pranksUpload('snd', 'snd', $cfg['maxkb']);
		if($e2 !== ''){ pranksDrop($img); pkOut(array('ok' => 0, 'msg' => $e2)); }
		$keepImg = $old ? $old['img'] : '';
		$keepSnd = $old ? $old['snd'] : '';
		if($img !== ''){ pranksDrop($keepImg); $keepImg = $img; }
		if($snd !== ''){ pranksDrop($keepSnd); $keepSnd = $snd; }
		if(!empty($_POST['noimg']) && $img === ''){ pranksDrop($keepImg); $keepImg = ''; }
		if(!empty($_POST['nosnd']) && $snd === ''){ pranksDrop($keepSnd); $keepSnd = ''; }
		if($emoji === '' && $keepImg === ''){ pkOut(array('ok' => 0, 'msg' => 'Add an emoji or upload a picture.')); }
		$v = array('name' => pkEnt($name), 'emoji' => pkEnt($emoji), 'img' => $keepImg, 'snd' => $keepSnd, 'verb' => pkEnt($verb), 'word' => pkEnt($word), 'grp' => $grp, 'fx' => $fx);
		if($old){
			$set = array();
			foreach($v as $k => $x){ $set[] = "`$k` = '" . pkDb($x) . "'"; }
			$mysqli->query("UPDATE pranks_items SET " . implode(', ', $set) . " WHERE id = '$id'");
		}
		else {
			$mysqli->query("INSERT INTO pranks_items (pkey, builtin, name, emoji, img, snd, verb, word, grp, fx, active, created) VALUES ('tmp" . bin2hex(random_bytes(6)) . "', 0, '"
				. pkDb($v['name']) . "', '" . pkDb($v['emoji']) . "', '" . pkDb($v['img']) . "', '" . pkDb($v['snd']) . "', '" . pkDb($v['verb']) . "', '" . pkDb($v['word']) . "', '$grp', '$fx', 1, '$t')");
			$id = (int)$mysqli->insert_id;
			if($id <= 0){ pranksDrop($keepImg); pranksDrop($keepSnd); pkOut(array('ok' => 0, 'msg' => 'Could not save. Is the add-on installed?')); }
			$mysqli->query("UPDATE pranks_items SET pkey = 'c$id' WHERE id = '$id'");
		}
		pkOut(array('ok' => 1, 'id' => $id));
	}

	if($act === 'admin_del'){
		$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
		$q = $mysqli->query("SELECT img, snd FROM pranks_items WHERE id = '$id' AND builtin = 0 LIMIT 1");
		if($q && $q->num_rows){ $r = $q->fetch_assoc(); pranksDrop($r['img']); pranksDrop($r['snd']); $mysqli->query("DELETE FROM pranks_items WHERE id = '$id'"); }
		pkOut(array('ok' => 1));
	}

	/* switch any prank on or off; k = built-in key or c<id> */
	$k = isset($_POST['k']) ? preg_replace('/[^a-z0-9]/', '', (string)$_POST['k']) : '';
	$cat = require(__DIR__ . '/catalog.php');
	$isBuilt = isset($cat[$k]);
	if($isBuilt){ $mysqli->query("INSERT IGNORE INTO pranks_items (pkey, builtin, created) VALUES ('$k', 1, '$t')"); }
	$q = $mysqli->query("SELECT * FROM pranks_items WHERE pkey = '" . pkDb($k) . "' LIMIT 1");
	if(!$q || !$q->num_rows){ pkOut(array('ok' => 0, 'msg' => 'That prank was not found.')); }
	$row = $q->fetch_assoc();

	if($act === 'admin_toggle'){
		$on = !empty($_POST['on']) ? 1 : 0;
		$mysqli->query("UPDATE pranks_items SET active = '$on' WHERE id = '" . (int)$row['id'] . "'");
		pkOut(array('ok' => 1, 'on' => $on));
	}
	if($act === 'admin_sound'){
		list($snd, $err) = pranksUpload('snd', 'snd', $cfg['maxkb']);
		if($err !== ''){ pkOut(array('ok' => 0, 'msg' => $err)); }
		if($snd === ''){ pkOut(array('ok' => 0, 'msg' => 'Choose an .mp3 file first.')); }
		pranksDrop($row['snd']);
		$mysqli->query("UPDATE pranks_items SET snd = '" . pkDb($snd) . "' WHERE id = '" . (int)$row['id'] . "'");
		pkOut(array('ok' => 1, 'snd' => PRANKS_URL . $snd));
	}
	if($act === 'admin_nosound'){
		pranksDrop($row['snd']);
		$mysqli->query("UPDATE pranks_items SET snd = '' WHERE id = '" . (int)$row['id'] . "'");
		pkOut(array('ok' => 1));
	}
	pkOut(array('ok' => 0, 'msg' => 'Bad request'));
}

/* ---------------- members ---------------- */
if(!boomAllow($addons['addons_access']) || mainBlocked() || isMuted($data)){ pkOut(array('ok' => 0, 'msg' => 'Pranks are not available for you right now.')); }

$me    = (int)$data['user_id'];
$room  = (int)$data['user_roomid'];
$items = pranksAll();

function pkUser($id){
	global $mysqli;
	$q = $mysqli->query("SELECT user_id, user_name, user_roomid, user_bot FROM boom_users WHERE user_id = '" . (int)$id . "' LIMIT 1");
	return ($q && $q->num_rows) ? $q->fetch_assoc() : null;
}
/* posts the line everyone sees; the hidden <i> tells each browser which effect to play */
function pkLine($id, $it, $from, $to, $fromName, $toName, $back){
	global $mysqli, $setting, $room, $t;
	$html = '<span class="pk-line' . ($it['g'] === 'sweet' ? ' pk-sweet' : '') . '"><span class="pk-ico">' . pranksIcon($it) . '</span><span>'
		. ($back ? '<em>Bounced back!</em> ' : '') . '<b>' . pranksTxt($fromName) . '</b> ' . pranksTxt($it['verb']) . ' <b>' . pranksTxt($toName) . '</b></span>'
		. '<i class="pk-go" data-p="' . (int)$id . '.' . $it['k'] . '.' . (int)$from . '.' . (int)$to . '.' . $t . '.' . ($back ? 1 : 0) . '"></i></span>';
	$sys = (int)$setting['system_id'];
	$mysqli->query("INSERT INTO `boom_chat` (post_date, user_id, post_roomid, type, syslog, tid, tname, custom) VALUES ('$t', '$sys', '$room', 'system__custom', '1', '" . (int)$from . "', '" . $mysqli->real_escape_string($fromName) . "', '" . $mysqli->real_escape_string($html) . "')");
	chatAction($room);
}

if($act === 'prank'){
	$k  = isset($_POST['k']) ? (string)$_POST['k'] : '';
	$to = isset($_POST['to']) ? (int)$_POST['to'] : 0;
	if(!isset($items[$k])){ pkOut(array('ok' => 0, 'msg' => 'That prank does not exist.')); }
	if($to <= 0 || $to === $me){ pkOut(array('ok' => 0, 'msg' => 'Pick someone else to prank.')); }
	$u = pkUser($to);
	if(!$u){ pkOut(array('ok' => 0, 'msg' => 'User not found.')); }
	if((int)$u['user_bot'] > 0){ pkOut(array('ok' => 0, 'msg' => 'Bots are prank-proof.')); }
	if((int)$u['user_roomid'] !== $room){ pkOut(array('ok' => 0, 'msg' => $u['user_name'] . ' is not in this room.')); }
	$gap = $cfg['gap'];
	$q = $mysqli->query("SELECT created FROM pranks_log WHERE user_id = '$me' AND bounced <> 1 ORDER BY id DESC LIMIT 1");
	if($gap > 0 && $q && $q->num_rows){
		$left = $gap - ($t - (int)$q->fetch_assoc()['created']);
		if($left > 0){ pkOut(array('ok' => 0, 'left' => $left, 'msg' => 'Reloading the prank box: ' . $left . 's')); }
	}
	$mysqli->query("INSERT INTO pranks_log (user_id, target_id, room_id, prank, created) VALUES ('$me', '$to', '$room', '" . pkDb($k) . "', '$t')");
	$id = (int)$mysqli->insert_id;
	pkLine($id, $items[$k], $me, $to, $data['user_name'], $u['user_name'], false);
	pkOut(array('ok' => 1, 'gap' => $gap, 'id' => $id));
}

if($act === 'bounce'){
	$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
	$q = $mysqli->query("SELECT * FROM pranks_log WHERE id = '$id' AND target_id = '$me' AND bounced = 0 AND created > " . ($t - 25) . " LIMIT 1");
	if(!$q || !$q->num_rows){ pkOut(array('ok' => 0, 'msg' => 'Too late to bounce that one back.')); }
	$r = $q->fetch_assoc();
	$mysqli->query("UPDATE pranks_log SET bounced = 2 WHERE id = '$id'");
	$u = pkUser((int)$r['user_id']);
	if(!$u || (int)$u['user_roomid'] !== $room){ pkOut(array('ok' => 0, 'msg' => 'They already left the room.')); }
	$k = $r['prank'];
	if(!isset($items[$k])){ pkOut(array('ok' => 0, 'msg' => 'That prank was removed.')); }
	$mysqli->query("INSERT INTO pranks_log (user_id, target_id, room_id, prank, bounced, created) VALUES ('$me', '" . (int)$u['user_id'] . "', '$room', '" . pkDb($k) . "', 1, '$t')");
	$nid = (int)$mysqli->insert_id;
	pkLine($nid, $items[$k], $me, (int)$u['user_id'], $data['user_name'], $u['user_name'], true);
	pkOut(array('ok' => 1, 'id' => $nid));
}
pkOut(array('ok' => 0, 'msg' => 'Bad request'));
?>
