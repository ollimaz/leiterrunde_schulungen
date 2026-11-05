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

/** @extends QBMapper<TrainingRecord> */
class TrainingRecordMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'lr_training_recs', TrainingRecord::class);
	}

	public function find(int $id): TrainingRecord {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));
		return $this->findEntity($qb);
	}

	/** @return TrainingRecord[] */
	public function findFiltered(?int $personId = null, ?int $typeId = null, ?string $status = null, int $limit = 500): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())
			->orderBy('attended_date', 'DESC')->addOrderBy('id', 'DESC')->setMaxResults($limit);
		if ($personId !== null) {
			$qb->andWhere($qb->expr()->eq('person_id', $qb->createNamedParameter($personId, IQueryBuilder::PARAM_INT)));
		}
		if ($typeId !== null) {
			$qb->andWhere($qb->expr()->eq('type_id', $qb->createNamedParameter($typeId, IQueryBuilder::PARAM_INT)));
		}
		if ($status !== null && $status !== '') {
			$qb->andWhere($qb->expr()->eq('status', $qb->createNamedParameter($status)));
		}
		return $this->findEntities($qb);
	}

	/** @return TrainingRecord[] approved records of person, newest first */
	public function findApprovedForPerson(int $personId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())
			->where($qb->expr()->eq('person_id', $qb->createNamedParameter($personId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('status', $qb->createNamedParameter('approved')))
			->orderBy('attended_date', 'DESC');
		return $this->findEntities($qb);
	}
}
