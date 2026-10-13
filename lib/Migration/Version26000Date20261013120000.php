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
use OCP\Migration\Attributes\CreateTable;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;
use Override;

/**
 * Megolm sessions to send messages to end-to-end encrypted Matrix rooms
 */
#[CreateTable(table: 'talk_matrix_megolm_out', columns: ['id', 'account_id', 'room_id', 'pickle', 'shared_with'], description: 'Megolm sessions to encrypt messages of a Matrix account')]
class Version26000Date20261013120000 extends SimpleMigrationStep {
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

		if ($schema->hasTable('talk_matrix_megolm_out')) {
			return null;
		}

		$table = $schema->createTable('talk_matrix_megolm_out');
		$table->addColumn('id', Types::BIGINT, ['notnull' => true, 'unsigned' => true, 'length' => 20]);
		$table->addColumn('account_id', Types::BIGINT, ['notnull' => true, 'unsigned' => true, 'length' => 20]);
		$table->addColumn('room_id', Types::STRING, ['notnull' => true, 'length' => 255]);
		$table->addColumn('pickle', Types::TEXT, ['notnull' => true]);
		$table->addColumn('shared_with', Types::TEXT, ['notnull' => true]);
		$table->setPrimaryKey(['id']);
		$table->addUniqueIndex(['account_id', 'room_id'], 'tmmo_room');

		return $schema;
	}
}
