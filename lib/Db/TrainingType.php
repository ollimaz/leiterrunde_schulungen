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
 * @method string getName()
 * @method void setName(string $v)
 * @method int getValidityMonths()
 * @method void setValidityMonths(int $v)
 * @method bool getCountsHours()
 * @method void setCountsHours(bool $v)
 * @method bool getRequired()
 * @method void setRequired(bool $v)
 * @method bool getRequiredForEvent()
 * @method void setRequiredForEvent(bool $v)
 * @method int getSort()
 * @method void setSort(int $v)
 * @method bool getActive()
 * @method void setActive(bool $v)
 */
class TrainingType extends BaseEntity {
	protected string $name = '';
	protected int $validityMonths = 0;
	protected bool $countsHours = false;
	protected bool $required = false;
	protected bool $requiredForEvent = false;
	protected int $sort = 0;
	protected bool $active = true;

	public function __construct() {
		$this->addType('validityMonths', 'integer');
		$this->addType('countsHours', 'boolean');
		$this->addType('required', 'boolean');
		$this->addType('requiredForEvent', 'boolean');
		$this->addType('sort', 'integer');
		$this->addType('active', 'boolean');
	}
}
