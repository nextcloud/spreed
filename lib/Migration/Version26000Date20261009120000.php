<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\Attributes\AddColumn;
use OCP\Migration\Attributes\AddIndex;
use OCP\Migration\Attributes\ColumnType;
use OCP\Migration\Attributes\IndexType;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;
use Override;

/**
 * Power levels and encryption of mirrored Matrix rooms, lookup of mirrored events by chat message
 */
#[AddColumn(table: 'talk_matrix_rooms', name: 'creator', type: ColumnType::STRING)]
#[AddColumn(table: 'talk_matrix_rooms', name: 'encrypted', type: ColumnType::BOOLEAN)]
#[AddColumn(table: 'talk_matrix_rooms', name: 'power_levels', type: ColumnType::TEXT)]
#[AddIndex(table: 'talk_matrix_events', type: IndexType::INDEX, description: 'Find the Matrix event of a chat message')]
class Version26000Date20261009120000 extends SimpleMigrationStep {
	/**
	 * @param IOutput $output
	 * @param Closure(): ISchemaWrapper $schemaClosure
	 * @param array $options
	 * @return null|ISchemaWrapper
	 */
	#[Override]
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		$table = $schema->getTable('talk_matrix_rooms');
		if ($table->hasColumn('power_levels')) {
			return null;
		}
		$table->addColumn('creator', Types::STRING, ['notnull' => false, 'length' => 255]);
		$table->addColumn('encrypted', Types::BOOLEAN, ['notnull' => false, 'default' => false]);
		$table->addColumn('power_levels', Types::TEXT, ['notnull' => false]);

		$schema->getTable('talk_matrix_events')->addIndex(['comment_id'], 'tme_comment');
		return $schema;
	}
}
