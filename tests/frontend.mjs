/* SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { test } from 'node:test';
import vm from 'node:vm';

const source = await readFile(new URL('../js/main.mjs', import.meta.url), 'utf8');

function setup(hash, caps, api) {
	let app, picker;
	const messages = [];
	const node = (tag, attrs = {}, ...children) => ({
		tag, attrs, children: children.flat().filter(x => x != null), value: attrs.value ?? '', files: [],
		append(...items) { this.children.push(...items); },
		addEventListener() {}, remove() {},
	});
	const ui = {
		h: node, mountApp: config => { app = config; },
		l10n: () => ({ t: s => s, n: s => s }),
		put: (view, ...children) => { view.children = children; },
		lrPageHeader: data => node('header', {}, data.title, ...(data.actions || [])),
		field: (label, input) => node('label', {}, label, input),
		formActions: (...items) => node('actions', {}, ...items),
		emptyState: text => node('p', {}, text), badge: state => node('badge', {}, state),
		formatDate: date => date, toast: message => messages.push(message),
		personPicker: config => { picker = config; return node('picker'); },
	};
	const context = vm.createContext({ window: { LeiterrundeUI: ui }, location: { hash },
		OC: { generateUrl: path => path }, FormData, console });
	vm.runInContext(source, context);
	const view = node('root');
	const persons = [{ id: 1, first_name: 'A', last_name: 'One' }, { id: 2, first_name: 'B', last_name: 'Two' }];
	return { view, context, messages, picker: () => picker,
		render: () => app.routes['#/gruppen'](view, { uid: 'trainer', caps }, { api, coreApi: async () => persons }) };
}

function nodes(root, predicate) {
	if (!root || typeof root !== 'object') return [];
	return [...(predicate(root) ? [root] : []), ...(root.children || []).flatMap(child => nodes(child, predicate))];
}

const button = (root, text) => nodes(root, n => n.tag === 'button' && n.children.includes(text))[0];
const input = (root, label) => nodes(root, n => n.tag === 'label' && n.children.includes(label))[0].children[1];
const types = [{ id: 1, active: true, name: 'First aid' }];

test('group session renders individual records without exposing edit controls to read-only users', async () => {
	const ui = setup('#/gruppen/10', { 'training.records.readAll': true }, async path => {
		if (path === '/training-types') return types;
		return { id: 10, title: 'First aid', attended_date: '2026-11-01', hours: 2, records: [
			{ id: 11, person_id: 1, status: 'pending', created_by: 'other' },
			{ id: 12, person_id: 2, status: 'approved', created_by: 'other' },
		] };
	});
	await ui.render();
	assert.equal(nodes(ui.view, n => n.tag === 'tr').length, 3);
	assert.equal(nodes(ui.view, n => n.tag === 'input').length, 0);
});

test('group form requires participants and does not submit an empty group', async () => {
	let writes = 0;
	const ui = setup('#/gruppen/neu', { 'training.records.submitOther': true }, async (path, options) => {
		if (options) writes++;
		return types;
	});
	await ui.render();
	await button(ui.view, 'Create group training').attrs.onclick();
	assert.equal(writes, 0);
	assert.equal(ui.messages.length, 1);
});

test('one group creation produces distinct certificate uploads per participant', async () => {
	const calls = [];
	const ui = setup('#/gruppen/neu', { 'training.records.submitOther': true }, async (path, options) => {
		calls.push([path, options]);
		if (path === '/training-types') return types;
		if (path === '/training-sessions') return { id: 10, records: [{ id: 11, person_id: 1 }, { id: 12, person_id: 2 }] };
		return {};
	});
	await ui.render();
	input(ui.view, 'Title').value = 'First aid';
	input(ui.view, 'Date').value = '2026-11-01';
	input(ui.view, 'Training type').value = '1';
	ui.picker().onChange(1);
	button(ui.view, 'Add participant').attrs.onclick();
	ui.picker().onChange(2);
	button(ui.view, 'Add participant').attrs.onclick();
	const files = nodes(ui.view, n => n.attrs.type === 'file');
	files[0].files = [new Blob(['certificate one'], { type: 'application/pdf' })];
	files[1].files = [new Blob(['certificate two'], { type: 'application/pdf' })];
	await button(ui.view, 'Create group training').attrs.onclick();
	assert.equal(calls.filter(([path]) => path === '/training-sessions').length, 1);
	assert.deepEqual(Array.from(calls[1][1].json.person_ids), [1, 2]);
	assert.equal(await calls[2][1].body.get('file').text(), 'certificate one');
	assert.equal(await calls[3][1].body.get('file').text(), 'certificate two');
	assert.equal(ui.context.location.hash, '#/gruppen/10');
});

test('a failed certificate upload does not repeat group creation', async () => {
	let creates = 0;
	const ui = setup('#/gruppen/neu', { 'training.records.submitOther': true }, async path => {
		if (path === '/training-types') return types;
		if (path === '/training-sessions') { creates++; return { id: 10, records: [{ id: 11, person_id: 1 }] }; }
		throw new Error('Upload failed');
	});
	await ui.render();
	input(ui.view, 'Title').value = 'First aid';
	input(ui.view, 'Date').value = '2026-11-01';
	ui.picker().onChange(1);
	button(ui.view, 'Add participant').attrs.onclick();
	nodes(ui.view, n => n.attrs.type === 'file')[0].files = [new Blob(['certificate'])];
	await button(ui.view, 'Create group training').attrs.onclick();
	assert.equal(creates, 1);
	assert.equal(ui.context.location.hash, '#/gruppen/10');
	assert.ok(ui.messages[0].includes('certificate upload failed'));
});
