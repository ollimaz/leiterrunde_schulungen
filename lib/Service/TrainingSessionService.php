<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\LeiterrundeSchulungen\Service;

use OCA\Leiterrunde\Db\PersonMapper;
use OCA\Leiterrunde\Exception\ApiException;
use OCA\Leiterrunde\Service\AuditService;
use OCA\Leiterrunde\Service\PermissionService;
use OCA\LeiterrundeSchulungen\Db\TrainingRecordMapper;
use OCA\LeiterrundeSchulungen\Db\TrainingSessionMapper;
use OCA\LeiterrundeSchulungen\Db\TrainingTypeMapper;
use OCP\IDBConnection;

class TrainingSessionService {
	public function __construct(
		private PermissionService $perm,
		private TrainingSessionMapper $sessions,
		private TrainingRecordMapper $records,
		private TrainingTypeMapper $types,
		private PersonMapper $persons,
		private IDBConnection $db,
		private AuditService $audit,
	) {
	}

	public function index(): array {
		$uid = $this->perm->requireLoggedIn()->getUID();
		if (!$this->perm->has('training.records.readAll')) {
			$this->perm->require('training.records.submitOther');
		}
		return array_map(static fn ($s) => $s->jsonSerialize(), $this->sessions->findRecent(
			$this->perm->has('training.records.readAll') ? null : $uid
		));
	}

	public function show(int $id): array {
		$uid = $this->perm->requireLoggedIn()->getUID();
		if (!$this->perm->has('training.records.readAll')) {
			$this->perm->require('training.records.submitOther');
		}
		$session = $this->sessions->find($id);
		if (!$this->perm->has('training.records.readAll') && $session->getCreatedBy() !== $uid) {
			throw new ApiException('Not found', 404);
		}
		return $session->jsonSerialize() + ['records' => array_map(
			static fn ($r) => $r->jsonSerialize(), $this->records->findForSession($id)
		)];
	}

	public function create(int $typeId, string $date, float $hours, string $title, array $personIds, ?string $notes): array {
		$this->perm->require('training.records.submitOther');
		$uid = $this->perm->requireLoggedIn()->getUID();
		$data = self::validate($typeId, $date, $hours, $title, $personIds, $notes);
		$type = $this->types->find($typeId);
		if (!$type->getActive()) {
			throw new ApiException('Training type is inactive', 422);
		}
		foreach ($personIds as $personId) {
			$this->persons->find($personId);
		}
		$this->db->beginTransaction();
		try {
			$session = $this->sessions->createSession($data, $uid);
			$records = [];
			foreach ($personIds as $personId) {
				$record = $this->records->createForSession($session, $personId, $uid);
				$this->audit->diff('training_record', $record->getId(), null, ['_action' => 'created', 'session_id' => $session->getId()]);
				$records[] = $record->jsonSerialize();
			}
			$this->audit->diff('training_session', $session->getId(), null, ['_action' => 'created']);
			$this->db->commit();
		} catch (\Throwable $e) {
			$this->db->rollBack();
			throw $e;
		}
		return $session->jsonSerialize() + ['records' => $records];
	}

	public static function assertRecordFields(\OCA\LeiterrundeSchulungen\Db\TrainingRecord $record, ?int $typeId, ?string $date, ?float $hours, ?string $title): void {
		if ($record->getSessionId() === null) {
			return;
		}
		if (($typeId !== null && $typeId !== $record->getTypeId())
			|| ($date !== null && $date !== $record->getAttendedDate())
			|| ($hours !== null && (!is_finite($hours) || number_format($hours, 2, '.', '') !== $record->getHours()))
			|| ($title !== null && $title !== $record->getTitle())) {
			throw new ApiException('Shared session details cannot be changed on an individual record', 409);
		}
	}

	public static function validate(int $typeId, string $date, float $hours, string $title, array $personIds, ?string $notes): array {
		$parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
		if ($parsed === false || $parsed->format('Y-m-d') !== $date) {
			throw new ApiException('Invalid date (YYYY-MM-DD)', 422);
		}
		$title = trim($title);
		if ($typeId <= 0 || $title === '' || mb_strlen($title) > 255 || mb_strlen($notes ?? '') > 10000
			|| !is_finite($hours) || $hours < 0 || $hours > 9999.99) {
			throw new ApiException('Invalid training session data', 422);
		}
		if (count($personIds) < 1 || count($personIds) > 100) {
			throw new ApiException('Select between 1 and 100 participants', 422);
		}
		$seen = [];
		foreach ($personIds as $id) {
			if (!is_int($id) || $id <= 0 || isset($seen[$id])) {
				throw new ApiException('Participants must be unique person IDs', 422);
			}
			$seen[$id] = true;
		}
		return ['type_id' => $typeId, 'attended_date' => $date, 'hours' => number_format($hours, 2, '.', ''),
			'title' => $title, 'notes' => $notes];
	}
}
