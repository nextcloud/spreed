<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version15000Date20220503121308 extends SimpleMigrationStep {
	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 * @return null|ISchemaWrapper
	 */
	#[\Override]
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('talk_polls')) {
			$table = $schema->createTable('talk_polls');

			$table->addColumn('id', Types::BIGINT, [
				'autoincrement' => true,
				'notnull' => true,
				'length' => 20,
			]);
			$table->addColumn('room_id', Types::BIGINT, [
				'notnull' => true,
				'length' => 20,
			]);
			$table->addColumn('question', Types::TEXT, [
				'notnull' => false,
				'length' => null,
			]);
			$table->addColumn('options', Types::TEXT, [
				'notnull' => false,
				'length' => null,
			]);
			$table->addColumn('votes', Types::TEXT, [
				'notnull' => false,
				'length' => null,
			]);
			$table->addColumn('num_voters', Types::BIGINT, [
				'notnull' => false,
				'length' => 20,
				'default' => 0,
			]);
			$table->addColumn('actor_type', Types::STRING, [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('actor_id', Types::STRING, [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('display_name', Types::STRING, [
				'notnull' => false,
				'length' => 255,
			]);
			$table->addColumn('status', Types::SMALLINT, [
				'notnull' => false,
				'default' => 0,
			]);
			$table->addColumn('result_mode', Types::SMALLINT, [
				'notnull' => false,
				'default' => 0,
			]);
			$table->addColumn('max_votes', Types::INTEGER, [
				'notnull' => true,
				'default' => 0,
			]);

			$table->setPrimaryKey(['id']);
			$table->addIndex(['room_id'], 'talk_poll_room');
		}

		if (!$schema->hasTable('talk_poll_votes')) {
			$table = $schema->createTable('talk_poll_votes');

			$table->addColumn('id', Types::BIGINT, [
				'autoincrement' => true,
				'notnull' => true,
				'length' => 20,
			]);
			$table->addColumn('poll_id', Types::BIGINT, [
				'notnull' => true,
				'length' => 20,
			]);
			$table->addColumn('room_id', Types::BIGINT, [
				'notnull' => true,
				'length' => 20,
			]);
			$table->addColumn('actor_type', Types::STRING, [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('actor_id', Types::STRING, [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('display_name', Types::STRING, [
				'notnull' => false,
				'length' => 255,
			]);
			$table->addColumn('option_id', Types::INTEGER, [
				'notnull' => true,
				'length' => 6,
			]);

			$table->setPrimaryKey(['id']);
			$table->addIndex(['poll_id', 'actor_type', 'actor_id'], 'talk_poll_vote');
			$table->addIndex(['room_id'], 'talk_vote_room');
		}

		return $schema;
	}
}
