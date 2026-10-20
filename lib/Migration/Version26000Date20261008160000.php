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
use OCP\Migration\Attributes\CreateTable;
use OCP\Migration\Attributes\IndexType;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;
use Override;

/**
 * Sync position of Matrix accounts and the mirrored Matrix rooms with their members
 */
#[AddColumn(table: 'talk_matrix_accounts', name: 'next_batch', type: ColumnType::TEXT)]
#[AddColumn(table: 'talk_matrix_accounts', name: 'filter_id', type: ColumnType::STRING)]
#[AddColumn(table: 'talk_matrix_accounts', name: 'last_sync', type: ColumnType::BIGINT)]
#[AddColumn(table: 'talk_matrix_accounts', name: 'lock_until', type: ColumnType::BIGINT)]
#[AddIndex(table: 'talk_matrix_accounts', type: IndexType::INDEX, description: 'Find accounts of sync due and by Matrix user id')]
#[CreateTable(table: 'talk_matrix_rooms', columns: ['id', 'room_id', 'matrix_room_id', 'name', 'topic', 'canonical_alias'], description: 'Matrix rooms mirrored as conversations')]
#[CreateTable(table: 'talk_matrix_members', columns: ['id', 'matrix_room_id', 'mxid', 'membership', 'display_name', 'account_id'], description: 'Members of the mirrored Matrix rooms')]
class Version26000Date20261008160000 extends SimpleMigrationStep {
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

		$table = $schema->getTable('talk_matrix_accounts');
		if (!$table->hasColumn('next_batch')) {
			$table->addColumn('next_batch', Types::TEXT, ['notnull' => false]);
			$table->addColumn('filter_id', Types::STRING, ['notnull' => false, 'length' => 255]);
			$table->addColumn('last_sync', Types::BIGINT, ['notnull' => true, 'default' => 0, 'unsigned' => true]);
			$table->addColumn('lock_until', Types::BIGINT, ['notnull' => true, 'default' => 0, 'unsigned' => true]);
			$table->addIndex(['status', 'last_sync'], 'tma_due_sync');
			$table->addIndex(['mxid'], 'tma_mxid');
		}

		if (!$schema->hasTable('talk_matrix_rooms')) {
			$table = $schema->createTable('talk_matrix_rooms');
			$table->addColumn('id', Types::BIGINT, ['notnull' => true, 'unsigned' => true, 'length' => 20]);
			$table->addColumn('room_id', Types::BIGINT, ['notnull' => true, 'default' => 0, 'unsigned' => true]);
			$table->addColumn('matrix_room_id', Types::STRING, ['notnull' => true, 'length' => 255]);
			$table->addColumn('name', Types::STRING, ['notnull' => false, 'length' => 255]);
			$table->addColumn('topic', Types::TEXT, ['notnull' => false]);
			$table->addColumn('canonical_alias', Types::STRING, ['notnull' => false, 'length' => 255]);
			$table->setPrimaryKey(['id']);
			$table->addUniqueIndex(['matrix_room_id'], 'tmr_matrix_room');
			$table->addIndex(['room_id'], 'tmr_room');
		}

		if (!$schema->hasTable('talk_matrix_members')) {
			$table = $schema->createTable('talk_matrix_members');
			$table->addColumn('id', Types::BIGINT, ['notnull' => true, 'unsigned' => true, 'length' => 20]);
			$table->addColumn('matrix_room_id', Types::BIGINT, ['notnull' => true, 'unsigned' => true, 'length' => 20]);
			$table->addColumn('mxid', Types::STRING, ['notnull' => true, 'length' => 255]);
			$table->addColumn('membership', Types::STRING, ['notnull' => true, 'length' => 16]);
			$table->addColumn('display_name', Types::STRING, ['notnull' => false, 'length' => 255]);
			$table->addColumn('account_id', Types::BIGINT, ['notnull' => false, 'unsigned' => true, 'length' => 20]);
			$table->setPrimaryKey(['id']);
			$table->addUniqueIndex(['matrix_room_id', 'mxid'], 'tmm_room_mxid');
			$table->addIndex(['account_id'], 'tmm_account');
		}

		return $schema;
	}
}
