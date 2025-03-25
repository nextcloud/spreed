<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Migration;

use Closure;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Participant;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\DB\Exception;
use OCP\DB\ISchemaWrapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\DB\Types;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * In order to be able to keep "attendees" which are not users, but groups,
 * email addresses, etc the sessions had to be decoupled from the participants
 */
class Version10000Date20201015134000 extends SimpleMigrationStep {

	public function __construct(
		protected IDBConnection $connection,
		protected ITimeFactory $timeFactory,
	) {
	}

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

		if (!$schema->hasTable('talk_attendees')) {
			$table = $schema->createTable('talk_attendees');

			// Auto increment id
			$table->addColumn('id', Types::BIGINT, [
				'autoincrement' => true,
				'notnull' => true,
			]);

			// Unique key
			$table->addColumn('room_id', Types::BIGINT, [
				'notnull' => true,
				'unsigned' => true,
			]);
			$table->addColumn('actor_type', Types::STRING, [
				'notnull' => true,
				'length' => 32,
			]);
			$table->addColumn('actor_id', Types::STRING, [
				'notnull' => true,
				'length' => 255,
			]);
			$table->addColumn('display_name', Types::STRING, [
				'notnull' => false,
				'default' => '',
				'length' => 64,
			]);

			$table->addColumn('pin', Types::STRING, [
				'notnull' => false,
				'length' => 32,
			]);
			$table->addColumn('participant_type', Types::SMALLINT, [
				'notnull' => true,
				'length' => 6,
				'default' => 0,
				'unsigned' => true,
			]);
			$table->addColumn('favorite', Types::BOOLEAN, [
				'default' => 0,
				'notnull' => false,
			]);
			$table->addColumn('notification_level', Types::INTEGER, [
				'default' => Participant::NOTIFY_DEFAULT,
				'notnull' => false,
			]);
			$table->addColumn('last_joined_call', Types::INTEGER, [
				'notnull' => true,
				'length' => 11,
				'default' => 0,
				'unsigned' => true,
			]);
			$table->addColumn('last_read_message', Types::BIGINT, [
				'default' => 0,
				'notnull' => false,
			]);
			$table->addColumn('last_mention_message', Types::BIGINT, [
				'default' => 0,
				'notnull' => false,
			]);

			$table->setPrimaryKey(['id']);

			$table->addUniqueIndex(['room_id', 'actor_type', 'actor_id'], 'ta_ident');
			$table->addIndex(['room_id', 'pin'], 'ta_roompin');
			//$table->addIndex(['room_id'], 'ta_room'); Removed in Version20000Date20240717180417
			$table->addIndex(['actor_type', 'actor_id'], 'ta_actor');
		}


		if (!$schema->hasTable('talk_sessions')) {
			$table = $schema->createTable('talk_sessions');

			// Auto increment id
			$table->addColumn('id', Types::BIGINT, [
				'autoincrement' => true,
				'notnull' => true,
			]);

			// Unique key (for now, might remove this in the future,
			// so a user can join multiple times.
			$table->addColumn('attendee_id', Types::BIGINT, [
				'notnull' => true,
				'unsigned' => true,
			]);

			// Unique key to avoid duplication issues
			$table->addColumn('session_id', Types::STRING, [
				'notnull' => true,
				'length' => 512,
			]);

			$table->addColumn('in_call', Types::INTEGER, [
				'default' => 0,
			]);
			$table->addColumn('last_ping', Types::INTEGER, [
				'notnull' => true,
				'length' => 11,
				'default' => 0,
				'unsigned' => true,
			]);

			$table->setPrimaryKey(['id']);

			$table->addUniqueIndex(['attendee_id'], 'ts_attendee');
			$table->addUniqueIndex(['session_id'], 'ts_session');
			$table->addIndex(['in_call'], 'ts_in_call');
			$table->addIndex(['last_ping'], 'ts_last_ping');
		}

		return $schema;
	}

	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 */
	#[\Override]
	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		$insert = $this->connection->getQueryBuilder();
		$insert->insert('talk_attendees')
			->values([
				'room_id' => $insert->createParameter('room_id'),
				'actor_type' => $insert->createParameter('actor_type'),
				'actor_id' => $insert->createParameter('actor_id'),
				'participant_type' => $insert->createParameter('participant_type'),
				'favorite' => $insert->createParameter('favorite'),
				'notification_level' => $insert->createParameter('notification_level'),
				'last_joined_call' => $insert->createParameter('last_joined_call'),
				'last_read_message' => $insert->createParameter('last_read_message'),
				'last_mention_message' => $insert->createParameter('last_mention_message'),
			]);

		$query = $this->connection->getQueryBuilder();
		$query->select('*')
			->from('talk_participants')
			->where($query->expr()->neq('user_id', $query->createNamedParameter('')))
			->andWhere($query->expr()->isNotNull('user_id'));


		$result = $query->executeQuery();
		while ($row = $result->fetch()) {
			$lastJoinedCall = 0;
			if (!empty($row['last_joined_call'])) {
				$lastJoinedCall = $this->timeFactory->getDateTime($row['last_joined_call'])->getTimestamp();
			}

			$insert
				->setParameter('room_id', (int)$row['room_id'], IQueryBuilder::PARAM_INT)
				->setParameter('actor_type', Attendee::ACTOR_USERS)
				->setParameter('actor_id', $row['user_id'])
				->setParameter('participant_type', (int)$row['participant_type'], IQueryBuilder::PARAM_INT)
				->setParameter('favorite', (bool)$row['favorite'], IQueryBuilder::PARAM_BOOL)
				->setParameter('notification_level', (int)$row['notification_level'], IQueryBuilder::PARAM_INT)
				->setParameter('last_joined_call', $lastJoinedCall, IQueryBuilder::PARAM_INT)
				->setParameter('last_read_message', (int)$row['last_read_message'], IQueryBuilder::PARAM_INT)
				->setParameter('last_mention_message', (int)$row['last_mention_message'], IQueryBuilder::PARAM_INT)
			;

			try {
				$insert->executeStatement();
			} catch (\Exception $e) {
				if (class_exists(UniqueConstraintViolationException::class)
					&& $e instanceof UniqueConstraintViolationException) {
					// UniqueConstraintViolationException before 21
					continue;
				}

				if (class_exists(Exception::class)
					&& $e instanceof Exception
					// Exception with 21 and later
					&& $e->getReason() === Exception::REASON_UNIQUE_CONSTRAINT_VIOLATION) {
					continue;
				}

				throw $e;
			}
		}
		$result->closeCursor();
	}
}
