<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\LeiterrundeSchulungen\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version1000Date20261105000000 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('lr_training_types')) {
			$t = $schema->createTable('lr_training_types');
			$t->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'unsigned' => true]);
			$t->addColumn('name', Types::STRING, ['notnull' => true, 'length' => 200]);
			$t->addColumn('validity_months', Types::INTEGER, ['notnull' => true, 'default' => 0]);
			$t->addColumn('counts_hours', Types::BOOLEAN, ['notnull' => true, 'default' => false]);
			$t->addColumn('required', Types::BOOLEAN, ['notnull' => true, 'default' => false]);
			$t->addColumn('required_for_event', Types::BOOLEAN, ['notnull' => true, 'default' => false]);
			$t->addColumn('sort', Types::INTEGER, ['notnull' => true, 'default' => 0]);
			$t->addColumn('active', Types::BOOLEAN, ['notnull' => true, 'default' => true]);
			$t->setPrimaryKey(['id']);
		}

		if (!$schema->hasTable('lr_training_recs')) {
			$t = $schema->createTable('lr_training_recs');
			$t->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'unsigned' => true]);
			$t->addColumn('person_id', Types::BIGINT, ['notnull' => true]);
			$t->addColumn('type_id', Types::BIGINT, ['notnull' => true]);
			$t->addColumn('attended_date', Types::DATE, ['notnull' => true]);
			$t->addColumn('hours', Types::DECIMAL, ['notnull' => true, 'precision' => 6, 'scale' => 2, 'default' => 0]);
			$t->addColumn('title', Types::STRING, ['notnull' => false, 'length' => 255]);
			$t->addColumn('notes', Types::TEXT, ['notnull' => false]);
			$t->addColumn('file_id', Types::BIGINT, ['notnull' => false]);
			$t->addColumn('file_owner', Types::STRING, ['notnull' => false, 'length' => 64]);
			$t->addColumn('file_name', Types::STRING, ['notnull' => false, 'length' => 255]);
			$t->addColumn('status', Types::STRING, ['notnull' => true, 'length' => 20, 'default' => 'pending']);
			$t->addColumn('decided_by', Types::STRING, ['notnull' => false, 'length' => 64]);
			$t->addColumn('decided_at', Types::BIGINT, ['notnull' => false]);
			$t->addColumn('decision_note', Types::TEXT, ['notnull' => false]);
			$t->addColumn('created_by', Types::STRING, ['notnull' => true, 'length' => 64]);
			$t->addColumn('created_at', Types::BIGINT, ['notnull' => true]);
			$t->setPrimaryKey(['id']);
			$t->addIndex(['person_id'], 'lr_trec_person_idx');
			$t->addIndex(['type_id'], 'lr_trec_type_idx');
			$t->addIndex(['status'], 'lr_trec_status_idx');
		}

		if (!$schema->hasTable('lr_fz')) {
			$t = $schema->createTable('lr_fz');
			$t->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'unsigned' => true]);
			$t->addColumn('person_id', Types::BIGINT, ['notnull' => true]);
			$t->addColumn('issued_at', Types::DATE, ['notnull' => true]);
			$t->addColumn('valid_months', Types::INTEGER, ['notnull' => true, 'default' => 36]);
			$t->addColumn('notes', Types::TEXT, ['notnull' => false]);
			$t->addColumn('created_by', Types::STRING, ['notnull' => false, 'length' => 64]);
			$t->addColumn('created_at', Types::BIGINT, ['notnull' => true]);
			$t->setPrimaryKey(['id']);
			$t->addIndex(['person_id'], 'lr_fz_person_idx');
		}

		return $schema;
	}
}
