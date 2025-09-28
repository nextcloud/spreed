<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Migration;

use Closure;
use OCA\Talk\Participant;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;
use Override;

class Version22000Date20250623142327 extends SimpleMigrationStep {
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

		if ($schema->hasTable('talk_threads')) {
			return null;
		}

		$table = $schema->createTable('talk_threads');
		$table->addColumn('id', Types::BIGINT, [
			'notnull' => true,
			'length' => 20,
		]);
		$table->addColumn('room_id', Types::BIGINT, [
			'notnull' => true,
			'length' => 20,
		]);
		$table->addColumn('last_message_id', Types::BIGINT, [
			'default' => 0,
			'length' => 20,
		]);
		$table->addColumn('num_replies', Types::BIGINT, [
			'default' => 0,
			'length' => 20,
		]);

		$table->setPrimaryKey(['id']);
		$table->addIndex(['room_id'], 'tt_room_threads');

		$table = $schema->createTable('talk_thread_attendees');
		$table->addColumn('id', Types::BIGINT, [
			'autoincrement' => true,
			'notnull' => true,
			'length' => 20,
		]);
		$table->addColumn('room_id', Types::BIGINT, [
			'notnull' => true,
			'length' => 20,
		]);
		$table->addColumn('thread_id', Types::BIGINT, [
			'notnull' => true,
			'length' => 20,
		]);
		$table->addColumn('attendee_id', Types::BIGINT, [
			'notnull' => true,
			'length' => 20,
		]);
		$table->addColumn('actor_type', Types::STRING, [
			'notnull' => true,
			'length' => 255,
		]);
		$table->addColumn('actor_id', Types::STRING, [
			'notnull' => true,
			'length' => 255,
		]);
		$table->addColumn('notification_level', Types::INTEGER, [
			'notnull' => false,
			'default' => Participant::NOTIFY_DEFAULT,
		]);
		$table->addColumn('last_read_message', Types::BIGINT, [
			'notnull' => false,
			'default' => 0,
		]);
		$table->addColumn('last_mention_message', Types::BIGINT, [
			'notnull' => false,
			'default' => 0,
		]);
		$table->addColumn('last_mention_direct', Types::BIGINT, [
			'notnull' => false,
			'default' => 0,
		]);
		$table->addColumn('read_privacy', Types::SMALLINT, [
			'notnull' => false,
			'default' => 0,
			'unsigned' => true,
		]);

		$table->setPrimaryKey(['id']);
		/** Replaced by @see Version22001Date20250927174738 */
		// $table->addUniqueIndex(['thread_id', 'actor_type', 'actor_id'], 'tta_thread_attendee');
		$table->addIndex(['room_id', 'actor_type', 'actor_id'], 'tta_room_attendee');

		$table = $schema->getTable('talk_attendees');
		if (!$table->hasColumn('has_unread_threads')) {
			$table->addColumn('has_unread_threads', Types::BOOLEAN, [
				'default' => 0,
				'notnull' => false,
			]);
		}
		if (!$table->hasColumn('has_unread_thread_mentions')) {
			$table->addColumn('has_unread_thread_mentions', Types::BOOLEAN, [
				'default' => 0,
				'notnull' => false,
			]);
		}
		if (!$table->hasColumn('has_unread_thread_directs')) {
			$table->addColumn('has_unread_thread_directs', Types::BOOLEAN, [
				'default' => 0,
				'notnull' => false,
			]);
		}

		return $schema;
	}
}
