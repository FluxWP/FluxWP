/*!
 * FluxWP phone field (flx-brand). One element for every FluxWP package: country picker with flags and search,
 * national number input, debounced validity tick (libphonenumber, loaded lazily from utils.js).
 * Markup comes from flx_phone_field() (PHP); any element with [data-flx-phone] is enhanced, also ones added later.
 * Value: the hidden input (the field's name) holds the number in E.164 (+4915123456789).
 * Events on the wrapper: "flx-phone:change" { detail: { valid: true|false|null, e164, country } }.
 * Country data and flags: intl-tel-input v25 (MIT). GPL v2 or later.
 */
(function () {
	'use strict';
	var C = window.FLX_PHONE || {};
	var T = C.i18n || {};
	/* [iso2, dial, priority, area codes, national prefix, flag index] */
	var DATA = [["af","93",0,0,"0",3],["ax","358",1,["18","4"],"0",14],["al","355",0,0,"0",6],["dz","213",0,0,"0",60],["as","1",5,["684"],"1",10],["ad","376",0,0,"",1],["ao","244",0,0,"",8],["ai","1",6,["264"],"1",5],["ag","1",7,["268"],"1",4],["ar","54",0,0,"0",9],["am","374",0,0,"0",7],["aw","297",0,0,"",13],["ac","247",0,0,"",0],["au","61",0,["4"],"0",12],["at","43",0,0,"0",11],["az","994",0,0,"0",15],["bs","1",8,["242"],"1",31],["bh","973",0,0,"",22],["bd","880",0,0,"0",18],["bb","1",9,["246"],"1",17],["by","375",0,0,"8",34],["be","32",0,0,"0",19],["bz","501",0,0,"",35],["bj","229",0,0,"",24],["bm","1",10,["441"],"1",26],["bt","975",0,0,"",32],["bo","591",0,0,"0",28],["ba","387",0,0,"0",16],["bw","267",0,0,"",33],["br","55",0,0,"0",30],["io","246",0,0,"",102],["vg","1",11,["284"],"1",232],["bn","673",0,0,"",27],["bg","359",0,0,"0",21],["bf","226",0,0,"",20],["bi","257",0,0,"",23],["kh","855",0,0,"0",113],["cm","237",0,0,"",45],["ca","1",1,["204","226","236","249","250","257","263","289","306","343","354","365","367","368","382","403","416","418","428","431","437","438","450","468","474","506","514","519","548","579","581","584","587","604","613","639","647","672","683","705","709","742","753","778","780","782","807","819","825","867","873","879","902","905","942"],"1",36],["cv","238",0,0,"",50],["bq","599",1,["3","4","7"],"",29],["ky","1",12,["345"],"1",120],["cf","236",0,0,"",39],["td","235",0,0,"",210],["cl","56",0,0,"",44],["cn","86",0,0,"0",46],["cx","61",2,["4","89164"],"0",52],["cc","61",1,["4","89162"],"0",37],["co","57",0,0,"0",47],["km","269",0,0,"",115],["cg","242",0,0,"",40],["cd","243",0,0,"0",38],["ck","682",0,0,"",43],["cr","506",0,0,"",48],["ci","225",0,0,"",42],["hr","385",0,0,"0",94],["cu","53",0,0,"0",49],["cw","599",0,0,"",51],["cy","357",0,0,"",53],["cz","420",0,0,"",54],["dk","45",0,0,"",57],["dj","253",0,0,"",56],["dm","1",13,["767"],"1",58],["do","1",2,["809","829","849"],"1",59],["ec","593",0,0,"0",61],["eg","20",0,0,"0",63],["sv","503",0,0,"",205],["gq","240",0,0,"",86],["er","291",0,0,"0",65],["ee","372",0,0,"",62],["sz","268",0,0,"",208],["et","251",0,0,"0",67],["fk","500",0,0,"",70],["fo","298",0,0,"",72],["fj","679",0,0,"",69],["fi","358",0,["4"],"0",68],["fr","33",0,0,"0",73],["gf","594",0,0,"0",78],["pf","689",0,0,"",171],["ga","241",0,0,"",74],["gm","220",0,0,"",83],["ge","995",0,0,"0",77],["de","49",0,0,"0",55],["gh","233",0,0,"0",80],["gi","350",0,0,"",81],["gr","30",0,0,"",87],["gl","299",0,0,"",82],["gd","1",14,["473"],"1",76],["gp","590",0,0,"0",85],["gu","1",15,["671"],"1",89],["gt","502",0,0,"",88],["gg","44",1,["1481","7781","7839","7911"],"0",79],["gn","224",0,0,"",84],["gw","245",0,0,"",90],["gy","592",0,0,"",91],["ht","509",0,0,"",95],["hn","504",0,0,"",93],["hk","852",0,0,"",92],["hu","36",0,0,"06",96],["is","354",0,0,"",105],["in","91",0,0,"0",101],["id","62",0,0,"0",97],["ir","98",0,0,"0",104],["iq","964",0,0,"0",103],["ie","353",0,0,"0",98],["im","44",2,["1624","74576","7524","7624","7924"],"0",100],["il","972",0,0,"0",99],["it","39",0,["3"],"",106],["jm","1",4,["658","876"],"1",108],["jp","81",0,0,"0",110],["je","44",3,["1534","7509","7700","7797","7829","7937"],"0",107],["jo","962",0,0,"0",109],["kz","7",1,["33","7"],"8",121],["ke","254",0,0,"0",111],["ki","686",0,0,"0",114],["xk","383",0,0,"0",238],["kw","965",0,0,"",119],["kg","996",0,0,"0",112],["la","856",0,0,"0",122],["lv","371",0,0,"",131],["lb","961",0,0,"0",123],["ls","266",0,0,"",128],["lr","231",0,0,"0",127],["ly","218",0,0,"0",132],["li","423",0,0,"0",125],["lt","370",0,0,"0",129],["lu","352",0,0,"",130],["mo","853",0,0,"",144],["mg","261",0,0,"0",138],["mw","265",0,0,"0",152],["my","60",0,0,"0",154],["mv","960",0,0,"",151],["ml","223",0,0,"",141],["mt","356",0,0,"",149],["mh","692",0,0,"1",139],["mq","596",0,0,"0",146],["mr","222",0,0,"",147],["mu","230",0,0,"",150],["yt","262",1,["269","639"],"0",73],["mx","52",0,0,"",153],["fm","691",0,0,"",71],["md","373",0,0,"0",135],["mc","377",0,0,"0",134],["mn","976",0,0,"0",143],["me","382",0,0,"0",136],["ms","1",16,["664"],"1",148],["ma","212",0,["6","7"],"0",133],["mz","258",0,0,"",155],["mm","95",0,0,"0",142],["na","264",0,0,"0",156],["nr","674",0,0,"",165],["np","977",0,0,"0",164],["nl","31",0,0,"0",162],["nc","687",0,0,"",157],["nz","64",0,0,"0",167],["ni","505",0,0,"",161],["ne","227",0,0,"",158],["ng","234",0,0,"0",160],["nu","683",0,0,"",166],["nf","672",0,0,"",159],["kp","850",0,0,"0",117],["mk","389",0,0,"0",140],["mp","1",17,["670"],"1",145],["no","47",0,["4","9"],"",163],["om","968",0,0,"",168],["pk","92",0,0,"0",174],["pw","680",0,0,"",180],["ps","970",0,0,"0",178],["pa","507",0,0,"",169],["pg","675",0,0,"",172],["py","595",0,0,"0",181],["pe","51",0,0,"0",170],["ph","63",0,0,"0",173],["pl","48",0,0,"",175],["pt","351",0,0,"",179],["pr","1",3,["787","939"],"1",177],["qa","974",0,0,"",182],["re","262",0,0,"0",183],["ro","40",0,0,"0",184],["ru","7",0,["33"],"8",186],["rw","250",0,0,"0",187],["ws","685",0,0,"",237],["sm","378",0,0,"",199],["st","239",0,0,"",204],["sa","966",0,0,"0",188],["sn","221",0,0,"",200],["rs","381",0,0,"0",185],["sc","248",0,0,"",190],["sl","232",0,0,"0",198],["sg","65",0,0,"",193],["sx","1",21,["721"],"1",206],["sk","421",0,0,"0",197],["si","386",0,0,"0",195],["sb","677",0,0,"",189],["so","252",0,0,"0",201],["za","27",0,0,"0",241],["kr","82",0,0,"0",118],["ss","211",0,0,"0",203],["es","34",0,0,"",66],["lk","94",0,0,"0",126],["bl","590",1,0,"0",25],["sh","290",0,0,"",194],["kn","1",18,["869"],"1",116],["lc","1",19,["758"],"1",124],["mf","590",2,0,"0",137],["pm","508",0,0,"0",176],["vc","1",20,["784"],"1",230],["sd","249",0,0,"0",191],["sr","597",0,0,"",202],["sj","47",1,["4","79","9"],"",196],["se","46",0,0,"0",192],["ch","41",0,0,"0",41],["sy","963",0,0,"0",207],["tw","886",0,0,"0",222],["tj","992",0,0,"",213],["tz","255",0,0,"0",223],["th","66",0,0,"0",212],["tl","670",0,0,"",215],["tg","228",0,0,"",211],["tk","690",0,0,"",214],["to","676",0,0,"",218],["tt","1",22,["868"],"1",220],["tn","216",0,0,"",217],["tr","90",0,0,"0",219],["tm","993",0,0,"8",216],["tc","1",23,["649"],"1",209],["tv","688",0,0,"",221],["vi","1",24,["340"],"1",233],["ug","256",0,0,"0",225],["ua","380",0,0,"0",224],["ae","971",0,0,"0",2],["gb","44",0,0,"0",75],["us","1",0,0,"1",226],["uy","598",0,0,"0",227],["uz","998",0,0,"",228],["vu","678",0,0,"",235],["va","39",1,["06698","3"],"",229],["ve","58",0,0,"0",231],["vn","84",0,0,"0",234],["wf","681",0,0,"",236],["eh","212",1,["5288","5289","6","7"],"0",64],["ye","967",0,0,"0",239],["zm","260",0,0,"0",242],["zw","263",0,0,"0",243]];
	var BY = {};
	DATA.forEach(function (d) { BY[d[0]] = d; });
	var names = null;
	function name(iso) {
		if (!names) {
			try { names = new Intl.DisplayNames([C.lang || document.documentElement.lang || 'en'], { type: 'region' }); } catch (e) { names = { of: function (x) { return x.toUpperCase(); } }; }
		}
		try { return names.of(iso.toUpperCase()) || iso.toUpperCase(); } catch (e) { return iso.toUpperCase(); }
	}
	function fold(s) {
		return String(s).toLocaleLowerCase(C.lang || undefined).normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/ı/g, 'i');
	}
	function flag(iso) {
		var d = BY[iso];
		return '<span class="flx-ph__flag" style="--flx-ph-o:' + (d ? d[5] : 0) + '" aria-hidden="true"></span>';
	}

	/* ---------- libphonenumber (lazy) ---------- */
	var utilsP = null;
	function utils() {
		if (window.flxPhoneUtils) { return Promise.resolve(window.flxPhoneUtils); }
		if (!utilsP) {
			utilsP = new Promise(function (ok) {
				if (!C.utils) { ok(null); return; }
				var s = document.createElement('script');
				s.src = C.utils;
				s.async = true;
				s.onload = function () { ok(window.flxPhoneUtils || null); };
				s.onerror = function () { ok(null); };
				document.head.appendChild(s);
			});
		}
		return utilsP;
	}

	/* ---------- number helpers ---------- */
	function digits(s) { return String(s || '').replace(/\D+/g, ''); }
	/* National format without the trunk prefix ("0151 …" → "151 …"): the dial code is shown on the button. */
	function bare(iso, s) {
		var np = BY[iso] && BY[iso][4];
		s = String(s || '').trim();
		return np && s.indexOf(np) === 0 ? s.slice(np.length).replace(/^[\s-]+/, '') : s;
	}
	/* Country from an international number (+49…): longest dial code; area codes pick the right +1 / +7 country. */
	function fromIntl(num) {
		var dg = digits(num), best = null;
		for (var len = 4; len >= 1 && !best; len--) {
			var code = dg.slice(0, len), rest = dg.slice(len), hits = DATA.filter(function (d) { return d[1] === code; });
			if (!hits.length) { continue; }
			hits.sort(function (a, b) { return a[2] - b[2]; });
			best = hits[0];
			for (var i = 0; i < hits.length; i++) {
				if (hits[i][3] && hits[i][3].some(function (a) { return rest.indexOf(a) === 0; })) { best = hits[i]; break; }
			}
		}
		return best;
	}
	function e164(iso, raw) {
		var d = BY[iso], s = String(raw || '').trim(), dg = digits(s);
		if (!dg) { return ''; }
		if (s.charAt(0) === '+' ) { return '+' + dg; }
		if (dg.indexOf('00') === 0) { return '+' + dg.slice(2); }
		if (!d) { return '+' + dg; }
		if (d[4] && dg.indexOf(d[4]) === 0) { dg = dg.slice(d[4].length); }
		return '+' + d[1] + dg;
	}

	/* ---------- one field ---------- */
	function Field(el) {
		this.el = el;
		this.btn = el.querySelector('.flx-ph__cc');
		this.num = el.querySelector('.flx-ph__num');
		this.out = el.querySelector('.flx-ph__val');
		this.state = el.querySelector('.flx-ph__state');
		this.msg = el.querySelector('.flx-ph__msg');
		this.pref = String(el.getAttribute('data-preferred') || C.preferred || '').split(',').filter(function (x) { return BY[x]; });
		this.iso = BY[el.getAttribute('data-country')] ? el.getAttribute('data-country') : (this.pref[0] || C.country || 'tr');
		this.timer = 0;
		this.valid = null;
		/* Without JavaScript the visible input carries the name; now the hidden one does. */
		if (this.out) {
			this.out.name = this.out.getAttribute('data-name') || this.num.name;
			this.num.removeAttribute('name');
		}
		var cur = this.out ? this.out.value : '';
		if (cur && cur.charAt(0) === '+') {
			var d = fromIntl(cur);
			if (d) { this.iso = d[0]; this.num.value = digits(cur).slice(d[1].length); }
		}
		this.setCountry(this.iso, true);
		this.bind();
		if (this.num.value) { this.check(true); }
	}
	Field.prototype.setCountry = function (iso, quiet) {
		this.iso = iso;
		var d = BY[iso];
		this.btn.querySelector('.flx-ph__flag').style.setProperty('--flx-ph-o', d[5]);
		this.btn.querySelector('.flx-ph__dial').textContent = '+' + d[1];
		this.btn.setAttribute('aria-label', (T.country || 'Country') + ': ' + name(iso) + ' +' + d[1]);
		var self = this;
		if (!this.el.hasAttribute('data-placeholder')) {
			utils().then(function (u) {
				if (u && self.iso === iso) {
					try { self.num.placeholder = bare(iso, u.getExampleNumber(iso, true, u.numberType.MOBILE)); } catch (e) {}
				}
			});
		}
		if (!quiet) { this.check(true); }
	};
	Field.prototype.bind = function () {
		var self = this;
		this.btn.addEventListener('click', function () { self.open ? self.close() : self.show(); });
		this.btn.addEventListener('keydown', function (e) {
			if (e.key === 'ArrowDown' || e.key === 'ArrowUp') { e.preventDefault(); self.show(); }
		});
		this.num.addEventListener('input', function () {
			var v = self.num.value;
			if (/^\s*(\+|00)/.test(v)) {
				var d = fromIntl(v.replace(/^\s*00/, '+'));
				if (d && d[0] !== self.iso && digits(v.replace(/^\s*00/, '')).length > d[1].length) {
					self.num.value = digits(v.replace(/^\s*00/, '')).slice(d[1].length);
					self.setCountry(d[0], true);
				}
			}
			self.sync();
			self.mark(null);
			clearTimeout(self.timer);
			self.timer = setTimeout(function () { self.check(); }, 450);
		});
		this.num.addEventListener('blur', function () { clearTimeout(self.timer); self.check(); });
		utils(); // warm up as soon as a field is on the page
	};
	Field.prototype.sync = function () {
		var v = e164(this.iso, this.num.value);
		if (this.out) { this.out.value = v; }
		return v;
	};
	Field.prototype.mark = function (ok) {
		this.valid = ok;
		this.el.classList.toggle('is-ok', ok === true);
		this.el.classList.toggle('is-bad', ok === false);
		this.state.innerHTML = ok === true ? '<svg viewBox="0 0 16 16" aria-hidden="true"><path d="M3.2 8.6c1.1.8 2.1 1.8 3 2.9 1.6-2.7 3.6-5 6.1-7"/></svg>' : ok === false ? '<svg viewBox="0 0 16 16" aria-hidden="true"><path d="M4.5 4.5l7 7m0-7l-7 7"/></svg>' : '';
		this.state.setAttribute('title', ok === true ? (T.ok || '') : ok === false ? (T.bad || '') : '');
		if (this.msg) {
			this.msg.textContent = ok === false ? (T.bad || 'Please check the number.') : '';
			this.msg.hidden = ok !== false;
		}
		this.num.setAttribute('aria-invalid', ok === false ? 'true' : 'false');
		this.el.dispatchEvent(new CustomEvent('flx-phone:change', { bubbles: true, detail: { valid: ok, e164: this.out ? this.out.value : '', country: this.iso } }));
	};
	Field.prototype.check = function (silent) {
		var self = this, v = this.sync(), iso = this.iso;
		if (!digits(this.num.value)) { this.mark(null); return; }
		utils().then(function (u) {
			if (self.iso !== iso) { return; }
			var ok;
			if (u) {
				try { ok = u.isValidNumber(v, iso); } catch (e) { ok = null; }
			}
			if (ok === undefined || ok === null) {
				var n = digits(v).length;
				ok = n >= 8 && n <= 15;
			}
			if (ok && u) {
				try { self.num.value = bare(iso, u.formatNumber(v, iso, u.numberFormat.NATIONAL)); self.sync(); } catch (e) {}
			}
			/* While typing (silent) a not-yet-valid number shows nothing; on blur / after the pause it shows ✗. */
			self.mark(ok ? true : (silent ? null : false));
		});
	};

	/* ---------- country list ---------- */
	Field.prototype.show = function () {
		var self = this;
		closeAll();
		if (!this.pop) {
			this.pop = document.createElement('div');
			this.pop.className = 'flx-ph__pop';
			this.pop.innerHTML = '<label class="flx-ph__search"><svg viewBox="0 0 20 20" aria-hidden="true"><circle cx="8.5" cy="8.5" r="5.5"/><path d="M13 13l4 4"/></svg><input type="search" autocomplete="off" placeholder="' + (T.search || 'Search country') + '" aria-label="' + (T.search || 'Search country') + '"></label><ul class="flx-ph__list" role="listbox" tabindex="-1"></ul>';
			this.el.appendChild(this.pop);
			this.q = this.pop.querySelector('input');
			this.ul = this.pop.querySelector('ul');
			this.q.addEventListener('input', function () { self.fill(); });
			this.q.addEventListener('keydown', function (e) { self.key(e); });
			this.ul.addEventListener('click', function (e) {
				var li = e.target.closest('li[data-iso]');
				if (li) { self.pick(li.getAttribute('data-iso')); }
			});
		}
		this.q.value = '';
		this.fill();
		this.pop.hidden = false;
		this.open = true;
		this.el.classList.add('is-open');
		this.btn.setAttribute('aria-expanded', 'true');
		this.q.focus();
		var on = this.ul.querySelector('.is-on');
		if (on) { on.scrollIntoView({ block: 'nearest' }); }
	};
	Field.prototype.close = function (focus) {
		if (!this.open) { return; }
		this.open = false;
		this.pop.hidden = true;
		this.el.classList.remove('is-open');
		this.btn.setAttribute('aria-expanded', 'false');
		if (focus) { this.btn.focus(); }
	};
	Field.prototype.fill = function () {
		var q = fold(this.q.value.trim()), qd = digits(q), self = this, html = '', rows = [];
		if (!this.sorted) {
			this.sorted = DATA.slice().sort(function (a, b) { return name(a[0]).localeCompare(name(b[0]), C.lang || undefined); });
		}
		var match = function (d) { return !q || fold(name(d[0])).indexOf(q) === 0 || fold(name(d[0])).indexOf(' ' + q) > -1 || d[0] === q || (qd && d[1].indexOf(qd) === 0); };
		var row = function (d) {
			rows.push(d[0]);
			return '<li role="option" data-iso="' + d[0] + '" class="' + (d[0] === self.iso ? 'is-on' : '') + '" aria-selected="' + (d[0] === self.iso) + '">' + flag(d[0]) + '<span class="flx-ph__name">' + name(d[0]) + '</span><span class="flx-ph__code">+' + d[1] + '</span></li>';
		};
		if (!q && this.pref.length) {
			this.pref.forEach(function (iso) { html += row(BY[iso]); });
			html += '<li class="flx-ph__sep" role="separator"></li>';
		}
		this.sorted.forEach(function (d) { if (match(d) && (q || self.pref.indexOf(d[0]) < 0)) { html += row(d); } });
		this.ul.innerHTML = html || '<li class="flx-ph__none">' + (T.none || 'No country found') + '</li>';
		this.hi = -1;
		this.rows = rows;
	};
	Field.prototype.key = function (e) {
		var items = this.ul.querySelectorAll('li[data-iso]');
		if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
			e.preventDefault();
			this.hi = Math.max(0, Math.min(items.length - 1, this.hi + (e.key === 'ArrowDown' ? 1 : -1)));
			items.forEach(function (li, i) { li.classList.toggle('is-hi', i === this.hi); }, this);
			if (items[this.hi]) { items[this.hi].scrollIntoView({ block: 'nearest' }); }
		} else if (e.key === 'Enter') {
			e.preventDefault();
			var li = items[this.hi >= 0 ? this.hi : 0];
			if (li) { this.pick(li.getAttribute('data-iso')); }
		} else if (e.key === 'Escape') {
			e.preventDefault();
			this.close(true);
		} else if (e.key === 'Tab') {
			this.close();
		}
	};
	Field.prototype.pick = function (iso) {
		this.close();
		this.setCountry(iso);
		this.num.focus();
	};

	var fields = [];
	function closeAll(except) {
		fields.forEach(function (f) { if (f !== except) { f.close(); } });
	}
	document.addEventListener('click', function (e) {
		fields.forEach(function (f) { if (f.open && !f.el.contains(e.target)) { f.close(); } });
	});

	function init(root) {
		(root || document).querySelectorAll('[data-flx-phone]:not([data-flx-phone-on])').forEach(function (el) {
			el.setAttribute('data-flx-phone-on', '1');
			var f = new Field(el);
			el.flxPhone = f;
			fields.push(f);
		});
	}
	window.flxPhone = { init: init, toE164: e164, countryOf: function (n) { var d = fromIntl(n); return d ? d[0] : ''; }, validate: function (n, iso) { return utils().then(function (u) { try { return u ? u.isValidNumber(n, iso) : null; } catch (e) { return null; } }); } };
	if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', function () { init(); }); } else { init(); }
	if (window.MutationObserver) {
		new MutationObserver(function (ms) {
			for (var i = 0; i < ms.length; i++) { if (ms[i].addedNodes.length) { init(); return; } }
		}).observe(document.documentElement, { childList: true, subtree: true });
	}
}());
