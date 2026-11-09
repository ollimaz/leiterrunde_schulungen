<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\LeiterrundeSchulungen\Module;

use OCA\Leiterrunde\Module\CapabilityDefinition;
use OCA\Leiterrunde\Module\DocumentType;
use OCA\Leiterrunde\Module\IModule;
use OCA\Leiterrunde\Module\ITrainingCheckProvider;
use OCA\Leiterrunde\Module\OverviewTile;
use OCA\LeiterrundeSchulungen\Service\TrainingStatusService;
use OCP\IUser;
use OCP\IL10N;

class SchulungenModule implements IModule, ITrainingCheckProvider {
	public function __construct(private TrainingStatusService $status,
		private \OCA\Leiterrunde\Service\PermissionService $perm,
		private \OCA\LeiterrundeSchulungen\Db\TrainingRecordMapper $recordMapper,
		private IL10N $l10n) {
	}

	public function getId(): string {
		return 'leiterrunde_schulungen';
	}

	public function getLabel(): string {
		return 'Schulungen';
	}

	public function getNavUrl(): string {
		return '/apps/leiterrunde_schulungen/';
	}

	public function getCapabilities(): array {
		return [
			new CapabilityDefinition('training.types.write', $this->l10n->t('Maintain training types'), $this->l10n->t('Training'), ['schulung', 'admin']),
			new CapabilityDefinition('training.records.readAll', $this->l10n->t('Read all training records'), $this->l10n->t('Training'), ['schulung', 'vorstand', 'admin']),
			new CapabilityDefinition('training.records.approve', $this->l10n->t('Approve training records'), $this->l10n->t('Training'), ['schulung', 'admin']),
			new CapabilityDefinition('training.records.submitOwn', $this->l10n->t('Submit own training records'), $this->l10n->t('Training'), ['any']),
			new CapabilityDefinition('training.records.submitOther', $this->l10n->t('Submit records for others'), $this->l10n->t('Training'), ['schulung', 'admin']),
			new CapabilityDefinition('training.check', $this->l10n->t('Supervisor check'), $this->l10n->t('Training'), ['schulung', 'vorstand', 'admin']),
			new CapabilityDefinition('fz.read', $this->l10n->t('Read certificates of conduct'), $this->l10n->t('Training'), ['fz'], ['fz']),
			new CapabilityDefinition('fz.expiry.read', $this->l10n->t('See certificate of conduct expiry dates'), $this->l10n->t('Training'), ['fz', 'vorstand', 'schulung']),
			new CapabilityDefinition('fz.write', $this->l10n->t('Write certificates of conduct'), $this->l10n->t('Training'), ['fz'], ['fz']),
		];
	}

	public function getDocumentTypes(): array {
		return [
			new DocumentType('nachweis', 'Schulungsnachweise',
				'Leiterrunde/Schulungsnachweise/{jahr}/{nachname}, {vorname}',
				['jahr', 'monat', 'datum', 'vorname', 'nachname', 'typ', 'id']),
		];
	}

	public function getOverviewTiles(IUser $user): array {
		$tiles = [];
		$uid = $user->getUID();
		$person = $this->perm->currentPerson($uid);
		if ($person !== null) {
			try {
				$st = $this->status->personStatus($person, new \DateTimeImmutable('today'));
				$worst = 'ok';
				foreach (array_merge($st['types'], [$st['hours'] ?? [], $st['fz'] ?? []]) as $t) {
					if ($t === []) { continue; }
					if (in_array($t['state'], ['fail', 'missing'], true)) { $worst = 'fail'; break; }
					if ($t['state'] === 'warn') { $worst = 'warn'; }
				}
				$items = array_map(static fn ($t) => ['label' => $t['name'], 'state' => $t['state']], $st['types']);
				$tiles[] = new OverviewTile('training.mine', $this->l10n->t('My training status'), $worst, null, $items, '/apps/leiterrunde_schulungen/#/mein');
			} catch (\Throwable) {
			}
		}
		if ($this->perm->has('training.records.approve', $uid)) {
			try {
				$recs = $this->recordMapper->findFiltered(null, null, 'pending');
				$items = array_slice(array_map(static fn ($r) => ['label' => 'Person #' . $r->getPersonId() . ' (' . $r->getAttendedDate() . ')'], $recs), 0, 5);
				$tiles[] = new OverviewTile('training.pending', $this->l10n->t('Records to review'), count($items) > 0 ? 'warn' : 'ok', count($recs), $items, '/apps/leiterrunde_schulungen/#/nachweise');
			} catch (\Throwable) {
			}
		}
		if ($this->perm->has('training.check', $uid)) {
			try {
				$matrix = $this->status->statusMatrix(new \DateTimeImmutable('today'), $this->perm->has('fz.read', $uid) || $this->perm->has('fz.expiry.read', $uid));
				$bad = array_filter($matrix, static fn ($r) => in_array($r['overall'], ['warn', 'fail', 'missing'], true));
				$seen = [];
				$bad = array_values(array_filter($bad, static function ($r) use (&$seen) {
					$pid = is_object($r['person']) ? $r['person']->getId() : ($r['person']['id'] ?? $r['person']->getId());
					if (isset($seen[$pid])) { return false; }
					$seen[$pid] = true; return true;
				}));
				$overall = $this->l10n->t('Overall status');
				$items = array_slice(array_map(static function ($r) use ($overall) {
					$p = $r['person'];
					$name = is_object($p) ? $p->getLastName() . ', ' . $p->getFirstName() : ($p['last_name'] ?? '') . ', ' . ($p['first_name'] ?? '');
					$detail = [];
				foreach (array_merge($r['types'] ?? [], [$r['hours'] ?? null, $r['fz'] ?? null]) as $t) {
					if ($t === null || $t === []) { continue; }
					if (in_array($t['state'] ?? 'ok', ['warn', 'fail', 'missing'], true)) { $detail = $t; break; }
				}
				return ['label' => $name, 'state' => $r['overall'],
					'check' => $detail['name'] ?? $overall,
					'valid' => $detail['expires'] ?? $detail['valid_until'] ?? ''];
			}, $bad), 0, 5);
				$tiles[] = new OverviewTile('training.expiring', $this->l10n->t('Expiring trainings'), count($bad) > 0 ? 'warn' : 'ok', count($bad), $items, '/apps/leiterrunde_schulungen/#/uebersicht');
				$states = [];
				foreach ($matrix as $r) { $states[$r['overall']] = ($states[$r['overall']] ?? 0) + 1; }
				$total = array_sum($states) ?: 1;
				$okPct = round(100 * ($states['ok'] ?? 0) / $total);
				$tiles[] = new OverviewTile('training.status', $this->l10n->t('Training status'), 'info', null, [], '/apps/leiterrunde_schulungen/#/uebersicht',
					['value' => $okPct . ' %', 'label' => $this->l10n->n('%n of %s supervisor', '%n of %s supervisors', (int)($states['ok'] ?? 0), [(string)$total])],
					['type' => 'stack', 'series' => array_map(static fn ($k, $v) => ['label' => $k, 'value' => $v, 'state' => $k === 'ok' ? 'ok' : ($k === 'warn' ? 'warn' : 'fail')], array_keys($states), $states)]);
			} catch (\Throwable $e) {
				error_log('SchulungenTile: ' . $e->getMessage() . ' @' . $e->getFile() . ':' . $e->getLine());
			}
		}
		return $tiles;
	}

	public function betreuerCheck(int $personId, \DateTimeImmutable $at, bool $fzDetails): array {
		return $this->status->betreuerCheckById($personId, $at, $fzDetails);
	}
}
