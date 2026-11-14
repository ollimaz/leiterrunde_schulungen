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
	{ route: '#/gruppen', label: _t('Group training'), cap: 'training.records.submitOther', icon: 'account-group' },
	{ route: '#/fz', label: _t('Certificates of conduct'), cap: 'fz.read', icon: 'shield-check' },
	{ route: '#/schulungsarten', label: _t('Training types'), cap: 'training.types.write', icon: 'list' },
	{ route: '#/mein', label: _t('My training status'), cap: null, icon: 'account' },
];

function trainingDetails(status, isHours = false) {
	const records = status.records || [];
	return h('details', { class: 'lr-details' },
		h('summary', {}, isHours
			? _t('Counted training ({hours}/{required} h)', { hours: status.hours, required: status.required })
			: _t('Valid training records')),
		isHours ? h('p', {}, _t('Hours drop below {required} h on', { required: status.required }) + ': ' + (status.valid_until ? formatDate(status.valid_until) : '–')) : null,
		records.length ? h('table', { class: 'lr-table' },
			h('thead', {}, h('tr', {}, h('th', {}, _t('Title')), h('th', {}, _t('Date')), h('th', {}, _t('Hours')), h('th', {}, _t('Valid until')))),
			h('tbody', {}, ...records.map(r => h('tr', {},
				h('td', {}, r.title || '–'), h('td', {}, formatDate(r.attended_date)),
				h('td', {}, r.hours), h('td', {}, (isHours ? r.valid_until : r.expires) ? formatDate(isHours ? r.valid_until : r.expires) : _t('unlimited'))))))
			: h('p', { class: 'lr-muted' }, _t('No qualifying training records.')));
}

async function viewUebersicht(v, ME, ctx) {
	if (!ME.caps['training.check']) return viewMein(v, ME, ctx);
	const { api } = ctx;
	const matrix = await api('/training/status');
	const typeNames = matrix.length ? matrix[0].types.map(t => t.name) : [];
	put(v, lrPageHeader({ kicker: _t('Leiterrunde · Training'), title: _t('Training status') }),
		matrix.length ? h('div', { class: 'lr-tablewrap' }, h('table', { class: 'lr-table lr-matrix' },
			h('thead', {}, h('tr', {},
				h('th', {}, _t('Person')), ...typeNames.map(n => h('th', {}, n)),
				h('th', {}, _t('Hours')), h('th', {}, _t('CoC')), h('th', {}, _t('Overall')))),
			h('tbody', {}, ...matrix.map(row => h('tr', {},
				h('td', {}, h('a', { href: OC.generateUrl('/apps/leiterrunde/') + '#/adressen/' + row.person.id }, row.person.last_name + ', ' + row.person.first_name)),
				...row.types.map(t => h('td', {}, badge(t.state), t.expires ? h('div', { class: 'lr-muted' }, formatDate(t.expires)) : null, trainingDetails(t))),
				h('td', {}, badge(row.hours.state), trainingDetails(row.hours, true)), 
				h('td', {}, badge(row.fz.state), row.fz.expires ? h('div', { class: 'lr-muted' }, formatDate(row.fz.expires)) : null),
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
	const canMutate = r => canApprove || (r.status === 'pending' && (isOwn(r)
		|| (r.session_id && r.created_by === ME.uid && ME.caps['training.records.submitOther'])));
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
					canApprove && r.status === 'pending' ? h('button', { class: 'lr-btn', onclick: async () => { try { await api('/training-records/' + r.id + '/approve', { method: 'POST', json: {} }); toast(_t('Approved'), false); viewNachweise(v, ME, { api, coreApi }); } catch (e) { toast(e.message); } } }, _t('Approve')) : null,
					canApprove && r.status === 'pending' ? h('button', { class: 'lr-btn quiet', onclick: async () => { try { await api('/training-records/' + r.id + '/reject', { method: 'POST', json: {} }); toast(_t('Rejected'), false); viewNachweise(v, ME, { api, coreApi }); } catch (e) { toast(e.message); } } }, _t('Reject')) : null,
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
	if (r.session_id) {
		for (const input of [typeSel, date, hours, titleI]) input.disabled = true;
	}
	const fileRow = h('div', { class: 'lr-actions' },
		r.file_id ? h('a', { href: OC.generateUrl('/apps/' + APP + '/api/training-records/' + r.id + '/file'), target: '_blank' }, r.file_name || _t('File')) : h('span', { class: 'lr-muted' }, _t('No file')));
	const fileInput = h('input', { type: 'file', style: 'display:none' });
	const uploadBtn = h('button', { class: 'lr-btn secondary', onclick: () => fileInput.click() }, r.file_id ? _t('Replace file') : _t('Upload file'));
	fileInput.addEventListener('change', async () => {
		if (!fileInput.files[0]) return;
		const fd = new FormData();
		fd.append('file', fileInput.files[0]);
		try { const u = await api('/training-records/' + r.id + '/file', { method: 'POST', body: fd }); toast(_t('File uploaded'), false); recordForm(v, ME, { api, coreApi }, u); }
		catch (e) { toast(e.message); }
	});
	fileRow.append(fileInput, uploadBtn);
	if (r.file_id) fileRow.append(h('button', { class: 'lr-btn quiet', onclick: async () => {
		if (!confirm(_t('Remove the file from this record?'))) return;
		try { const u = await api('/training-records/' + r.id + '/file', { method: 'DELETE' }); toast(_t('File removed'), false); recordForm(v, ME, { api, coreApi }, u); }
		catch (e) { toast(e.message); }
	} }, _t('Remove file')));
	put(v, lrPageHeader({ kicker: _t('Leiterrunde · Training'), title: _t('Edit record') }),
		h('div', { class: 'lr-form lr-formgrid' },
			field(_t('Training type'), typeSel, 4), field(_t('Date'), date, 3),
			field(_t('Hours'), hours, 2), field(_t('Title'), titleI, 6), field(_t('Notes'), notes, 12),
			field(_t('File (record)'), fileRow, 12),
			formActions(
				h('button', { class: 'lr-btn', onclick: async () => {
					try { await api('/training-records/' + r.id, { method: 'PUT', json: { type_id: +typeSel.value, attended_date: date.value, hours: +hours.value || 0, title: titleI.value || null, notes: notes.value || null } }); toast(_t('Saved'), false); viewNachweise(v, ME, { api, coreApi }); }
					catch (e) { toast(e.message); }
				} }, _t('Save')),
				h('button', { class: 'lr-btn secondary', onclick: () => viewNachweise(v, ME, { api, coreApi }) }, _t('Cancel')))));
}

async function viewGruppen(v, ME, ctx) {
	const { api, coreApi } = ctx;
	if (!ME.caps['training.records.submitOther'] && !ME.caps['training.records.readAll']) return viewMein(v, ME, ctx);
	await loadTypes(api);
	const tail = location.hash.split('/').pop();
	if (tail === 'neu') return sessionForm(v, ME, ctx);
	if (/^\d+$/.test(tail)) {
		const session = await api('/training-sessions/' + tail);
		const persons = await coreApi('/persons/options').catch(() => []);
		const name = id => { const p = persons.find(x => x.id === id); return p ? p.last_name + ', ' + p.first_name : '#' + id; };
		const canEdit = r => ME.caps['training.records.approve'] || (r.status === 'pending'
			&& r.created_by === ME.uid && ME.caps['training.records.submitOther']);
		put(v, lrPageHeader({ kicker: _t('Group training'), title: session.title,
			lede: formatDate(session.attended_date) + ' · ' + session.hours + ' h',
			actions: [h('button', { class: 'lr-btn secondary', onclick: () => location.hash = '#/gruppen' }, _t('Back'))] }),
			h('p', {}, session.notes || ''),
			h('table', { class: 'lr-table' },
				h('thead', {}, h('tr', {}, h('th', {}, _t('Person')), h('th', {}, _t('Status')), h('th', {}, _t('File')), h('th', {}, _t('Action')))),
				h('tbody', {}, ...session.records.map(r => {
					const upload = h('input', { type: 'file', accept: '.pdf,.jpg,.jpeg,.png,.heic' });
					const button = h('button', { class: 'lr-btn secondary', onclick: async () => {
						if (!upload.files[0]) { toast(_t('Please select a certificate')); return; }
						button.disabled = true;
						const fd = new FormData(); fd.append('file', upload.files[0]);
						try { await api('/training-records/' + r.id + '/file', { method: 'POST', body: fd }); await viewGruppen(v, ME, ctx); }
						catch (e) { toast(e.message); button.disabled = false; }
					} }, r.file_id ? _t('Replace file') : _t('Upload file'));
					return h('tr', {}, h('td', {}, name(r.person_id)), h('td', {}, badge(r.status)),
						h('td', {}, r.file_id ? h('a', { href: OC.generateUrl('/apps/' + APP + '/api/training-records/' + r.id + '/file'), target: '_blank', rel: 'noopener' }, r.file_name || _t('File')) : _t('No file')),
						h('td', {}, canEdit(r) ? h('div', {}, upload, button) : null));
				}))));
		return;
	}
	const sessions = await api('/training-sessions');
	put(v, lrPageHeader({ kicker: _t('Leiterrunde · Training'), title: _t('Group training'),
		actions: ME.caps['training.records.submitOther'] ? [h('button', { class: 'lr-btn', onclick: () => location.hash = '#/gruppen/neu' }, _t('New group training'))] : [] }),
		sessions.length ? h('table', { class: 'lr-table' },
			h('thead', {}, h('tr', {}, h('th', {}, _t('Title')), h('th', {}, _t('Type')), h('th', {}, _t('Date')), h('th', {}, _t('Hours')))),
			h('tbody', {}, ...sessions.map(s => h('tr', {},
				h('td', {}, h('a', { href: '#/gruppen/' + s.id }, s.title)),
				h('td', {}, TYPES.find(t => t.id === s.type_id)?.name || s.type_id),
				h('td', {}, formatDate(s.attended_date)), h('td', {}, s.hours)))))
			: emptyState(_t('No group training sessions.')));
}

async function sessionForm(v, ME, { api, coreApi }) {
	if (!ME.caps['training.records.submitOther']) return;
	const persons = await coreApi('/persons/options');
	const type = h('select', {}, ...TYPES.filter(t => t.active).map(t => h('option', { value: t.id }, t.name)));
	const title = h('input', { maxlength: 255 }), date = h('input', { type: 'date' });
	const hours = h('input', { type: 'number', min: 0, max: 9999.99, step: '0.25', value: 0 });
	const notes = h('textarea', { maxlength: 10000 });
	const selected = new Map();
	const rows = h('tbody', {});
	let personId = null;
	const picker = window.LeiterrundeUI.personPicker({ persons, value: null, onChange: id => { personId = id; } });
	const add = h('button', { class: 'lr-btn secondary', onclick: () => {
		const id = +personId;
		if (!id || selected.has(id)) { toast(_t('Please select a new participant')); return; }
		if (selected.size >= 100) { toast(_t('A session can have at most 100 participants')); return; }
		const person = persons.find(p => p.id === id);
		const file = h('input', { type: 'file', accept: '.pdf,.jpg,.jpeg,.png,.heic' });
		const row = h('tr', {}, h('td', {}, person ? person.last_name + ', ' + person.first_name : '#' + id),
			h('td', {}, file), h('td', {}, h('button', { class: 'lr-btn quiet', onclick: () => { selected.delete(id); row.remove(); } }, _t('Remove'))));
		selected.set(id, file); rows.append(row);
	} }, _t('Add participant'));
	const save = h('button', { class: 'lr-btn', onclick: async () => {
		if (!title.value.trim() || !date.value || !selected.size) { toast(_t('Enter a title, date and at least one participant')); return; }
		for (const file of selected.values()) {
			if (file.files[0]?.size > 20 * 1024 * 1024) { toast(_t('File is larger than 20 MB')); return; }
		}
		save.disabled = true;
		let session;
		try {
			session = await api('/training-sessions', { method: 'POST', json: { type_id: +type.value,
				attended_date: date.value, hours: +hours.value, title: title.value,
				person_ids: [...selected.keys()], notes: notes.value || null } });
		} catch (e) { toast(e.message); save.disabled = false; return; }
		for (const record of session.records) {
			const file = selected.get(record.person_id)?.files[0];
			if (!file) continue;
			const fd = new FormData(); fd.append('file', file);
			try { await api('/training-records/' + record.id + '/file', { method: 'POST', body: fd }); }
			catch (e) { toast(_t('Session created, but a certificate upload failed. Retry it on the session page.') + ' ' + e.message); }
		}
		location.hash = '#/gruppen/' + session.id;
	} }, _t('Create group training'));
	put(v, lrPageHeader({ kicker: _t('Leiterrunde · Training'), title: _t('New group training') }),
		h('div', { class: 'lr-form lr-formgrid' },
			field(_t('Title'), title, 6), field(_t('Training type'), type, 6), field(_t('Date'), date, 4), field(_t('Hours'), hours, 4),
			field(_t('Notes'), notes, 12), field(_t('Person'), picker, 8), formActions(add)),
		h('p', {}, _t('Each participant gets an individual record and certificate. Certificates can also be uploaded later.')),
		h('table', { class: 'lr-table' }, h('thead', {}, h('tr', {}, h('th', {}, _t('Person')), h('th', {}, _t('File')), h('th', {}, _t('Action')))), rows),
		formActions(save, h('button', { class: 'lr-btn secondary', onclick: () => location.hash = '#/gruppen' }, _t('Cancel'))));
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
				...st.types.map(t => h('tr', {}, h('td', {}, t.name, trainingDetails(t)), h('td', {}, badge(t.state)), h('td', {}, t.expires ? formatDate(t.expires) : '–'))),
				h('tr', {}, h('td', {}, _t('Training hours'), trainingDetails(st.hours, true)), h('td', {}, badge(st.hours.state)), h('td', {}, st.hours.valid_until ? formatDate(st.hours.valid_until) : '–')),
				h('tr', {}, h('td', {}, _t('Certificate of conduct')), h('td', {}, badge(st.fz.state)), h('td', {}, st.fz.expires ? formatDate(st.fz.expires) : '–')))),
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
				try { await api('/training-records', { method: 'POST', body: fd }); toast(_t('Record submitted'), false); viewMein(v, ME, { api }); }
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
		'#/gruppen': viewGruppen,
		'#/fz': viewFz,
		'#/schulungsarten': viewTypen,
		'#/mein': viewMein,
	},
	defaultRoute: '#/uebersicht',
});
