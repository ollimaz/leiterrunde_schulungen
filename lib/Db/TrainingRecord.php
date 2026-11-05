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
 * @method int getTypeId()
 * @method void setTypeId(int $v)
 * @method string getAttendedDate()
 * @method void setAttendedDate(string $v)
 * @method string getHours()
 * @method void setHours(string $v)
 * @method ?string getTitle()
 * @method void setTitle(?string $v)
 * @method ?string getNotes()
 * @method void setNotes(?string $v)
 * @method ?int getFileId()
 * @method void setFileId(?int $v)
 * @method ?string getFileOwner()
 * @method void setFileOwner(?string $v)
 * @method ?string getFileName()
 * @method void setFileName(?string $v)
 * @method string getStatus()
 * @method void setStatus(string $v)
 * @method ?string getDecidedBy()
 * @method void setDecidedBy(?string $v)
 * @method ?int getDecidedAt()
 * @method void setDecidedAt(?int $v)
 * @method ?string getDecisionNote()
 * @method void setDecisionNote(?string $v)
 * @method string getCreatedBy()
 * @method void setCreatedBy(string $v)
 * @method int getCreatedAt()
 * @method void setCreatedAt(int $v)
 */
class TrainingRecord extends BaseEntity {
	protected int $personId = 0;
	protected int $typeId = 0;
	protected string $attendedDate = '';
	protected string $hours = '0';
	protected ?string $title = null;
	protected ?string $notes = null;
	protected ?int $fileId = null;
	protected ?string $fileOwner = null;
	protected ?string $fileName = null;
	protected string $status = 'pending';
	protected ?string $decidedBy = null;
	protected ?int $decidedAt = null;
	protected ?string $decisionNote = null;
	protected string $createdBy = '';
	protected int $createdAt = 0;

	public function __construct() {
		$this->addType('personId', 'integer');
		$this->addType('typeId', 'integer');
		$this->addType('fileId', 'integer');
		$this->addType('decidedAt', 'integer');
		$this->addType('createdAt', 'integer');
	}
}
