<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\LeiterrundeSchulungen\Controller\Api;

use OCA\Leiterrunde\AppInfo\Application;
use OCA\Leiterrunde\Controller\Api\ApiController;
use OCA\LeiterrundeSchulungen\Db\FzRecord;
use OCA\LeiterrundeSchulungen\Db\FzRecordMapper;
use OCA\Leiterrunde\Exception\ApiException;
use OCA\Leiterrunde\Service\AuditService;
use OCA\Leiterrunde\Service\PermissionService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

class FzController extends ApiController {
	public function __construct(
		IRequest $request,
		LoggerInterface $logger,
		private PermissionService $perm,
		private FzRecordMapper $mapper,
		private AuditService $audit,
	) {
		parent::__construct(Application::APP_ID, $request, $logger);
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function index(?int $person_id = null): JSONResponse {
		return $this->wrap(function () use ($person_id) {
			$this->perm->require('fz.read');
			return array_map(static fn ($r) => $r->jsonSerialize(), $this->mapper->findAll($person_id));
		});
	}

	#[NoAdminRequired]
	public function create(
		int $person_id,
		string $issued_at,
		int $valid_months = 36,
		?string $notes = null,
	): JSONResponse {
		return $this->wrap(function () use ($person_id, $issued_at, $valid_months, $notes) {
			$this->perm->require('fz.write');
			$d = \DateTimeImmutable::createFromFormat('!Y-m-d', $issued_at);
			if ($d === false || !in_array($valid_months, [36, 60], true)) {
				throw new ApiException('Invalid data (date YYYY-MM-DD, validity 36 or 60 months)', 422);
			}
			$r = new FzRecord();
			$r->setPersonId($person_id);
			$r->setIssuedAt($d->format('Y-m-d'));
			$r->setValidMonths($valid_months);
			$r->setNotes($notes);
			$r->setCreatedBy($this->perm->requireLoggedIn()->getUID());
			$r->setCreatedAt(time());
			$r = $this->mapper->insert($r);
			$this->audit->diff('fz', $r->getId(), null, ['_action' => 'created']);
			return new JSONResponse($r->jsonSerialize(), Http::STATUS_CREATED);
		});
	}

	#[NoAdminRequired]
	public function update(
		int $id,
		?string $issued_at = null,
		?int $valid_months = null,
		?string $notes = null,
	): JSONResponse {
		return $this->wrap(function () use ($id, $issued_at, $valid_months, $notes) {
			$this->perm->require('fz.write');
			$r = $this->mapper->find($id);
			$before = $r->jsonSerialize();
			if ($issued_at !== null) {
				$d = \DateTimeImmutable::createFromFormat('!Y-m-d', $issued_at);
				if ($d === false) {
					throw new ApiException('Invalid date', 422);
				}
				$r->setIssuedAt($d->format('Y-m-d'));
			}
			if ($valid_months !== null) {
				if (!in_array($valid_months, [36, 60], true)) {
					throw new ApiException('Validity must be 36 or 60 months', 422);
				}
				$r->setValidMonths($valid_months);
			}
			if ($notes !== null) {
				$r->setNotes($notes);
			}
			$r = $this->mapper->update($r);
			$this->audit->diff('fz', $r->getId(), $before, $r->jsonSerialize());
			return $r->jsonSerialize();
		});
	}

	#[NoAdminRequired]
	public function destroy(int $id): JSONResponse {
		return $this->wrap(function () use ($id) {
			$this->perm->require('fz.write');
			$this->mapper->delete($this->mapper->find($id));
			$this->audit->diff('fz', $id, null, ['_action' => 'deleted']);
			return ['ok' => true];
		});
	}
}
