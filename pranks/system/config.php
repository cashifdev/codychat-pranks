<?php
$load_addons = 'pranks';
require(__DIR__ . '/../../../system/config_addons.php');
if(!canManageAddons()){ die(); }
$c = pranksCfg($addons);
$onoff = array(1 => 'On', 0 => 'Off');
$items = pranksAll(true);
$groups = pranksGroups();
$effects = pranksEffects();
$writable = is_dir(PRANKS_DIR) && is_writable(PRANKS_DIR);
$phpMax = ini_get('upload_max_filesize');
function pkFace($it){ return $it['img'] !== '' ? '<img src="' . pranksTxt($it['img']) . '" alt="">' : pranksTxt($it['e']); }
echo elementTitle('Pranks', "loadLob('admin/setting_addons.php');");
?>
<style>
.pka_row{display:flex;align-items:center;gap:10px;padding:9px 0;border-bottom:1px solid rgba(128,128,128,.18)}
.pka_face{width:38px;height:38px;flex:none;display:grid;place-items:center;font-size:24px;border-radius:10px;background:rgba(250,204,21,.16)}
.pka_face img{width:30px;height:30px;object-fit:contain}
.pka_main{flex:1;min-width:0}
.pka_main b{display:block;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.pka_main small{opacity:.7}
.pka_btns{display:flex;gap:6px;flex-wrap:wrap;justify-content:flex-end}
.pka_btn{border:0;border-radius:8px;padding:7px 10px;font-size:12px;font-weight:700;cursor:pointer;background:rgba(128,128,128,.16);color:inherit}
.pka_btn.on{background:#16a34a;color:#fff}
.pka_btn.off{background:#9ca3af;color:#fff}
.pka_btn.red{background:#dc2626;color:#fff}
.pka_box{padding:12px;border-radius:12px;border:1px dashed rgba(245,158,11,.6);margin-top:8px}
.pka_two{display:grid;grid-template-columns:1fr 1fr;gap:10px}
.pka_warn{padding:10px;border-radius:10px;background:rgba(220,38,38,.12);color:#dc2626;font-weight:700}
@media(max-width:520px){.pka_two{grid-template-columns:1fr}.pka_row{flex-wrap:wrap}.pka_btns{width:100%;justify-content:flex-start}}
</style>
<div class="page_full"><div class="page_element"><div class="tpad15">
	<p class="sub_text">Members open someone's menu and tap <b>Prank</b>. The whole room sees it land, and the person pranked gets 20 seconds to <b>bounce it back</b>.</p>
	<?php if(!$writable){ ?><p class="pka_warn tmargin10">To upload pictures and sounds, make the folder <code>addons/pranks/files/custom</code> writable (755).</p><?php } ?>

	<p class="label tmargin15"><b>Settings</b></p>
	<div class="setting_element"><p class="label">Seconds between pranks (per member)</p>
		<input id="pka_gap" class="full_input" type="number" min="0" max="3600" value="<?php echo $c['gap']; ?>"/></div>
	<div class="setting_element"><p class="label">Picker theme</p>
		<p class="sub_text text_xsmall">Auto follows your chat theme, light or dark.</p>
		<?php echo pranksSel('pka_theme', $c['theme'], array(0 => 'Auto (follow the chat theme)', 1 => 'Light', 2 => 'Dark')); ?></div>
	<div class="setting_element"><p class="label">Sound volume (0 to 100)</p>
		<input id="pka_vol" class="full_input" type="number" min="0" max="100" value="<?php echo $c['vol']; ?>"/></div>
	<div class="setting_element"><p class="label">Built-in sound effects</p>
		<p class="sub_text text_xsmall">The little splat, boing and chime sounds made in the browser. Your own .mp3 sounds always play.</p>
		<?php echo pranksSel('pka_synth', $c['synth'], $onoff); ?></div>
	<div class="setting_element"><p class="label">Biggest sound file (KB)</p>
		<p class="sub_text text_xsmall">Your server allows uploads up to <?php echo pranksTxt($phpMax); ?>.</p>
		<input id="pka_maxkb" class="full_input" type="number" min="100" max="5120" value="<?php echo $c['maxkb']; ?>"/></div>
	<div class="setting_element"><p class="label">Show credit link</p>
		<p class="sub_text text_xsmall">A small "Pranks by M Kashif" line with links to GitHub and chatnoregister.com in the prank picker. Keeping it on supports free add-ons.</p>
		<?php echo pranksSel('pka_credit', $c['credit'], $onoff); ?></div>
	<button class="reg_button theme_btn tmargin10" onclick="pkaSave()">Save settings</button>

	<p class="label tmargin20"><b id="pka_ftitle">Add your own prank</b></p>
	<div class="pka_box">
		<input type="hidden" id="pka_id" value="0"/>
		<div class="pka_two">
			<div><p class="label">Name</p><input id="pka_name" class="full_input" maxlength="40" placeholder="Snowball"/></div>
			<div><p class="label">Emoji</p><input id="pka_emoji" class="full_input" maxlength="16" placeholder="&#x2744;&#xFE0F;"/></div>
		</div>
		<p class="label tmargin10">Chat line</p>
		<input id="pka_verb" class="full_input" maxlength="80" placeholder="threw a snowball at"/>
		<p class="sub_text text_xsmall">Shows as: <i>Alex threw a snowball at Sam</i></p>
		<div class="pka_two tmargin10">
			<div><p class="label">Tab</p><?php echo pranksSel('pka_grp', 'more', $groups); ?></div>
			<div><p class="label">Effect</p><?php echo pranksSel('pka_fx', 'throw', $effects); ?></div>
		</div>
		<p class="label tmargin10">Pop-up word (optional)</p>
		<input id="pka_word" class="full_input" maxlength="24" placeholder="SPLAT!"/>
		<div class="pka_two tmargin10">
			<div><p class="label">Picture instead of emoji (optional)</p><input id="pka_img" type="file" accept=".png,.gif,.webp,.jpg,.jpeg,image/*"/>
				<p class="sub_text text_xsmall">PNG, GIF, WEBP or JPG, up to 512 KB. Animated GIFs work.</p>
				<label class="sub_text text_xsmall pka_editonly" style="display:none"><input type="checkbox" id="pka_noimg"/> Remove the picture</label></div>
			<div><p class="label">Sound effect (optional)</p><input id="pka_snd" type="file" accept=".mp3,audio/mpeg"/>
				<p class="sub_text text_xsmall">.mp3, up to <?php echo $c['maxkb']; ?> KB. Short sounds (1 to 3 seconds) work best.</p>
				<label class="sub_text text_xsmall pka_editonly" style="display:none"><input type="checkbox" id="pka_nosnd"/> Remove the sound</label></div>
		</div>
		<button class="reg_button theme_btn tmargin10" onclick="pkaAdd()" id="pka_addbtn">Add prank</button>
		<button class="reg_button default_btn tmargin10 pka_editonly" style="display:none" onclick="pkaReset()">Cancel</button>
	</div>

	<p class="label tmargin20"><b>Your pranks</b></p>
	<div id="pka_mine">
	<?php $mine = 0; foreach($items as $it){ if(!$it['c']){ continue; } $mine++; ?>
		<div class="pka_row" data-k="<?php echo $it['k']; ?>">
			<div class="pka_face"><?php echo pkFace($it); ?></div>
			<div class="pka_main"><b><?php echo pranksTxt($it['n']); ?></b><small><?php echo pranksTxt($groups[$it['g']] ?? $it['g']); ?> · <?php echo pranksTxt($effects[$it['fx']] ?? $it['fx']); ?><?php echo $it['snd'] !== '' ? ' · own sound' : ''; ?></small></div>
			<div class="pka_btns">
				<?php if($it['snd'] !== ''){ ?><button class="pka_btn" onclick="pkaPlay('<?php echo pranksTxt($it['snd']); ?>')">&#9654; Sound</button><?php } ?>
				<button class="pka_btn <?php echo $it['on'] ? 'on' : 'off'; ?>" onclick="pkaToggle(this,'<?php echo $it['k']; ?>')"><?php echo $it['on'] ? 'On' : 'Off'; ?></button>
				<button class="pka_btn" onclick='pkaEdit(<?php echo json_encode(array('id' => $it['id'], 'n' => $it['n'], 'e' => $it['e'], 'verb' => $it['verb'], 'w' => $it['w'], 'g' => $it['g'], 'fx' => $it['fx']), JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP); ?>)'>Edit</button>
				<button class="pka_btn red" onclick="pkaDel(<?php echo (int)$it['id']; ?>)">Delete</button>
			</div>
		</div>
	<?php } if(!$mine){ ?><p class="sub_text">None yet. Add your first one above.</p><?php } ?>
	</div>

	<p class="label tmargin20"><b>Built-in pranks</b></p>
	<p class="sub_text text_xsmall">Switch any of them off, or give it your own .mp3 sound.</p>
	<?php foreach($items as $it){ if($it['c']){ continue; } ?>
		<div class="pka_row" data-k="<?php echo $it['k']; ?>">
			<div class="pka_face"><?php echo pkFace($it); ?></div>
			<div class="pka_main"><b><?php echo pranksTxt($it['n']); ?></b><small class="pka_sndlbl"><?php echo $it['snd'] !== '' ? 'Own sound' : 'Built-in sound'; ?></small></div>
			<div class="pka_btns">
				<?php if($it['snd'] !== ''){ ?><button class="pka_btn" onclick="pkaPlay('<?php echo pranksTxt($it['snd']); ?>')">&#9654;</button><button class="pka_btn" onclick="pkaNoSound('<?php echo $it['k']; ?>')">Use built-in</button><?php } ?>
				<button class="pka_btn" onclick="pkaPickSound('<?php echo $it['k']; ?>')">Upload .mp3</button>
				<button class="pka_btn <?php echo $it['on'] ? 'on' : 'off'; ?>" onclick="pkaToggle(this,'<?php echo $it['k']; ?>')"><?php echo $it['on'] ? 'On' : 'Off'; ?></button>
			</div>
		</div>
	<?php } ?>
	<input type="file" id="pka_bsnd" accept=".mp3,audio/mpeg" style="display:none"/>
	<p class="sub_text text_xsmall tmargin15">Pranks add-on by <a href="https://github.com/cashifdev" target="_blank">M Kashif</a> · see it live at <a href="https://chatnoregister.com" target="_blank">chatnoregister.com</a></p>
</div></div></div>
<script>
var pkaUrl = 'addons/pranks/system/action.php';
function pkaReload(){ configAddons('pranks'); }
function pkaSend(fd, done){
	fd.append('token', typeof utk !== 'undefined' ? utk : '');
	$.ajax({ url: pkaUrl, type: 'POST', data: fd, processData: false, contentType: false, dataType: 'json' })
		.done(function(r){ if(r && r.ok){ done(r); } else { callSaved((r && r.msg) || 'Not saved', 3); } })
		.fail(function(){ callSaved('Upload failed. The file may be bigger than your server allows.', 3); });
}
function pkaSave(){
	var fd = new FormData(); fd.append('act', 'admin_save');
	['gap', 'theme', 'vol', 'synth', 'maxkb', 'credit'].forEach(function(k){ fd.append(k, $('#pka_' + k).val()); });
	pkaSend(fd, function(){ callSaved('Saved', 1); });
}
function pkaAdd(){
	var id = parseInt($('#pka_id').val(), 10) || 0, fd = new FormData();
	fd.append('act', id ? 'admin_edit' : 'admin_add'); fd.append('id', id);
	['name', 'emoji', 'verb', 'word', 'grp', 'fx'].forEach(function(k){ fd.append(k, $('#pka_' + k).val()); });
	if($('#pka_img')[0].files[0]){ fd.append('img', $('#pka_img')[0].files[0]); }
	if($('#pka_snd')[0].files[0]){ fd.append('snd', $('#pka_snd')[0].files[0]); }
	if($('#pka_noimg').is(':checked')){ fd.append('noimg', 1); }
	if($('#pka_nosnd').is(':checked')){ fd.append('nosnd', 1); }
	pkaSend(fd, function(){ callSaved(id ? 'Saved' : 'Prank added. Members see it after a page refresh.', 1); pkaReload(); });
}
function pkaEdit(it){
	$('#pka_id').val(it.id); $('#pka_name').val(it.n); $('#pka_emoji').val(it.e); $('#pka_verb').val(it.verb);
	$('#pka_word').val(it.w); $('#pka_grp').val(it.g); $('#pka_fx').val(it.fx);
	$('#pka_ftitle').text('Edit ' + it.n); $('#pka_addbtn').text('Save prank'); $('.pka_editonly').show();
	document.getElementById('pka_ftitle').scrollIntoView({ behavior: 'smooth' });
}
function pkaReset(){
	$('#pka_id').val(0); $('#pka_name,#pka_emoji,#pka_verb,#pka_word,#pka_img,#pka_snd').val('');
	$('#pka_noimg,#pka_nosnd').prop('checked', false);
	$('#pka_ftitle').text('Add your own prank'); $('#pka_addbtn').text('Add prank'); $('.pka_editonly').hide();
}
function pkaDel(id){
	if(!confirm('Delete this prank?')){ return; }
	var fd = new FormData(); fd.append('act', 'admin_del'); fd.append('id', id);
	pkaSend(fd, function(){ callSaved('Deleted', 1); pkaReload(); });
}
function pkaToggle(b, k){
	var on = $(b).hasClass('on') ? 0 : 1, fd = new FormData();
	fd.append('act', 'admin_toggle'); fd.append('k', k); fd.append('on', on);
	pkaSend(fd, function(){ $(b).toggleClass('on', !!on).toggleClass('off', !on).text(on ? 'On' : 'Off'); });
}
var pkaKey = '';
function pkaPickSound(k){ pkaKey = k; $('#pka_bsnd').val('').trigger('click'); }
$(document).off('change.pka').on('change.pka', '#pka_bsnd', function(){
	if(!this.files[0] || !pkaKey){ return; }
	var fd = new FormData(); fd.append('act', 'admin_sound'); fd.append('k', pkaKey); fd.append('snd', this.files[0]);
	pkaSend(fd, function(r){ callSaved('Sound saved', 1); pkaPlay(r.snd); pkaReload(); });
});
function pkaNoSound(k){
	var fd = new FormData(); fd.append('act', 'admin_nosound'); fd.append('k', k);
	pkaSend(fd, function(){ callSaved('Back to the built-in sound', 1); pkaReload(); });
}
function pkaPlay(u){ try { var a = new Audio(u); a.volume = Math.max(0, Math.min(100, parseInt($('#pka_vol').val(), 10) || 80)) / 100; a.play(); } catch(e){} }
</script>
