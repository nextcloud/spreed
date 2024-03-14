<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2024 Joas Schilling <coding@schilljs.com>
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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\Talk\Migration;

use Closure;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Model\Invitation;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Cache the invite state in the attendees and room table to allow reducing efforts
 */
class Version19000Date20240313134926 extends SimpleMigrationStep {
	public function __construct(
		protected IDBConnection $connection,
	) {
	}

	/**
	 * @param IOutput $output
	 * @param Closure(): ISchemaWrapper $schemaClosure
	 * @param array $options
	 * @return null|ISchemaWrapper
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		$table = $schema->getTable('talk_attendees');
		$table->addColumn('state', Types::SMALLINT, [
			'default' => 0,
			'unsigned' => true,
		]);
		$table->addColumn('unread_messages', Types::BIGINT, [
			'default' => 0,
			'unsigned' => true,
		]);

		$table = $schema->getTable('talk_rooms');
		$table->addColumn('has_federation', Types::SMALLINT, [
			'default' => 0,
			'unsigned' => true,
		]);

		return $schema;
	}

	/**
	 * Set the invitation state to accepted for existing federated users
	 * Set the "has federation" for rooms with TalkV1 users
	 */
	public function postSchemaChange(IOutput $output, \Closure $schemaClosure, array $options) {
		$query = $this->connection->getQueryBuilder();
		$query->update('talk_attendees')
			->set('state', $query->createNamedParameter(Invitation::STATE_ACCEPTED))
			->where($query->expr()->eq('actor_type', $query->createNamedParameter(Attendee::ACTOR_FEDERATED_USERS)));
		$query->executeStatement();

		$query = $this->connection->getQueryBuilder();
		$subQuery = $this->connection->getQueryBuilder();
		$subQuery->select('room_id')
			->from('talk_attendees')
			->where($subQuery->expr()->eq('actor_type', $query->createNamedParameter(Attendee::ACTOR_FEDERATED_USERS)))
			->groupBy('room_id');

		$query = $this->connection->getQueryBuilder();
		$query->update('talk_rooms')
			// Don't use const Room::HAS_FEDERATION_TALKv1 because the file might have been loaded with old content before the migration
			// ->set('has_federation', $query->createNamedParameter(Room::HAS_FEDERATION_TALKv1))
			->set('has_federation', $query->createNamedParameter(1))
			->where($query->expr()->in('id', $query->createFunction($subQuery->getSQL())));
		$query->executeStatement();
	}
}
