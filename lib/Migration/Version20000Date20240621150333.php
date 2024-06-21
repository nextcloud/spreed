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

class Version20000Date20240621150333 extends SimpleMigrationStep {
	/**
	 * @param IOutput $output
	 * @param Closure(): ISchemaWrapper $schemaClosure
	 * @param array $options
	 * @return null|ISchemaWrapper
	 */
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
			$table->addColumn('actor_id', Types::STRING, [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('actor_type', Types::STRING, [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('room_id', Types::BIGINT, [
				'notnull' => true,
				'unsigned' => true,
			]);
			$table->addColumn('banned_by_actor_id', Types::STRING, [
				'length' => 64,
				'notnull' => true,
			]);
			$table->addColumn('banned_by_actor_type', Types::STRING, [
				'length' => 64,
				'notnull' => true,
			]);
			$table->addColumn('banned_at', Types::DATETIME, [
				'notnull' => true,
			]);
			$table->addColumn('reason', Types::TEXT, [
				'notnull' => false,
			]);

			$table->setPrimaryKey(['id']);
			// $table->addUniqueIndex(['user_id', 'room_id'], 'talk_ban_user_room'); //A user should not be banned from the same room more than once
			// $table->addIndex(['banned_at'], 'talk_ban_banned_at');
			return $schema;
		}

		return null;
	}
}
