<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\LeiterrundeSchulungen\AppInfo;

use OCA\Leiterrunde\Module\RegisterModulesEvent;
use OCA\LeiterrundeSchulungen\Listener\RegisterSchulungenModule;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;

class Application extends App implements IBootstrap {
	public const APP_ID = 'leiterrunde_schulungen';
	public const CORE_APP_ID = 'leiterrunde';

	public function __construct() {
		parent::__construct(self::APP_ID);
	}

	public function register(IRegistrationContext $context): void {
		// Core-Klassen nur als Strings referenzieren (Core könnte fehlen)
		$context->registerEventListener(
			'OCA\Leiterrunde\Module\RegisterModulesEvent',
			RegisterSchulungenModule::class
		);
		$context->registerDashboardWidget(\OCA\LeiterrundeSchulungen\Dashboard\SchulungsstandWidget::class);
		$context->registerDashboardWidget(\OCA\LeiterrundeSchulungen\Dashboard\NachweiseWidget::class);
	}

	public function boot(IBootContext $context): void {
	}
}
