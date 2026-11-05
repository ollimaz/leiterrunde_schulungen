<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\LeiterrundeSchulungen\Migration;

use OCA\LeiterrundeSchulungen\AppInfo\Application;
use OCP\IAppConfig;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;

/** Kopiert Modul-Settings aus dem Core-Config-Space (idempotent, nur wenn eigenes leer). */
class MigrateConfig implements IRepairStep {
	private const KEYS = ['warn_days', 'hours_required', 'hours_window_months', 'scope_statuses'];

	public function __construct(private IAppConfig $appConfig) {
	}

	public function getName(): string {
		return 'Leiterrunde Training: migrate settings';
	}

	public function run(IOutput $output): void {
		foreach (self::KEYS as $key) {
			if ($this->appConfig->getValueString(Application::APP_ID, $key, '') !== '') {
				continue;
			}
			$old = $this->appConfig->getValueString('leiterrunde', $key, '');
			if ($old !== '') {
				$this->appConfig->setValueString(Application::APP_ID, $key, $old);
			}
		}
	}
}
