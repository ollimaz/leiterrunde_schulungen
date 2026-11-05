<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\LeiterrundeSchulungen\Controller\Api;

use OCA\Leiterrunde\AppInfo\Application;
use OCA\Leiterrunde\Controller\Api\ApiController;
use OCA\Leiterrunde\Db\PersonMapper;
use OCA\Leiterrunde\Exception\ApiException;
use OCA\Leiterrunde\Service\PermissionService;
use OCA\LeiterrundeSchulungen\Service\TrainingStatusService;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

class TrainingController extends ApiController {
	public function __construct(
		IRequest $request,
		LoggerInterface $logger,
		private PermissionService $perm,
		private PersonMapper $personMapper,
		private TrainingStatusService $statusService,
	) {
		parent::__construct(Application::APP_ID, $request, $logger);
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function status(): JSONResponse {
		return $this->wrap(function () {
			$this->perm->require('training.check');
			return $this->statusService->statusMatrix(new \DateTimeImmutable('today'));
		});
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function check(int $person_id, ?string $date = null): JSONResponse {
		return $this->wrap(function () use ($person_id, $date) {
			$this->perm->require('training.check');
			$person = $this->personMapper->find($person_id);
			$at = $date !== null && $date !== ''
				? \DateTimeImmutable::createFromFormat('!Y-m-d', $date)
				: new \DateTimeImmutable('today');
			if ($at === false) {
				throw new ApiException('Invalid date', 400);
			}
			return $this->statusService->betreuerCheck($person, $at, $this->perm->has('fz.read'));
		});
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function personStatus(int $id): JSONResponse {
		return $this->wrap(function () use ($id) {
			$person = $this->personMapper->find($id);
			$own = $this->perm->currentPerson()?->getId() === $person->getId();
			if (!$own) {
				$this->perm->require('training.check');
			}
			$today = new \DateTimeImmutable('today');
			$status = $this->statusService->personStatus($person, $today);
			$fz = $this->statusService->fzStatus($person, $today);
			$status['fz'] = $this->perm->has('fz.read') ? $fz : ['state' => $fz['state']];
			return $status;
		});
	}
}
