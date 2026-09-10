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
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;
use Override;

/**
 * Matrix E2EE key storage (phase 2): devices, Olm sessions, Megolm sessions, secrets.
 * All pickles are encrypted with the instance secret before they hit these tables.
 */
class Version25000Date20260830120000 extends SimpleMigrationStep {
	#[Override]
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('talk_matrix_devices')) {
			$table = $schema->createTable('talk_matrix_devices');
			$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'unsigned' => true, 'length' => 20]);
			$table->addColumn('account_id', Types::BIGINT, ['notnull' => true, 'unsigned' => true, 'length' => 20]);
			$table->addColumn('mxid', Types::STRING, ['notnull' => true, 'length' => 255]);
			$table->addColumn('device_id', Types::STRING, ['notnull' => true, 'length' => 255]);
			$table->addColumn('curve25519_key', Types::STRING, ['notnull' => true, 'length' => 64]);
			$table->addColumn('ed25519_key', Types::STRING, ['notnull' => true, 'length' => 64]);
			$table->addColumn('keys_json', Types::TEXT, ['notnull' => true]);
			$table->addColumn('trust', Types::SMALLINT, ['notnull' => true, 'default' => 0]);
			$table->addColumn('stale', Types::BOOLEAN, ['notnull' => false, 'default' => 0]);
			$table->addColumn('updated_at', Types::DATETIME, ['notnull' => false, 'default' => null]);
			$table->setPrimaryKey(['id']);
			$table->addUniqueIndex(['account_id', 'mxid', 'device_id'], 'tmd_acc_user_dev');
			$table->addIndex(['account_id', 'mxid'], 'tmd_acc_user');
		}

		if (!$schema->hasTable('talk_matrix_olm_sessions')) {
			$table = $schema->createTable('talk_matrix_olm_sessions');
			$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'unsigned' => true, 'length' => 20]);
			$table->addColumn('account_id', Types::BIGINT, ['notnull' => true, 'unsigned' => true, 'length' => 20]);
			$table->addColumn('their_curve25519', Types::STRING, ['notnull' => true, 'length' => 64]);
			$table->addColumn('session_id', Types::STRING, ['notnull' => true, 'length' => 64]);
			$table->addColumn('pickle', Types::TEXT, ['notnull' => true]);
			$table->addColumn('last_used', Types::DATETIME, ['notnull' => false, 'default' => null]);
			$table->setPrimaryKey(['id']);
			$table->addUniqueIndex(['account_id', 'session_id'], 'tmos_acc_session');
			$table->addIndex(['account_id', 'their_curve25519'], 'tmos_acc_their');
		}

		if (!$schema->hasTable('talk_matrix_megolm_in')) {
			$table = $schema->createTable('talk_matrix_megolm_in');
			$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'unsigned' => true, 'length' => 20]);
			$table->addColumn('account_id', Types::BIGINT, ['notnull' => true, 'unsigned' => true, 'length' => 20]);
			$table->addColumn('matrix_room_id', Types::STRING, ['notnull' => true, 'length' => 255]);
			$table->addColumn('session_id', Types::STRING, ['notnull' => true, 'length' => 64]);
			$table->addColumn('sender_key', Types::STRING, ['notnull' => true, 'length' => 64, 'default' => '']);
			$table->addColumn('pickle', Types::TEXT, ['notnull' => true]);
			$table->addColumn('first_known_index', Types::BIGINT, ['notnull' => true, 'default' => 0]);
			$table->addColumn('forwarding_chains', Types::TEXT, ['notnull' => false, 'default' => null]);
			$table->addColumn('imported_from', Types::STRING, ['notnull' => true, 'length' => 16, 'default' => 'to-device']);
			$table->addColumn('created_at', Types::DATETIME, ['notnull' => false, 'default' => null]);
			$table->setPrimaryKey(['id']);
			$table->addUniqueIndex(['account_id', 'matrix_room_id', 'session_id'], 'tmmi_acc_room_session');
			$table->addIndex(['matrix_room_id', 'session_id'], 'tmmi_room_session');
		}

		if (!$schema->hasTable('talk_matrix_megolm_out')) {
			$table = $schema->createTable('talk_matrix_megolm_out');
			$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'unsigned' => true, 'length' => 20]);
			$table->addColumn('account_id', Types::BIGINT, ['notnull' => true, 'unsigned' => true, 'length' => 20]);
			$table->addColumn('matrix_room_id', Types::STRING, ['notnull' => true, 'length' => 255]);
			$table->addColumn('session_id', Types::STRING, ['notnull' => true, 'length' => 64]);
			$table->addColumn('pickle', Types::TEXT, ['notnull' => true]);
			$table->addColumn('created_at', Types::BIGINT, ['notnull' => true, 'default' => 0]);
			$table->addColumn('message_count', Types::INTEGER, ['notnull' => true, 'default' => 0]);
			$table->addColumn('shared_with', Types::TEXT, ['notnull' => false, 'default' => null]);
			$table->setPrimaryKey(['id']);
			$table->addUniqueIndex(['account_id', 'matrix_room_id'], 'tmmo_acc_room');
		}

		if (!$schema->hasTable('talk_matrix_secrets')) {
			$table = $schema->createTable('talk_matrix_secrets');
			$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'unsigned' => true, 'length' => 20]);
			$table->addColumn('account_id', Types::BIGINT, ['notnull' => true, 'unsigned' => true, 'length' => 20]);
			$table->addColumn('name', Types::STRING, ['notnull' => true, 'length' => 128]);
			$table->addColumn('value_enc', Types::TEXT, ['notnull' => false, 'default' => null]);
			$table->addColumn('updated_at', Types::DATETIME, ['notnull' => false, 'default' => null]);
			$table->setPrimaryKey(['id']);
			$table->addUniqueIndex(['account_id', 'name'], 'tms_acc_name');
		}

		if (!$schema->hasTable('talk_matrix_cross_signing')) {
			$table = $schema->createTable('talk_matrix_cross_signing');
			$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'unsigned' => true, 'length' => 20]);
			$table->addColumn('account_id', Types::BIGINT, ['notnull' => true, 'unsigned' => true, 'length' => 20]);
			$table->addColumn('mxid', Types::STRING, ['notnull' => true, 'length' => 255]);
			$table->addColumn('master_key', Types::STRING, ['notnull' => false, 'length' => 64, 'default' => null]);
			$table->addColumn('self_signing_key', Types::STRING, ['notnull' => false, 'length' => 64, 'default' => null]);
			$table->addColumn('user_signing_key', Types::STRING, ['notnull' => false, 'length' => 64, 'default' => null]);
			$table->addColumn('updated_at', Types::DATETIME, ['notnull' => false, 'default' => null]);
			$table->setPrimaryKey(['id']);
			$table->addUniqueIndex(['account_id', 'mxid'], 'tmcs_acc_user');
		}

		return $schema;
	}
}
