<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;
use Override;

class Version23000Date20251105125333 extends SimpleMigrationStep {
	/**
	 * @param IOutput $output
	 * @param Closure(): ISchemaWrapper $schemaClosure
	 * @param array $options
	 * @return null|ISchemaWrapper
	 */
	#[Override]
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		$schema = $schemaClosure();
		if (!$schema->hasTable('talk_scheduled_msg')) {
			$table = $schema->createTable('talk_scheduled_msg');
			$table->addColumn('id', Types::BIGINT, [
				'notnull' => true,
				'unsigned' => true,
				'length' => 20
			]);
			$table->addColumn('room_id', Types::BIGINT, [
				'notnull' => true,
				'length' => 20,
			]);
			$table->addColumn('actor_id', Types::STRING, [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('actor_type', Types::STRING, [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('message', Types::TEXT, [
				'notnull' => false,
				'default' => '',
			]);
			$table->addColumn('message_type', Types::STRING, [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('meta_data', Types::TEXT, [
				'notnull' => false,
			]);
			$table->addColumn('thread_id', Types::BIGINT, [
				'notnull' => true,
				'default' => 0,
			]);
			$table->addColumn('parent_id', Types::BIGINT, [
				'notnull' => false,
				'default' => null,
			]);
			/** Removed in @see Version23000Date20260107162758 */
			// $table->addColumn('created_at', Types::DATETIME, [
			// 'notnull' => false,
			// 'default' => null,
			// ]);
			$table->addColumn('send_at', Types::DATETIME, [
				'notnull' => false,
				'default' => null,
			]);
			$table->setPrimaryKey(['id']);
			$table->addIndex(['room_id'], 'tt_room_sched');
			$table->addIndex(['room_id', 'actor_type', 'actor_id'], 'tt_actor_room_sched');
			$table->addIndex(['send_at'], 'tt_send_at_sched');
		}

		$table = $schema->getTable('talk_attendees');
		if (!$table->hasColumn('has_scheduled_messages')) {
			$table->addColumn('has_scheduled_messages', Types::BOOLEAN, [
				'notnull' => true,
				'default' => false,
			]);
		}

		return $schema;
	}
}
