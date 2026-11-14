<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\LeiterrundeSchulungen\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class TrainingSessionMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'lr_training_sessions', TrainingSession::class);
	}

	public function find(int $id): TrainingSession {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));
		return $this->findEntity($qb);
	}

	public function findRecent(?string $createdBy = null): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())
			->orderBy('attended_date', 'DESC')->addOrderBy('id', 'DESC')->setMaxResults(500);
		if ($createdBy !== null) {
			$qb->where($qb->expr()->eq('created_by', $qb->createNamedParameter($createdBy)));
		}
		return $this->findEntities($qb);
	}

	public function createSession(array $data, string $uid): TrainingSession {
		$session = new TrainingSession();
		$session->setTypeId($data['type_id']);
		$session->setAttendedDate($data['attended_date']);
		$session->setHours($data['hours']);
		$session->setTitle($data['title']);
		$session->setNotes($data['notes']);
		$session->setCreatedBy($uid);
		$session->setCreatedAt(time());
		return $this->insert($session);
	}
}
