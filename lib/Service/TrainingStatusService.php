<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\LeiterrundeSchulungen\Service;

use OCA\LeiterrundeSchulungen\Db\FzRecordMapper;
use OCA\Leiterrunde\Db\Person;
use OCA\Leiterrunde\Db\PersonMapper;
use OCA\LeiterrundeSchulungen\Db\TrainingRecordMapper;
use OCA\LeiterrundeSchulungen\Db\TrainingType;
use OCA\LeiterrundeSchulungen\Db\TrainingTypeMapper;

/**
 * Verbindet Regelwerk (TrainingRulesService) mit der Datenbank.
 */
class TrainingStatusService {
	public function __construct(
		private TrainingRulesService $rules,
		private TrainingTypeMapper $typeMapper,
		private TrainingRecordMapper $recordMapper,
		private FzRecordMapper $fzMapper,
		private PersonMapper $personMapper,
		private ModuleConfigService $settings,
	) {
	}

	/**
	 * Status aller aktiven, "required"-Typen + Stundenkonto für eine Person.
	 * @return array{types: array, hours: array, overall: string}
	 */
	public function personStatus(Person $person, \DateTimeImmutable $at): array {
		$warnDays = (int)$this->settings->get('warn_days');
		$records = $this->recordMapper->findApprovedForPerson($person->getId());
		$byType = [];
		foreach ($records as $r) {
			$byType[$r->getTypeId()][] = $r;
		}
		$types = [];
		$states = [];
		foreach ($this->typeMapper->findAll(true) as $type) {
			if (!$type->getRequired()) {
				continue;
			}
			$res = $this->rules->typeState(
				array_map(static fn ($r) => ['attended_date' => $r->getAttendedDate()], $byType[$type->getId()] ?? []),
				$type->getValidityMonths(), $at, $warnDays
			);
			$types[] = [
				'type_id' => $type->getId(),
				'name' => $type->getName(),
				'state' => $res['state'],
				'attended' => $res['attended'],
				'expires' => $res['expires'],
			];
			$states[] = $res['state'];
		}
		$hours = $this->hoursStatus($person, $at);
		$states[] = $hours['state'];
		return [
			'types' => $types,
			'hours' => $hours,
			'overall' => $this->rules->overall($states),
		];
	}

	public function hoursStatus(Person $person, \DateTimeImmutable $at): array {
		$warnDays = (int)$this->settings->get('warn_days');
		$hourTypeIds = [];
		foreach ($this->typeMapper->findAll(true) as $type) {
			if ($type->getCountsHours()) {
				$hourTypeIds[] = $type->getId();
			}
		}
		$records = [];
		foreach ($this->recordMapper->findApprovedForPerson($person->getId()) as $r) {
			if (in_array($r->getTypeId(), $hourTypeIds, true)) {
				$records[] = ['attended_date' => $r->getAttendedDate(), 'hours' => (float)$r->getHours()];
			}
		}
		return $this->rules->hoursState(
			$records,
			(float)$this->settings->get('hours_required'),
			(int)$this->settings->get('hours_window_months'),
			$at, $warnDays
		);
	}

	/** @return array{state: string, issued: ?string, expires: ?string} */
	public function fzStatus(Person $person, \DateTimeImmutable $at): array {
		$warnDays = (int)$this->settings->get('warn_days');
		$latest = $this->fzMapper->findLatestForPerson($person->getId());
		return $this->rules->fzState(
			$latest?->getIssuedAt(),
			$latest?->getValidMonths() ?? 36,
			$at, $warnDays
		);
	}

	public function betreuerCheckById(int $personId, \DateTimeImmutable $at, bool $fzDetails): array {
		$person = $this->personMapper->find($personId);
		return $this->betreuerCheck($person, $at, $fzDetails);
	}

	/**
	 * Betreuer-Check: required_for_event Typen + FZ.
	 * @param bool $fzDetails ob FZ-Daten (Daten statt nur Status) gezeigt werden dürfen
	 */
	public function betreuerCheck(Person $person, \DateTimeImmutable $at, bool $fzDetails): array {
		$warnDays = (int)$this->settings->get('warn_days');
		$records = $this->recordMapper->findApprovedForPerson($person->getId());
		$byType = [];
		foreach ($records as $r) {
			$byType[$r->getTypeId()][] = $r;
		}
		$types = [];
		$states = [];
		foreach ($this->typeMapper->findAll(true) as $type) {
			if (!$type->getRequiredForEvent()) {
				continue;
			}
			$res = $this->rules->typeState(
				array_map(static fn ($r) => ['attended_date' => $r->getAttendedDate()], $byType[$type->getId()] ?? []),
				$type->getValidityMonths(), $at, $warnDays
			);
			$types[] = [
				'type_id' => $type->getId(),
				'name' => $type->getName(),
				'state' => $res['state'],
				'expires' => $res['expires'],
			];
			$states[] = $res['state'];
		}
		$fz = $this->fzStatus($person, $at);
		$states[] = $fz['state'];
		$fzOut = ['state' => $fz['state']];
		if ($fzDetails) {
			$fzOut['issued'] = $fz['issued'];
			$fzOut['expires'] = $fz['expires'];
		}
		return [
			'person_id' => $person->getId(),
			'date' => $at->format('Y-m-d'),
			'types' => $types,
			'fz' => $fzOut,
			'overall' => $this->rules->overall($states),
		];
	}

	/**
	 * Statusmatrix für alle Personen in scope_statuses.
	 * @param bool $fzExpiry ob das FZ-Ablaufdatum mit ausgegeben werden darf
	 * @return array<int, array>
	 */
	public function statusMatrix(\DateTimeImmutable $at, bool $fzExpiry = false): array {
		$persons = $this->personMapper->findByStatuses(array_map('trim', explode(',', $this->settings->get('scope_statuses'))));
		$out = [];
		foreach ($persons as $p) {
			$status = $this->personStatus($p, $at);
			$fz = $this->fzStatus($p, $at);
			$fzOut = ['state' => $fz['state']];
			if ($fzExpiry) {
				$fzOut['expires'] = $fz['expires'];
			}
			$out[] = [
				'person' => [
					'id' => $p->getId(),
					'first_name' => $p->getFirstName(),
					'last_name' => $p->getLastName(),
					'status' => $p->getStatus(),
				],
				'types' => $status['types'],
				'hours' => $status['hours'],
				'fz' => $fzOut,
				'overall' => $status['overall'],
			];
		}
		return $out;
	}
}
