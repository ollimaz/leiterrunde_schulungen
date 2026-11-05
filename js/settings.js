(function(){
const t = (s, v) => window.t('leiterrunde_schulungen', s, v, undefined, { escape: false, sanitize: false });
(async () => {
	const root = document.getElementById('lr-schulungen-settings');
	if (!root) return;
	const base = OC.generateUrl('/apps/leiterrunde_schulungen/api');
	const req = async (p, o = {}) => (await fetch(base + p, { headers: { requesttoken: OC.requestToken }, ...o })).json();
	const s = await req('/settings');
	const form = document.getElementById('lr-rules-form');
	const num = (id, v, min) => { const i = document.createElement('input'); i.type = 'number'; i.min = min; i.value = v; i.id = 'lrr-' + id; return i; };
	const warn = num('warn_days', s.warn_days, 0), hrs = num('hours_required', s.hours_required, 0), win = num('hours_window_months', s.hours_window_months, 0);
	form.append(
		Object.assign(document.createElement('label'), { textContent: t('Warning period (days)') }), warn,
		Object.assign(document.createElement('label'), { textContent: t('Required training hours') }), hrs,
		Object.assign(document.createElement('label'), { textContent: t('Hours account period (months)') }), win);
	const scope = new Set((s.scope_statuses || '').split(',').map(x => x.trim()));
	const scopeBoxes = [['aktiv', t('Active')], ['nwl', t('NWL')], ['inaktiv', t('Inactive')], ['ehemalig', t('Former')]].map(([k, l]) => {
		const c = document.createElement('input'); c.type = 'checkbox'; c.className = 'checkbox'; c.checked = scope.has(k);
		const la = document.createElement('label'); la.append(c, ' ' + l); la.style.marginRight = '10px';
		return [k, la, c];
	});
	form.append(Object.assign(document.createElement('p'), {}, document.createTextNode(t('Checked person statuses'))));
	form.append(...scopeBoxes.map(([, l]) => l));
	const msg = document.createElement('span'); msg.style.marginLeft = '8px';
	document.getElementById('lr-rules-save').after(msg);
	document.getElementById('lr-rules-save').addEventListener('click', async () => {
		const body = { warn_days: warn.value, hours_required: hrs.value, hours_window_months: win.value,
			scope_statuses: scopeBoxes.filter(([, , c]) => c.checked).map(([k]) => k).join(',') };
		const r = await req('/settings', { method: 'PUT', headers: { requesttoken: OC.requestToken, 'Content-Type': 'application/json' }, body: JSON.stringify(body) });
		msg.textContent = r.error ? ('Error: ' + r.error) : t('Saved');
	});
})();

})();
