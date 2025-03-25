<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version20000Date20240621150335 extends SimpleMigrationStep {
	/**
	 * @param IOutput $output
	 * @param Closure(): ISchemaWrapper $schemaClosure
	 * @param array $options
	 * @return null|ISchemaWrapper
	 */
	#[\Override]
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('talk_bans')) {
			$table = $schema->createTable('talk_bans');
			$table->addColumn('id', Types::BIGINT, [
				'autoincrement' => true,
				'notnull' => true,
				'length' => 20,
			]);
			$table->addColumn('moderator_actor_type', Types::STRING, [
				'notnull' => true,
				'length' => 32,
			]);
			$table->addColumn('moderator_actor_id', Types::STRING, [
				'notnull' => true,
				'length' => 255,
			]);
			$table->addColumn('moderator_displayname', Types::STRING, [
				'notnull' => false,
				'length' => 255,
			]);
			$table->addColumn('banned_actor_type', Types::STRING, [
				'notnull' => true,
				'length' => 32,
			]);
			$table->addColumn('banned_actor_id', Types::STRING, [
				'notnull' => true,
				'length' => 255,
			]);
			$table->addColumn('banned_displayname', Types::STRING, [
				'notnull' => false,
				'length' => 255,
			]);
			$table->addColumn('room_id', Types::BIGINT, [
				'unsigned' => true,
			]);
			$table->addColumn('banned_time', Types::DATETIME, [
				'notnull' => false,
			]);
			$table->addColumn('internal_note', Types::TEXT, [
				'notnull' => false,
			]);

			$table->setPrimaryKey(['id']);
			//A user should not be banned from the same room more than once
			$table->addUniqueIndex(['banned_actor_type', 'banned_actor_id', 'room_id'], 'talk_room_bans');
			$table->addIndex(['room_id']);
			return $schema;
		}

		return null;
	}
}
