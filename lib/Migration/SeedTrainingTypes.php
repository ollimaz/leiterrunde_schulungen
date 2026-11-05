<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\LeiterrundeSchulungen\Migration;

use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;

class SeedTrainingTypes implements IRepairStep {
	public function __construct(
		private IDBConnection $db,
	) {
	}

	public function getName(): string {
		return 'Leiterrunde-Schulungstypen anlegen';
	}

	public function run(IOutput $output): void {
		$qb = $this->db->getQueryBuilder();
		$qb->select('id')->from('lr_training_types')->setMaxResults(1);
		if ($qb->executeQuery()->fetch() !== false) {
			return;
		}
		$insert = $this->db->getQueryBuilder();
		$insert->insert('lr_training_types')
			->values([
				'name' => $insert->createParameter('name'),
				'validity_months' => $insert->createParameter('validity'),
				'counts_hours' => $insert->createParameter('counts'),
				'required' => $insert->createParameter('req'),
				'required_for_event' => $insert->createParameter('reqev'),
				'sort' => $insert->createParameter('sort'),
				'active' => $insert->createParameter('act'),
			]);
		$seed = [
			['Erste Hilfe', 24, false, true, true, 1],
			['Fortbildung / Schulung', 0, true, false, false, 2],
		];
		foreach ($seed as [$name, $validity, $counts, $req, $reqev, $sort]) {
			$insert->setParameter('name', $name)
				->setParameter('validity', $validity)
				->setParameter('counts', $counts, IQueryBuilder::PARAM_BOOL)
				->setParameter('req', $req, IQueryBuilder::PARAM_BOOL)
				->setParameter('reqev', $reqev, IQueryBuilder::PARAM_BOOL)
				->setParameter('sort', $sort)
				->setParameter('act', true, IQueryBuilder::PARAM_BOOL);
			$insert->executeStatement();
		}
		$output->info('Schulungstypen angelegt');
	}
}
