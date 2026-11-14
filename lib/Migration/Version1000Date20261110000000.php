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

class Version1000Date20261110000000 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		$schema = $schemaClosure();
		if (!$schema->hasTable('lr_training_sessions')) {
			$table = $schema->createTable('lr_training_sessions');
			$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'unsigned' => true]);
			$table->addColumn('type_id', Types::BIGINT, ['notnull' => true]);
			$table->addColumn('attended_date', Types::DATE, ['notnull' => true]);
			$table->addColumn('hours', Types::DECIMAL, ['notnull' => true, 'precision' => 6, 'scale' => 2]);
			$table->addColumn('title', Types::STRING, ['notnull' => true, 'length' => 255]);
			$table->addColumn('notes', Types::TEXT, ['notnull' => false]);
			$table->addColumn('created_by', Types::STRING, ['notnull' => true, 'length' => 64]);
			$table->addColumn('created_at', Types::BIGINT, ['notnull' => true]);
			$table->setPrimaryKey(['id']);
			$table->addIndex(['created_by'], 'lr_ts_creator_idx');
		}
		$records = $schema->getTable('lr_training_recs');
		if (!$records->hasColumn('session_id')) {
			$records->addColumn('session_id', Types::BIGINT, ['notnull' => false]);
			$records->addUniqueIndex(['session_id', 'person_id'], 'lr_trec_session_person_idx');
		}
		return $schema;
	}
}
