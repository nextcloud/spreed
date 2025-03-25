<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2017 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OCA\Talk\Migration;

use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version2000Date20170707093535 extends SimpleMigrationStep {
	/**
	 * @param IOutput $output
	 * @param \Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 * @return null|ISchemaWrapper
	 * @since 13.0.0
	 */
	#[\Override]
	public function changeSchema(IOutput $output, \Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('spreedme_messages')) {
			$table = $schema->createTable('spreedme_messages');

			$table->addColumn('id', Types::INTEGER, [
				'autoincrement' => true,
				'notnull' => true,
				'length' => 11,
			]);
			$table->addColumn('sender', Types::STRING, [
				'notnull' => true,
				'length' => 255,
			]);
			$table->addColumn('recipient', Types::STRING, [
				'notnull' => true,
				'length' => 255,
			]);
			$table->addColumn('sessionId', Types::STRING, [
				'notnull' => true,
				'length' => 255,
			]);
			$table->addColumn('object', Types::TEXT, [
				'notnull' => true,
			]);
			$table->addColumn('timestamp', Types::INTEGER, [
				'notnull' => true,
				'length' => 11,
			]);

			$table->setPrimaryKey(['id']);
		}

		if (!$schema->hasTable('spreedme_rooms')) {
			$table = $schema->createTable('spreedme_rooms');

			$table->addColumn('id', Types::INTEGER, [
				'autoincrement' => true,
				'notnull' => true,
				'length' => 11,
			]);
			$table->addColumn('name', Types::STRING, [
				'notnull' => false,
				'length' => 255,
				'default' => '',
			]);
			$table->addColumn('token', Types::STRING, [
				'notnull' => false,
				'length' => 32,
				'default' => '',
			]);
			$table->addColumn('type', Types::INTEGER, [
				'notnull' => true,
				'length' => 11,
			]);

			$table->setPrimaryKey(['id']);
			// FIXME: Couldn't make it unique because the migration only ran afterwards on updates
			$table->addIndex(['token'], 'unique_token');
		}

		if (!$schema->hasTable('spreedme_room_participants')) {
			$table = $schema->createTable('spreedme_room_participants');

			$table->addColumn('userId', Types::STRING, [
				'notnull' => false,
				'length' => 255,
			]);
			$table->addColumn('roomId', Types::INTEGER, [
				'notnull' => true,
				'length' => 11,
			]);
			$table->addColumn('lastPing', Types::INTEGER, [
				'notnull' => true,
				'length' => 11,
			]);
			$table->addColumn('sessionId', Types::STRING, [
				'notnull' => true,
				'length' => 255,
			]);
		}

		return $schema;
	}
}
