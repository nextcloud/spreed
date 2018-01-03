<?php
/**
 * @copyright Copyright (c) 2018 Joas Schilling <coding@schilljs.com>
 *
 * @author Joas Schilling <coding@schilljs.com>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */
namespace OCA\Spreed\Migration;

use Doctrine\DBAL\Platforms\PostgreSqlPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Type;
use OCP\IDBConnection;
use OCP\Migration\SimpleMigrationStep;
use OCP\Migration\IOutput;

class Version2001Date20180103144447 extends SimpleMigrationStep {

	/** @var IDBConnection */
	protected $connection;

	/**
	 * @param IDBConnection $connection
	 */
	public function __construct(IDBConnection $connection) {
		$this->connection = $connection;
	}


	/**
	 * @param IOutput $output
	 * @param \Closure $schemaClosure The `\Closure` returns a `Schema`
	 * @param array $options
	 * @return null|Schema
	 * @since 13.0.0
	 */
	public function changeSchema(IOutput $output, \Closure $schemaClosure, array $options) {
		/** @var Schema $schema */
		$schema = $schemaClosure();

		$table = $schema->getTable('talk_rooms');

		if (!$table->hasColumn('active_since')) {
			$table->addColumn('active_since', Type::DATETIME, [
				'notnull' => false,
			]);
			$table->addColumn('active_guests', Type::INTEGER, [
				'notnull' => true,
				'length' => 4,
				'default' => 0,
				'unsigned' => true,
			]);
		}

		$table = $schema->getTable('talk_participants');

		if (!$table->hasColumn('user_id')) {
			$table->addColumn('user_id', Type::STRING, [
				'notnull' => false,
				'length' => 255,
			]);
			$table->addColumn('room_id', Type::INTEGER, [
				'notnull' => true,
				'length' => 11,
			]);
			$table->addColumn('last_ping', Type::INTEGER, [
				'notnull' => true,
				'length' => 11,
			]);
			$table->addColumn('session_id', Type::STRING, [
				'notnull' => true,
				'length' => 255,
			]);
			$table->addColumn('participant_type', Type::SMALLINT, [
				'notnull' => true,
				'length' => 6,
				'default' => 0,
			]);
			$table->addColumn('in_call', Type::BOOLEAN, [
				'default' => 0,
			]);
		}

		return $schema;
	}

	/**
	 * @param IOutput $output
	 * @param \Closure $schemaClosure The `\Closure` returns a `Schema`
	 * @param array $options
	 * @since 13.0.0
	 */
	public function postSchemaChange(IOutput $output, \Closure $schemaClosure, array $options) {

		if ($this->connection->getDatabasePlatform() instanceof PostgreSqlPlatform) {
			// Couldn't install prior anyway, so we can skip this update step as well
			return;
		}

		$update = $this->connection->getQueryBuilder();
		$update->update('talk_rooms')
			->set('active_since', 'activeSince')
			->set('active_guests', 'activeGuests');
		$update->execute();

		$update = $this->connection->getQueryBuilder();
		$update->update('talk_participants')
			->set('user_id', 'userId')
			->set('room_id', 'roomId')
			->set('last_ping', 'lastPing')
			->set('session_id', 'sessionId')
			->set('participant_type', 'participantType')
			->set('in_call', 'inCall');
		$update->execute();

	}
}
