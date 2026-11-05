<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\LeiterrundeSchulungen\Controller\Api;

use OCA\Leiterrunde\AppInfo\Application;
use OCA\Leiterrunde\Controller\Api\ApiController;
use OCA\LeiterrundeSchulungen\Db\TrainingRecord;
use OCA\LeiterrundeSchulungen\Db\TrainingRecordMapper;
use OCA\LeiterrundeSchulungen\Db\TrainingTypeMapper;
use OCA\Leiterrunde\Exception\ApiException;
use OCA\Leiterrunde\Service\AuditService;
use OCA\Leiterrunde\Service\DocumentStorageService;
use OCA\Leiterrunde\Service\NotificationService;
use OCA\Leiterrunde\Service\PermissionService;
use OCA\Leiterrunde\Service\SettingsService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\Response;
use OCP\AppFramework\Http\StreamResponse;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

class TrainingRecordsController extends ApiController {
	public function __construct(
		IRequest $request,
		LoggerInterface $logger,
		private PermissionService $perm,
		private TrainingRecordMapper $mapper,
		private TrainingTypeMapper $typeMapper,
		private DocumentStorageService $files,
		private AuditService $audit,
		private NotificationService $notifications,
		private SettingsService $settings,
		private \OCA\Leiterrunde\Db\PersonMapper $personMapper,
	) {
		parent::__construct(Application::APP_ID, $request, $logger);
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function index(?int $person_id = null, ?int $type_id = null, ?string $status = null): JSONResponse {
		return $this->wrap(function () use ($person_id, $type_id, $status) {
			$own = $this->perm->currentPerson();
			if ($this->perm->has('training.records.readAll')) {
				return array_map(static fn ($r) => $r->jsonSerialize(), $this->mapper->findFiltered($person_id, $type_id, $status));
			}
			// ohne readAll: nur eigene Nachweise
			$this->perm->require('training.records.submitOwn');
			if ($own === null) {
				return [];
			}
			return array_map(static fn ($r) => $r->jsonSerialize(), $this->mapper->findFiltered($own->getId(), $type_id, $status));
		});
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function show(int $id): JSONResponse {
		return $this->wrap(function () use ($id) {
			$record = $this->mapper->find($id);
			$this->assertCanView($record);
			return $record->jsonSerialize();
		});
	}

	#[NoAdminRequired]
	public function create(
		int $person_id,
		int $type_id,
		string $attended_date,
		?float $hours = null,
		?string $title = null,
		?string $notes = null,
	): JSONResponse {
		return $this->wrap(function () use ($person_id, $type_id, $attended_date, $hours, $title, $notes) {
			$own = $this->perm->currentPerson();
			$isOwn = $own !== null && $own->getId() === $person_id;
			if ($isOwn) {
				$this->perm->require('training.records.submitOwn');
			} else {
				$this->perm->require('training.records.submitOther');
			}
			$this->personMapper->find($person_id);
			$type = $this->typeMapper->find($type_id);
			$d = \DateTimeImmutable::createFromFormat('!Y-m-d', $attended_date);
			if ($d === false) {
				throw new ApiException('Invalid date (YYYY-MM-DD)', 422);
			}
			$user = $this->perm->requireLoggedIn();
			$r = new TrainingRecord();
			$r->setPersonId($person_id);
			$r->setTypeId($type_id);
			$r->setAttendedDate($d->format('Y-m-d'));
			$r->setHours(number_format((float)($hours ?? 0), 2, '.', ''));
			$r->setTitle($title);
			$r->setNotes($notes);
			$r->setStatus('pending');
			$r->setCreatedBy($user->getUID());
			$r->setCreatedAt(time());
			$upload = $this->request->getUploadedFile('file');
			if (is_array($upload) && !empty($upload['tmp_name']) && is_file($upload['tmp_name'])) {
				$vars = $this->docVars($person_id);

				$stored = $this->files->store('nachweis', $user->getUID(), $upload, $vars);
				$r->setFileId($stored['file_id']);
				$r->setFileOwner($stored['file_owner']);
				$r->setFileName($stored['file_name']);
			}
			$r = $this->mapper->insert($r);
			$this->audit->diff('training_record', $r->getId(), null, ['_action' => 'created']);
			if ($isOwn) {
				$this->notifications->notifyState(
					'record_submitted',
					['person_id' => $person_id, 'first_name' => $own->getFirstName(), 'last_name' => $own->getLastName()],
					$this->settings->notifyGroups('training'),
					null,
					'New training record',
					"Hello,\n\n{$own->getFirstName()} {$own->getLastName()} submitted a record („{$type->getName()}“).\n",
				);
			}
			return new JSONResponse($r->jsonSerialize(), Http::STATUS_CREATED);
		});
	}

	#[NoAdminRequired]
	public function approve(int $id, ?string $decision_note = null): JSONResponse {
		return $this->wrap(function () use ($id, $decision_note) {
			$this->perm->require('training.records.approve');
			$r = $this->mapper->find($id);
			$before = $r->jsonSerialize();
			$r->setStatus('approved');
			$r->setDecidedBy($this->perm->requireLoggedIn()->getUID());
			$r->setDecidedAt(time());
			$r->setDecisionNote($decision_note);
			$r = $this->mapper->update($r);
			$this->audit->diff('training_record', $r->getId(), $before, $r->jsonSerialize());
			return $r->jsonSerialize();
		});
	}

	#[NoAdminRequired]
	public function reject(int $id, ?string $decision_note = null): JSONResponse {
		return $this->wrap(function () use ($id, $decision_note) {
			$this->perm->require('training.records.approve');
			$r = $this->mapper->find($id);
			$before = $r->jsonSerialize();
			$r->setStatus('rejected');
			$r->setDecidedBy($this->perm->requireLoggedIn()->getUID());
			$r->setDecidedAt(time());
			$r->setDecisionNote($decision_note);
			$r = $this->mapper->update($r);
			$this->audit->diff('training_record', $r->getId(), $before, $r->jsonSerialize());
			return $r->jsonSerialize();
		});
	}

	#[NoAdminRequired]
	public function update(
		int $id,
		?int $type_id = null,
		?string $attended_date = null,
		?float $hours = null,
		?string $title = null,
		?string $notes = null,
	): JSONResponse {
		return $this->wrap(function () use ($id, $type_id, $attended_date, $hours, $title, $notes) {
			$r = $this->mapper->find($id);
			$own = $this->perm->currentPerson();
			$isOwn = $own !== null && $own->getId() === $r->getPersonId() && $r->getCreatedBy() === $this->perm->requireLoggedIn()->getUID();
			if (!$isOwn || $r->getStatus() !== 'pending') {
				$this->perm->require('training.records.approve');
			}
			$before = $r->jsonSerialize();
			if ($type_id !== null) {
				$this->typeMapper->find($type_id);
				$r->setTypeId($type_id);
			}
			if ($attended_date !== null) {
				$d = \DateTimeImmutable::createFromFormat('!Y-m-d', $attended_date);
				if ($d === false) {
					throw new ApiException('Invalid date (YYYY-MM-DD)', 422);
				}
				$r->setAttendedDate($d->format('Y-m-d'));
			}
			if ($hours !== null) {
				$r->setHours(number_format((float)$hours, 2, '.', ''));
			}
			if ($title !== null) {
				$r->setTitle($title);
			}
			if ($notes !== null) {
				$r->setNotes($notes);
			}
			$r = $this->mapper->update($r);
			$this->audit->diff('training_record', $r->getId(), $before, $r->jsonSerialize());
			return $r->jsonSerialize();
		});
	}

	#[NoAdminRequired]
	public function destroy(int $id): JSONResponse {
		return $this->wrap(function () use ($id) {
			$r = $this->mapper->find($id);
			$own = $this->perm->currentPerson();
			$isOwn = $own !== null && $own->getId() === $r->getPersonId() && $r->getCreatedBy() === $this->perm->requireLoggedIn()->getUID();
			if (!$isOwn || $r->getStatus() !== 'pending') {
				$this->perm->require('training.records.approve');
			}
			$this->mapper->delete($r);
			$this->audit->diff('training_record', $id, null, ['_action' => 'deleted']);
			return ['ok' => true];
		});
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function file(int $id): Response {
		try {
			$r = $this->mapper->find($id);
			$this->assertCanView($r);
			if ($r->getFileId() === null || $r->getFileOwner() === null) {
				throw new ApiException('No file present', 404);
			}
			$file = $this->files->get($r->getFileOwner(), $r->getFileId());
			$res = new StreamResponse($file->fopen('rb'));
			$res->addHeader('Content-Type', $file->getMimeType());
			$res->addHeader('Content-Disposition', 'inline; filename="' . addslashes($r->getFileName() ?? 'nachweis') . '"');
			return $res;
		} catch (\OCA\Leiterrunde\Exception\ApiException $e) {
			return new JSONResponse(['error' => $e->getMessage()], $e->getStatus());
		} catch (\OCP\AppFramework\Db\DoesNotExistException) {
			return new JSONResponse(['error' => 'Nicht gefunden'], 404);
		}
	}

	private function docVars(int $personId): array {
		$person = null;
		try { $person = $this->personMapper->find($personId); } catch (\Throwable) {}
		$now = new \DateTimeImmutable();
		return [
			'jahr' => $now->format('Y'), 'monat' => $now->format('m'), 'datum' => $now->format('Y-m-d'),
			'vorname' => $person?->getFirstName() ?? '', 'nachname' => $person?->getLastName() ?? '',
			'id' => (string)$personId, 'typ' => $type?->getName() ?? '',
		];
	}

	private function assertCanView(TrainingRecord $r): void {
		if ($this->perm->has('training.records.readAll')) {
			return;
		}
		$own = $this->perm->currentPerson();
		if ($own !== null && $own->getId() === $r->getPersonId()) {
			return;
		}
		throw new \OCA\Leiterrunde\Exception\ForbiddenException();
	}
}
