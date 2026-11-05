<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\LeiterrundeSchulungen\Controller\Api;

use OCA\Leiterrunde\Controller\Api\ApiController;
use OCA\Leiterrunde\Exception\ApiException;
use OCA\Leiterrunde\Service\PermissionService;
use OCA\LeiterrundeSchulungen\AppInfo\Application;
use OCA\LeiterrundeSchulungen\Service\ModuleConfigService;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

class SettingsController extends ApiController {
	private const KEYS = ['warn_days', 'hours_required', 'hours_window_months', 'scope_statuses'];

	public function __construct(
		IRequest $request,
		LoggerInterface $logger,
		private PermissionService $perm,
		private ModuleConfigService $config,
	) {
		parent::__construct(Application::APP_ID, $request, $logger);
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function show(): JSONResponse {
		return $this->wrap(function () {
			$this->perm->require('settings.manage');
			return $this->config->all();
		});
	}

	#[NoAdminRequired]
	public function update(): JSONResponse {
		return $this->wrap(function () {
			$this->perm->require('settings.manage');
			foreach ($this->request->getParams() as $k => $v) {
				if (str_starts_with((string)$k, "_")) { continue; }
				if (!in_array($k, self::KEYS, true)) {
					throw new ApiException('Unbekannte Einstellung: ' . $k, 400);
				}
				$this->config->set($k, (string)$v);
			}
			return $this->config->all();
		});
	}
}
