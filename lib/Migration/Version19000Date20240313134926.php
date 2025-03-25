<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
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
	#[\Override]
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
	#[\Override]
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
