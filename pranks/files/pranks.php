<?php if(boomAllow($addons['addons_access'])){
	require_once(BOOM_PATH . '/addons/pranks/system/addons_function.php');
	$pk_c = pranksCfg($addons);
	$pk_left = 0;
	$pk_me = isset($data['user_id']) ? (int)$data['user_id'] : 0;
	if($pk_me > 0 && $pk_c['gap'] > 0){
		$pk_q = $mysqli->query("SELECT created FROM pranks_log WHERE user_id = '$pk_me' AND bounced <> 1 ORDER BY id DESC LIMIT 1");
		if($pk_q && $pk_q->num_rows){ $pk_left = max(0, $pk_c['gap'] - (time() - (int)$pk_q->fetch_assoc()['created'])); }
	}
	$pk_js = pranksClient(pranksAll());
	$pk_js += array('base' => 'addons/pranks/', 'gap' => $pk_c['gap'], 'left' => $pk_left, 'theme' => $pk_c['theme'], 'cr' => $pk_c['credit'], 'vol' => $pk_c['vol'], 'synth' => $pk_c['synth']);
	$pk_v = @filemtime(__DIR__ . '/pranks.js') . '-' . @filemtime(__DIR__ . '/pranks.css');
?>
<script data-cfasync="false">
window.PRANKS_CFG = <?php echo json_encode($pk_js); ?>;
window.PRANKS_CFG.off = <?php echo time(); ?> - Math.floor(Date.now() / 1000);
boomAddCss('addons/pranks/files/pranks.css?v=<?php echo $pk_v; ?>');
</script>
<script data-cfasync="false" src="addons/pranks/files/pranks.js?v=<?php echo $pk_v; ?>"></script>
<?php } ?>
