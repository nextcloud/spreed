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
 * Keys and sessions to decrypt end-to-end encrypted Matrix rooms
 */
#[AddColumn(table: 'talk_matrix_accounts', name: 'olm_account', type: ColumnType::TEXT, description: 'Encrypted keys of the Matrix device')]
#[AddColumn(table: 'talk_matrix_events', name: 'megolm_session', type: ColumnType::STRING)]
#[AddColumn(table: 'talk_matrix_events', name: 'megolm_index', type: ColumnType::BIGINT)]
#[AddColumn(table: 'talk_matrix_events', name: 'ciphertext', type: ColumnType::TEXT, description: 'Encrypted events that wait for their key')]
#[AddIndex(table: 'talk_matrix_events', type: IndexType::INDEX, description: 'Find events of a Megolm session')]
#[CreateTable(table: 'talk_matrix_devices', columns: ['id', 'account_id', 'mxid', 'device_id', 'curve25519', 'ed25519', 'outdated'], description: 'Device keys of Matrix users')]
#[CreateTable(table: 'talk_matrix_olm_sessions', columns: ['id', 'account_id', 'sender_key', 'session_id', 'pickle', 'last_used'], description: 'Olm sessions with other Matrix devices')]
#[CreateTable(table: 'talk_matrix_megolm_in', columns: ['id', 'account_id', 'room_id', 'session_id', 'sender', 'sender_key', 'first_known_index', 'pickle'], description: 'Megolm sessions to decrypt room messages')]
class Version26000Date20261012120000 extends SimpleMigrationStep {
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
		if (!$table->hasColumn('olm_account')) {
			$table->addColumn('olm_account', Types::TEXT, ['notnull' => false]);
		}

		$table = $schema->getTable('talk_matrix_events');
		if (!$table->hasColumn('megolm_session')) {
			$table->addColumn('megolm_session', Types::STRING, ['notnull' => false, 'length' => 64]);
			$table->addColumn('megolm_index', Types::BIGINT, ['notnull' => false, 'unsigned' => true, 'length' => 20]);
			$table->addColumn('ciphertext', Types::TEXT, ['notnull' => false]);
			$table->addIndex(['matrix_room_id', 'megolm_session'], 'tme_megolm');
		}

		if (!$schema->hasTable('talk_matrix_devices')) {
			$table = $schema->createTable('talk_matrix_devices');
			$table->addColumn('id', Types::BIGINT, ['notnull' => true, 'unsigned' => true, 'length' => 20]);
			$table->addColumn('account_id', Types::BIGINT, ['notnull' => true, 'unsigned' => true, 'length' => 20]);
			$table->addColumn('mxid', Types::STRING, ['notnull' => true, 'length' => 255]);
			$table->addColumn('device_id', Types::STRING, ['notnull' => true, 'length' => 255]);
			$table->addColumn('curve25519', Types::STRING, ['notnull' => true, 'length' => 64]);
			$table->addColumn('ed25519', Types::STRING, ['notnull' => true, 'length' => 64]);
			$table->addColumn('outdated', Types::BOOLEAN, ['notnull' => false, 'default' => false]);
			$table->setPrimaryKey(['id']);
			$table->addUniqueIndex(['account_id', 'mxid', 'device_id'], 'tmd_device');
		}

		if (!$schema->hasTable('talk_matrix_olm_sessions')) {
			$table = $schema->createTable('talk_matrix_olm_sessions');
			$table->addColumn('id', Types::BIGINT, ['notnull' => true, 'unsigned' => true, 'length' => 20]);
			$table->addColumn('account_id', Types::BIGINT, ['notnull' => true, 'unsigned' => true, 'length' => 20]);
			$table->addColumn('sender_key', Types::STRING, ['notnull' => true, 'length' => 64]);
			$table->addColumn('session_id', Types::STRING, ['notnull' => true, 'length' => 64]);
			$table->addColumn('pickle', Types::TEXT, ['notnull' => true]);
			$table->addColumn('last_used', Types::BIGINT, ['notnull' => true, 'unsigned' => true, 'length' => 20, 'default' => 0]);
			$table->setPrimaryKey(['id']);
			$table->addUniqueIndex(['account_id', 'sender_key', 'session_id'], 'tmos_session');
		}

		if (!$schema->hasTable('talk_matrix_megolm_in')) {
			$table = $schema->createTable('talk_matrix_megolm_in');
			$table->addColumn('id', Types::BIGINT, ['notnull' => true, 'unsigned' => true, 'length' => 20]);
			$table->addColumn('account_id', Types::BIGINT, ['notnull' => true, 'unsigned' => true, 'length' => 20]);
			$table->addColumn('room_id', Types::STRING, ['notnull' => true, 'length' => 255]);
			$table->addColumn('session_id', Types::STRING, ['notnull' => true, 'length' => 64]);
			$table->addColumn('sender', Types::STRING, ['notnull' => true, 'length' => 255]);
			$table->addColumn('sender_key', Types::STRING, ['notnull' => true, 'length' => 64]);
			$table->addColumn('first_known_index', Types::BIGINT, ['notnull' => true, 'unsigned' => true, 'length' => 20, 'default' => 0]);
			$table->addColumn('pickle', Types::TEXT, ['notnull' => true]);
			$table->setPrimaryKey(['id']);
			$table->addUniqueIndex(['account_id', 'room_id', 'session_id'], 'tmmi_session');
		}

		return $schema;
	}
}
