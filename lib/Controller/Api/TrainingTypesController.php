<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\LeiterrundeSchulungen\Controller\Api;

use OCA\Leiterrunde\AppInfo\Application;
use OCA\Leiterrunde\Controller\Api\ApiController;
use OCA\LeiterrundeSchulungen\Db\TrainingType;
use OCA\LeiterrundeSchulungen\Db\TrainingTypeMapper;
use OCA\Leiterrunde\Exception\ApiException;
use OCA\Leiterrunde\Service\PermissionService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

class TrainingTypesController extends ApiController {
	public function __construct(
		IRequest $request,
		LoggerInterface $logger,
		private PermissionService $perm,
		private TrainingTypeMapper $mapper,
	) {
		parent::__construct(Application::APP_ID, $request, $logger);
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function index(): JSONResponse {
		return $this->wrap(function () {
			$this->perm->require('app.access');
			return array_map(static fn ($t) => $t->jsonSerialize(), $this->mapper->findAll());
		});
	}

	#[NoAdminRequired]
	public function create(
		string $name,
		int $validity_months = 0,
		bool $counts_hours = false,
		bool $required = false,
		bool $required_for_event = false,
		int $sort = 0,
		bool $active = true,
	): JSONResponse {
		return $this->wrap(function () use ($name, $validity_months, $counts_hours, $required, $required_for_event, $sort, $active) {
			$this->perm->require('training.types.write');
			if (trim($name) === '' || $validity_months < 0) {
				throw new ApiException('Name required, validity >= 0', 422);
			}
			$t = new TrainingType();
			$t->setName(trim($name));
			$t->setValidityMonths($validity_months);
			$t->setCountsHours($counts_hours);
			$t->setRequired($required);
			$t->setRequiredForEvent($required_for_event);
			$t->setSort($sort);
			$t->setActive($active);
			return new JSONResponse($this->mapper->insert($t)->jsonSerialize(), Http::STATUS_CREATED);
		});
	}

	#[NoAdminRequired]
	public function update(
		int $id,
		?string $name = null,
		?int $validity_months = null,
		?bool $counts_hours = null,
		?bool $required = null,
		?bool $required_for_event = null,
		?int $sort = null,
		?bool $active = null,
	): JSONResponse {
		return $this->wrap(function () use ($id, $name, $validity_months, $counts_hours, $required, $required_for_event, $sort, $active) {
			$this->perm->require('training.types.write');
			$t = $this->mapper->find($id);
			if ($name !== null) {
				$t->setName(trim($name));
			}
			if ($validity_months !== null) {
				$t->setValidityMonths($validity_months);
			}
			if ($counts_hours !== null) {
				$t->setCountsHours($counts_hours);
			}
			if ($required !== null) {
				$t->setRequired($required);
			}
			if ($required_for_event !== null) {
				$t->setRequiredForEvent($required_for_event);
			}
			if ($sort !== null) {
				$t->setSort($sort);
			}
			if ($active !== null) {
				$t->setActive($active);
			}
			if ($t->getName() === '' || $t->getValidityMonths() < 0) {
				throw new ApiException('Invalid data', 422);
			}
			return $this->mapper->update($t)->jsonSerialize();
		});
	}

	#[NoAdminRequired]
	public function destroy(int $id): JSONResponse {
		return $this->wrap(function () use ($id) {
			$this->perm->require('training.types.write');
			$this->mapper->delete($this->mapper->find($id));
			return ['ok' => true];
		});
	}
}
