<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OCA\Talk\Migration;

use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version2001Date20180103144447 extends SimpleMigrationStep {

	public function __construct(
		protected IDBConnection $connection,
		protected IConfig $config,
	) {
	}


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

		$table = $schema->getTable('talk_rooms');

		if (!$table->hasColumn('active_since')) {
			$table->addColumn('active_since', Types::DATETIME, [
				'notnull' => false,
			]);
			$table->addColumn('active_guests', Types::INTEGER, [
				'notnull' => true,
				'length' => 4,
				'default' => 0,
				'unsigned' => true,
			]);
		}

		$table = $schema->getTable('talk_participants');

		if (!$table->hasColumn('user_id')) {
			$table->addColumn('user_id', Types::STRING, [
				'notnull' => false,
				'length' => 255,
			]);
			$table->addColumn('room_id', Types::INTEGER, [
				'notnull' => true,
				'length' => 11,
				'default' => 0,
				'unsigned' => true,
			]);
			$table->addColumn('last_ping', Types::INTEGER, [
				'notnull' => true,
				'length' => 11,
				'default' => 0,
				'unsigned' => true,
			]);
			$table->addColumn('session_id', Types::STRING, [
				'notnull' => true,
				'length' => 255,
				'default' => '0',
			]);
			$table->addColumn('participant_type', Types::SMALLINT, [
				'notnull' => true,
				'length' => 6,
				'default' => 0,
				'unsigned' => true,
			]);
			$table->addColumn('in_call', Types::BOOLEAN, [
				'default' => 0,
			]);
		}

		return $schema;
	}

	/**
	 * @param IOutput $output
	 * @param \Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 * @since 13.0.0
	 */
	#[\Override]
	public function postSchemaChange(IOutput $output, \Closure $schemaClosure, array $options): void {
		if (version_compare($this->config->getAppValue('spreed', 'installed_version', '0.0.0'), '2.0.0', '<')) {
			// Migrations only work after 2.0.0
			return;
		}

		if ($this->connection->getDatabaseProvider() !== IDBConnection::PLATFORM_POSTGRES) {
			$update = $this->connection->getQueryBuilder();
			$update->update('talk_rooms')
				->set('active_since', 'activeSince')
				->set('active_guests', 'activeGuests');
			$update->executeStatement();

			$update = $this->connection->getQueryBuilder();
			$update->update('talk_participants')
				->set('user_id', 'userId')
				->set('room_id', 'roomId')
				->set('last_ping', 'lastPing')
				->set('session_id', 'sessionId')
				->set('participant_type', 'participantType')
				->set('in_call', 'inCall');
			$update->executeStatement();
		} else {
			$update = $this->connection->getQueryBuilder();
			$update->update('talk_rooms')
				->set('active_since', 'activesince')
				->set('active_guests', 'activeguests');
			$update->executeStatement();

			$update = $this->connection->getQueryBuilder();
			$update->update('talk_participants')
				->set('user_id', 'userid')
				->set('room_id', 'roomid')
				->set('last_ping', 'lastping')
				->set('session_id', 'sessionid')
				->set('participant_type', 'participanttype')
				->set('in_call', 'incall');
			$update->executeStatement();
		}
	}
}
