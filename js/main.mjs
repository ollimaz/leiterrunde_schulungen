/* SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
const { mountApp, h, badge, toast, formatDate, put, lrPageHeader, field, checkField, formActions, emptyState } = window.LeiterrundeUI;
const { t: _t, n: _n } = window.LeiterrundeUI.l10n('leiterrunde_schulungen');

const APP = 'leiterrunde_schulungen';

let TYPES = [];
async function loadTypes(api) { TYPES = await api('/training-types'); }

const NAV = [
	{ route: '#/uebersicht', label: _t('Overview'), cap: 'training.check', icon: 'dashboard' },
	{ route: '#/nachweise', label: _t('Training records'), cap: null, icon: 'clipboard' },
	{ route: '#/fz', label: _t('Certificates of conduct'), cap: 'fz.read', icon: 'shield-check' },
	{ route: '#/schulungsarten', label: _t('Training types'), cap: 'training.types.write', icon: 'list' },
	{ route: '#/mein', label: _t('My training status'), cap: null, icon: 'account' },
];

async function viewUebersicht(v, ME, ctx) {
	if (!ME.caps['training.check']) return viewMein(v, ME, ctx);
	const { api } = ctx;
	const matrix = await api('/training/status');
	const typeNames = matrix.length ? matrix[0].types.map(t => t.name) : [];
	put(v, lrPageHeader({ kicker: _t('Leiterrunde · Training'), title: _t('Training status') }),
		matrix.length ? h('div', { class: 'lr-tablewrap' }, h('table', { class: 'lr-table' },
			h('thead', {}, h('tr', {},
				h('th', {}, _t('Person')), ...typeNames.map(n => h('th', {}, n)),
				h('th', {}, _t('Hours')), h('th', {}, _t('CoC')), h('th', {}, _t('Overall')))),
			h('tbody', {}, ...matrix.map(row => h('tr', {},
				h('td', {}, h('a', { href: OC.generateUrl('/apps/leiterrunde/') + '#/adressen/' + row.person.id }, row.person.last_name + ', ' + row.person.first_name)),
				...row.types.map(t => h('td', { title: t.expires ? formatDate(t.expires) : '' }, badge(t.state))),
				h('td', { title: row.hours.hours + '/' + row.hours.required + ' h' }, badge(row.hours.state)),
				h('td', {}, badge(row.fz.state)),
				h('td', {}, badge(row.overall)))))))
			: emptyState(_t('No people found.')));
}

async function viewNachweise(v, ME, { api, coreApi }) {
	await loadTypes(api);
	const records = await api('/training-records');
	const persons = await coreApi('/persons/options').catch(() => []);
	const name = id => { const p = persons.find(x => x.id === id); return p ? p.last_name + ', ' + p.first_name : '#' + id; };
	const canApprove = ME.caps['training.records.approve'];
	const isOwn = r => ME.person && r.person_id === ME.person.id && r.created_by === ME.uid;
	const canMutate = r => canApprove || (isOwn(r) && r.status === 'pending');
	const anyMutate = records.some(canMutate);
	const showActions = canApprove || anyMutate;
	const filter = h('select', {}, h('option', { value: '' }, _t('All')), h('option', { value: 'pending' }, _t('To review')));
	const tbody = h('tbody', {});
	const empty = emptyState(_t('No training records.'));
	const render = () => {
		tbody.textContent = '';
		const f = filter.value;
		const shown = records.filter(r => !f || r.status === f);
		empty.style.display = shown.length ? 'none' : '';
		for (const r of shown) {
			tbody.append(h('tr', {},
				h('td', {}, name(r.person_id)),
				h('td', {}, (TYPES.find(t => t.id === r.type_id) || {}).name || r.type_id),
				h('td', {}, formatDate(r.attended_date)), h('td', {}, r.hours), h('td', {}, r.title || ''),
				h('td', {}, badge(r.status)),
				h('td', {}, r.file_id ? h('a', { href: OC.generateUrl('/apps/' + APP + '/api/training-records/' + r.id + '/file'), target: '_blank' }, r.file_name || _t('File')) : '–'),
				showActions ? h('td', { class: 'lr-actions' },
					canApprove && r.status === 'pending' ? h('button', { class: 'lr-btn', onclick: async () => { await api('/training-records/' + r.id + '/approve', { method: 'POST', json: {} }); toast(_t('Approved'), false); } }, _t('Approve')) : null,
					canApprove && r.status === 'pending' ? h('button', { class: 'lr-btn quiet', onclick: async () => { await api('/training-records/' + r.id + '/reject', { method: 'POST', json: {} }); toast(_t('Rejected'), false); } }, _t('Reject')) : null,
					canMutate(r) ? h('button', { class: 'lr-btn secondary', onclick: () => recordForm(v, ME, { api, coreApi }, r) }, _t('Edit')) : null,
					canMutate(r) ? h('button', { class: 'lr-btn quiet', onclick: async () => {
						if (!confirm(_t('Delete this record?'))) return;
						try { await api('/training-records/' + r.id, { method: 'DELETE' }); toast(_t('Deleted'), false); viewNachweise(v, ME, { api, coreApi }); }
						catch (e) { toast(e.message); }
					} }, _t('Delete')) : null) : null));
		}
	};
	filter.addEventListener('change', render);
	render();
	put(v, lrPageHeader({ kicker: _t('Leiterrunde · Training'), title: _t('Training records'),
			actions: [h('button', { class: 'lr-btn', onclick: () => location.hash = '#/mein' }, _t('Submit record'))] }),
		h('div', { class: 'lr-search' }, filter),
		h('table', { class: 'lr-table' },
			h('thead', {}, h('tr', {},
				h('th', {}, _t('Person')), h('th', {}, _t('Type')), h('th', {}, _t('Date')), h('th', {}, _t('hrs')),
				h('th', {}, _t('Title')), h('th', {}, _t('Status')), h('th', {}, _t('File')), showActions ? h('th', {}, _t('Action')) : null)),
			tbody),
		empty);
}

function recordForm(v, ME, { api, coreApi }, r) {
	const typeSel = h('select', {}, ...TYPES.map(t => h('option', { value: t.id, selected: t.id === r.type_id }, t.name)));
	const date = h('input', { type: 'date', value: r.attended_date });
	const hours = h('input', { type: 'number', step: '0.5', min: 0, value: r.hours });
	const titleI = h('input', { value: r.title || '' });
	const notes = h('input', { value: r.notes || '' });
	put(v, lrPageHeader({ kicker: _t('Leiterrunde · Training'), title: _t('Edit record') }),
		h('div', { class: 'lr-form lr-formgrid' },
			field(_t('Training type'), typeSel, 4), field(_t('Date'), date, 3),
			field(_t('Hours'), hours, 2), field(_t('Title'), titleI, 6), field(_t('Notes'), notes, 12),
			formActions(
				h('button', { class: 'lr-btn', onclick: async () => {
					try { await api('/training-records/' + r.id, { method: 'PUT', json: { type_id: +typeSel.value, attended_date: date.value, hours: +hours.value || 0, title: titleI.value || null, notes: notes.value || null } }); toast(_t('Saved'), false); viewNachweise(v, ME, { api, coreApi }); }
					catch (e) { toast(e.message); }
				} }, _t('Save')),
				h('button', { class: 'lr-btn secondary', onclick: () => viewNachweise(v, ME, { api, coreApi }) }, _t('Cancel')))));
}

async function viewTypen(v, ME, { api }) {
	await loadTypes(api);
	const canWrite = ME.caps['training.types.write'];
	const typeForm = (t) => {
		const name = h('input', { value: t ? t.name : '' });
		const validity = h('input', { type: 'number', min: 0, value: t ? t.validity_months : 0 });
		const mkCheck = (x) => { const c = h('input', { type: 'checkbox' }); c.checked = !!x; return c; };
		const ch = mkCheck(t ? t.counts_hours : false), rq = mkCheck(t ? t.required : false),
			re = mkCheck(t ? t.required_for_event : false), ac = mkCheck(t ? t.active : true);
		const sort = h('input', { type: 'number', value: t ? t.sort : 0 });
		v.textContent = '';
		const save = h('button', { class: 'lr-btn', onclick: async () => {
			if (!name.value.trim()) { toast(_t('Please enter a name')); return; }
			const body = { name: name.value, validity_months: +validity.value, sort: +sort.value, counts_hours: ch.checked, required: rq.checked, required_for_event: re.checked, active: ac.checked };
			try {
				if (t) await api('/training-types/' + t.id, { method: 'PUT', json: body });
				else await api('/training-types', { method: 'POST', json: body });
				toast(_t('Saved'), false); viewTypen(v, ME, { api });
			} catch (e) { toast(e.message); }
		} }, _t('Save'));
		const cancel = h('button', { class: 'lr-btn secondary', onclick: () => viewTypen(v, ME, { api }) }, _t('Cancel'));
		put(v, lrPageHeader({ kicker: _t('Leiterrunde · Training'), title: t ? _t('Edit type') : _t('New training type') }),
			h('div', { class: 'lr-form lr-formgrid' },
				field(_t('Name'), name, 6),
				field(_t('Validity'), validity, 3, _t('Months, 0 = unlimited')),
				field(_t('Sort order'), sort, 3),
				h('div', { class: 'lr-f col-12' },
					checkField(_t('Counts as hours'), ch), checkField(_t('Required for everyone'), rq),
					checkField(_t('Required for events'), re), checkField(_t('Active'), ac)),
				formActions(save, cancel)));
	};
	put(v, lrPageHeader({ kicker: _t('Leiterrunde · Training'), title: _t('Training types'),
			actions: canWrite ? [h('button', { class: 'lr-btn', onclick: () => typeForm(null) }, _t('New type'))] : [] }),
		h('table', { class: 'lr-table' },
			h('thead', {}, h('tr', {}, h('th', {}, _t('Name')), h('th', {}, _t('Validity')), h('th', {}, _t('Hours')), h('th', {}, _t('Required')), h('th', {}, _t('Event-required')), h('th', {}, _t('Status')), h('th', {}, ''))),
			h('tbody', {}, ...TYPES.map(t => h('tr', {},
				h('td', {}, t.name),
				h('td', {}, t.validity_months === 0 ? _t('unlimited') : _n('%n month', '%n months', t.validity_months)),
				h('td', {}, t.counts_hours ? _t('yes') : '–'), h('td', {}, t.required ? _t('yes') : '–'),
				h('td', {}, t.required_for_event ? _t('yes') : '–'), h('td', {}, t.active ? _t('Active') : _t('Inactive')),
				h('td', { class: 'lr-actions' }, canWrite ? h('button', { class: 'lr-btn secondary', onclick: () => typeForm(t) }, _t('Edit')) : null,
					canWrite ? h('button', { class: 'lr-btn quiet', onclick: async () => {
						if (!confirm(_t('Delete type "{name}"?', { name: t.name }))) return;
						try { await api('/training-types/' + t.id, { method: 'DELETE' }); toast(_t('Deleted'), false); viewTypen(v, ME, { api }); }
						catch (e) { toast(e.message); }
					} }, _t('Delete')) : null))))));
}

async function viewFz(v, ME, { api, coreApi }) {
	const rows = await api('/fz');
	const persons = await coreApi('/persons/options').catch(() => []);
	const name = id => { const p = persons.find(x => x.id === id); return p ? p.last_name + ', ' + p.first_name : '#' + id; };
	const canWrite = ME.caps['fz.write'];
	let pid = null;
	const pidEl = window.LeiterrundeUI.personPicker({ persons, value: pid, onChange: (id) => { pid = id; } });
	const issued = h('input', { type: 'date', max: new Date().toISOString().slice(0, 10) });
	const valid = h('select', {}, ...[36, 60].map(n => h('option', { value: n }, _n('%n month', '%n months', n))));
	const add = h('button', { class: 'lr-btn', onclick: async () => {
		if (!pid) { toast(_t('Please select a person')); return; }
		if (!issued.value) { toast(_t('Please enter the issue date')); return; }
		try { await api('/fz', { method: 'POST', json: { person_id: +pid, issued_at: issued.value, valid_months: +valid.value } }); toast(_t('Saved'), false); viewFz(v, ME, { api, coreApi }); }
		catch (e) { toast(e.message); }
	} }, _t('Add'));
	put(v, lrPageHeader({ kicker: _t('Leiterrunde · Training'), title: _t('Certificates of conduct') }),
		canWrite ? h('div', { class: 'lr-form lr-formgrid' },
			field(_t('Person'), pidEl, 6), field(_t('Issued on'), issued, 3), field(_t('Validity'), valid, 3),
			formActions(add)) : null,
		h('table', { class: 'lr-table' },
			h('thead', {}, h('tr', {}, h('th', {}, _t('Person')), h('th', {}, _t('Issued')), h('th', {}, _t('Validity')), h('th', {}, _t('Notes')), canWrite ? h('th', {}, '') : null)),
			h('tbody', {}, ...rows.map(r => h('tr', {},
				h('td', {}, name(r.person_id)), h('td', {}, formatDate(r.issued_at)),
				h('td', {}, _n('%n month', '%n months', r.valid_months)), h('td', {}, r.notes || ''),
				canWrite ? h('td', { class: 'lr-actions' },
					h('button', { class: 'lr-btn secondary', onclick: () => fzForm(v, ME, { api, coreApi }, r) }, _t('Edit')),
					h('button', { class: 'lr-btn quiet', onclick: async () => {
						if (!confirm(_t('Delete certificate for {name}?', { name: name(r.person_id) }))) return;
						try { await api('/fz/' + r.id, { method: 'DELETE' }); toast(_t('Deleted'), false); viewFz(v, ME, { api, coreApi }); }
						catch (e) { toast(e.message); }
					} }, _t('Delete'))) : null)))));
}

function fzForm(v, ME, { api, coreApi }, r) {
	const issued = h('input', { type: 'date', max: new Date().toISOString().slice(0, 10), value: r.issued_at });
	const valid = h('select', {}, ...[36, 60].map(n => h('option', { value: n, selected: r.valid_months === n }, _n('%n month', '%n months', n))));
	const notes = h('input', { value: r.notes || '' });
	put(v, lrPageHeader({ kicker: _t('Leiterrunde · Training'), title: _t('Edit certificate of conduct') }),
		h('div', { class: 'lr-form lr-formgrid' },
			field(_t('Issued on'), issued, 3), field(_t('Validity'), valid, 3), field(_t('Notes'), notes, 6),
			formActions(
				h('button', { class: 'lr-btn', onclick: async () => {
					if (!issued.value) { toast(_t('Please enter the issue date')); return; }
					try { await api('/fz/' + r.id, { method: 'PUT', json: { issued_at: issued.value, valid_months: +valid.value, notes: notes.value || null } }); toast(_t('Saved'), false); viewFz(v, ME, { api, coreApi }); }
					catch (e) { toast(e.message); }
				} }, _t('Save')),
				h('button', { class: 'lr-btn secondary', onclick: () => viewFz(v, ME, { api, coreApi }) }, _t('Cancel')))));
}

async function viewMein(v, ME, { api }) {
	if (!ME.person) {
		put(v, lrPageHeader({ kicker: _t('Leiterrunde · Training'), title: _t('My training status') }), h('p', {}, _t('Your account is not linked to a person.')));
		return;
	}
	const p = ME.person;
	const st = await api('/persons/' + p.id + '/training-status');
	await loadTypes(api);
	const typeSel = h('select', {}, ...TYPES.map(t => h('option', { value: t.id }, t.name)));
	const date = h('input', { type: 'date' });
	const hours = h('input', { type: 'number', step: '0.5', min: 0, value: 0 });
	const titleI = h('input', {});
	const file = h('input', { type: 'file', accept: '.pdf,.jpg,.jpeg,.png,.heic' });
	put(v, lrPageHeader({ kicker: _t('Leiterrunde · Training'), title: _t('My training status') }),
		h('table', { class: 'lr-table' },
			h('thead', {}, h('tr', {}, h('th', {}, _t('Check')), h('th', {}, _t('Status')), h('th', {}, _t('Valid until')))),
			h('tbody', {},
				...st.types.map(t => h('tr', {}, h('td', {}, t.name), h('td', {}, badge(t.state)), h('td', {}, t.expires ? formatDate(t.expires) : '–'))),
				h('tr', {}, h('td', {}, _t('Hours balance ({hours}/{required} h)', { hours: st.hours.hours, required: st.hours.required })), h('td', {}, badge(st.hours.state)), h('td', {}, '–')),
				h('tr', {}, h('td', {}, _t('Certificate of conduct')), h('td', {}, badge(st.fz.state)), h('td', {}, '–')))),
		h('h2', { class: 'lr-section' }, _t('Submit record')),
		h('div', { class: 'lr-form lr-formgrid' },
			field(_t('Training type'), typeSel, 4), field(_t('Date'), date, 3),
			field(_t('Hours'), hours, 2), field(_t('Title'), titleI, 6),
			field(_t('File (record)'), file, 6),
			formActions(h('button', { class: 'lr-btn', onclick: async () => {
				const fd = new FormData();
				fd.append('person_id', p.id); fd.append('type_id', typeSel.value);
				fd.append('attended_date', date.value); fd.append('hours', hours.value || 0);
				fd.append('title', titleI.value);
				if (file.files[0]) fd.append('file', file.files[0]);
				try { await api('/training-records', { method: 'POST', body: fd }); toast(_t('Record submitted'), false); }
				catch (e) { toast(e.message); }
			} }, _t('Submit')))));
}

mountApp({
	appId: APP,
	title: _t('Training'),
	nav: NAV,
	routes: {
		'#/uebersicht': viewUebersicht,
		'#/nachweise': viewNachweise,
		'#/fz': viewFz,
		'#/schulungsarten': viewTypen,
		'#/mein': viewMein,
	},
	defaultRoute: '#/uebersicht',
});
