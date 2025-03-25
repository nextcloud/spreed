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

/**
 * Auto-generated migration step: Please modify to your needs!
 */
class Version18000Date20231024141627 extends SimpleMigrationStep {
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

		if (!$schema->hasTable('talk_invitations')) {
			$table = $schema->createTable('talk_invitations');
			$table->addColumn('id', Types::BIGINT, [
				'autoincrement' => true,
				'notnull' => true,
			]);
			$table->addColumn('user_id', Types::STRING, [
				'notnull' => true,
				'length' => 255,
			]);
			$table->addColumn('state', Types::SMALLINT, [
				'default' => 0,
				'unsigned' => true,
			]);
			$table->addColumn('local_room_id', Types::BIGINT, [
				'notnull' => true,
				'unsigned' => true,
			]);
			$table->addColumn('access_token', Types::STRING, [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('remote_server_url', Types::STRING, [
				'notnull' => true,
				'length' => 512,
			]);
			$table->addColumn('remote_token', Types::STRING, [
				'notnull' => true,
				'length' => 32,
			]);
			$table->addColumn('remote_attendee_id', Types::BIGINT, [
				'notnull' => true,
				'unsigned' => true,
			]);

			$table->setPrimaryKey(['id']);

			$table->addIndex(['local_room_id'], 'talk_fedinv_lri');
			$table->addUniqueIndex(['remote_server_url', 'remote_attendee_id'], 'talk_fedinv_remote');
			return $schema;
		}
		return null;
	}
}
