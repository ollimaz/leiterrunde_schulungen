<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\LeiterrundeSchulungen\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * lr_fz.created_by was created nullable in Version1000Date20261105000000,
 * but the entity declares it non-null like the sibling tables do.
 */
class Version1000Date20261119000000 extends SimpleMigrationStep {
	public function __construct(private IDBConnection $db) {
	}

	public function preSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();
		if (!$schema->hasTable('lr_fz')) {
			return;
		}
		$u = $this->db->getQueryBuilder();
		$u->update('lr_fz')
			->set('created_by', $u->createNamedParameter(''))
			->where($u->expr()->isNull('created_by'));
		$u->executeStatement();
	}

	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();
		if ($schema->hasTable('lr_fz')) {
			$t = $schema->getTable('lr_fz');
			$t->changeColumn('created_by', ['notnull' => true, 'length' => 64]);
		}
		return $schema;
	}
}
