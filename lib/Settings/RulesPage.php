<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\LeiterrundeSchulungen\Settings;

use OCP\AppFramework\Http\TemplateResponse;
use OCP\Settings\IDelegatedSettings;

class RulesPage implements IDelegatedSettings {
	public function getForm(): TemplateResponse {
		return new TemplateResponse('leiterrunde_schulungen', 'settings-rules', []);
	}

	public function getSection(): string {
		return 'leiterrunde';
	}

	public function getPriority(): int {
		return 50;
	}

	public function getName(): ?string {
		return null;
	}

	public function getAuthorizedAppConfig(): array {
		return [];
	}
}
