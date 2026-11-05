<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\LeiterrundeSchulungen\Db;

use OCA\Leiterrunde\Db\BaseEntity;

use OCP\AppFramework\Db\Entity;

/**
 * @method int getPersonId()
 * @method void setPersonId(int $v)
 * @method string getIssuedAt()
 * @method void setIssuedAt(string $v)
 * @method int getValidMonths()
 * @method void setValidMonths(int $v)
 * @method ?string getNotes()
 * @method void setNotes(?string $v)
 * @method string getCreatedBy()
 * @method void setCreatedBy(string $v)
 * @method int getCreatedAt()
 * @method void setCreatedAt(int $v)
 */
class FzRecord extends BaseEntity {
	protected int $personId = 0;
	protected string $issuedAt = '';
	protected int $validMonths = 36;
	protected ?string $notes = null;
	protected string $createdBy = '';
	protected int $createdAt = 0;

	public function __construct() {
		$this->addType('personId', 'integer');
		$this->addType('validMonths', 'integer');
		$this->addType('createdAt', 'integer');
	}
}
