<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Migration;

use Closure;
use Doctrine\DBAL\Schema\SchemaException;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version13000Date20210625232111 extends SimpleMigrationStep {
	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 * @return null|ISchemaWrapper
	 * @throws SchemaException
	 */
	#[\Override]
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		$table = $schema->getTable('talk_attendees');
		if (!$table->hasColumn('access_token')) {
			$table->addColumn('access_token', Types::STRING, [
				'notnull' => false,
				'default' => null,
				'length' => 64
			]);
		}
		if (!$table->hasColumn('remote_id')) {
			$table->addColumn('remote_id', Types::STRING, [
				'notnull' => false,
				'default' => null,
				'length' => 255,
			]);
		}

		$table = $schema->getTable('talk_rooms');
		if (!$table->hasColumn('server_url')) {
			$table->addColumn('server_url', Types::STRING, [
				'notnull' => false,
				'default' => null,
			]);
		}

		/** Removed in {@see Version18000Date20231024141626} */
		/** Recreated in {@see Version18000Date20231024141627} */
		// if (!$schema->hasTable('talk_invitations')) {
		// $table = $schema->createTable('talk_invitations');
		// $table->addColumn('id', Types::BIGINT, [
		// 'autoincrement' => true,
		// 'notnull' => true,
		// ]);
		// $table->addColumn('room_id', Types::BIGINT, [
		// 'notnull' => true,
		// 'unsigned' => true,
		// ]);
		// $table->addColumn('user_id', Types::STRING, [
		// 'notnull' => true,
		// 'length' => 255,
		// ]);
		// $table->addColumn('access_token', Types::STRING, [
		// 'notnull' => true,
		// 'length' => 64,
		// ]);
		// $table->addColumn('remote_id', Types::STRING, [
		// 'notnull' => true,
		// 'length' => 255,
		// ]);
		//
		// $table->setPrimaryKey(['id']);
		//
		// $table->addIndex(['room_id']);
		// }

		return $schema;
	}
}
