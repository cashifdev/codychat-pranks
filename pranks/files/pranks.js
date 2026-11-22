/*!
 * Pranks for Codychat 10.1
 * by M Kashif (github.com/cashifdev) · see it live at chatnoregister.com
 * MIT License
 */
(function(){
	'use strict';
	if(window.boomPranks){ return; }

	var C = window.PRANKS_CFG || { base: 'addons/pranks/', gap: 15, left: 0, off: 0, theme: 0, cr: 1, vol: 80, synth: 1, list: [], groups: [] };
	var ITEMS = {};
	C.list.forEach(function(it){ ITEMS[it.k] = it; });

	var readyAt = Date.now() + 1000 * (C.left | 0);
	var tab = (C.groups[0] || ['splat'])[0];
	var target = 0;
	var busy = false;
	var played = {};
	var loadedAt = Date.now();
	var audio = null, master = null;

	/* ---------- small helpers ---------- */
	function myId(){ return (typeof user_id !== 'undefined' && parseInt(user_id, 10)) || 0; }
	function esc(s){ return String(s == null ? '' : s).replace(/[&<>"']/g, function(c){ return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
	function rnd(a, b){ return a + Math.random() * (b - a); }
	function pick(a){ return a[Math.floor(Math.random() * a.length)]; }
	function store(k, v){ try { if(v === undefined){ return localStorage.getItem(k); } localStorage.setItem(k, v); } catch(e){ return null; } }
	function soundOn(){ return store('pranks_sound') !== '0'; }
	function calm(){
		if(store('pranks_calm') === '1'){ return true; }
		return !!(window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches);
	}
	function post(data, cb){
		data.token = typeof utk !== 'undefined' ? utk : '';
		jQuery.ajax({ url: C.base + 'system/action.php', type: 'POST', data: data, dataType: 'json', global: false, timeout: 8000 })
			.done(function(r){ cb(r || {}); })
			.fail(function(){ cb({ ok: 0, msg: 'Connection problem, try again.' }); });
	}
	function secondsLeft(){ return Math.max(0, Math.ceil((readyAt - Date.now()) / 1000)); }

	/* ---------- theme ---------- */
	function isDarkBg(){
		/* the chat area decides, not the header: many themes pair a dark header with a light chat */
		var els = [document.getElementById('chat_logs_container'), document.getElementById('show_chat'), document.querySelector('.back_chat'), document.getElementById('global_chat'), document.body, document.getElementById('chat_head')];
		for(var i = 0; i < els.length; i++){
			if(!els[i]){ continue; }
			var m = getComputedStyle(els[i]).backgroundColor.match(/[\d.]+/g);
			if(!m || (m.length > 3 && parseFloat(m[3]) === 0)){ continue; }
			return (0.2126 * m[0] + 0.7152 * m[1] + 0.0722 * m[2]) < 128;
		}
		return !!(window.matchMedia && matchMedia('(prefers-color-scheme: dark)').matches);
	}
	function themeClass(){
		var t = parseInt(C.theme, 10) || 0;
		return (t === 2 || (t === 0 && isDarkBg())) ? 'pk-dark' : 'pk-light';
	}

	/* ---------- sound ---------- */
	function ctx(){
		if(!audio){
			try {
				audio = new (window.AudioContext || window.webkitAudioContext)();
				master = audio.createGain();
				master.gain.value = Math.max(0, Math.min(100, C.vol | 0)) / 100;
				master.connect(audio.destination);
			}
			catch(e){ audio = null; }
		}
		return (audio && audio.state === 'running') ? audio : null;
	}
	document.addEventListener('pointerdown', function(){ if(audio){ if(audio.state === 'suspended'){ audio.resume(); } } else { ctx(); } }, true);

	var quietSynth = false;
	function tone(type, f1, f2, dur, vol, delay){
		var c = ctx(); if(!c || quietSynth || !C.synth || !soundOn()){ return; }
		var o = c.createOscillator(), g = c.createGain(), t0 = c.currentTime + (delay || 0);
		o.type = type;
		o.frequency.setValueAtTime(f1, t0);
		o.frequency.exponentialRampToValueAtTime(Math.max(30, f2), t0 + dur);
		g.gain.setValueAtTime(vol, t0);
		g.gain.exponentialRampToValueAtTime(0.0001, t0 + dur);
		o.connect(g); g.connect(master);
		o.start(t0); o.stop(t0 + dur + 0.05);
	}
	function noise(dur, freq, vol){
		var c = ctx(); if(!c || quietSynth || !C.synth || !soundOn()){ return; }
		var n = Math.floor(c.sampleRate * dur), buf = c.createBuffer(1, n, c.sampleRate), d = buf.getChannelData(0);
		for(var i = 0; i < n; i++){ d[i] = (Math.random() * 2 - 1) * Math.pow(1 - i / n, 2); }
		var src = c.createBufferSource(), flt = c.createBiquadFilter(), g = c.createGain();
		src.buffer = buf; flt.type = 'lowpass'; flt.frequency.value = freq; g.gain.value = vol;
		src.connect(flt); flt.connect(g); g.connect(master); src.start();
	}
	var sfx = {
		splat:   function(){ noise(0.25, 900, 0.35); },
		bawk:    function(){ tone('square', 900, 1400, 0.12, 0.06); tone('square', 1400, 700, 0.18, 0.06, 0.12); },
		boing:   function(){ tone('sine', 180, 520, 0.35, 0.2); },
		pffft:   function(){ noise(0.7, 260, 0.5); tone('sawtooth', 110, 70, 0.6, 0.05); },
		zap:     function(){ noise(0.3, 6000, 0.12); tone('sawtooth', 1200, 200, 0.3, 0.05); },
		chime:   function(){ [880, 1109, 1319].forEach(function(f, i){ tone('sine', f, f, 0.35, 0.05, i * 0.09); }); },
		whoops:  function(){ tone('triangle', 600, 150, 0.6, 0.1); }
	};
	var mp3Cache = {};
	function playFile(url){
		if(!url || !soundOn()){ return; }
		try {
			var a = mp3Cache[url] ? mp3Cache[url].cloneNode() : (mp3Cache[url] = new Audio(url));
			a.volume = Math.max(0, Math.min(100, C.vol | 0)) / 100;
			var p = a.play(); if(p && p.catch){ p.catch(function(){}); }
		}
		catch(e){}
	}

	/* ---------- drawing ---------- */
	function layer(){
		var l = document.getElementById('pk-fx');
		if(!l){ l = document.createElement('div'); l.id = 'pk-fx'; document.body.appendChild(l); }
		return l;
	}
	function bit(cls, x, y, text, life){
		var b = document.createElement('span');
		b.className = 'pk-bit ' + cls;
		b.style.left = x + 'px'; b.style.top = y + 'px';
		if(text){ b.textContent = text; }
		layer().appendChild(b);
		setTimeout(function(){ if(b.parentNode){ b.parentNode.removeChild(b); } }, life || 1800);
		return b;
	}
	/* an emoji or an uploaded picture */
	function icon(cls, x, y, it, life){
		var b = bit(cls, x, y, it.img ? '' : it.e, life);
		if(it.img){ var im = document.createElement('img'); im.src = it.img; im.alt = ''; im.className = 'pk-img'; b.appendChild(im); }
		return b;
	}
	function word(p, text, color){ bit('pk-word ' + (color || ''), p.x, p.y - 34, text, 1300); }
	function sparks(p, list, count, reach, life){
		for(var i = 0; i < count; i++){
			var a = rnd(0, 6.283), r = rnd(reach * 0.4, reach), b = bit('pk-spark', p.x, p.y, pick(list), life || 1100);
			b.style.setProperty('--dx', Math.cos(a) * r + 'px');
			b.style.setProperty('--dy', Math.sin(a) * r + 'px');
			b.style.fontSize = rnd(12, 22) + 'px';
		}
	}
	function dots(p, colors, count, reach){
		for(var i = 0; i < count; i++){
			var a = rnd(0, 6.283), r = rnd(reach * 0.3, reach), b = bit('pk-dot', p.x, p.y, '', 1200), s = rnd(4, 9);
			b.style.background = pick(colors);
			b.style.setProperty('--dx', Math.cos(a) * r + 'px');
			b.style.setProperty('--dy', Math.sin(a) * r + 'px');
			b.style.width = s + 'px'; b.style.height = s + 'px';
		}
	}
	function mark(el, cls, ms){
		if(!el){ return; }
		el.classList.remove(cls); void el.offsetWidth; el.classList.add(cls);
		setTimeout(function(){ el.classList.remove(cls); }, ms);
	}
	function centerOf(el){
		if(!el){ return { x: innerWidth / 2, y: innerHeight / 2 }; }
		var r = el.getBoundingClientRect();
		return { x: r.left + Math.min(r.width, 160) / 2, y: r.top + r.height / 2 };
	}
	function visible(el){
		if(!el){ return false; }
		var r = el.getBoundingClientRect();
		return r.width > 0 && r.bottom > 0 && r.top < innerHeight;
	}
	/* the newest visible name of a user: in the chat first, then in the user list */
	function nameEl(uid){
		var av = document.querySelectorAll('#show_chat .chat_avatar[data-id="' + uid + '"], #show_chat li[data-user="' + uid + '"]');
		for(var i = av.length - 1; i >= 0; i--){
			var log = av[i].closest('.chat_log') || av[i], n = log && log.querySelector('.username');
			if(visible(n)){ return n; }
		}
		var ul = document.querySelectorAll('.avtrig[data-id="' + uid + '"]');
		for(var j = ul.length - 1; j >= 0; j--){
			var u = ul[j].querySelector('.username') || ul[j];
			if(visible(u)){ return u; }
		}
		return null;
	}
	function fly(it, from, to, ms, arc, spin, done){
		var b = icon('pk-proj', from.x, from.y, it, ms + 100), t0 = performance.now();
		(function step(now){
			var k = Math.min(1, (now - t0) / ms), x = from.x + (to.x - from.x) * k, y = from.y + (to.y - from.y) * k - Math.sin(k * Math.PI) * arc;
			b.style.transform = 'translate(-50%,-50%) translate(' + (x - from.x) + 'px,' + (y - from.y) + 'px) rotate(' + (spin * k) + 'deg) scale(' + (1.5 - 0.5 * k) + ')';
			if(k < 1){ requestAnimationFrame(step); } else { b.remove(); if(done){ done(); } }
		})(t0);
	}

	/* ---------- built-in pranks ---------- */
	var FX = {
		pie: function(p, el){ sfx.splat(); bit('pk-cream', p.x, p.y, '', 2600); dots(p, ['#fff', '#fef3c7', '#fde68a'], 14, 60); word(p, 'SPLAT!'); mark(el, 'pk-messy', 2600); },
		balloon: function(p, el){ sfx.splat(); sparks(p, ['💧', '💦'], 12, 80); word(p, 'SPLASH!', 'pk-blue'); mark(el, 'pk-wet', 2400); },
		slime: function(p, el){ sfx.splat(); for(var i = 0; i < 6; i++){ bit('pk-drip', p.x + rnd(-40, 40), p.y, '', 2200).style.animationDelay = rnd(0, 0.5) + 's'; } word(p, 'SPLORCH!', 'pk-green'); mark(el, 'pk-slimed', 2600); },
		glitter: function(p, el){ sfx.chime(); dots(p, ['#f472b6', '#facc15', '#38bdf8', '#a78bfa', '#34d399', '#fff'], 46, 120); word(p, 'SO SPARKLY'); mark(el, 'pk-shiny', 3000); },
		chicken: function(p, el){ sfx.bawk(); word(p, 'BAWK!'); sparks(p, ['🪶'], 6, 50); mark(el, 'pk-bonk', 700); },
		banana: function(p, el){ sfx.whoops(); word(p, 'WHOOPS!'); mark(el, 'pk-slip', 1300); },
		cushion: function(p, el){ sfx.pffft(); for(var i = 0; i < 5; i++){ bit('pk-puff', p.x + rnd(-30, 30), p.y + 6, '', 1500).style.animationDelay = (i * 0.08) + 's'; } word(p, 'PFFFT!', 'pk-green'); mark(el, 'pk-hop', 900); },
		glove: function(p, el){ sfx.boing(); bit('pk-glove', p.x - 120, p.y, '🥊', 900).style.setProperty('--reach', '100px'); setTimeout(function(){ word(p, 'BOING!'); mark(el, 'pk-bonk', 700); }, 260); },
		shrink: function(p, el){ sfx.zap(); bit('pk-beam', p.x, p.y, '', 700).style.setProperty('--len', Math.min(p.y, 260) + 'px'); word(p, 'tiny mode'); mark(el, 'pk-tiny', 2600); },
		cloud: function(p, el){ bit('pk-cloud', p.x, p.y - 46, '🌧️', 3200); for(var i = 0; i < 22; i++){ bit('pk-rain', p.x + rnd(-26, 26), p.y - 30, '', 3200).style.animationDelay = rnd(0, 2.4) + 's'; } mark(el, 'pk-gloomy', 3200); },
		zap: function(p, el){ sfx.zap(); bit('pk-bolt', p.x, p.y, '', 600); bit('pk-flash', 0, 0, '', 400); word(p, 'ZZZAP!', 'pk-blue'); mark(el, 'pk-zapped', 1600); },
		disco: function(p, el){ sfx.chime(); bit('pk-ball', p.x, p.y - 40, '🪩', 3200); bit('pk-rays', p.x, p.y - 40, '', 3200); word(p, 'PARTY!'); mark(el, 'pk-rainbow', 3200); },
		hug: function(p, el){ sfx.chime(); sparks(p, ['💗', '🤍', '💕'], 12, 70, 1600); word(p, 'awww', 'pk-pink'); mark(el, 'pk-squeeze', 1000); },
		letter: function(p, el){ sfx.chime(); sparks(p, ['❤️', '💌', '💖'], 14, 90, 1600); word(p, 'xoxo', 'pk-pink'); mark(el, 'pk-blush', 2400); },
		cupcake: function(p){ sfx.chime(); dots(p, ['#f472b6', '#38bdf8', '#facc15', '#34d399'], 30, 80); word(p, 'yum!', 'pk-pink'); },
		bouquet: function(p){ sfx.chime(); for(var i = 0; i < 14; i++){ bit('pk-petal', p.x + rnd(-70, 70), p.y - rnd(40, 90), pick(['🌸', '🌺', '🌷']), 2400).style.animationDelay = rnd(0, 0.6) + 's'; } word(p, 'for you', 'pk-pink'); }
	};
	/* arc height, spin, flight time for pranks that are thrown */
	var THROWN = { pie: [90, 360, 650], balloon: [120, 200, 700], slime: [90, 540, 650], glitter: [70, 720, 600], chicken: [40, -720, 520], cupcake: [100, 200, 700], hug: [60, 0, 700], letter: [80, 20, 800], bouquet: [70, 0, 750] };

	/* ---------- pranks added by the chat owner ---------- */
	function customFx(it, p, el){
		var w = it.w || '';
		if(it.fx === 'rain'){
			for(var i = 0; i < 16; i++){ icon('pk-fall', p.x + rnd(-90, 90), p.y - rnd(80, 160), it, 2200).style.animationDelay = rnd(0, 0.8) + 's'; }
			mark(el, 'pk-bonk', 700);
		}
		else if(it.fx === 'zoom'){
			icon('pk-zoom', innerWidth / 2, innerHeight / 2, it, 1600);
			mark(el, 'pk-hop', 900);
		}
		else if(it.fx === 'shake'){
			icon('pk-pop', p.x, p.y, it, 1400);
			var box = document.getElementById('chat_logs_container') || document.getElementById('show_chat');
			mark(box, 'pk-shake', 700);
			mark(el, 'pk-zapped', 1600);
		}
		else {
			icon('pk-pop', p.x, p.y, it, 1400);
			if(it.img){ for(var j = 0; j < 8; j++){ var s = icon('pk-spark pk-mini', p.x, p.y, it, 1100), a = rnd(0, 6.283), r = rnd(30, 80); s.style.setProperty('--dx', Math.cos(a) * r + 'px'); s.style.setProperty('--dy', Math.sin(a) * r + 'px'); } }
			else { sparks(p, [it.e], 10, 80); }
			mark(el, 'pk-bonk', 700);
		}
		if(w){ word(p, w); }
		if(!it.snd){ sfx.chime(); }
	}

	/* ---------- playing a prank everyone sees ---------- */
	function run(id, k, from, to, back, fromName){
		var it = ITEMS[k];
		if(!it || played[id]){ return; }
		played[id] = 1;
		if(typeof ignored === 'function' && ignored(from)){ return; }
		if(to === myId() && !back){ offerBounce(id, from, fromName); }
		if(calm()){ if(it.snd){ playFile(it.snd); } return; }

		var el = nameEl(to), p = centerOf(el), src = nameEl(from);
		var start = src ? centerOf(src) : { x: p.x < innerWidth / 2 ? innerWidth - 30 : 30, y: innerHeight - 90 };
		var go = function(){
			quietSynth = !!it.snd;
			if(it.snd){ playFile(it.snd); }
			if(it.c){ customFx(it, p, el); } else if(FX[k]){ FX[k](p, el); }
			quietSynth = false;
		};
		if(k === 'banana' && !it.c){
			bit('pk-peel', p.x, p.y + 14, it.e, 1500).style.setProperty('--from', (start.x - p.x) + 'px');
			setTimeout(go, 520);
			return;
		}
		var th = it.c ? (it.fx === 'throw' ? [90, 360, 650] : null) : THROWN[k];
		if(th){ fly(it, start, p, th[2], th[0], th[1], go); } else { go(); }
	}

	function offerBounce(id, from, fromName){
		var old = document.getElementById('pk-back'); if(old){ old.remove(); }
		var n = nameEl(from), who = fromName || (n ? n.textContent : 'Someone');
		var box = document.createElement('div');
		box.id = 'pk-back';
		box.className = themeClass();
		box.innerHTML = '<span class="pk-back-txt"><b>' + esc(who) + '</b> got you!</span><button type="button">↩ Bounce it back</button><i></i>';
		document.body.appendChild(box);
		var timer = setTimeout(function(){ box.classList.add('pk-out'); setTimeout(function(){ box.remove(); }, 300); }, 20000);
		box.querySelector('button').addEventListener('click', function(){
			clearTimeout(timer); box.remove();
			post({ act: 'bounce', id: id }, function(r){ if(!r.ok && typeof callError === 'function'){ callError(r.msg); } });
		});
	}

	/* ---------- the prank picker ---------- */
	function sheetHtml(){
		var name = '', av = document.querySelector('.avtrig[data-id="' + target + '"]');
		if(av){ name = av.getAttribute('data-name') || (av.querySelector('.username') || {}).textContent || ''; }
		var tabs = C.groups.map(function(g){ return '<button type="button" class="pk-tab' + (g[0] === tab ? ' on' : '') + '" data-g="' + esc(g[0]) + '">' + esc(g[1]) + '</button>'; }).join('');
		var tiles = C.list.filter(function(it){ return it.g === tab; }).map(function(it){
			var face = it.img ? '<img src="' + esc(it.img) + '" alt="">' : esc(it.e);
			return '<button type="button" class="pk-tile" data-k="' + esc(it.k) + '"><span class="e">' + face + '</span><span class="n">' + esc(it.n) + '</span></button>';
		}).join('');
		var credit = C.cr ? '<div class="pk-cr">Pranks by <a href="https://github.com/cashifdev" target="_blank" rel="noopener">M Kashif</a> · <a href="https://chatnoregister.com" target="_blank" rel="noopener">chatnoregister.com</a></div>' : '';
		return '<div class="pk-sheet" role="dialog" aria-label="Pranks"><div class="pk-grab"></div>' +
			'<div class="pk-head"><div><div class="pk-title">🤡 Prank ' + esc(name || 'them') + '</div><div class="pk-sub">The whole room sees it, and they get one chance to bounce it back.</div></div>' +
			'<button type="button" class="pk-snd" aria-label="Sound">' + (soundOn() ? '🔊' : '🔇') + '</button><button type="button" class="pk-x" aria-label="Close">×</button></div>' +
			'<div class="pk-tabs" style="grid-template-columns:repeat(' + Math.max(1, C.groups.length) + ',1fr)">' + tabs + '</div><div class="pk-grid">' + tiles + '</div><div class="pk-wait"></div>' + credit + '</div>';
	}
	function tick(){
		var w = document.querySelector('#pk-wrap .pk-wait'); if(!w){ return; }
		var s = secondsLeft();
		w.textContent = s > 0 ? 'Reloading the prank box: ' + s + 's' : 'Ready. Tap a prank to send it.';
		document.querySelectorAll('#pk-wrap .pk-tile').forEach(function(t){ t.classList.toggle('pk-cool', s > 0); });
		if(s > 0){ setTimeout(tick, 500); }
	}
	function openSheet(uid){
		target = uid; closeSheet(true);
		var w = document.createElement('div');
		w.id = 'pk-wrap'; w.className = themeClass(); w.innerHTML = sheetHtml();
		document.body.appendChild(w);
		requestAnimationFrame(function(){ w.classList.add('on'); });
		tick();
	}
	function closeSheet(now){
		var w = document.getElementById('pk-wrap'); if(!w){ return; }
		if(now === true){ w.remove(); return; }
		w.classList.remove('on'); setTimeout(function(){ w.remove(); }, 200);
	}
	function send(k){
		if(busy){ return; }
		if(secondsLeft() > 0){ tick(); return; }
		busy = true;
		post({ act: 'prank', to: target, k: k }, function(r){
			busy = false;
			if(r.ok){ readyAt = Date.now() + 1000 * (r.gap | 0); closeSheet(); return; }
			if(r.left){ readyAt = Date.now() + 1000 * r.left; tick(); }
			if(typeof callError === 'function'){ callError(r.msg || 'That prank fizzled.'); }
		});
	}

	jQuery(document).on('click', '#pk-wrap', function(e){ if(e.target === this){ closeSheet(); } });
	jQuery(document).on('click', '#pk-wrap .pk-x', closeSheet);
	jQuery(document).on('click', '#pk-wrap .pk-snd', function(){ store('pranks_sound', soundOn() ? '0' : '1'); this.textContent = soundOn() ? '🔊' : '🔇'; });
	jQuery(document).on('click', '#pk-wrap .pk-tab', function(){ tab = this.getAttribute('data-g'); document.querySelector('#pk-wrap').innerHTML = sheetHtml(); tick(); });
	jQuery(document).on('click', '#pk-wrap .pk-tile', function(){ send(this.getAttribute('data-k')); });
	jQuery(document).on('keydown', function(e){ if(e.key === 'Escape'){ closeSheet(); } });
	jQuery(document).on('click', '.pk-mi', function(e){
		e.stopPropagation();
		var uid = parseInt(this.getAttribute('data'), 10) || 0;
		if(typeof resetAvMenu === 'function'){ resetAvMenu(); }
		if(uid > 0 && uid !== myId()){ openSheet(uid); }
	});

	/* ---------- watching the chat for new prank lines ---------- */
	function scan(node){
		if(!node || node.nodeType !== 1){ return; }
		var list = node.classList && node.classList.contains('pk-go') ? [node] : (node.querySelectorAll ? node.querySelectorAll('.pk-go') : []);
		for(var i = 0; i < list.length; i++){
			var g = list[i];
			if(g.hasAttribute('data-seen')){ continue; }
			g.setAttribute('data-seen', '1');
			var d = (g.getAttribute('data-p') || '').split('.');
			if(d.length < 6){ continue; }
			var sent = parseInt(d[4], 10) || 0;
			/* skip the history that loads with the page, and anything older than 20 seconds */
			if(Date.now() - loadedAt < 2500 || Math.floor(Date.now() / 1000) + (C.off || 0) - sent > 20){ continue; }
			var line = g.closest ? g.closest('.pk-line') : null, b = line ? line.querySelector('b') : null;
			(function(d, who){ setTimeout(function(){ run(d[0], d[1], parseInt(d[2], 10), parseInt(d[3], 10), d[5] === '1', who); }, 80); })(d, b ? b.textContent : '');
		}
	}
	function boot(){
		if(typeof jQuery === 'undefined'){ return setTimeout(boot, 600); }
		['other', 'staff', 'roomstaff', 'bot'].forEach(function(r){
			var box = document.querySelector('#action_menu .av' + r) || document.querySelector('.av' + r);
			if(box && !box.querySelector('.pk-mi')){
				var m = document.createElement('div');
				m.className = 'avset bmenu avitem pk-mi';
				m.setAttribute('data', ''); m.setAttribute('value', ''); m.setAttribute('data-av', '');
				m.innerHTML = '<i class="fa fa-face-grin-squint-tears" style="color:#f59e0b"></i> Prank';
				box.appendChild(m);
			}
		});
		document.querySelectorAll('.pk-go').forEach(function(g){ g.setAttribute('data-seen', '1'); });
		try { new MutationObserver(function(ms){ ms.forEach(function(m){ m.addedNodes.forEach(scan); }); }).observe(document.body, { childList: true, subtree: true }); } catch(e){}
	}

	window.boomPranks = {
		open: openSheet,
		/* preview used by the admin page: boomPranks.play('pie') */
		play: function(k){ var me = myId(); run('t' + Date.now(), k, me, me, true, ''); }
	};
	if(document.readyState === 'loading'){ document.addEventListener('DOMContentLoaded', boot); } else { boot(); }
})();
