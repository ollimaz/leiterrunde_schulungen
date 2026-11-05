<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\LeiterrundeSchulungen\Service;

use OCA\LeiterrundeSchulungen\AppInfo\Application;
use OCP\IAppConfig;

class ModuleConfigService {
	private const DEFAULTS = [
		'warn_days' => '60',
		'hours_required' => '8',
		'hours_window_months' => '36',
		'scope_statuses' => 'aktiv,nwl',
	];

	public function __construct(private IAppConfig $appConfig) {
	}

	public function get(string $key): string {
		return $this->appConfig->getValueString(Application::APP_ID, $key, self::DEFAULTS[$key] ?? '');
	}

	public function set(string $key, string $value): void {
		$this->appConfig->setValueString(Application::APP_ID, $key, $value);
	}

	public function all(): array {
		$out = [];
		foreach (self::DEFAULTS as $k => $d) {
			$out[$k] = $this->get($k);
		}
		return $out;
	}
}
