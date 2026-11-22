<?php
if(!defined('BOOM')){ die(); }
/* Pranks for Codychat, by M Kashif (github.com/cashifdev) · chatnoregister.com */

define('PRANKS_DIR', BOOM_PATH . '/addons/pranks/files/custom/');
define('PRANKS_URL', 'addons/pranks/files/custom/');

function pranksCfg($a){
	$v = function($k, $d) use ($a){ return (isset($a[$k]) && $a[$k] !== '' && $a[$k] !== null) ? (int)$a[$k] : $d; };
	return array(
		'gap'    => min(3600, max(0, $v('custom1', 15))),
		'theme'  => min(2, max(0, $v('custom2', 0))),
		'credit' => $v('custom3', 1) === 1 ? 1 : 0,
		'vol'    => min(100, max(0, $v('custom4', 80))),
		'maxkb'  => min(5120, max(100, $v('custom5', 1024))),
		'synth'  => $v('custom6', 1) === 1 ? 1 : 0,
	);
}
function pranksGroups(){
	return array('splat' => 'Splat', 'silly' => 'Silly', 'magic' => 'Magic', 'sweet' => 'Sweet', 'more' => 'More');
}
function pranksEffects(){
	return array('burst' => 'Pop + burst', 'throw' => 'Thrown at them', 'rain' => 'Rain down', 'zoom' => 'Giant on screen', 'shake' => 'Pop + chat shake');
}
function pranksTxt($s){
	$s = htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
	return function_exists('mb_encode_numericentity') ? mb_encode_numericentity($s, array(0x10000, 0x10FFFF, 0, 0x1FFFFF), 'UTF-8') : $s;
}

/* Every prank: the built-in ones (with the owner's changes) and the owner's own.
 * $all = true also returns pranks that are switched off (for the admin page). */
function pranksAll($all = false){
	global $mysqli;
	$rows = array();
	$q = $mysqli->query("SELECT * FROM pranks_items ORDER BY id ASC");
	if($q){ while($r = $q->fetch_assoc()){ $rows[$r['pkey']] = $r; } }
	$out = array();
	foreach(require(__DIR__ . '/catalog.php') as $k => $c){
		$r = isset($rows[$k]) ? $rows[$k] : null;
		$it = array('k' => $k, 'n' => $c[0], 'e' => html_entity_decode($c[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'), 'img' => '', 'snd' => '', 'verb' => $c[2],
			'g' => $c[3], 'fx' => '', 'w' => '', 'c' => 0, 'on' => 1);
		if($r){
			$it['on'] = (int)$r['active'];
			if($r['snd'] !== ''){ $it['snd'] = PRANKS_URL . $r['snd']; }
		}
		$out[$k] = $it;
	}
	$d = function($x){ return html_entity_decode((string)$x, ENT_QUOTES | ENT_HTML5, 'UTF-8'); };
	foreach($rows as $k => $r){
		if((int)$r['builtin'] === 1){ continue; }
		foreach(array('name', 'emoji', 'verb', 'word') as $f){ $r[$f] = $d($r[$f]); }
		$out[$k] = array('k' => $k, 'n' => $r['name'], 'e' => $r['emoji'], 'img' => $r['img'] !== '' ? PRANKS_URL . $r['img'] : '',
			'snd' => $r['snd'] !== '' ? PRANKS_URL . $r['snd'] : '', 'verb' => $r['verb'] !== '' ? $r['verb'] : 'pranked',
			'g' => $r['grp'], 'fx' => $r['fx'], 'w' => $r['word'], 'c' => 1, 'on' => (int)$r['active'], 'id' => (int)$r['id']);
	}
	if(!$all){ $out = array_filter($out, function($it){ return $it['on'] === 1; }); }
	return $out;
}

/* What the chat page needs: the list plus the tabs that have something in them. */
function pranksClient($items){
	$list = array(); $used = array();
	foreach($items as $it){
		$list[] = array('k' => $it['k'], 'n' => $it['n'], 'e' => $it['e'], 'img' => $it['img'], 'snd' => $it['snd'], 'g' => $it['g'], 'fx' => $it['fx'], 'w' => $it['w'], 'c' => $it['c']);
		$used[$it['g']] = 1;
	}
	$groups = array();
	foreach(pranksGroups() as $g => $label){ if(isset($used[$g])){ $groups[] = array($g, $label); } }
	return array('list' => $list, 'groups' => $groups);
}

/* The little icon in the chat line: an emoji or the uploaded picture */
function pranksIcon($it){
	if($it['img'] !== ''){ return '<img src="' . pranksTxt($it['img']) . '" alt="">'; }
	return pranksTxt($it['e']);
}

function pranksSel($id, $v, $opts){
	$h = '<select id="' . $id . '" class="full_input">';
	foreach($opts as $k => $label){ $h .= '<option value="' . $k . '"' . ((string)$v === (string)$k ? ' selected' : '') . '>' . $label . '</option>'; }
	return $h . '</select>';
}

/* Saves one uploaded file into files/custom with a random name.
 * kind = 'snd' (.mp3 only) or 'img' (png, gif, webp, jpg). Returns [filename, error]. */
function pranksUpload($field, $kind, $maxkb){
	if(empty($_FILES[$field]) || !isset($_FILES[$field]['error']) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE){ return array('', ''); }
	$f = $_FILES[$field];
	if($f['error'] === UPLOAD_ERR_INI_SIZE || $f['error'] === UPLOAD_ERR_FORM_SIZE){ return array('', 'That file is bigger than your server allows (upload_max_filesize).'); }
	if($f['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'])){ return array('', 'Upload failed, try again.'); }
	if(!is_dir(PRANKS_DIR) || !is_writable(PRANKS_DIR)){ return array('', 'Make the folder addons/pranks/files/custom writable (755) and try again.'); }
	$ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
	$head = (string)@file_get_contents($f['tmp_name'], false, null, 0, 16);
	if($kind === 'snd'){
		if($f['size'] > $maxkb * 1024){ return array('', 'Sound files can be up to ' . $maxkb . ' KB.'); }
		$mp3 = (substr($head, 0, 3) === 'ID3') || (strlen($head) > 1 && ord($head[0]) === 0xFF && (ord($head[1]) & 0xE0) === 0xE0);
		if($ext !== 'mp3' || !$mp3){ return array('', 'Please upload an .mp3 file.'); }
		$save = 'mp3';
	}
	else {
		if($f['size'] > 512 * 1024){ return array('', 'Pictures can be up to 512 KB.'); }
		$info = @getimagesize($f['tmp_name']);
		$types = array(IMAGETYPE_PNG => 'png', IMAGETYPE_GIF => 'gif', IMAGETYPE_JPEG => 'jpg');
		if(defined('IMAGETYPE_WEBP')){ $types[IMAGETYPE_WEBP] = 'webp'; }
		if(!$info || !isset($types[$info[2]])){ return array('', 'Please upload a PNG, GIF, WEBP or JPG picture.'); }
		$save = $types[$info[2]];
	}
	$name = bin2hex(random_bytes(8)) . '.' . $save;
	if(!@move_uploaded_file($f['tmp_name'], PRANKS_DIR . $name)){ return array('', 'Could not save the file. Check the folder permissions.'); }
	@chmod(PRANKS_DIR . $name, 0644);
	return array($name, '');
}
function pranksDrop($file){
	$file = basename((string)$file);
	if($file !== '' && preg_match('/^[a-f0-9]{16}\.(mp3|png|gif|webp|jpg)$/', $file) && is_file(PRANKS_DIR . $file)){ @unlink(PRANKS_DIR . $file); }
}
?>
