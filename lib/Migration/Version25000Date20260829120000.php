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
 * Matrix rooms in Talk – phase 1 tables (homeservers, linked accounts, room,
 * member and event mappings). E2EE key storage follows in a later step.
 */
class Version25000Date20260829120000 extends SimpleMigrationStep {
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

		if (!$schema->hasTable('talk_matrix_homeservers')) {
			$table = $schema->createTable('talk_matrix_homeservers');
			$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'unsigned' => true, 'length' => 20]);
			$table->addColumn('name', Types::STRING, ['notnull' => true, 'length' => 64]);
			$table->addColumn('server_name', Types::STRING, ['notnull' => true, 'length' => 255]);
			$table->addColumn('base_url', Types::STRING, ['notnull' => true, 'length' => 255]);
			$table->addColumn('enabled', Types::BOOLEAN, ['notnull' => false, 'default' => 1]);
			$table->addColumn('allow_e2ee', Types::BOOLEAN, ['notnull' => false, 'default' => 1]);
			$table->addColumn('allow_upload', Types::BOOLEAN, ['notnull' => false, 'default' => 1]);
			$table->addColumn('versions_json', Types::TEXT, ['notnull' => false, 'default' => null]);
			$table->addColumn('versions_fetched', Types::DATETIME, ['notnull' => false, 'default' => null]);
			$table->setPrimaryKey(['id']);
			$table->addUniqueIndex(['server_name'], 'tmh_server_name');
		}

		if (!$schema->hasTable('talk_matrix_accounts')) {
			$table = $schema->createTable('talk_matrix_accounts');
			$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'unsigned' => true, 'length' => 20]);
			$table->addColumn('user_id', Types::STRING, ['notnull' => true, 'length' => 64]);
			$table->addColumn('homeserver_id', Types::BIGINT, ['notnull' => true, 'unsigned' => true, 'length' => 20]);
			$table->addColumn('mxid', Types::STRING, ['notnull' => true, 'length' => 255]);
			$table->addColumn('access_token', Types::TEXT, ['notnull' => false, 'default' => null]);
			$table->addColumn('device_id', Types::STRING, ['notnull' => true, 'length' => 64, 'default' => '']);
			$table->addColumn('next_batch', Types::STRING, ['notnull' => false, 'length' => 255, 'default' => null]);
			$table->addColumn('filter_id', Types::STRING, ['notnull' => false, 'length' => 64, 'default' => null]);
			$table->addColumn('status', Types::SMALLINT, ['notnull' => true, 'default' => 0]);
			$table->addColumn('last_sync', Types::DATETIME, ['notnull' => false, 'default' => null]);
			$table->addColumn('last_activity', Types::DATETIME, ['notnull' => false, 'default' => null]);
			$table->addColumn('last_error', Types::TEXT, ['notnull' => false, 'default' => null]);
			$table->addColumn('lock_until', Types::DATETIME, ['notnull' => false, 'default' => null]);
			$table->addColumn('olm_account', Types::TEXT, ['notnull' => false, 'default' => null]);
			$table->addColumn('cross_signing', Types::TEXT, ['notnull' => false, 'default' => null]);
			$table->addColumn('created_at', Types::DATETIME, ['notnull' => false, 'default' => null]);
			$table->setPrimaryKey(['id']);
			$table->addUniqueIndex(['user_id'], 'tma_user_id');
			$table->addIndex(['mxid'], 'tma_mxid');
			$table->addIndex(['homeserver_id'], 'tma_homeserver');
		}

		if (!$schema->hasTable('talk_matrix_rooms')) {
			$table = $schema->createTable('talk_matrix_rooms');
			$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'unsigned' => true, 'length' => 20]);
			$table->addColumn('room_id', Types::BIGINT, ['notnull' => true, 'unsigned' => true, 'length' => 20]);
			$table->addColumn('matrix_room_id', Types::STRING, ['notnull' => true, 'length' => 255]);
			$table->addColumn('room_version', Types::STRING, ['notnull' => true, 'length' => 16, 'default' => '1']);
			$table->addColumn('encrypted', Types::BOOLEAN, ['notnull' => false, 'default' => 0]);
			$table->addColumn('encryption_algo', Types::STRING, ['notnull' => false, 'length' => 64, 'default' => null]);
			$table->addColumn('rotation_period_ms', Types::BIGINT, ['notnull' => false, 'default' => null]);
			$table->addColumn('rotation_period_msgs', Types::INTEGER, ['notnull' => false, 'default' => null]);
			$table->addColumn('join_rule', Types::STRING, ['notnull' => true, 'length' => 32, 'default' => 'invite']);
			$table->addColumn('is_direct', Types::BOOLEAN, ['notnull' => false, 'default' => 0]);
			$table->addColumn('canonical_alias', Types::STRING, ['notnull' => false, 'length' => 255, 'default' => null]);
			$table->addColumn('power_levels', Types::TEXT, ['notnull' => false, 'default' => null]);
			$table->addColumn('creator', Types::STRING, ['notnull' => true, 'length' => 255, 'default' => '']);
			$table->addColumn('tombstone_target', Types::STRING, ['notnull' => false, 'length' => 255, 'default' => null]);
			$table->addColumn('prev_batch', Types::STRING, ['notnull' => false, 'length' => 255, 'default' => null]);
			$table->addColumn('backfill_done', Types::BOOLEAN, ['notnull' => false, 'default' => 0]);
			$table->addColumn('capabilities', Types::TEXT, ['notnull' => false, 'default' => null]);
			$table->addColumn('state_updated', Types::DATETIME, ['notnull' => false, 'default' => null]);
			$table->setPrimaryKey(['id']);
			$table->addUniqueIndex(['room_id'], 'tmr_room_id');
			$table->addUniqueIndex(['matrix_room_id'], 'tmr_matrix_room_id');
		}

		if (!$schema->hasTable('talk_matrix_members')) {
			$table = $schema->createTable('talk_matrix_members');
			$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'unsigned' => true, 'length' => 20]);
			$table->addColumn('matrix_room_id', Types::STRING, ['notnull' => true, 'length' => 255]);
			$table->addColumn('mxid', Types::STRING, ['notnull' => true, 'length' => 255]);
			$table->addColumn('membership', Types::STRING, ['notnull' => true, 'length' => 16, 'default' => 'leave']);
			$table->addColumn('display_name', Types::STRING, ['notnull' => false, 'length' => 255, 'default' => null]);
			$table->addColumn('avatar_url', Types::STRING, ['notnull' => false, 'length' => 255, 'default' => null]);
			$table->addColumn('power_level', Types::INTEGER, ['notnull' => true, 'default' => 0]);
			$table->addColumn('attendee_id', Types::BIGINT, ['notnull' => false, 'unsigned' => true, 'length' => 20, 'default' => null]);
			$table->addColumn('account_id', Types::BIGINT, ['notnull' => false, 'unsigned' => true, 'length' => 20, 'default' => null]);
			$table->addColumn('updated_at', Types::DATETIME, ['notnull' => false, 'default' => null]);
			$table->setPrimaryKey(['id']);
			$table->addUniqueIndex(['matrix_room_id', 'mxid'], 'tmm_room_mxid');
			$table->addIndex(['account_id'], 'tmm_account');
		}

		if (!$schema->hasTable('talk_matrix_events')) {
			$table = $schema->createTable('talk_matrix_events');
			$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'unsigned' => true, 'length' => 20]);
			$table->addColumn('matrix_room_id', Types::STRING, ['notnull' => true, 'length' => 255]);
			$table->addColumn('event_id', Types::STRING, ['notnull' => true, 'length' => 255]);
			$table->addColumn('event_type', Types::STRING, ['notnull' => true, 'length' => 128]);
			$table->addColumn('comment_id', Types::BIGINT, ['notnull' => false, 'unsigned' => true, 'length' => 20, 'default' => null]);
			$table->addColumn('txn_id', Types::STRING, ['notnull' => false, 'length' => 64, 'default' => null]);
			$table->addColumn('origin_ts', Types::BIGINT, ['notnull' => true, 'default' => 0]);
			$table->addColumn('sender', Types::STRING, ['notnull' => true, 'length' => 255, 'default' => '']);
			$table->addColumn('relates_to', Types::STRING, ['notnull' => false, 'length' => 255, 'default' => null]);
			$table->addColumn('rel_type', Types::STRING, ['notnull' => false, 'length' => 64, 'default' => null]);
			$table->addColumn('encrypted', Types::BOOLEAN, ['notnull' => false, 'default' => 0]);
			$table->addColumn('ciphertext', Types::TEXT, ['notnull' => false, 'default' => null]);
			$table->addColumn('decrypt_state', Types::SMALLINT, ['notnull' => true, 'default' => 0]);
			$table->addColumn('session_id', Types::STRING, ['notnull' => false, 'length' => 255, 'default' => null]);
			$table->addColumn('processed', Types::BOOLEAN, ['notnull' => false, 'default' => 1]);
			$table->setPrimaryKey(['id']);
			$table->addUniqueIndex(['event_id'], 'tme_event_id');
			$table->addIndex(['matrix_room_id', 'origin_ts'], 'tme_room_ts');
			$table->addIndex(['comment_id'], 'tme_comment');
			$table->addIndex(['relates_to'], 'tme_relates_to');
		}

		return $schema;
	}
}
