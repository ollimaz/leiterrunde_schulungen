<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\LeiterrundeSchulungen\Db;

use OCA\Leiterrunde\Db\BaseEntity;

class TrainingSession extends BaseEntity {
	protected int $typeId = 0;
	protected string $attendedDate = '';
	protected string $hours = '0';
	protected string $title = '';
	protected ?string $notes = null;
	protected string $createdBy = '';
	protected int $createdAt = 0;

	public function __construct() {
		$this->addType('typeId', 'integer');
		$this->addType('createdAt', 'integer');
	}
}
